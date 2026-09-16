<?php

namespace App\Http\Requests\Vendor;

use App\Rules\AllowedVendorPseudonym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('vendor')->check();
    }

    public function rules(): array
    {
        $vendor = Auth::guard('vendor')->user();

        return [
            'pseudonym' => ['required', 'string', 'max:30', Rule::unique('vendors', 'pseudonym')->ignore($vendor->id), new AllowedVendorPseudonym],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('vendors', 'email')->ignore($vendor->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $vendor = Auth::guard('vendor')->user();
            if (! Hash::check($this->current_password, $vendor->password)) {
                $validator->errors()->add('current_password', __('validation.incorrect_current_password'));
            }
        });
    }
}
