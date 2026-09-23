<?php

namespace App\Http\Requests\Placement;

use App\Models\Placement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreEndPlacementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Evaluates active Admin authority via policy. Unauthorized roles receive 403.
     */
    public function authorize(): bool
    {
        $placement = $this->route('placement');

        return Gate::allows('end', $placement ?? Placement::class);
    }

    /**
     * Prepare the data for validation.
     * Trim end_reason before min/max validation so whitespace-only inputs fail.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('end_reason') && is_string($this->input('end_reason'))) {
            $this->merge([
                'end_reason' => trim($this->input('end_reason')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'end_reason' => [
                'bail',
                'required',
                'string',
                'min:5',
                'max:255',
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
            'end_reason.required' => 'Alasan pengakhiran penempatan wajib diisi.',
            'end_reason.string' => 'Alasan pengakhiran penempatan harus berupa teks.',
            'end_reason.min' => 'Alasan pengakhiran minimal 5 karakter.',
            'end_reason.max' => 'Alasan pengakhiran maksimal 255 karakter.',
            'preview_token.required' => 'Token preview pengakhiran wajib disertakan.',
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
            'end_reason' => trim((string) ($data['end_reason'] ?? '')),
            'preview_token' => (string) ($data['preview_token'] ?? ''),
        ];

        return $key ? data_get($sanitized, $key, $default) : $sanitized;
    }
}
