<?php

namespace App\Actions\Demo;

use App\Actions\Auth\DemoAccess;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Reuses the administration screens with illustrative data, without opening an administrator account. */
class DemoAdminPreview
{
    public static function respond(Request $request): Response
    {
        abort_unless(DemoAccess::administrator($request) && $request->isMethodSafe(), 403);
        $request->validate(['q' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $search = trim((string) $request->query('q', ''));
        $time = now()->subDay()->toIso8601String();
        $people = [
            ['id' => 1, 'name' => 'Sample super administrator', 'email' => 'super-admin@demo.elancer.test', 'status' => 'active', 'email_verified_at' => $time, 'is_admin' => false, 'is_super_admin' => true],
            ['id' => 2, 'name' => 'Sample administrator', 'email' => 'admin@demo.elancer.test', 'status' => 'active', 'email_verified_at' => $time, 'is_admin' => true, 'is_super_admin' => false],
            ['id' => 3, 'name' => 'Sample member', 'email' => 'member@demo.elancer.test', 'status' => 'suspended', 'email_verified_at' => $time, 'is_admin' => false, 'is_super_admin' => false],
        ];
        $categories = [
            ['id' => 1, 'categoryname' => 'Sample design', 'slug' => 'sample-design', 'deleted_at' => null],
            ['id' => 2, 'categoryname' => 'Sample development', 'slug' => 'sample-development', 'deleted_at' => null],
            ['id' => 3, 'categoryname' => 'Sample writing', 'slug' => 'sample-writing', 'deleted_at' => $time],
        ];
        $skills = [
            ['id' => 1, 'name' => 'Sample Laravel', 'retired' => false, 'profiles' => 8, 'projects' => 4],
            ['id' => 2, 'name' => 'Sample React', 'retired' => false, 'profiles' => 6, 'projects' => 3],
            ['id' => 3, 'name' => 'Sample legacy skill', 'retired' => true, 'profiles' => 1, 'projects' => 0],
        ];
        $report = ['id' => 1, 'target_type' => 'project', 'target_id' => 1, 'title' => 'Sample website brief', 'reason' => 'spam_scam',
            'explanation' => 'An illustrative report for exploring the moderation screen.', 'status' => 'submitted', 'outcome' => null,
            'snapshot' => ['title' => 'Sample website brief', 'summary' => 'An illustrative project for the administrator preview.'],
            'created_at' => $time, 'resolved_at' => null, 'reporter' => 'Sample client', 'subject' => 'Sample member', 'handler' => null];
        $status = (string) $request->query('status', '');
        $page = null;
        $props = [];

        switch ($request->route()?->getName()) {
            case 'admin.categories.index':
                $status = $status === 'deleted' ? 'deleted' : 'active';
                $page = 'admin/categories/index';
                $props = ['categories' => self::paginate($request, array_values(array_filter($categories, fn (array $row): bool => ($row['deleted_at'] !== null) === ($status === 'deleted')))), 'status' => $status, 'notice' => null];
                break;
            case 'admin.categories.create':
            case 'admin.categories.edit':
                $page = 'admin/categories/form';
                $props = ['category' => $request->routeIs('admin.categories.create') ? null : self::item($request, 'category', $categories)];
                break;
            case 'admin.skills.index':
                $status = in_array($status, ['active', 'retired', 'all'], true) ? $status : 'active';
                $page = 'admin/skills/index';
                $rows = array_values(array_filter($skills, fn (array $row): bool => ($status === 'all' || $row['retired'] === ($status === 'retired')) && self::matches($row, $search)));
                $props = ['skills' => self::paginate($request, $rows), 'search' => $search, 'status' => $status, 'events' => [], 'notice' => null];
                break;
            case 'admin.skills.create':
            case 'admin.skills.edit':
                $page = 'admin/skills/form';
                $props = ['skill' => $request->routeIs('admin.skills.create') ? null : self::item($request, 'skill', $skills)];
                break;
            case 'admin.accounts.index':
            case 'admin.administrators.index':
                $accounts = $request->routeIs('admin.accounts.index');
                $status = $status === 'suspended' ? 'suspended' : 'all';
                $page = $accounts ? 'admin/accounts/index' : 'admin/administrators/index';
                $rows = array_values(array_filter($people, fn (array $row): bool => self::matches($row, $search) && (! $accounts || $status === 'all' || $row['status'] === 'suspended')));
                $props = ['users' => self::paginate($request, $rows), 'search' => $search, 'status' => $status, 'notice' => null];
                break;
            case 'admin.accounts.show':
                $page = 'admin/accounts/show';
                $person = self::item($request, 'user', $people);
                $props = ['account' => [...$person, 'created_at' => $time, 'suspension_reason' => $person['status'] === 'suspended' ? 'Illustrative moderation decision.' : null,
                    'suspended_at' => $person['status'] === 'suspended' ? $time : null, 'contracts' => 0, 'reports' => 1],
                    'events' => [], 'report' => null, 'can' => ['suspend' => false, 'reinstate' => false], 'notice' => null];
                break;
            case 'admin.administrators.edit':
                $page = 'admin/administrators/edit';
                $props = ['account' => self::item($request, 'user', $people)];
                break;
            case 'admin.reports.index':
                $status = $status === 'resolved' ? 'resolved' : 'open';
                $page = 'admin/reports/index';
                $props = ['reports' => self::paginate($request, $status === 'open' ? [$report] : []), 'status' => $status];
                break;
            case 'admin.reports.show':
                self::item($request, 'report', [$report]);
                $page = 'admin/reports/show';
                $props = ['report' => [...$report, 'reporter' => ['name' => 'Sample client', 'email' => 'client@demo.elancer.test'],
                    'subject' => ['id' => 3, 'name' => 'Sample member', 'email' => 'member@demo.elancer.test', 'status' => 'active'], 'subject_reports' => 0],
                    'target' => ['title' => $report['title'], 'summary' => $report['snapshot']['summary'], 'href' => null],
                    'notes' => [], 'events' => [], 'moderation' => null,
                    'can' => ['start' => false, 'take' => false, 'resolve' => false, 'conversation' => false, 'hide' => false, 'restore' => false], 'notice' => null];
                break;
            case 'admin.identity.index':
                $page = 'admin/identity/index';
                $props = ['submissions' => self::paginate($request, [['id' => 1, 'name' => 'Sample verified member', 'email' => 'verified@demo.elancer.test', 'status' => 'approved', 'reason' => 'Illustrative completed identity review.', 'created_at' => $time, 'updated_at' => $time]])];
                break;
            case 'admin.operations.index':
                $page = 'admin/operations/index';
                $props = ['failures' => self::paginate($request, [['id' => 1, 'queue' => 'default', 'kind' => 'email', 'name' => 'Sample notification', 'error' => 'Illustrative mail delivery failure.', 'failed_at' => $time]]),
                    'queue' => ['waiting' => 0, 'oldest' => null, 'failed' => 1], 'notice' => null];
                break;
            case 'admin.demo.index':
                $page = 'admin/demo/index';
                $props = ['accounts' => 3, 'entry' => ['client', 'freelancer', 'admin'], 'actions' => false,
                    'rules' => ['idle_minutes' => 30, 'max_hours' => 12],
                    'baseline' => ['taken_at' => $time, 'taken_by' => 'Sample super administrator', 'accounts' => 3, 'rows' => 24, 'changed_at' => null, 'restored_at' => $time, 'problem' => null],
                    'events' => [], 'notice' => null];
                break;
        }

        // Unlisted routes include identity images, reported conversations and downloads; no private controller runs.
        abort_unless($page !== null, 404);

        return Inertia::render($page, $props)->toResponse($request);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private static function paginate(Request $request, array $rows): LengthAwarePaginator
    {
        $page = (int) $request->query('page', 1);

        return new LengthAwarePaginator(array_slice($rows, ($page - 1) * 20, 20), count($rows), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);
    }

    /** @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private static function item(Request $request, string $parameter, array $rows): array
    {
        foreach ($rows as $row) {
            if ((string) $row['id'] === (string) $request->route($parameter)) {
                return $row;
            }
        }
        abort(404);
    }

    /** @param array<string, mixed> $row */
    private static function matches(array $row, string $search): bool
    {
        return $search === '' || str_contains(mb_strtolower($row['name'].' '.($row['email'] ?? '')), mb_strtolower($search));
    }
}
