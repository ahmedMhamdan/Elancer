<?php

namespace App\Actions\Demo;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;
use Throwable;

/**
 * Plan 027 (plan 003 section 10): the demo accounts' rows saved as a starting point, and the reset
 * that returns to it. The reset works by primary key on rows found through the demo accounts, in one
 * transaction. It never writes the accounts table, never touches a row a real member shares, and
 * refuses, changing nothing, when a row outside the demo depends on one it would delete.
 */
class DemoBaseline
{
    /**
     * Every table a demo account's work reaches, parents before children. A row is in the demo world
     * when each column under 'in' points into it ('users' are the demo accounts) and each column under
     * 'maybe' is empty or does; a row shared with a real member is therefore never in it.
     * 'key' names a composite key. Such rows and the rows of a 'leaf' table are replaced; all others
     * are updated in place, because other rows point at them. 'needs' and 'clears' are pointers out of
     * the demo: when the target is gone the saved row is left out, or the pointer emptied.
     *
     * @return array<string, array{in: array<string, string>, maybe?: array<string, string>, key?: list<string>, leaf?: bool, needs?: array<string, string>, clears?: array<string, string>, where?: array<string, string>}>
     */
    private static function tables(): array
    {
        return [
            'profiles' => ['in' => ['user_id' => 'users']],
            'profile_skill' => ['key' => ['profile_id', 'skill_id'], 'in' => ['profile_id' => 'profiles'], 'needs' => ['skill_id' => 'skills']],
            'projects' => ['in' => ['user_id' => 'users'], 'clears' => ['category_id' => 'categories', 'moderated_by' => 'users']],
            'project_skill' => ['key' => ['project_id', 'skill_id'], 'in' => ['project_id' => 'projects'], 'needs' => ['skill_id' => 'skills']],
            'project_attachments' => ['in' => ['project_id' => 'projects']],
            'project_clarifications' => ['in' => ['project_id' => 'projects']],
            'proposals' => ['in' => ['project_id' => 'projects', 'user_id' => 'users']],
            'proposal_events' => ['in' => ['proposal_id' => 'proposals'], 'clears' => ['actor_id' => 'users']],
            'proposal_files' => ['in' => ['proposal_id' => 'proposals']],
            'invitations' => ['in' => ['project_id' => 'projects', 'recipient_id' => 'users']],
            'invitation_events' => ['in' => ['invitation_id' => 'invitations']],
            'conversations' => ['in' => ['proposal_id' => 'proposals', 'client_id' => 'users', 'freelancer_id' => 'users']],
            'conversation_messages' => ['in' => ['conversation_id' => 'conversations', 'sender_id' => 'users'], 'clears' => ['moderated_by' => 'users']],
            'message_revisions' => ['in' => ['conversation_message_id' => 'conversation_messages']],
            'message_files' => ['in' => ['conversation_message_id' => 'conversation_messages']],
            'offers' => ['in' => ['proposal_id' => 'proposals', 'project_id' => 'projects', 'client_id' => 'users', 'freelancer_id' => 'users']],
            'contracts' => ['in' => ['offer_id' => 'offers', 'project_id' => 'projects', 'proposal_id' => 'proposals', 'conversation_id' => 'conversations',
                'client_id' => 'users', 'freelancer_id' => 'users']],
            'contract_amendments' => ['in' => ['contract_id' => 'contracts', 'proposer_id' => 'users']],
            'contract_cancellations' => ['in' => ['contract_id' => 'contracts', 'requester_id' => 'users']],
            'contract_submissions' => ['in' => ['contract_id' => 'contracts']],
            'contract_submission_files' => ['in' => ['contract_submission_id' => 'contract_submissions']],
            'contract_revision_requests' => ['in' => ['contract_id' => 'contracts', 'contract_submission_id' => 'contract_submissions']],
            'contract_reviews' => ['in' => ['contract_id' => 'contracts', 'author_id' => 'users', 'subject_id' => 'users']],
            'contract_review_revisions' => ['in' => ['contract_review_id' => 'contract_reviews']],
            'payment_attempts' => ['in' => ['contract_id' => 'contracts', 'payer_id' => 'users']],
            'payment_events' => ['in' => ['payment_attempt_id' => 'payment_attempts']],
            // The stand-in provider's own side of a payment, tied to the attempt by its reference.
            'simulated_payments' => ['in' => ['application_reference' => 'payment_attempts.reference']],
            'portfolio_cases' => ['in' => ['user_id' => 'users'], 'maybe' => ['contract_id' => 'contracts'], 'clears' => ['moderated_by' => 'users']],
            'portfolio_approvals' => ['in' => ['portfolio_case_id' => 'portfolio_cases']],
            'portfolio_images' => ['in' => ['portfolio_case_id' => 'portfolio_cases']],
            // Plan 034: a case study shared in an application, which a visitor makes by applying as the demo freelancer.
            'proposal_cases' => ['in' => ['proposal_id' => 'proposals', 'portfolio_case_id' => 'portfolio_cases']],
            'saved_projects' => ['leaf' => true, 'in' => ['user_id' => 'users'], 'needs' => ['project_id' => 'projects']],
            'saved_profiles' => ['leaf' => true, 'in' => ['user_id' => 'users'], 'needs' => ['profile_id' => 'profiles']],
            'saved_searches' => ['leaf' => true, 'in' => ['user_id' => 'users']],
            'user_blocks' => ['leaf' => true, 'in' => ['user_id' => 'users', 'blocked_user_id' => 'users']],
            'notifications' => ['leaf' => true, 'in' => ['notifiable_id' => 'users'], 'where' => ['notifiable_type' => (new User)->getMorphClass()]],
        ];
    }

    /** Saves the demo world as it is now. What it holds becomes what a reset returns to, and public. */
    public static function save(?User $actor): void
    {
        DB::transaction(function () use ($actor): void {
            $accounts = DemoWorld::accounts(true);
            if ($accounts === []) {
                throw new DemoResetRefused('accounts');
            }
            $world = self::collect($accounts);
            $rows = array_filter(array_map('count', $world));
            $id = DB::table('demo_baselines')->insertGetId(['taken_by' => $actor?->id, 'taken_at' => now(),
                'summary' => json_encode(['accounts' => count($accounts), 'tables' => $rows], JSON_THROW_ON_ERROR),
                'payload' => self::pack(['accounts' => User::query()->toBase()->whereIn('id', $accounts)->pluck('email', 'id')->all(), 'tables' => array_map('array_values', $world)])]);
            // The copy before this one is kept as a way back by hand; older ones are not.
            DB::table('demo_baselines')->where('id', '<', (int) DB::table('demo_baselines')->where('id', '<', $id)->max('id'))->delete();
            self::record($actor, 'demo_saved', $id, ['accounts' => count($accounts), 'rows' => array_sum($rows)]);
        });
        DemoWorld::forget();
    }

    /**
     * Returns the demo world to the starting point. An automatic reset that finds nothing changed does nothing.
     *
     * @return array{removed: int, returned: int, restored: int}|null
     */
    public static function restore(?User $actor, bool $automatic = false): ?array
    {
        try {
            return DB::transaction(fn (): ?array => self::run($actor, $automatic));
        } catch (DemoResetRefused $refusal) {
            // Outside the rolled-back transaction, so the administration page can say why.
            DB::table('demo_baselines')->where('id', (int) DB::table('demo_baselines')->max('id'))->update(['problem' => $refusal->getMessage()]);
            throw $refusal;
        } finally {
            DemoWorld::forget();
        }
    }

    /** A visitor is entering: a demo nobody changed for a while, or one that has stayed changed for long, starts clean. */
    public static function restoreWhenDue(): void
    {
        $baseline = DemoWorld::baseline(true);
        // A refused reset waits for an administrator instead of being tried again by every visitor.
        if (! self::due($baseline) || $baseline?->problem !== null) {
            return;
        }
        try {
            self::restore(null, true);
        } catch (DemoResetRefused) {
            // Recorded on the starting point; the visitor enters the demo as it is.
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public static function due(?stdClass $baseline): bool
    {
        if ($baseline === null || $baseline->changed_at === null) {
            return false;
        }

        return CarbonImmutable::parse($baseline->changed_at)->lte(now()->subMinutes((int) config('demo.reset.idle_minutes')))
            || CarbonImmutable::parse($baseline->changed_since ?? $baseline->changed_at)->lte(now()->subHours((int) config('demo.reset.max_hours')));
    }

    /** @return array{removed: int, returned: int, restored: int}|null */
    private static function run(?User $actor, bool $automatic): ?array
    {
        $baseline = DB::table('demo_baselines')->orderByDesc('id')->lockForUpdate()->first();
        if ($baseline === null) {
            throw new DemoResetRefused('none');
        }
        // Two visitors entering together: the second finds the work done.
        if ($automatic && $baseline->changed_at === null) {
            return null;
        }
        $saved = self::unpack((string) $baseline->payload);
        // The same accounts under the same demo addresses, or this is not the database the copy was made from.
        $accounts = array_map('intval', array_keys($saved['accounts']));
        $present = User::query()->toBase()->whereIn('id', $accounts)->pluck('email', 'id')->all();
        foreach ($saved['accounts'] as $id => $email) {
            if (! DemoWorld::address($email) || ($present[$id] ?? null) !== $email) {
                throw new DemoResetRefused('accounts');
            }
        }
        $tables = self::tables();
        $current = self::collect($accounts);
        $target = self::target($saved['tables'], $accounts);

        $delete = $insert = $update = [];
        $counts = ['removed' => 0, 'returned' => 0, 'restored' => 0];
        // A copy saved before a table was listed here holds nothing for it, which is not the same as holding no rows:
        // that table's rows are left as they are, as the reset of that time left them.
        foreach (array_intersect_key($tables, $saved['tables']) as $table => $rules) {
            $replace = isset($rules['key']) || ($rules['leaf'] ?? false);
            foreach ($current[$table] as $key => $row) {
                if (! isset($target[$table][$key])) {
                    $delete[$table][$key] = $row;
                    $counts['removed']++;

                    continue;
                }
                $changes = self::changes($target[$table][$key], $row);
                if ($changes === []) {
                    continue;
                }
                $counts['restored']++;
                if ($replace) {
                    $delete[$table][$key] = $row;
                    $insert[$table][$key] = $target[$table][$key];
                } else {
                    $update[$table][$key] = $changes;
                }
            }
            foreach (array_diff_key($target[$table], $current[$table]) as $key => $row) {
                $insert[$table][$key] = $row;
                $counts['returned']++;
            }
        }
        self::guard($delete);

        // Children go before their parents, and parents return before their children.
        foreach (array_reverse(array_keys($tables)) as $table) {
            if (isset($tables[$table]['key'])) {
                foreach ($delete[$table] ?? [] as $row) {
                    DB::table($table)->where(array_intersect_key($row, array_flip($tables[$table]['key'])))->delete();
                }
            } else {
                foreach (array_chunk(array_keys($delete[$table] ?? []), 500) as $keys) {
                    DB::table($table)->whereIn('id', $keys)->delete();
                }
            }
        }
        foreach (array_keys($tables) as $table) {
            $rows = array_values($insert[$table] ?? []);
            foreach ($rows === [] ? [] : array_chunk($rows, max(1, intdiv(900, count($rows[0])))) as $chunk) {
                DB::table($table)->insert($chunk);
            }
            foreach ($update[$table] ?? [] as $key => $changes) {
                DB::table($table)->where('id', $key)->update($changes);
            }
        }
        DB::table('demo_baselines')->where('id', $baseline->id)->update(['restored_at' => now(), 'changed_since' => null, 'changed_at' => null, 'problem' => null]);
        self::record($actor, 'demo_reset', (int) $baseline->id, ['automatic' => $automatic, ...$counts]);

        return $counts;
    }

    /**
     * The demo world as the database holds it now.
     *
     * @param  list<int>  $accounts
     * @return array<string, array<int|string, array<string, mixed>>>
     */
    private static function collect(array $accounts): array
    {
        $world = [];
        $sets = ['users' => array_fill_keys($accounts, true)];
        foreach (self::tables() as $table => $rules) {
            $world[$table] = [];
            $first = array_key_first($rules['in']);
            foreach (array_chunk(array_keys(self::values($sets, $world, $rules['in'][$first])), 500) as $chunk) {
                foreach (DB::table($table)->whereIn($first, $chunk)->where($rules['where'] ?? [])->get() as $row) {
                    $row = (array) $row;
                    if (self::inside($rules, $row, $sets, $world)) {
                        $world[$table][self::key($rules, $row)] = $row;
                    }
                }
            }
        }

        return $world;
    }

    /**
     * The saved rows as they can return today: each still inside the demo, and none pointing at something that is gone.
     *
     * @param  array<string, list<array<string, mixed>>>  $saved
     * @param  list<int>  $accounts
     * @return array<string, array<int|string, array<string, mixed>>>
     */
    private static function target(array $saved, array $accounts): array
    {
        $target = [];
        $sets = ['users' => array_fill_keys($accounts, true)];
        foreach (self::tables() as $table => $rules) {
            $target[$table] = [];
            $rows = $saved[$table] ?? [];
            if ($rows === []) {
                continue;
            }
            // A column dropped since the copy was made cannot be written back.
            if (array_diff(array_keys($rows[0]), Schema::getColumnListing($table)) !== []) {
                throw new DemoResetRefused('structure|'.$table);
            }
            $outside = ($rules['needs'] ?? []) + ($rules['clears'] ?? []);
            $existing = [];
            foreach ($outside as $column => $other) {
                $existing[$column] = [];
                foreach (array_chunk(array_values(array_unique(array_filter(array_column($rows, $column), fn (mixed $value): bool => $value !== null))), 500) as $chunk) {
                    $existing[$column] += array_fill_keys(DB::table($other)->whereIn('id', $chunk)->pluck('id')->all(), true);
                }
            }
            foreach ($rows as $row) {
                if (! self::inside($rules, $row, $sets, $target)) {
                    throw new DemoResetRefused('accounts');
                }
                foreach ($outside as $column => $other) {
                    // Present now, or a demo row this reset is about to bring back.
                    if ($row[$column] !== null && ! isset($existing[$column][$row[$column]]) && ! isset($target[$other][$row[$column]])) {
                        if (isset($rules['needs'][$column])) {
                            continue 2;
                        }
                        $row[$column] = null;
                    }
                }
                $target[$table][self::key($rules, $row)] = $row;
            }
        }

        return $target;
    }

    /**
     * Nothing outside the demo may point at a row about to be deleted, whatever the database would
     * do about it (refuse, cascade or empty the pointer). The foreign keys are read from the database
     * itself, so a table added later is covered without being listed here.
     *
     * @param  array<string, array<int|string, array<string, mixed>>>  $delete
     */
    private static function guard(array $delete): void
    {
        if ($delete === []) {
            return;
        }
        $tables = self::tables();
        foreach (Schema::getTables(Schema::getCurrentSchemaName()) as $listed) {
            $child = $listed['name'];
            $rules = $tables[$child] ?? null;
            foreach (Schema::getForeignKeys($child) as $foreign) {
                $parent = $foreign['foreign_table'];
                if (empty($delete[$parent]) || isset($tables[$parent]['key']) || count($foreign['columns']) !== 1 || $foreign['foreign_columns'] !== ['id']) {
                    continue;
                }
                $column = $foreign['columns'][0];
                foreach (array_chunk(array_keys($delete[$parent]), 500) as $keys) {
                    foreach (DB::table($child)->whereIn($column, $keys)->get($rules === null ? [$column] : ($rules['key'] ?? ['id'])) as $row) {
                        if ($rules === null || ! isset($delete[$child][self::key($rules, (array) $row)])) {
                            throw new DemoResetRefused('dependents|'.$parent.'|'.$child);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $row
     * @param  array<string, array<int|string, true>>  $sets
     * @param  array<string, array<int|string, array<string, mixed>>>  $world
     */
    private static function inside(array $rules, array $row, array &$sets, array $world): bool
    {
        foreach ($rules['in'] as $column => $parent) {
            if (! isset(self::values($sets, $world, $parent)[$row[$column] ?? ''])) {
                return false;
            }
        }
        foreach ($rules['maybe'] ?? [] as $column => $parent) {
            if ($row[$column] !== null && ! isset(self::values($sets, $world, $parent)[$row[$column]])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The values a pointer into the demo may take: the demo accounts, a collected table's keys, or one of its columns.
     *
     * @param  array<string, array<int|string, true>>  $sets
     * @param  array<string, array<int|string, array<string, mixed>>>  $world
     * @return array<int|string, true>
     */
    private static function values(array &$sets, array $world, string $parent): array
    {
        if (! isset($sets[$parent])) {
            [$table, $column] = array_pad(explode('.', $parent), 2, null);
            $sets[$parent] = array_fill_keys($column === null ? array_keys($world[$table]) : array_column($world[$table], $column), true);
        }

        return $sets[$parent];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $row
     */
    private static function key(array $rules, array $row): string
    {
        return implode('|', array_map(fn (string $column): string => (string) $row[$column], $rules['key'] ?? ['id']));
    }

    /**
     * The saved values that differ from the row as it is now. Only the columns the copy holds are compared.
     *
     * @param  array<string, mixed>  $saved
     * @param  array<string, mixed>  $now
     * @return array<string, mixed>
     */
    private static function changes(array $saved, array $now): array
    {
        $text = fn (mixed $value): ?string => $value === null ? null : (string) (is_bool($value) ? (int) $value : $value);

        return array_filter($saved, fn (mixed $value, string $column): bool => $text($value) !== $text($now[$column] ?? null), ARRAY_FILTER_USE_BOTH);
    }

    /** @param array<string, mixed> $copy */
    private static function pack(array $copy): string
    {
        return base64_encode((string) gzencode(json_encode($copy, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 6));
    }

    /** @return array{accounts: array<int, string>, tables: array<string, list<array<string, mixed>>>} */
    private static function unpack(string $payload): array
    {
        return json_decode((string) gzdecode((string) base64_decode($payload, true)), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $details */
    private static function record(?User $actor, string $action, int $baseline, array $details): void
    {
        DB::table('moderation_events')->insert(['actor_id' => $actor?->id, 'actor_email' => $actor?->email, 'action' => $action,
            'target_type' => 'demo', 'target_id' => $baseline, 'reason' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
