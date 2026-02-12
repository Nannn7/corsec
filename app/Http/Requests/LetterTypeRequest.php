<?php

namespace Modules\Corsec\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Corsec\Models\LetterType;

class LetterTypeRequest extends FormRequest
{
    public function rules(): array
    {
        $routeLetterType = $this->route('letterType') ?? $this->route('letter_type');
        $id = $routeLetterType instanceof LetterType ? $routeLetterType->id : (is_numeric($routeLetterType) ? (int) $routeLetterType : null);
        $routeName = (string) ($this->route()?->getName() ?? '');
        $scope = (string) ($this->route('scope') ?? $this->input('scope', ''));
        if ($scope === '' && $routeLetterType instanceof LetterType) {
            $scope = (string) ($routeLetterType->scope ?: LetterType::SCOPE_IN);
        }
        if ($scope === '') {
            $scope = str_starts_with($routeName, 'letter-type.out.')
                ? LetterType::SCOPE_OUT
                : LetterType::SCOPE_IN;
        }
        if (!in_array($scope, [LetterType::SCOPE_IN, LetterType::SCOPE_OUT], true)) {
            $scope = LetterType::SCOPE_IN;
        }

        $uniqueCode = Rule::unique('corsec_letter_types', 'code')
            ->where(function ($query) use ($scope) {
                if ($scope === LetterType::SCOPE_IN) {
                    $query->where(function ($inner) {
                        $inner->where('scope', LetterType::SCOPE_IN)->orWhereNull('scope');
                    });

                    return;
                }

                $query->where('scope', $scope);
            });
        if (($this->isMethod('put') || $this->isMethod('patch')) && $id) {
            $uniqueCode = $uniqueCode->ignore($id);
        }

        return [
            'code'        => ['required', 'string', 'max:50', $uniqueCode],
            'name'        => ['required', 'string', 'max:150'],
            'scope'       => ['nullable', Rule::in([LetterType::SCOPE_IN, LetterType::SCOPE_OUT])],
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
