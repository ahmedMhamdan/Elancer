<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $category = $this->route('category');

        return [
            'name_en' => ['required', 'string', 'max:120'],
            'name_ar' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique('categories', 'slug')->ignore($category instanceof Category ? $category->id : null)],
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
            'slug.regex' => $ar ? 'استخدم أحرفاً إنجليزية صغيرة وأرقاماً تفصلها شرطات.' : 'Use lowercase English letters and numbers separated by single hyphens.',
            'slug.unique' => $ar ? 'هذا الرابط مستخدم، وقد يكون لتصنيف محذوف.' : 'This slug is already reserved, possibly by a deleted category.',
        ];
    }
}
