<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Policies\AdministratorPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdministratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && app(AdministratorPolicy::class)->update($this->user(), $target);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_admin' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
            'is_super_admin' => ['missing'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        if ($this->user()->locale !== 'ar') {
            return [];
        }

        return [
            'required' => 'هذا الحقل مطلوب.',
            'string' => 'أدخل نصاً صالحاً.',
            'max' => 'الحد الأقصى 500 حرف.',
            'boolean' => 'اختر صلاحية صالحة.',
            'missing' => 'لا يمكن تعديل صلاحية المشرف العام هنا.',
        ];
    }
}
