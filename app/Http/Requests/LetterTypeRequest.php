<?php

namespace Modules\Corsec\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LetterTypeRequest extends FormRequest
{
    public function rules(): array
    {
        $id = (int) $this->route('letter_type');

        $uniqueCode = Rule::unique('corsec_letter_types', 'code');
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $uniqueCode = $uniqueCode->ignore($id);
        }

        return [
            'code'        => ['required', 'string', 'max:50', $uniqueCode],
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        $user = auth()->guard('web')->user();

        if (!$user) {
            return false;
        }

        if ($this->method() == 'PUT') {
            return $user->can('letter-type.update');
        } elseif ($this->method() == 'POST') {
            return $user->can('letter-type.create');
        }

        return true;
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode jenis surat wajib diisi.',
            'code.string'   => 'Kode jenis surat harus berupa teks.',
            'code.max'      => 'Kode jenis surat maksimal 50 karakter.',
            'code.unique'   => 'Kode jenis surat sudah ada.',
            'name.required' => 'Nama jenis surat wajib diisi.',
            'name.string'   => 'Nama jenis surat harus berupa teks.',
            'name.max'      => 'Nama jenis surat maksimal 150 karakter.',
            'status.boolean' => 'Status harus bernilai aktif atau non-aktif.',
        ];
    }
}
