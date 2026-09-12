<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string|null $id_path
 * @property string|null $selfie_path
 * @property string $id_mime
 * @property string $selfie_mime
 * @property string|null $reason
 */
class IdentityVerification extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['id_path', 'selfie_path', 'id_mime', 'selfie_mime'];

    protected static function booted(): void
    {
        static::deleted(function (self $verification): void {
            Storage::disk('identity')->delete(array_filter([$verification->id_path, $verification->selfie_path]));
        });
    }
}
