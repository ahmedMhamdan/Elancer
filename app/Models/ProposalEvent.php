<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $proposal_id
 * @property string $kind
 * @property array<string, mixed>|null $content
 * @property CarbonImmutable $created_at
 */
class ProposalEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['content' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
