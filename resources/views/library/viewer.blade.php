@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('library.preview', $libraryItem) }}
@endsection

@section('content')
    <div class="grid gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-gray-900">{{ $fileName }}</h1>
                <p class="text-sm text-gray-500">Preview dokumen library</p>
            </div>
            <div class="flex items-center gap-2">
                <a class="btn btn-sm btn-light" href="{{ route('library.index') }}">
                    <i class="ki-outline ki-arrow-left"></i>
                    Kembali
                </a>
                <a class="btn btn-sm btn-primary" href="{{ $downloadUrl }}">
                    <i class="ki-outline ki-file-down"></i>
                    Download
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <iframe
                    src="{{ $inlineUrl }}"
                    class="block w-full border-0 rounded-b-xl"
                    style="height: calc(100vh - 220px); min-height: 520px;"
                    title="{{ $fileName }}"
                ></iframe>
            </div>
        </div>
    </div>
@endsection
