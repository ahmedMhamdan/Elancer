<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $contract_id
 * @property int|null $open_contract_id
 * @property int $proposer_id
 * @property string $reason
 * @property string|null $date_kind
 * @property CarbonImmutable|null $new_due_at
 * @property CarbonImmutable|null $previous_due_at
 * @property int $extra_rounds
 * @property int $revisions_used
 * @property string $status
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable $created_at
 */
class ContractAmendment extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['new_due_at' => 'immutable_datetime', 'previous_due_at' => 'immutable_datetime', 'extra_rounds' => 'integer',
            'revisions_used' => 'integer', 'decided_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
