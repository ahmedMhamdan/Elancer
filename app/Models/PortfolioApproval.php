<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $portfolio_case_id
 * @property int|null $open_case_id
 * @property array<string, mixed> $content
 * @property string $status
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable $created_at
 */
class PortfolioApproval extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['content' => 'array', 'decided_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
