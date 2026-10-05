<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use App\Models\ContractReview;
use App\Models\ContractRevisionRequest;
use App\Models\ContractSubmission;
use App\Models\ContractSubmissionFile;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContractWork
{
    /** Q83: reviews stay hidden until both exist or this many days pass after completion. */
    public const REVIEW_DAYS = 14;

    /**
     * Q49: one immutable record for the complete agreed set. A retry with the same token returns the same delivery.
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $files
     */
    public static function submit(Contract $contract, array $data, array $files): ContractSubmission
    {
        $stored = [];
        try {
            return DB::transaction(function () use ($contract, $data, $files, &$stored): ContractSubmission {
                $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
                $existing = ContractSubmission::query()->where('contract_id', $locked->id)->where('client_token', $data['client_token'])->first();
                if ($existing) {
                    return $existing;
                }
                if (! in_array($locked->status, ['active', 'revision_requested'], true)) {
                    throw ValidationException::withMessages(['delivery' => __('This contract is not waiting for a delivery. Reload to see its current status.')]);
                }
                $submission = new ContractSubmission;
                $submission->forceFill(['contract_id' => $locked->id, 'number' => (int) ContractSubmission::query()->where('contract_id', $locked->id)->max('number') + 1,
                    'client_token' => $data['client_token'], 'message' => $data['message'], 'links' => array_values($data['links'] ?? []), 'created_at' => now()])->save();
                foreach ($files as $file) {
                    $path = 'deliveries/'.$locked->id.'/'.Str::uuid();
                    if (! Storage::disk('local')->put($path, $file->getContent())) {
                        throw new \RuntimeException('A delivery file could not be stored.');
                    }
                    $stored[] = $path;
                    $record = new ContractSubmissionFile;
                    $record->forceFill(['contract_submission_id' => $submission->id, 'name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
                        'path' => $path, 'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize()])->save();
                }
                $locked->forceFill(['status' => 'submitted'])->save();
                self::notify($locked, 'delivery_submitted', $locked->client_id, $locked->freelancer_id);

                return $submission;
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($stored);
            throw $exception;
        }
    }

    /** Q48: one grouped request consumes one round, once. Only the latest delivery can be answered. */
    public static function requestRevision(Contract $contract, int $submission, string $changes): ContractRevisionRequest
    {
        return DB::transaction(function () use ($contract, $submission, $changes): ContractRevisionRequest {
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $existing = ContractRevisionRequest::query()->where('contract_id', $locked->id)->where('contract_submission_id', $submission)->first();
            if ($existing) {
                return $existing;
            }
            self::current($locked, $submission);
            if ($locked->revisions_used >= $locked->revisionRounds()) {
                throw ValidationException::withMessages(['revision' => __('Every included revision round has been used.')]);
            }
            $request = new ContractRevisionRequest;
            $request->forceFill(['contract_id' => $locked->id, 'contract_submission_id' => $submission, 'round' => $locked->revisions_used + 1,
                'changes' => $changes, 'created_at' => now()])->save();
            // Q81: a revision date is agreed per round, so a new round starts without one.
            $locked->forceFill(['status' => 'revision_requested', 'revisions_used' => $locked->revisions_used + 1, 'revision_due_at' => null])->save();
            self::notify($locked, 'revision_requested', $locked->freelancer_id, $locked->client_id);

            return $request;
        }, 3);
    }

    /** Only the client's approval completes a contract; nothing completes on its own. */
    public static function complete(Contract $contract, int $submission): void
    {
        DB::transaction(function () use ($contract, $submission): void {
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($locked->status === 'completed') {
                return;
            }
            self::current($locked, $submission);
            $locked->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            ContractAmendments::close($locked);
            self::notify($locked, 'contract_completed', $locked->freelancer_id, $locked->client_id);
        }, 3);
    }

    /** Q83: authors edit while hidden; the contract lock keeps an edit from racing publication. */
    public static function review(Contract $contract, int $author, int $rating, string $body): void
    {
        DB::transaction(function () use ($contract, $author, $rating, $body): void {
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($locked->status !== 'completed') {
                throw ValidationException::withMessages(['review' => __('Reviews open once the contract is completed.')]);
            }
            $review = ContractReview::query()->where('contract_id', $locked->id)->where('author_id', $author)->first();
            if ($review && self::reviewsPublished($locked)) {
                throw ValidationException::withMessages(['review' => __('Published reviews can no longer be edited.')]);
            }
            $subject = $author === $locked->client_id ? $locked->freelancer_id : $locked->client_id;
            $new = $review === null;
            $review ??= new ContractReview;
            $review->forceFill(['contract_id' => $locked->id, 'author_id' => $author, 'subject_id' => $subject, 'rating' => $rating, 'body' => $body])->save();
            if ($new) {
                self::notify($locked, 'review_received', $subject, $author);
            }
        }, 3);
    }

    /** Computed on every read and write, so publication never depends on the scheduler. */
    public static function reviewsPublished(Contract $contract): bool
    {
        return $contract->completed_at !== null
            && ($contract->completed_at->addDays(self::REVIEW_DAYS)->isPast() || ContractReview::query()->where('contract_id', $contract->id)->count() >= 2);
    }

    private static function current(Contract $contract, int $submission): void
    {
        if ($contract->status !== 'submitted' || (int) ContractSubmission::query()->where('contract_id', $contract->id)->orderByDesc('number')->value('id') !== $submission) {
            throw ValidationException::withMessages(['delivery' => __('This delivery is no longer the one awaiting your decision. Reload to see the current status.')]);
        }
    }

    private static function notify(Contract $contract, string $kind, int $recipient, int $actor): void
    {
        $title = $contract->agreement['project_title'] ?? null;
        $name = $actor === $contract->client_id ? $contract->agreement['client_name'] ?? null : $contract->agreement['freelancer_name'] ?? null;
        DB::afterCommit(fn () => User::query()->find($recipient)?->notify(new WorkspaceEvent($kind, '/contracts/'.$contract->id, $title, $name)));
    }
}
