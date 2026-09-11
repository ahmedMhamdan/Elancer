<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $categoryname
 * @property string $slug
 */
class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ['categoryname'];

    // Retained only to preserve translations entered before the single-name change.
    protected $hidden = ['legacy_name_ar'];

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            // Keep Arabic letters readable; normalize punctuation and whitespace.
            $name = preg_replace('/\p{M}+/u', '', $category->categoryname) ?? '';
            $base = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', Str::lower($name)) ?? '', '-');
            $base = rtrim(Str::substr($base, 0, 100), '-') ?: 'category';
            $slug = $base;
            $suffix = 2;

            // Soft-deleted rows still reserve their slugs.
            while (static::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$suffix++;
            }

            $category->slug = $slug;
        });
        // Renaming preserves the slug so existing links remain stable.
    }
}
