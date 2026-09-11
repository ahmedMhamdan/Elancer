<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property string $slug
 */
class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ['name_en', 'name_ar', 'slug'];
}
