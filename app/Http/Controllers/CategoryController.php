<?php

namespace App\Http\Controllers;

use App\Actions\CreateCategory;
use App\Http\Requests\SaveCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Category::class);
        $filters = $request->validate(['status' => ['nullable', 'in:active,deleted'], 'page' => ['nullable', 'integer', 'min:1']]);
        $status = $filters['status'] ?? 'active';
        $query = Category::query();
        if ($status === 'deleted') {
            $query->onlyTrashed();
        }

        return Inertia::render('admin/categories/index', [
            'categories' => $query->orderBy('id', 'desc')->paginate(10)->withQueryString(),
            'status' => $status,
            'notice' => $request->session()->get('category_notice'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Category::class);

        return Inertia::render('admin/categories/form', ['category' => null]);
    }

    public function store(SaveCategoryRequest $request, CreateCategory $createCategory): RedirectResponse
    {
        $createCategory($request->validated('categoryname'));

        return to_route('admin.categories.index')->with('category_notice', 'created');
    }

    public function edit(Category $category): Response
    {
        Gate::authorize('update', $category);

        return Inertia::render('admin/categories/form', ['category' => $category]);
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return to_route('admin.categories.index')->with('category_notice', 'updated');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);
        $category->delete();

        return to_route('admin.categories.index')->with('category_notice', 'deleted');
    }

    public function restore(Category $category): RedirectResponse
    {
        Gate::authorize('restore', $category);
        $category->restore();

        return to_route('admin.categories.index', ['status' => 'deleted'])->with('category_notice', 'restored');
    }
}
