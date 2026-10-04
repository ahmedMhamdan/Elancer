<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $contract_id
 * @property int $contract_submission_id
 * @property int $round
 * @property string $changes
 * @property CarbonImmutable $created_at
 */
class ContractRevisionRequest extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('A formal revision request is immutable.'));
    }
}
