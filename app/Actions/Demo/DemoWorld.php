<?php

namespace App\Actions\Demo;

use App\Actions\Auth\DemoAccess;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Plan 027 (plan 003 section 10): the prepared accounts and what they hold are a world apart from
 * real members. This class answers who is inside it, who is looking, and what that changes:
 * a demo account and a real member never hire each other, a member may try applying to a demo project
 * and no more, and what a demo account wrote after the saved starting point is shown inside the demo only.
 */
class DemoWorld
{
    /** The reserved address domain every demo account is on; no real member can register on it. */
    public static function domain(): string
    {
        return strtolower(trim((string) config('demo.domain')));
    }

    public static function address(?string $email): bool
    {
        return self::domain() !== '' && str_ends_with(strtolower(trim((string) $email)), '@'.self::domain());
    }

    /**
     * The demo accounts' numbers. The reset reads them fresh; a page reads them once.
     *
     * @return list<int>
     */
    public static function accounts(bool $fresh = false): array
    {
        $read = fn (): array => self::domain() === '' ? [] : array_values(array_map('intval', User::query()->toBase()
            ->whereRaw("LOWER(email) LIKE ? ESCAPE '!'", ['%@'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], self::domain())])
            ->orderBy('id')->pluck('id')->all()));

        return $fresh ? $read() : self::once('accounts', $read);
    }

    public static function holds(?int $account): bool
    {
        return $account !== null && in_array($account, self::accounts(), true);
    }

    /** Exactly one of the two is a demo account, so they may look at each other and nothing more. */
    public static function apart(?int $first, ?int $second): bool
    {
        return self::holds($first) !== self::holds($second);
    }

    /**
     * A real member applying to a demo account's project. Ahmed opened this on 2026-10-13 so that a registrant can
     * try applying while almost every project is a demo one. The application stays with the member: the shared demo
     * account is never shown it or told of it, and it takes no conversation and no offer.
     */
    public static function trial(?int $applicant, ?int $owner): bool
    {
        return $applicant !== null && self::holds($owner) && ! self::holds($applicant);
    }

    /** Leaves members' applications out of what the demo account that owns the project is shown. */
    public static function ownApplicants(Builder $query, ?int $owner, string $applicant = 'user_id'): void
    {
        if (self::holds($owner)) {
            $query->whereIn($applicant, self::accounts());
        }
    }

    /** The saved starting point without its copy of the rows, or null while none was saved. */
    public static function baseline(bool $fresh = false): ?stdClass
    {
        $read = function (): ?stdClass {
            $row = DB::table('demo_baselines')->orderByDesc('id')->first(['id', 'taken_by', 'taken_at', 'summary', 'changed_since', 'changed_at', 'restored_at', 'problem']);

            return $row instanceof stdClass ? $row : null;
        };

        return $fresh ? $read() : self::once('baseline', $read);
    }

    /** A visitor who enters now may change things: acting is switched on and a reset can take it back. */
    public static function open(): bool
    {
        return (bool) config('demo.actions') && self::baseline() !== null;
    }

    public static function acting(Request $request): bool
    {
        return DemoAccess::active($request) && self::open();
    }

    /** Which side the owner is on when the viewer and the owner are apart ('demo' or 'member'), or null when they may hire each other. */
    public static function across(?int $viewer, int $owner): ?string
    {
        return $viewer === null || ! self::apart($viewer, $owner) ? null : (self::holds($owner) ? 'demo' : 'member');
    }

    /** A demo session changed something; the next reset has work to do. */
    public static function touched(): void
    {
        $baseline = self::baseline();
        if ($baseline !== null) {
            DB::table('demo_baselines')->where('id', $baseline->id)->update(['changed_at' => now(), ...($baseline->changed_since === null ? ['changed_since' => now()] : [])]);
        }
    }

    /**
     * The moment after which a demo account's writing is hidden from whoever is looking, or null when
     * nothing is hidden: a demo account sees the whole demo, a guest or a real member sees it as saved.
     */
    public static function hiddenSince(): ?string
    {
        $viewer = Auth::user();
        if (($viewer !== null && self::holds((int) $viewer->getAuthIdentifier())) || self::accounts() === []) {
            return null;
        }

        return self::baseline()?->taken_at;
    }

    /** Whether rows of this owner written after the starting point are hidden from whoever is looking. */
    public static function hides(?int $owner): bool
    {
        return self::holds($owner) && self::hiddenSince() !== null;
    }

    /** Leaves out what a demo account wrote after the starting point, for a viewer outside the demo. */
    public static function keepInside(Builder $query, string $owner, string $written): void
    {
        $since = self::hiddenSince();
        if ($since !== null) {
            $accounts = self::accounts();
            $query->where(fn (Builder $inner) => $inner->whereNotIn($owner, $accounts)->orWhere($written, '<=', $since));
        }
    }

    /** After the starting point or the accounts changed within one request. */
    public static function forget(): void
    {
        request()->attributes->remove('demo.accounts');
        request()->attributes->remove('demo.baseline');
    }

    /**
     * Read once per page request. Outside one (a command, a queued job) nothing is kept,
     * because that process may live longer than the answer stays true.
     *
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    private static function once(string $name, Closure $read): mixed
    {
        $request = request();
        if ($request->route() === null) {
            return $read();
        }
        if (! $request->attributes->has('demo.'.$name)) {
            $request->attributes->set('demo.'.$name, [$read()]);
        }

        return $request->attributes->get('demo.'.$name)[0];
    }
}
