<?php

namespace App\Http\Requests\Room;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Evaluated BEFORE validation so unauthorized roles always receive 403.
     */
    public function authorize(): bool
    {
        $room = $this->route('room');
        return Gate::allows('update', $room);
    }

    /**
     * Get the validation rules that apply to the request.
     * Only allow valid business attributes (no archived_at or occupancy fields).
     */
    public function rules(): array
    {
        $room = $this->route('room');
        $roomId = $room instanceof Room ? $room->id : $room;

        return [
            'number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('rooms', 'number')->ignore($roomId),
            ],
            'type' => ['required', 'string', 'max:50'],
            'monthly_rate' => ['required', 'integer', 'min:1', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Custom Indonesian error messages.
     */
    public function messages(): array
    {
        return [
            'number.required' => 'Nomor kamar wajib diisi.',
            'number.string' => 'Nomor kamar harus berupa teks.',
            'number.max' => 'Nomor kamar maksimal 20 karakter.',
            'number.unique' => 'Nomor kamar sudah digunakan.',
            'type.required' => 'Tipe kamar wajib diisi.',
            'type.string' => 'Tipe kamar harus berupa teks.',
            'type.max' => 'Tipe kamar maksimal 50 karakter.',
            'monthly_rate.required' => 'Tarif bulanan wajib diisi.',
            'monthly_rate.integer' => 'Tarif bulanan harus berupa bilangan bulat.',
            'monthly_rate.min' => 'Tarif bulanan minimal Rp1.',
            'monthly_rate.max' => 'Tarif bulanan maksimal Rp999.999.999.',
            'notes.string' => 'Catatan harus berupa teks.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
