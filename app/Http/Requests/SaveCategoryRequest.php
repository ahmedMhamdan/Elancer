<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', Category::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'categoryname' => ['required', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $ar = $this->user()->locale === 'ar';

        return [
            'required' => $ar ? 'هذا الحقل مطلوب.' : 'This field is required.',
            'string' => $ar ? 'أدخل نصاً صالحاً.' : 'Enter valid text.',
            'max' => $ar ? 'الحد الأقصى 120 حرفاً.' : 'Use no more than 120 characters.',
        ];
    }
}
