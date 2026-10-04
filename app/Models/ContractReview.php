<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $contract_id
 * @property int $author_id
 * @property int $subject_id
 * @property int $rating
 * @property string $body
 * @property CarbonImmutable $created_at
 * @property-read Contract $contract
 */
class ContractReview extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
