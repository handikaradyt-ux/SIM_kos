<?php

namespace App\Http\Requests\Facility;

use App\Models\Facility;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateFacilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Evaluated BEFORE validation so unauthorized roles always receive 403.
     */
    public function authorize(): bool
    {
        $facility = $this->route('facility');

        if (! $facility instanceof Facility) {
            $facility = Facility::find($facility);
        }

        if (! $facility) {
            return false;
        }

        return Gate::allows('update', $facility);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $facility = $this->route('facility');
        if (! $facility instanceof Facility) {
            $facility = Facility::findOrFail($facility);
        }

        $hasComplaints = $facility->complaints()->exists();

        $rules = [
            'code' => [
                'bail',
                'required',
                'string',
                'min:1',
                'max:30',
                function ($attribute, $value, $fail) use ($facility) {
                    if (! is_string($value)) {
                        return;
                    }
                    $exists = Facility::whereRaw('LOWER(code) = ?', [strtolower(trim($value))])
                        ->where('id', '!=', $facility->id)
                        ->exists();

                    if ($exists) {
                        $fail('Kode fasilitas sudah digunakan oleh fasilitas lain (termasuk fasilitas diarsipkan).');
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
            'notes' => [
                'bail',
                'nullable',
                'string',
                'max:1000',
            ],
        ];

        if ($hasComplaints) {
            // When facility has complaints, location is locked.
            // Disabled inputs might not be submitted by browser form, so missing fields are allowed and will retain current DB location.
            // However, any location field that IS present in payload must be strictly type-checked and validated.
            if ($this->has('location_type')) {
                $rules['location_type'] = [
                    'bail',
                    'required',
                    'string',
                    Rule::in(['room', 'shared']),
                    function ($attribute, $value, $fail) use ($facility) {
                        if (! is_string($value)) {
                            return;
                        }
                        if ($value !== (string) $facility->location_type) {
                            $fail('Lokasi fasilitas yang memiliki riwayat keluhan tidak dapat diubah demi menjaga integritas data riwayat. Silakan arsipkan fasilitas ini dan buat fasilitas baru di lokasi yang dituju.');
                        }
                    },
                ];
            }

            if ($this->has('room_id')) {
                if ($facility->location_type === 'room') {
                    $rules['room_id'] = [
                        'bail',
                        'required',
                        'integer',
                        function ($attribute, $value, $fail) use ($facility) {
                            if (! is_numeric($value)) {
                                return;
                            }
                            if ((int) $value !== (int) $facility->room_id) {
                                $fail('Lokasi kamar fasilitas yang memiliki riwayat keluhan tidak dapat dipindahkan.');
                            }
                        },
                    ];
                } else {
                    $rules['room_id'] = [
                        'bail',
                        'prohibited',
                    ];
                }
            }

            if ($this->has('area_name')) {
                if ($facility->location_type === 'shared') {
                    $rules['area_name'] = [
                        'bail',
                        'required',
                        'string',
                        'min:2',
                        'max:100',
                        function ($attribute, $value, $fail) use ($facility) {
                            if (! is_string($value)) {
                                return;
                            }
                            $currentArea = trim((string) $facility->area_name);
                            $newArea = trim($value);
                            if ($newArea !== $currentArea) {
                                $fail('Lokasi area bersama fasilitas yang memiliki riwayat keluhan tidak dapat diubah.');
                            }
                        },
                    ];
                } else {
                    $rules['area_name'] = [
                        'bail',
                        'prohibited',
                    ];
                }
            }
        } else {
            // Location is fully editable when no complaint history exists
            $rawLocationType = $this->input('location_type');
            $locationType = is_string($rawLocationType) ? $rawLocationType : null;

            $rules['location_type'] = [
                'bail',
                'required',
                'string',
                Rule::in(['room', 'shared']),
            ];

            $rules['room_id'] = array_values(array_filter([
                'bail',
                Rule::requiredIf($locationType === 'room'),
                'nullable',
                $locationType === 'shared' ? 'prohibited' : 'integer',
                $locationType === 'room' ? 'exists:rooms,id' : null,
                function ($attribute, $value, $fail) use ($locationType, $facility) {
                    if ($locationType === 'room' && $value !== null) {
                        if (! is_numeric($value)) {
                            return;
                        }
                        $newRoomId = (int) $value;
                        $currentRoomId = $facility->room_id !== null ? (int) $facility->room_id : null;

                        // If retaining the current room, allow it even if room is archived
                        if ($facility->location_type === 'room' && $newRoomId === $currentRoomId) {
                            return;
                        }

                        // If selecting a new room, room must not be archived
                        $room = Room::find($newRoomId);
                        if ($room && $room->isArchived()) {
                            $fail('Kamar tujuan yang dipilih sedang diarsipkan dan tidak dapat digunakan untuk penempatan fasilitas.');
                        }
                    }
                },
            ]));

            $rules['area_name'] = array_values(array_filter([
                'bail',
                Rule::requiredIf($locationType === 'shared'),
                'nullable',
                $locationType === 'room' ? 'prohibited' : 'string',
                'min:2',
                'max:100',
            ]));
        }

        return $rules;
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
     * Sanitize validated data, handle locked fields for complaints, and wipe irrelevant fields.
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        $facility = $this->route('facility');
        if (! $facility instanceof Facility) {
            $facility = Facility::findOrFail($facility);
        }

        $hasComplaints = $facility->complaints()->exists();

        if ($hasComplaints) {
            // Retain canonical location from database strictly
            $data['location_type'] = $facility->location_type;
            $data['room_id'] = $facility->room_id !== null ? (int) $facility->room_id : null;
            $data['area_name'] = $facility->area_name !== null ? (string) $facility->area_name : null;
        } else {
            // If location changed, normalize appropriately
            $locType = $data['location_type'] ?? $facility->location_type;
            if ($locType === 'room') {
                $data['location_type'] = 'room';
                $data['room_id'] = isset($data['room_id']) && is_numeric($data['room_id']) ? (int) $data['room_id'] : null;
                $data['area_name'] = null;
            } elseif ($locType === 'shared') {
                $data['location_type'] = 'shared';
                $data['room_id'] = null;
                $data['area_name'] = isset($data['area_name']) && is_string($data['area_name']) ? trim($data['area_name']) : null;
            }
        }

        unset($data['archived_at']);

        return $key ? data_get($data, $key, $default) : $data;
    }
}
