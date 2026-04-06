<?php

namespace Modules\Corsec\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Corsec\Models\LibraryItem;

class LibraryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->guard('web')->user();
    }

    public function rules(): array
    {
        $fileRules = ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'];
        if ($this->isMethod('post')) {
            $fileRules[0] = 'required';
        }

        return [
            'category_code' => ['required', Rule::in(array_keys(LibraryItem::categoryOptions()))],
            'file' => $fileRules,
            'return_to' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_code.required' => 'Kategori daftar pustaka wajib dipilih.',
            'category_code.in' => 'Kategori daftar pustaka tidak valid.',
            'file.required' => 'File daftar pustaka wajib diunggah.',
            'file.file' => 'Dokumen yang diunggah tidak valid.',
            'file.mimes' => 'File daftar pustaka hanya boleh berformat PDF, DOC, atau DOCX.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ];
    }
}
