<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $contract_id
 * @property int $number
 * @property string $message
 * @property list<string> $links
 * @property CarbonImmutable $created_at
 */
class ContractSubmission extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['links' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('A formal delivery is immutable.'));
    }

    /** @return HasMany<ContractSubmissionFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ContractSubmissionFile::class)->orderBy('id');
    }

    /** @return HasOne<ContractRevisionRequest, $this> */
    public function revision(): HasOne
    {
        return $this->hasOne(ContractRevisionRequest::class);
    }
}
