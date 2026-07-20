<?php

namespace Modules\Corsec\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Corsec\Models\MeetingType;

class MeetingTypeRequest extends FormRequest
{
    public function rules(): array
    {
        $routeMeetingType = $this->route('meetingType') ?? $this->route('meeting_type');
        $id = $routeMeetingType instanceof MeetingType ? $routeMeetingType->id : (is_numeric($routeMeetingType) ? (int) $routeMeetingType : null);

        $uniqueCode = Rule::unique('corsec_meeting_types', 'code');
        if (($this->isMethod('put') || $this->isMethod('patch')) && $id) {
            $uniqueCode = $uniqueCode->ignore($id);
        }

        return [
            'code' => ['required', 'string', 'max:50', $uniqueCode],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        $user = auth()->guard('web')->user();

        if (!$user) {
            return false;
        }

        if ($this->method() == 'PUT') {
            return $user->can('meeting-type.update');
        } elseif ($this->method() == 'POST') {
            return $user->can('meeting-type.create');
        }

        return true;
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode meeting type wajib diisi.',
            'code.string' => 'Kode meeting type harus berupa teks.',
            'code.max' => 'Kode meeting type maksimal 50 karakter.',
            'code.unique' => 'Kode meeting type sudah ada.',
            'name.required' => 'Nama meeting type wajib diisi.',
            'name.string' => 'Nama meeting type harus berupa teks.',
            'name.max' => 'Nama meeting type maksimal 150 karakter.',
            'status.boolean' => 'Status harus bernilai aktif atau non-aktif.',
        ];
    }
}
