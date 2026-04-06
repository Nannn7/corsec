@extends('layouts.main')

@section('breadcrumbs')
    @if (isset($libraryItem))
        {{ Breadcrumbs::render('library.edit', $libraryItem) }}
    @else
        {{ Breadcrumbs::render('library.create') }}
    @endif
@endsection

@section('content')
    @php
        $categoryOptions = $categoryOptions ?? [];
        $selectedCategory = $selectedCategory ?? old('category_code');
        $returnTo = $returnTo ?? 'library.index';
    @endphp

    <div class="grid gap-5 mx-auto w-full lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-filled ki-book-open text-primary"></i>
                    {{ isset($libraryItem) ? 'Edit Dokumen Library' : 'Upload Dokumen Library' }}
                </h3>
                <a href="{{ $returnTo === 'library.guideline.index' ? route('library.guideline.index') : route('library.index', array_filter(['category' => $selectedCategory])) }}"
                    class="btn btn-sm btn-info">
                    <i class="ki-filled ki-exit-left"></i> Back
                </a>
            </div>

            <div class="card-body">
                <form method="POST" enctype="multipart/form-data"
                    action="{{ isset($libraryItem) ? route('library.update', $libraryItem) : route('library.store') }}">
                    @csrf
                    @if (isset($libraryItem))
                        @method('PUT')
                    @endif

                    <input type="hidden" name="return_to" value="{{ $returnTo }}">

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="flex flex-col">
                            <label class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="select @error('category_code') border-danger bg-danger-light @enderror"
                                name="category_code" required>
                                <option value="">- Pilih kategori -</option>
                                @foreach ($categoryOptions as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ (string) $selectedCategory === (string) $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_code')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">
                                File Dokumen @if (!isset($libraryItem))
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            <input class="file-input @error('file') border-danger bg-danger-light @enderror" type="file"
                                name="file" accept=".pdf,.doc,.docx" {{ isset($libraryItem) ? '' : 'required' }}>
                            <div class="mt-1 text-xs text-gray-500">
                                Format file yang didukung: PDF, DOC, DOCX. Maksimal 10 MB.
                            </div>
                            @error('file')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        @if (isset($libraryItem))
                            <div class="flex flex-col md:col-span-2">
                                <label class="form-label">File Saat Ini</label>
                                <div class="rounded-xl border border-gray-200 px-4 py-3">
                                    <div class="font-medium text-gray-800">
                                        {{ $libraryItem->original_name ?: $libraryItem->title }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        {{ $libraryItem->categoryLabel() }}
                                    </div>
                                    <div class="mt-3">
                                        <a href="{{ route('library.download', $libraryItem) }}"
                                            class="btn btn-sm btn-light-primary">
                                            Download File
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end mt-7 gap-2">
                        <a href="{{ $returnTo === 'library.guideline.index' ? route('library.guideline.index') : route('library.index', array_filter(['category' => $selectedCategory])) }}"
                            class="btn btn-light">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ki-filled ki-check"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
