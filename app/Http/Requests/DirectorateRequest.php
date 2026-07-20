<?php

namespace Modules\Corsec\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Corsec\Models\Directorate;

class DirectorateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $routeDirectorate = $this->route('directorate');
        $id = $routeDirectorate instanceof Directorate ? $routeDirectorate->id : (is_numeric($routeDirectorate) ? (int) $routeDirectorate : null);

        $uniqueCode = Rule::unique('corsec_directorates', 'code');
        if (($this->isMethod('put') || $this->isMethod('patch')) && $id) {
            $uniqueCode = $uniqueCode->ignore($id);
        }

        return [
            'code'        => ['required', 'string', 'max:50', $uniqueCode],
            'name'        => ['required', 'string', 'max:150'],
            'tabulation_label' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', 'boolean'],
            'is_meeting_operational' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = auth()->guard('web')->user();

        if (!$user) {
            return false;
        }

        if ($this->method() == 'PUT') {
            return $user->can('directorate.update');
        } elseif ($this->method() == 'POST') {
            return $user->can('directorate.create');
        }

        return true;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode direktorat wajib diisi.',
            'code.string'   => 'Kode direktorat harus berupa teks.',
            'code.max'      => 'Kode direktorat maksimal 50 karakter.',
            'code.unique'   => 'Kode direktorat sudah ada.',
            'name.required' => 'Nama direktorat wajib diisi.',
            'name.string'   => 'Nama direktorat harus berupa teks.',
            'name.max'      => 'Nama direktorat maksimal 150 karakter.',
            'tabulation_label.string' => 'Label tabulasi harus berupa teks.',
            'tabulation_label.max' => 'Label tabulasi maksimal 150 karakter.',
            'status.boolean' => 'Status harus bernilai aktif atau non-aktif.',
            'is_meeting_operational.boolean' => 'Tipe unit meeting harus bernilai valid.',
        ];
    }
}
