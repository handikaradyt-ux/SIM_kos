<?php

namespace App\Http\Requests\Facility;

use App\Models\Facility;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreFacilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Evaluated BEFORE validation so unauthorized roles always receive 403.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Facility::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rawLocationType = $this->input('location_type');
        $locationType = is_string($rawLocationType) ? $rawLocationType : null;

        return [
            'code' => [
                'bail',
                'required',
                'string',
                'min:1',
                'max:30',
                function ($attribute, $value, $fail) {
                    if (! is_string($value)) {
                        return;
                    }
                    if (Facility::whereRaw('LOWER(code) = ?', [strtolower(trim($value))])->exists()) {
                        $fail('Kode fasilitas sudah digunakan (termasuk fasilitas diarsipkan).');
                    }
                },
            ],
            'name' => [
                'bail',
                'required',
                'string',
                'min:2',
                'max:100',
            ],
            'condition' => [
                'bail',
                'required',
                'string',
                Rule::in(['good', 'broken', 'repairing']),
            ],
            'location_type' => [
                'bail',
                'required',
                'string',
                Rule::in(['room', 'shared']),
            ],
            'room_id' => array_values(array_filter([
                'bail',
                Rule::requiredIf($locationType === 'room'),
                'nullable',
                $locationType === 'shared' ? 'prohibited' : 'integer',
                $locationType === 'room' ? 'exists:rooms,id' : null,
                function ($attribute, $value, $fail) use ($locationType) {
                    if ($locationType === 'room' && $value !== null) {
                        if (! is_numeric($value)) {
                            return;
                        }
                        $room = Room::find((int) $value);
                        if ($room && $room->isArchived()) {
                            $fail('Kamar yang dipilih sedang diarsipkan dan tidak dapat digunakan untuk fasilitas baru.');
                        }
                    }
                },
            ])),
            'area_name' => array_values(array_filter([
                'bail',
                Rule::requiredIf($locationType === 'shared'),
                'nullable',
                $locationType === 'room' ? 'prohibited' : 'string',
                'min:2',
                'max:100',
            ])),
            'notes' => [
                'bail',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Custom Indonesian validation error messages.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode fasilitas wajib diisi.',
            'code.string' => 'Kode fasilitas harus berupa teks.',
            'code.min' => 'Kode fasilitas minimal 1 karakter.',
            'code.max' => 'Kode fasilitas maksimal 30 karakter.',
            'name.required' => 'Nama fasilitas wajib diisi.',
            'name.string' => 'Nama fasilitas harus berupa teks.',
            'name.min' => 'Nama fasilitas minimal 2 karakter.',
            'name.max' => 'Nama fasilitas maksimal 100 karakter.',
            'condition.required' => 'Kondisi fasilitas wajib dipilih.',
            'condition.string' => 'Kondisi fasilitas harus berupa teks.',
            'condition.in' => 'Kondisi fasilitas harus berupa Baik, Rusak, atau Dalam Perbaikan.',
            'location_type.required' => 'Tipe lokasi fasilitas wajib dipilih.',
            'location_type.string' => 'Tipe lokasi harus berupa teks.',
            'location_type.in' => 'Tipe lokasi harus berupa kamar atau area bersama.',
            'room_id.required' => 'Kamar penempatan wajib dipilih.',
            'room_id.required_if' => 'Kamar wajib dipilih jika tipe lokasi adalah kamar.',
            'room_id.prohibited' => 'Kamar tidak boleh diisi jika lokasi adalah area bersama.',
            'room_id.integer' => 'Kamar harus berupa ID yang valid.',
            'room_id.exists' => 'Kamar yang dipilih tidak ditemukan.',
            'area_name.required' => 'Nama area bersama wajib diisi.',
            'area_name.required_if' => 'Nama area bersama wajib diisi jika tipe lokasi adalah area bersama.',
            'area_name.prohibited' => 'Nama area bersama tidak boleh diisi jika lokasi adalah kamar.',
            'area_name.string' => 'Nama area bersama harus berupa teks.',
            'area_name.min' => 'Nama area bersama minimal 2 karakter.',
            'area_name.max' => 'Nama area bersama maksimal 100 karakter.',
            'notes.string' => 'Catatan harus berupa teks.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }

    /**
     * Sanitize validated data and strip irrelevant location fields.
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        if (($data['location_type'] ?? '') === 'room') {
            $data['area_name'] = null;
            $data['room_id'] = isset($data['room_id']) && is_numeric($data['room_id']) ? (int) $data['room_id'] : null;
        } elseif (($data['location_type'] ?? '') === 'shared') {
            $data['room_id'] = null;
            $data['area_name'] = isset($data['area_name']) && is_string($data['area_name']) ? trim($data['area_name']) : null;
        }

        unset($data['archived_at']);

        return $key ? data_get($data, $key, $default) : $data;
    }
}
