<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan 031: a case study's public version as a proposal sent it (Q55).
 *
 * @property int $id
 * @property int $proposal_id
 * @property int $portfolio_case_id
 * @property array<string, mixed> $content
 * @property CarbonImmutable $created_at
 * @property-read Proposal $proposal
 * @property-read PortfolioCase $case
 */
class ProposalCase extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['content' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Proposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /** @return BelongsTo<PortfolioCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(PortfolioCase::class, 'portfolio_case_id');
    }
}
