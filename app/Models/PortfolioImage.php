<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $portfolio_case_id
 * @property string $path
 * @property int $width
 * @property int $height
 */
class PortfolioImage extends Model
{
    protected $guarded = ['*'];
}
