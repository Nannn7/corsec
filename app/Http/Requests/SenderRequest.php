<?php

namespace Modules\Corsec\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Corsec\Models\Sender;

class SenderRequest extends FormRequest
{
    public function rules(): array
    {
        $routeSender = $this->route('sender');
        $id = $routeSender instanceof Sender ? $routeSender->id : (is_numeric($routeSender) ? (int) $routeSender : null);

        $uniqueCode = Rule::unique('corsec_senders', 'code');
        if (($this->isMethod('put') || $this->isMethod('patch')) && $id) {
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
            return $user->can('sender.update');
        } elseif ($this->method() == 'POST') {
            return $user->can('sender.create');
        }

        return true;
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode sender wajib diisi.',
            'code.string'   => 'Kode sender harus berupa teks.',
            'code.max'      => 'Kode sender maksimal 50 karakter.',
            'code.unique'   => 'Kode sender sudah ada.',
            'name.required' => 'Nama sender wajib diisi.',
            'name.string'   => 'Nama sender harus berupa teks.',
            'name.max'      => 'Nama sender maksimal 150 karakter.',
            'status.boolean' => 'Status harus bernilai aktif atau non-aktif.',
        ];
    }
}
