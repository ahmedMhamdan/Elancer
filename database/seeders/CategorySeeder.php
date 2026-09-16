<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Development & IT', 'Design & Creative', 'Writing & Translation', 'Marketing & Sales', 'Business & Consulting', 'Video & Animation', 'Data & Analytics', 'Engineering & Architecture'] as $name) {
            $slug = Str::slug($name);
            // Never rename an existing category or recreate an intentionally deleted one.
            if (Category::withTrashed()->where('categoryname', $name)->orWhere('slug', $slug)->exists()) {
                continue;
            }
            $category = new Category;
            $category->forceFill(['categoryname' => $name, 'slug' => $slug])->save();
        }
    }
}
