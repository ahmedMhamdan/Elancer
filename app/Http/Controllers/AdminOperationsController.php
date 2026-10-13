<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan 026: super administrators see background work that failed and may put one piece back on the
 * queue or dismiss it. The stored payload is never shown: only what kind of work it was, when it
 * failed and one sanitized line of the error.
 */
class AdminOperationsController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $oldest = DB::table('jobs')->min('created_at');

        return Inertia::render('admin/operations/index', [
            'failures' => DB::table('failed_jobs')->orderByDesc('id')->paginate(20, ['id', 'queue', 'payload', 'exception', 'failed_at'])->through(fn ($job) => [
                'id' => $job->id, 'queue' => $job->queue, ...self::work((string) $job->payload), 'error' => self::error((string) $job->exception),
                'failed_at' => Carbon::parse((string) $job->failed_at)->toIso8601String(),
            ]),
            'queue' => ['waiting' => DB::table('jobs')->count(), 'oldest' => $oldest === null ? null : Carbon::createFromTimestamp((int) $oldest)->toIso8601String(),
                'failed' => DB::table('failed_jobs')->count()],
            'notice' => $request->session()->get('operations_notice'),
        ]);
    }

    public function retry(Request $request, int $job): RedirectResponse
    {
        $failed = DB::table('failed_jobs')->where('id', $job)->first(['id', 'uuid', 'payload']);
        abort_if($failed === null, 404);
        Artisan::call('queue:retry', ['id' => [$failed->uuid]]);
        // The command only reports on the console, so the outcome is read from the table itself.
        $retried = ! DB::table('failed_jobs')->where('id', $job)->exists();
        if ($retried) {
            $this->record($request, 'job_retried', $job, (string) $failed->payload);
        }

        return back(303, [], route('admin.operations.index'))->with('operations_notice', $retried ? 'retried' : 'unchanged');
    }

    public function destroy(Request $request, int $job): RedirectResponse
    {
        $failed = DB::table('failed_jobs')->where('id', $job)->first(['id', 'uuid', 'payload']);
        abort_if($failed === null, 404);
        DB::table('failed_jobs')->where('id', $job)->delete();
        $this->record($request, 'job_dismissed', $job, (string) $failed->payload);

        return back(303, [], route('admin.operations.index'))->with('operations_notice', 'dismissed');
    }

    /**
     * What kind of work a stored job was, from its name alone.
     *
     * @return array{kind: string, name: string}
     */
    private static function work(string $payload): array
    {
        $decoded = json_decode($payload, true);
        $name = is_array($decoded) && is_string($decoded['displayName'] ?? null) ? $decoded['displayName'] : '';

        return ['kind' => match (true) {
            str_contains($name, 'Broadcast') => 'live',
            str_contains($name, 'Mail') || str_contains($name, 'Notification') => 'email',
            default => 'other',
        }, 'name' => Str::limit(class_basename($name), 80, '')];
    }

    /**
     * The exception's short class name and its first line. Left out: the statement and values of a database error,
     * file paths, addresses, long tokens and whatever follows the name of a secret.
     */
    private static function error(string $exception): string
    {
        $line = trim((string) strtok($exception, "\n"));
        [$class, $message] = str_contains($line, ': ') ? explode(': ', $line, 2) : ['', $line];
        if (preg_match('/^[\w\\\\]+$/', $class) !== 1) {
            [$class, $message] = ['', $line];
        }
        foreach ([
            '/\s*\((?:Connection: [^,]+, )?SQL: .*$/s' => '',
            '/\s+in\s+\S+\.php:\d+$/' => '',
            '/\S*[\\\\\/]\S+\.php(?::\d+)?/' => '[file]',
            '/\b(password|passwd|secret|token|key|authorization|signature)\b\s*[=:]?\s*\S+/i' => '$1 [hidden]',
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i' => '[address]',
            '/[A-Za-z0-9+\/_=-]{32,}/' => '[hidden]',
        ] as $pattern => $replacement) {
            $message = (string) preg_replace($pattern, $replacement, $message);
        }

        return Str::limit(trim(($class === '' ? '' : class_basename($class).': ').$message), 200);
    }

    private function record(Request $request, string $action, int $job, string $payload): void
    {
        DB::table('moderation_events')->insert(['actor_id' => $request->user()->id, 'actor_email' => $request->user()->email, 'action' => $action,
            'target_type' => 'failed_job', 'target_id' => $job, 'reason' => self::work($payload)['name'], 'created_at' => now()]);
    }
}
