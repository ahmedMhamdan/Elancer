<?php

namespace App\Actions;

use App\Models\Category;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateCategory
{
    public function __invoke(string $categoryname): Category
    {
        // Retry a concurrent slug collision outside the failed transaction.
        // Each attempt creates a fresh model and chooses the next available slug.
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction(fn (): Category => Category::create([
                    'categoryname' => $categoryname,
                ]));
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 4) {
                    throw $exception;
                }
            }
        }
    }
}
