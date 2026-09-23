<?php

namespace App\Http\Requests\Placement;

use App\Models\Placement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PreviewEndPlacementRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            '_delay_ms' => [
                'nullable',
                'integer',
                'min:0',
                'max:10000',
            ],
        ];
    }
}
