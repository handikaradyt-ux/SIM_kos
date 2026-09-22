<?php

namespace App\Http\Requests\Resident;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateResidentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Evaluated BEFORE validation rules to guarantee 403 Forbidden for unauthorized roles.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('resident'));
    }

    /**
     * Get the validation rules that apply to the request.
     * Whitelist strictly permits only name, email, phone, and origin_address.
     */
    public function rules(): array
    {
        $resident = $this->route('resident');
        $userId = $resident?->user_id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => [
                'required',
                'string',
                'email:filter',
                'max:191',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['required', 'string', 'min:8', 'max:20', 'regex:/^(?=.*[0-9])[0-9+\-\s]{8,20}$/'],
            'origin_address' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * Custom validation error messages in Indonesian.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.min' => 'Nama lengkap minimal 2 karakter.',
            'name.max' => 'Nama lengkap maksimal 100 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh akun lain.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.min' => 'Nomor telepon minimal 8 karakter.',
            'phone.max' => 'Nomor telepon maksimal 20 karakter.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, tanda +, tanda hubung, atau spasi, dan wajib mengandung angka.',
            'origin_address.required' => 'Alamat asal wajib diisi.',
            'origin_address.min' => 'Alamat asal minimal 5 karakter.',
            'origin_address.max' => 'Alamat asal maksimal 255 karakter.',
        ];
    }
}
