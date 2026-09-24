<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['content' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
