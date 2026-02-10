@extends('layouts.main')

@section('breadcrumbs')
    @if (isset($workplan))
        {{ Breadcrumbs::render('workplan.edit', $workplan) }}
    @else
        {{ Breadcrumbs::render('workplan.create') }}
    @endif
@endsection

@section('content')
    @php
        $workplan = $workplan ?? null;
        $isEdit = isset($workplan);
        $isEditableStatus = !$isEdit || in_array((string) $workplan->status, ['draft', 'returned'], true);
        $user = auth()->user();
        $isAdmin = $user?->hasRole('administrator');
        $selectedDirectorate = old('directorate_id', $workplan?->directorate_id ?? $user?->directorate_id);
        $currentYear = (int) now()->format('Y');
        $rows = old('items');
        if (!is_array($rows)) {
            if ($isEdit && $workplan?->relationLoaded('items')) {
                $rows = $workplan->items
                    ->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'title' => $item->title,
                            'target_date' => optional($item->target_date)->format('Y-m-d'),
                            'description' => $item->description,
                            'note' => '',
                            'existing_file' => $item->attachables->first()?->attachment,
                        ];
                    })
                    ->toArray();
            } else {
                $rows = [];
            }
        }
        if (count($rows) === 0) {
            $rows[] = [
                'title' => '',
                'target_date' => '',
                'description' => '',
                'note' => '',
                'existing_file' => null,
            ];
        }
    @endphp

    <div class="grid gap-5 lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    {{ $isEdit ? 'Edit Program Kerja' : 'Input Program Kerja Direktorat' }}
                </h3>
                <a href="{{ route('workplan.index') }}" class="btn btn-sm btn-light">
                    <i class="ki-filled ki-arrow-left"></i> Kembali
                </a>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ $isEdit ? route('workplan.update', $workplan) : route('workplan.store') }}"
                    enctype="multipart/form-data" id="workplan-form">
                    @csrf
                    @if ($isEdit)
                        @method('PUT')
                    @endif

                    <input type="hidden" name="submit_for_approval" id="submit_for_approval"
                        value="{{ old('submit_for_approval', 0) }}">

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="flex flex-col">
                            <label class="form-label">Tanggal Input</label>
                            <input class="input" type="text" readonly
                                value="{{ $workplan?->created_at ? $workplan->created_at->format('Y-m-d H:i') : now()->format('Y-m-d H:i') }}">
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Direktorat <span class="text-danger">*</span></label>
                            <select class="select @error('directorate_id') border-danger bg-danger-light @enderror"
                                name="directorate_id" {{ !$isAdmin ? 'disabled' : '' }} required>
                                <option value="">- Pilih Direktorat -</option>
                                @foreach ($directorates as $directorate)
                                    <option value="{{ $directorate->id }}"
                                        {{ (string) $selectedDirectorate === (string) $directorate->id ? 'selected' : '' }}>
                                        {{ $directorate->name }} ({{ $directorate->code }})
                                    </option>
                                @endforeach
                            </select>
                            @if (!$isAdmin)
                                <input type="hidden" name="directorate_id" value="{{ $selectedDirectorate }}">
                            @endif
                            @error('directorate_id')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Tahun <span class="text-danger">*</span></label>
                            <input class="input @error('year') border-danger bg-danger-light @enderror" type="number"
                                min="2000" max="2100" name="year"
                                value="{{ old('year', $workplan?->year ?? $currentYear) }}" required>
                            @error('year')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Judul Program<span class="text-danger">*</span></label>
                            <input class="input @error('title') border-danger bg-danger-light @enderror" type="text"
                                name="title" maxlength="255" value="{{ old('title', $workplan?->title) }}"
                                placeholder="Contoh: Program Kerja Direktorat 2026" required>
                            @error('title')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-5">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="textarea w-full @error('description') border-danger bg-danger-light @enderror" name="description"
                            rows="3" placeholder="Catatan untuk deskripsi (opsional)">{{ old('description', $workplan?->description) }}</textarea>
                        @error('description')
                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                        @enderror
                    </div>

                    <div class="border-t border-gray-200 my-7"></div>

                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h4 class="font-semibold text-gray-800">Daftar Program Kerja (Item)</h4>
                        <button type="button" class="btn btn-sm btn-light-primary" id="add-item-row">
                            <i class="ki-filled ki-plus"></i> Tambah Item
                        </button>
                    </div>

                    <div id="item-rows" class="grid gap-4">
                        @foreach ($rows as $i => $row)
                            @php
                                $existingAttachment = $row['existing_file'] ?? null;
                                if (is_numeric($row['id'] ?? null) && $workplan) {
                                    $existingAttachment = $workplan->items
                                        ->firstWhere('id', (int) $row['id'])
                                        ?->attachables?->first()?->attachment;
                                }
                            @endphp
                            <div class="p-4 border rounded-xl border-gray-200 item-row">
                                @if (!empty($row['id']))
                                    <input type="hidden" name="items[{{ $i }}][id]"
                                        value="{{ $row['id'] }}">
                                @endif
                                <div class="flex justify-between items-center mb-3">
                                    <div class="font-medium text-gray-800">Item #{{ $i + 1 }}</div>
                                    <button type="button" class="btn btn-xs btn-danger remove-item-row">
                                        Hapus
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="flex flex-col">
                                        <label class="form-label">Program Kerja <span class="text-danger">*</span></label>
                                        <input
                                            class="input @error('items.' . $i . '.title') border-danger bg-danger-light @enderror"
                                            type="text" name="items[{{ $i }}][title]"
                                            value="{{ old('items.' . $i . '.title', $row['title'] ?? '') }}" required>
                                        @error('items.' . $i . '.title')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                    </div>

                                    <div class="flex flex-col">
                                        <label class="form-label">Target <span class="text-danger">*</span></label>
                                        <input
                                            class="input @error('items.' . $i . '.target_date') border-danger bg-danger-light @enderror"
                                            type="date" name="items[{{ $i }}][target_date]"
                                            value="{{ old('items.' . $i . '.target_date', $row['target_date'] ?? '') }}"
                                            required>
                                        @error('items.' . $i . '.target_date')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                    </div>

                                    <div class="flex flex-col">
                                        <label class="form-label">Deskripsi Item</label>
                                        <textarea class="textarea w-full @error('items.' . $i . '.description') border-danger bg-danger-light @enderror"
                                            name="items[{{ $i }}][description]" rows="3"
                                            placeholder="Catatan untuk deskripsi item (opsional)">{{ old('items.' . $i . '.description', $row['description'] ?? '') }}</textarea>
                                        @error('items.' . $i . '.description')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                    </div>

                                    <div class="flex flex-col">
                                        <label class="form-label">Catatan (Opsional)</label>
                                        <textarea class="textarea w-full @error('items.' . $i . '.note') border-danger bg-danger-light @enderror"
                                            name="items[{{ $i }}][note]" rows="3" placeholder="Catatan untuk item (opsional)">{{ old('items.' . $i . '.note', $row['note'] ?? '') }}</textarea>
                                        @error('items.' . $i . '.note')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                    </div>

                                </div>
                                <div class="mt-5 w-full">
                                    <label class="form-label">
                                        Upload
                                        @if (!$isEdit)
                                            <span class="text-danger">*</span>
                                        @endif
                                    </label>
                                    <input
                                        class="file-input w-full @error('items.' . $i . '.file') border-danger bg-danger-light @enderror"
                                        style="width: 100%;"
                                        type="file" name="items[{{ $i }}][file]"
                                        accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx"
                                        {{ !$isEdit ? 'required' : '' }}>
                                    @if ($existingAttachment)
                                        <a class="text-primary hover:underline text-xs mt-1"
                                            href="{{ Storage::disk($existingAttachment->disk ?? 'public')->url($existingAttachment->path) }}"
                                            target="_blank" rel="noopener">
                                            File existing:
                                            {{ $existingAttachment->original_name ?? $existingAttachment->file_name }}
                                        </a>
                                    @endif
                                    @error('items.' . $i . '.file')
                                        <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                    @enderror
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-gray-200 my-8"></div>

                    <div class="grid grid-cols-1 gap-4 mt-1">
                        <div class="flex flex-col">
                            <label class="form-label">Catatan Submit ke Otorisator</label>
                            <textarea class="textarea w-full @error('submit_note') border-danger bg-danger-light @enderror" name="submit_note"
                                rows="3" placeholder="Catatan untuk approval (opsional)">{{ old('submit_note') }}</textarea>
                            @error('submit_note')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end mt-8 gap-2">
                        <a href="{{ route('workplan.index') }}" class="btn btn-light">Cancel</a>

                        @if ($isEditableStatus)
                            @if ($isEdit)
                                <button type="button" class="btn btn-light" id="update-draft-btn">Update Draft</button>
                                <button type="button" class="btn btn-primary" id="update-submit-btn">
                                    Update + Submit Approval
                                </button>
                            @else
                                <button type="button" class="btn btn-light" id="save-draft-btn">Save Draft</button>
                                <button type="button" class="btn btn-primary" id="submit-approval-btn">
                                    Request Approval
                                </button>
                            @endif
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('workplan-form');
            const hiddenSubmit = document.getElementById('submit_for_approval');
            const rowsContainer = document.getElementById('item-rows');
            const addButton = document.getElementById('add-item-row');

            const submitButtons = [{
                    id: 'save-draft-btn',
                    approval: '0'
                },
                {
                    id: 'submit-approval-btn',
                    approval: '1'
                },
                {
                    id: 'update-draft-btn',
                    approval: '0'
                },
                {
                    id: 'update-submit-btn',
                    approval: '1'
                }
            ];

            submitButtons.forEach((cfg) => {
                const button = document.getElementById(cfg.id);
                if (!button) return;
                button.addEventListener('click', function() {
                    if (hiddenSubmit) {
                        hiddenSubmit.value = cfg.approval;
                    }
                    if (form.requestSubmit) {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });
            });

            function rebuildIndexes() {
                const rows = rowsContainer.querySelectorAll('.item-row');
                rows.forEach((row, index) => {
                    row.dataset.index = index;
                    const titleLabel = row.querySelector('.font-medium');
                    if (titleLabel) {
                        titleLabel.textContent = `Item #${index + 1}`;
                    }

                    row.querySelectorAll('input, textarea, select').forEach((input) => {
                        if (!input.name) return;
                        input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
                    });
                });
            }

            function bindRemoveAction(row) {
                const removeBtn = row.querySelector('.remove-item-row');
                if (!removeBtn) return;
                removeBtn.addEventListener('click', function() {
                    const allRows = rowsContainer.querySelectorAll('.item-row');
                    if (allRows.length <= 1) {
                        return;
                    }
                    row.remove();
                    rebuildIndexes();
                });
            }

            rowsContainer.querySelectorAll('.item-row').forEach((row) => bindRemoveAction(row));

            addButton?.addEventListener('click', function() {
                const index = rowsContainer.querySelectorAll('.item-row').length;
                const wrapper = document.createElement('div');
                wrapper.className = 'p-4 border rounded-xl border-gray-200 item-row';
                wrapper.dataset.index = index;
                wrapper.innerHTML = `
                    <div class="flex justify-between items-center mb-3">
                        <div class="font-medium text-gray-800">Item #${index + 1}</div>
                        <button type="button" class="btn btn-xs btn-danger remove-item-row">Hapus</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="flex flex-col">
                            <label class="form-label">Program Kerja <span class="text-danger">*</span></label>
                            <input class="input" type="text" name="items[${index}][title]" required>
                        </div>
                        <div class="flex flex-col">
                            <label class="form-label">Target <span class="text-danger">*</span></label>
                            <input class="input" type="date" name="items[${index}][target_date]" required>
                        </div>
                        <div class="flex flex-col">
                            <label class="form-label">Deskripsi Item</label>
                            <textarea class="textarea w-full" name="items[${index}][description]" rows="3" placeholder="Catatan untuk deskripsi item (opsional)"></textarea>
                        </div>
                        <div class="flex flex-col">
                            <label class="form-label">Catatan (Opsional)</label>
                            <textarea class="textarea w-full" name="items[${index}][note]" rows="3" placeholder="Catatan untuk item (opsional)"></textarea>
                        </div>
                    </div>
                    <div class="mt-5 w-full">
                        <label class="form-label">Upload <span class="text-danger">*</span></label>
                        <input class="file-input w-full" style="width: 100%;" type="file" name="items[${index}][file]" accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx" required>
                    </div>
                `;

                rowsContainer.appendChild(wrapper);
                bindRemoveAction(wrapper);
                rebuildIndexes();
            });
        });
    </script>
@endpush
