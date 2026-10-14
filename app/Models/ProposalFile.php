<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $proposal_id
 * @property string $name
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $removed_at
 * @property-read Proposal $proposal
 */
class ProposalFile extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    // The stored location never leaves the server.
    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'submitted_at' => 'immutable_datetime', 'removed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Proposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
