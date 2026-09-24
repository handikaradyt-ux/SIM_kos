<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SyncPreviewInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Evaluates active Admin authority via policy.
     */
    public function authorize(): bool
    {
        return Gate::allows('preview', Invoice::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'placement_id' => [
                'nullable',
                'integer',
                'exists:placements,id',
            ],
            '_delay_ms' => [
                'nullable',
                'integer',
                'min:0',
                'max:10000',
            ],
        ];
    }
}
