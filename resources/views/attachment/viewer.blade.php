@extends('layouts.main')

@section('content')
    <div class="grid gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-gray-900">{{ $fileName }}</h1>
                <p class="text-sm text-gray-600">Preview attachment</p>
            </div>
            <a class="btn btn-sm btn-primary" href="{{ $downloadUrl }}">
                <i class="ki-outline ki-file-down"></i>
                Download
            </a>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <iframe
                    src="{{ $inlineUrl }}"
                    class="block w-full border-0"
                    style="height: calc(100vh - 220px); min-height: 520px;"
                    title="{{ $fileName }}"
                ></iframe>
            </div>
        </div>
    </div>
@endsection
