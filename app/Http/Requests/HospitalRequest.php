<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HospitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_emergency' => $this->boolean('is_emergency'),
        ]);
    }

    public function rules(): array
    {
        $hospitalId = $this->route('hospital')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('hospitals', 'code')->ignore($hospitalId),
            ],
            'class' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'ownership' => ['required', Rule::in(['Pemerintah', 'BUMN', 'Swasta'])],
            'phone' => ['required', 'string', 'max:30'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_emergency' => ['required', 'boolean'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['integer', 'exists:facilities,id'],
            'services' => ['nullable', 'array'],
            'services.*' => ['integer', 'exists:services,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama rumah sakit',
            'district_id' => 'kecamatan',
            'code' => 'kode rumah sakit',
            'class' => 'kelas rumah sakit',
            'ownership' => 'kepemilikan',
            'phone' => 'nomor telepon',
            'emergency_phone' => 'nomor telepon IGD',
            'address' => 'alamat',
            'city' => 'kota/kabupaten',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'description' => 'deskripsi',
            'facilities' => 'fasilitas',
            'services' => 'layanan medis',
        ];
    }
}
