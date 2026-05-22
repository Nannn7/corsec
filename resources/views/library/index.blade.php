@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render($breadcrumbName ?? 'library.index') }}
@endsection

@section('content')
    @php
        $items = $items ?? null;
        $categoryOptions = $categoryOptions ?? [];
        $categoryCounts = $categoryCounts ?? collect();
        $activeCategory = $activeCategory ?? null;
        $canManageLibrary = (bool) ($canManageLibrary ?? false);
        $currentRouteName = $currentRouteName ?? 'library.index';
        $pageTitle = $pageTitle ?? 'Library';
        $pageDescription = $pageDescription ?? 'Daftar pustaka Corsec.';
        $search = $search ?? '';
        $buildCategoryRoute = function (string $categoryCode) {
            if ($categoryCode === \Modules\Corsec\Models\LibraryItem::CATEGORY_APP_GUIDELINE) {
                return route('library.guideline.index');
            }

            return route('library.index', ['category' => $categoryCode]);
        };
    @endphp

    <div class="grid gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">{{ $pageTitle }}</h2>
                <div class="text-sm text-gray-500">{{ $pageDescription }}</div>
            </div>
            @if ($canManageLibrary)
                <a href="{{ route('library.create', array_filter(['category' => $activeCategory, 'return_to' => $currentRouteName])) }}"
                    class="btn btn-sm btn-primary">
                    <i class="ki-filled ki-plus"></i>
                    Upload Dokumen
                </a>
            @endif
        </div>

        <div class="card">
            <div class="card-body flex flex-wrap gap-2">
                <a href="{{ route('library.index') }}"
                    class="btn btn-sm {{ $activeCategory === null && $currentRouteName === 'library.index' ? 'btn-primary' : 'btn-light' }}">
                    Semua
                    <span class="ml-1 text-xs">({{ (int) collect($categoryCounts)->sum() }})</span>
                </a>
                @foreach ($categoryOptions as $categoryCode => $label)
                    <a href="{{ $buildCategoryRoute($categoryCode) }}"
                        class="btn btn-sm {{ $activeCategory === $categoryCode ? 'btn-primary' : 'btn-light' }}">
                        {{ $label }}
                        <span class="ml-1 text-xs">({{ (int) ($categoryCounts[$categoryCode] ?? 0) }})</span>
                    </a>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route($currentRouteName) }}" class="card">
            <div class="card-body grid gap-4 md:grid-cols-[1fr_auto_auto]">
                @if ($currentRouteName === 'library.index' && $activeCategory)
                    <input type="hidden" name="category" value="{{ $activeCategory }}">
                @endif
                <div class="flex flex-col">
                    <label class="form-label">Cari Dokumen</label>
                    <input class="input" type="text" name="search" value="{{ $search }}"
                        placeholder="Cari nama dokumen">
                </div>
                <div class="flex items-end gap-2">
                    <a href="{{ $activeCategory === \Modules\Corsec\Models\LibraryItem::CATEGORY_APP_GUIDELINE ? route('library.guideline.index') : route('library.index', array_filter(['category' => $activeCategory])) }}"
                        class="btn btn-light">
                        Reset
                    </a>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Dokumen</h3>
            </div>
            <div class="card-body">
                @if ($items && $items->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="min-w-[220px]">Dokumen</th>
                                    <th class="min-w-[220px]">Kategori</th>
                                    <th class="min-w-[140px]">Format</th>
                                    <th class="min-w-[140px]">Ukuran</th>
                                    <th class="min-w-[160px]">Update Terakhir</th>
                                    <th class="min-w-[180px] text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    @php
                                        $extension = strtoupper((string) ($item->file_extension ?? '-'));
                                        $size = $item->file_size
                                            ? number_format(((int) $item->file_size) / 1024, 1) . ' KB'
                                            : '-';
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="font-medium text-gray-800">
                                                {{ $item->original_name ?: $item->title }}</div>
                                            <div class="text-xs text-gray-500">{{ $item->title }}</div>
                                        </td>
                                        <td>
                                            <span class="badge badge-light">{{ $item->categoryLabel() }}</span>
                                        </td>
                                        <td>{{ $extension }}</td>
                                        <td>{{ $size }}</td>
                                        <td>
                                            {{ optional($item->updated_at ?? $item->created_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap justify-center gap-2">
                                                <a href="{{ route('library.download', $item) }}"
                                                    class="btn btn-sm btn-primary">
                                                    Download
                                                </a>
                                                @if ($canManageLibrary)
                                                    <a href="{{ route('library.edit', ['libraryItem' => $item, 'return_to' => $currentRouteName]) }}"
                                                        class="btn btn-sm btn-warning">
                                                        Edit
                                                    </a>
                                                    <form method="POST" action="{{ route('library.destroy', $item) }}"
                                                        onsubmit="return confirm('Hapus dokumen daftar pustaka ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="return_to"
                                                            value="{{ $currentRouteName }}">
                                                        <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $items->links() }}
                    </div>
                @else
                    <div
                        class="rounded-xl border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500">
                        Belum ada dokumen daftar pustaka yang cocok dengan filter saat ini.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
