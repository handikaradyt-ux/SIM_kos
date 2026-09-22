<?php

namespace App\Http\Requests\Placement;

use App\Models\Placement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePlacementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Evaluated BEFORE validation so unauthorized roles receive 403.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Placement::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'resident_id' => [
                'bail',
                'required',
                'integer',
                'exists:residents,id',
            ],
            'room_id' => [
                'bail',
                'required',
                'integer',
                'exists:rooms,id',
            ],
            'preview_token' => [
                'bail',
                'required',
                'string',
                'max:100',
            ],
        ];
    }

    /**
     * Custom Indonesian validation error messages.
     */
    public function messages(): array
    {
        return [
            'resident_id.required' => 'Penghuni wajib dipilih.',
            'resident_id.integer' => 'Penghuni harus berupa ID yang valid.',
            'resident_id.exists' => 'Penghuni yang dipilih tidak ditemukan.',
            'room_id.required' => 'Kamar wajib dipilih.',
            'room_id.integer' => 'Kamar harus berupa ID yang valid.',
            'room_id.exists' => 'Kamar yang dipilih tidak ditemukan.',
            'preview_token.required' => 'Token preview penempatan wajib disertakan.',
            'preview_token.string' => 'Token preview tidak valid.',
            'preview_token.max' => 'Token preview melebihi batas panjang.',
        ];
    }

    /**
     * Sanitize and limit validated fields.
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        $sanitized = [
            'resident_id' => (int) ($data['resident_id'] ?? 0),
            'room_id' => (int) ($data['room_id'] ?? 0),
            'preview_token' => (string) ($data['preview_token'] ?? ''),
        ];

        return $key ? data_get($sanitized, $key, $default) : $sanitized;
    }
}
