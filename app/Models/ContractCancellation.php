<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $contract_id
 * @property int|null $open_contract_id
 * @property int $requester_id
 * @property string $reason
 * @property string $prior_status
 * @property string $status
 * @property string|null $refund_status
 * @property string|null $refund_reference
 * @property string|null $refund_failure
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable $created_at
 */
class ContractCancellation extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime', 'refunded_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
