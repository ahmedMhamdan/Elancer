<?php

namespace App\Actions\Portfolio;

use App\Actions\PrepareProfilePhoto;
use App\Models\Contract;
use App\Models\PortfolioApproval;
use App\Models\PortfolioCase;
use App\Models\PortfolioImage;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Q24, Q52, Q53, Q56: a case keeps a private working copy and a public version.
 * Completed Elancer work becomes public only with the client's approval of the exact content.
 */
class PortfolioCases
{
    public const MAX_CASES = 12;

    /** Images one version of a case can list. */
    public const MAX_IMAGES = 6;

    /** Images a case can store, counting those kept for earlier requests. */
    public const MAX_STORED = 30;

    /** @param  array<string, mixed>  $content */
    public static function create(User $owner, array $content, ?int $contract): PortfolioCase
    {
        return DB::transaction(function () use ($owner, $content, $contract): PortfolioCase {
            // The owner row serializes the limit and the one-case-per-contract check.
            User::query()->lockForUpdate()->findOrFail($owner->id);
            if (PortfolioCase::query()->where('user_id', $owner->id)->count() >= self::MAX_CASES) {
                throw ValidationException::withMessages(['case' => __('A portfolio can hold at most :count case studies.', ['count' => self::MAX_CASES])]);
            }
            if ($contract !== null && ! self::eligibleContracts($owner->id)->whereKey($contract)->exists()) {
                throw ValidationException::withMessages(['contract' => __('Choose a completed contract of yours that has no case study yet.')]);
            }
            $case = new PortfolioCase;
            $case->forceFill(['user_id' => $owner->id, 'contract_id' => $contract, 'content' => $content])->save();

            return $case;
        }, 3);
    }

    /**
     * Completed contracts the member delivered that have no case study yet.
     *
     * @return Builder<Contract>
     */
    public static function eligibleContracts(int $owner)
    {
        return Contract::query()->where('freelancer_id', $owner)->where('status', 'completed')
            ->whereNotIn('id', PortfolioCase::query()->whereNotNull('contract_id')->select('contract_id'));
    }

    /** @param  array<string, mixed>  $content */
    public static function update(PortfolioCase $case, array $content): void
    {
        DB::transaction(function () use ($case, $content): void {
            $locked = PortfolioCase::query()->lockForUpdate()->findOrFail($case->id);
            $locked->forceFill(['content' => $content])->save();
            self::prune($locked);
        }, 3);
    }

    /**
     * Stores an image only after it passed the content check. It stays private until a
     * saved version lists it, and it is never replaced in place (Q52).
     */
    public static function addImage(PortfolioCase $case, UploadedFile $file, PrepareProfilePhoto $prepare): PortfolioImage
    {
        // Checked before the provider call too, so a refused upload is never sent for checking.
        self::refuseWhenFull($case->id);
        $bytes = $prepare->image($file);
        $size = getimagesizefromstring($bytes) ?: [0, 0];
        $path = 'portfolio-images/'.$case->id.'/'.Str::uuid().'.jpg';
        try {
            return DB::transaction(function () use ($case, $bytes, $size, $path): PortfolioImage {
                PortfolioCase::query()->lockForUpdate()->findOrFail($case->id);
                self::refuseWhenFull($case->id);
                if (! Storage::disk('local')->put($path, $bytes)) {
                    throw ValidationException::withMessages(['image' => __('We could not save this image. Please try again.')]);
                }
                $image = new PortfolioImage;
                $image->forceFill(['portfolio_case_id' => $case->id, 'path' => $path, 'width' => $size[0], 'height' => $size[1]])->save();

                return $image;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    private static function refuseWhenFull(int $case): void
    {
        if (PortfolioImage::query()->where('portfolio_case_id', $case)->count() >= self::MAX_STORED) {
            throw ValidationException::withMessages(['image' => __('This case study stores too many images. Save it to clear the ones you removed, then try again.')]);
        }
    }

    /**
     * Removes images that the working copy, the public version and every request no longer
     * list. Images named by a past request stay as private history. Call inside the case lock.
     */
    private static function prune(PortfolioCase $case): void
    {
        $kept = PortfolioApproval::query()->where('portfolio_case_id', $case->id)->get()->map(fn (PortfolioApproval $approval) => $approval->content)
            ->push($case->content, $case->public_content)
            ->flatMap(fn (?array $content) => array_column($content['images'] ?? [], 'id'))->all();
        $unused = PortfolioImage::query()->where('portfolio_case_id', $case->id)->whereNotIn('id', $kept)->get();
        if ($unused->isEmpty()) {
            return;
        }
        PortfolioImage::query()->whereKey($unused->modelKeys())->delete();
        $paths = $unused->pluck('path')->all();
        DB::afterCommit(fn () => rescue(fn () => Storage::disk('local')->delete($paths)));
    }

    /** The owner's own actions. Client-owned work is never published from here. */
    public static function act(PortfolioCase $case, string $action): void
    {
        DB::transaction(function () use ($case, $action): void {
            $locked = PortfolioCase::query()->lockForUpdate()->findOrFail($case->id);
            $pending = PortfolioApproval::query()->where('open_case_id', $locked->id)->lockForUpdate()->first();
            if ($action === 'hide' || $action === 'show') {
                $locked->forceFill(['hidden_at' => $action === 'hide' ? ($locked->hidden_at ?? now()) : null])->save();

                return;
            }
            if ($action === 'publish') {
                if ($locked->contract_id !== null) {
                    throw ValidationException::withMessages(['case' => __('Work completed on Elancer is published by its client\'s approval.')]);
                }
                $locked->forceFill(['public_content' => $locked->content, 'published_at' => now()])->save();
                self::prune($locked);

                return;
            }
            if ($action === 'withdraw') {
                if (! $pending) {
                    throw ValidationException::withMessages(['case' => __('This request is no longer awaiting an answer. Reload to see the current status.')]);
                }
                $pending->forceFill(['status' => 'withdrawn', 'decided_at' => now(), 'open_case_id' => null])->save();

                return;
            }
            // request
            $contract = $locked->contract_id === null ? null : Contract::query()->find($locked->contract_id);
            if (! $contract) {
                throw ValidationException::withMessages(['case' => __('Only work completed on Elancer is sent to a client for approval.')]);
            }
            if ($pending) {
                throw ValidationException::withMessages(['case' => __('A request for this case study is still awaiting the client\'s answer.')]);
            }
            if ($locked->public_content == $locked->content) {
                throw ValidationException::withMessages(['case' => __('The client has already approved this exact content.')]);
            }
            $approval = new PortfolioApproval;
            $approval->forceFill(['portfolio_case_id' => $locked->id, 'open_case_id' => $locked->id, 'content' => $locked->content, 'status' => 'pending'])->save();
            self::notify($contract, 'portfolio_requested', $contract->client_id, $contract->freelancer_id);
        }, 3);
    }

    /** The answer names the request it was shown, so a stale page cannot approve different content. */
    public static function respond(Contract $contract, int $approval, string $action): void
    {
        DB::transaction(function () use ($contract, $approval, $action): void {
            $case = PortfolioCase::query()->where('contract_id', $contract->id)->lockForUpdate()->first();
            $pending = $case ? PortfolioApproval::query()->where('open_case_id', $case->id)->lockForUpdate()->first() : null;
            if (! $case || ! $pending || $pending->id !== $approval) {
                throw ValidationException::withMessages(['portfolio' => __('This request is no longer awaiting an answer. Reload to see the current status.')]);
            }
            if ($action === 'approve') {
                $case->forceFill(['public_content' => $pending->content, 'published_at' => now(), 'revoked_at' => null])->save();
            }
            $pending->forceFill(['status' => $action === 'approve' ? 'approved' : 'declined', 'decided_at' => now(), 'open_case_id' => null])->save();
            self::notify($contract, 'portfolio_'.$pending->status, $contract->freelancer_id, $contract->client_id);
        }, 3);
    }

    /** Q53: removes the public version at once; nothing earlier can bring it back. */
    public static function revoke(Contract $contract): void
    {
        DB::transaction(function () use ($contract): void {
            $case = PortfolioCase::query()->where('contract_id', $contract->id)->lockForUpdate()->first();
            if (! $case || $case->public_content === null) {
                throw ValidationException::withMessages(['portfolio' => __('There is no published case study to withdraw. Reload to see the current status.')]);
            }
            PortfolioApproval::query()->where('portfolio_case_id', $case->id)->where('status', 'approved')->update(['status' => 'revoked']);
            PortfolioApproval::query()->where('open_case_id', $case->id)->update(['status' => 'closed', 'decided_at' => now(), 'open_case_id' => null]);
            $case->forceFill(['public_content' => null, 'published_at' => null, 'revoked_at' => now()])->save();
            self::notify($contract, 'portfolio_revoked', $contract->freelancer_id, $contract->client_id);
        }, 3);
    }

    /** A case that was ever sent to a client is kept as history. */
    public static function delete(PortfolioCase $case): void
    {
        DB::transaction(function () use ($case): void {
            $locked = PortfolioCase::query()->lockForUpdate()->findOrFail($case->id);
            if (PortfolioApproval::query()->where('portfolio_case_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['case' => __('A case study that was sent to a client is kept as history. You can hide it instead.')]);
            }
            $locked->delete();
            DB::afterCommit(fn () => rescue(fn () => Storage::disk('local')->deleteDirectory('portfolio-images/'.$locked->id)));
        }, 3);
    }

    private static function notify(Contract $contract, string $kind, int $recipient, int $actor): void
    {
        $title = $contract->agreement['project_title'] ?? null;
        $name = $actor === $contract->client_id ? $contract->agreement['client_name'] ?? null : $contract->agreement['freelancer_name'] ?? null;
        DB::afterCommit(fn () => User::query()->find($recipient)?->notify(new WorkspaceEvent($kind, '/contracts/'.$contract->id.'?tab=portfolio', $title, $name)));
    }
}
