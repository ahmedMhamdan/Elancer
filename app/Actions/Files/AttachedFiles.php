<?php

namespace App\Actions\Files;

use App\Models\ProjectAttachment;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Plan 030 (plan 003 sections 10 and 15): what a proposal or a message may carry, and how much
 * member files one account and the whole site may keep before a new one is refused.
 */
class AttachedFiles
{
    public const LIMIT = 3;

    public const KILOBYTES = ProjectAttachment::KILOBYTES;

    /** A brief's types plus ZIP: each of these files has one named recipient, as a delivery has. Nothing opens an archive. */
    public const TYPES = ProjectAttachment::TYPES + ['zip' => 'application/zip'];

    /**
     * The rules for one file. The content must be what the name says, so a renamed program is refused.
     *
     * @return list<mixed>
     */
    public static function rules(): array
    {
        return ['bail', 'required', 'file', 'max:'.self::KILOBYTES, 'extensions:'.implode(',', array_keys(self::TYPES)),
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! $value instanceof UploadedFile || ! str_starts_with((string) $value->getMimeType(), self::TYPES[strtolower($value->getClientOriginalExtension())] ?? '-')) {
                    $fail(__('Attach a JPG, PNG, WebP, PDF, TXT or ZIP file.'));
                }
            }];
    }

    /** @return array<string, string> */
    public static function messages(string $attribute): array
    {
        return [$attribute.'.max' => __('Each file can be up to 5 MB.'), $attribute.'.extensions' => __('Attach a JPG, PNG, WebP, PDF, TXT or ZIP file.'),
            $attribute.'.uploaded' => __('This file could not be uploaded. It may be larger than this server accepts.')];
    }

    /** Stores the file under a generated name in a folder of its own and answers where. */
    public static function store(UploadedFile $file, string $folder, string $field): string
    {
        $path = $folder.'/'.Str::uuid();
        if (! Storage::disk('uploads')->put($path, $file->getContent())) {
            throw ValidationException::withMessages([$field => __('We could not save this file. Please try again.')]);
        }

        return $path;
    }

    /** The name a member gave the file, without any folder, cut to fit. */
    public static function name(UploadedFile $file): string
    {
        return Str::limit(basename($file->getClientOriginalName()), 200, '');
    }

    /**
     * Refuses new files that would take the account or the site past its budget. The member's row should be
     * locked by the caller so two uploads at once cannot both fit in the same room.
     */
    public static function ensureRoom(int $user, int $bytes, string $field): void
    {
        $megabyte = 1024 * 1024;
        $account = (int) config('filesystems.budgets.account') * $megabyte;
        $site = (int) config('filesystems.budgets.site') * $megabyte;
        if (self::used($user) + $bytes > $account) {
            throw ValidationException::withMessages([$field => __('Your account has used its :size MB for files. Files you already attached stay; new ones cannot be added.', ['size' => (int) config('filesystems.budgets.account')])]);
        }
        if (self::used(null) + $bytes > $site) {
            throw ValidationException::withMessages([$field => __('Elancer has no room for new files right now. Files you already attached stay.')]);
        }
    }

    /** Bytes of member files still stored: one member's, or everyone's when no member is given. Portfolio images keep no size and are not counted. */
    public static function used(?int $user): int
    {
        $sum = fn (string $table, ?Closure $owner = null): int => (int) DB::table($table)->when($user !== null && $owner, $owner)->sum($table.'.size');

        return $sum('project_attachments', fn ($q) => $q->join('projects', 'projects.id', '=', 'project_attachments.project_id')->where('projects.user_id', $user))
            + $sum('proposal_files', fn ($q) => $q->join('proposals', 'proposals.id', '=', 'proposal_files.proposal_id')->where('proposals.user_id', $user))
            + $sum('message_files', fn ($q) => $q->join('conversation_messages', 'conversation_messages.id', '=', 'message_files.conversation_message_id')->where('conversation_messages.sender_id', $user))
            + $sum('contract_submission_files', fn ($q) => $q->join('contract_submissions', 'contract_submissions.id', '=', 'contract_submission_files.contract_submission_id')
                ->join('contracts', 'contracts.id', '=', 'contract_submissions.contract_id')->where('contracts.freelancer_id', $user));
    }
}
