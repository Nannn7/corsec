<?php

namespace Modules\Corsec\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankRequest extends FormRequest
{
    public function rules(): array
    {
        $id = (int) $this->route('bank');

        $uniqueCode = Rule::unique('corsec_banks', 'code');
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $uniqueCode = $uniqueCode->ignore($id);
        }

        return [
            'code'        => ['required', 'string', 'max:50', $uniqueCode],
            'swift_code'  => ['nullable', 'string', 'max:50'],
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
            return $user->can('bank.update');
        } elseif ($this->method() == 'POST') {
            return $user->can('bank.create');
        }

        return true;
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode bank wajib diisi.',
            'code.string'   => 'Kode bank harus berupa teks.',
            'code.max'      => 'Kode bank maksimal 50 karakter.',
            'code.unique'   => 'Kode bank sudah ada.',
            'swift_code.string' => 'Swift code harus berupa teks.',
            'swift_code.max' => 'Swift code maksimal 50 karakter.',
            'name.required' => 'Nama bank wajib diisi.',
            'name.string'   => 'Nama bank harus berupa teks.',
            'name.max'      => 'Nama bank maksimal 150 karakter.',
            'status.boolean' => 'Status harus bernilai aktif atau non-aktif.',
        ];
    }
}
