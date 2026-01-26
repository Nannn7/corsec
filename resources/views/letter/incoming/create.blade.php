@extends('layouts.main')

@section('breadcrumbs')
    @if (isset($incomingLetter))
        {{ Breadcrumbs::render('letter.incoming.edit', $incomingLetter) }}
    @else
        {{ Breadcrumbs::render('letter.incoming.create') }}
    @endif
@endsection

@section('content')
    @php
        $incomingLetter = $incomingLetter ?? null;
        $isEditableStatus = !$incomingLetter || in_array($incomingLetter->status, ['draft', 'returned'], true);
    @endphp
    <div class="grid gap-5 mx-auto w-full lg:gap-7.5">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-filled ki-document text-primary"></i>
                    {{ isset($incomingLetter) ? 'Edit Surat Masuk' : 'Input Surat Masuk' }}
                </h3>
                <a href="{{ route('letter.incoming.index') }}" class="btn btn-sm btn-info">
                    <i class="ki-filled ki-exit-left"></i> Back
                </a>
            </div>

            <div class="card-body">
                <form id="incoming-letter-form" method="POST"
                    action="{{ isset($incomingLetter) ? route('letter.incoming.update', $incomingLetter) : route('letter.incoming.store') }}"
                    enctype="multipart/form-data">
                    @csrf
                    @if (isset($incomingLetter))
                        @method('PUT')
                    @else
                        <input type="hidden" name="submit_for_approval" id="submit_for_approval" value="1">
                    @endif

                    {{-- INFO SURAT --}}
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="flex flex-col">
                            <label class="form-label">No. Registrasi</label>
                            <input class="input" type="text" name="registration_no" readonly
                                value="{{ old('registration_no', $incomingLetter?->registration_no ?? 'Auto Generated') }}">
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Tanggal Terima <span class="text-danger">*</span></label>
                            <input class="input @error('received_date') border-danger bg-danger-light @enderror"
                                type="date" name="received_date" readonly
                                value="{{ old('received_date', $incomingLetter?->received_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                            @error('received_date')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Nomor Surat <span class="text-danger">*</span></label>
                            <input class="input @error('external_letter_no') border-danger bg-danger-light @enderror"
                                type="text" name="external_letter_no"
                                value="{{ old('external_letter_no', $incomingLetter?->external_letter_no) }}"
                                maxlength="255" placeholder="Contoh: 001/ABC/I/2026" required>
                            @error('external_letter_no')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Tanggal Surat <span class="text-danger">*</span></label>
                            <input class="input @error('letter_date') border-danger bg-danger-light @enderror"
                                type="date" name="letter_date"
                                value="{{ old('letter_date', $incomingLetter?->letter_date?->format('Y-m-d')) }}" required>
                            @error('letter_date')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Perihal <span class="text-danger">*</span></label>
                            <input class="input @error('subject') border-danger bg-danger-light @enderror" type="text"
                                name="subject" value="{{ old('subject', $incomingLetter?->subject) }}" maxlength="255"
                                placeholder="Contoh: Permohonan informasi / Undangan / dll" required>
                            @error('subject')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Ringkasan Isi Surat <span class="text-danger">*</span></label>
                            <textarea class="textarea w-full @error('summary') border-danger bg-danger-light @enderror" name="summary"
                                rows="1" placeholder="Ringkasan isi surat..." required>{{ old('summary', $incomingLetter?->summary) }}</textarea>
                            @error('summary')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Pengirim <span class="text-danger">*</span></label>
                            <select class="select @error('sender_id') border-danger bg-danger-light @enderror"
                                name="sender_id" id="sender_id" required>
                                <option value="">- Pilih Pengirim -</option>
                                @foreach ($senders as $sender)
                                    <option value="{{ $sender->id }}"
                                        {{ (string) old('sender_id', $incomingLetter?->sender_id) === (string) $sender->id ? 'selected' : '' }}>
                                        {{ $sender->name }}
                                    </option>
                                @endforeach
                                <option value="other"
                                    {{ old('sender_id', $incomingLetter?->sender_id ?? ($incomingLetter?->sender_other ? 'other' : '')) === 'other' ? 'selected' : '' }}>
                                    Other
                                </option>
                            </select>
                            @error('sender_id')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col" id="sender-other-wrapper" style="display: none;">
                            <label class="form-label">Pengirim Lainnya <span class="text-danger">*</span></label>
                            <input class="input @error('sender_other') border-danger bg-danger-light @enderror"
                                type="text" name="sender_other" id="sender_other"
                                value="{{ old('sender_other', $incomingLetter?->sender_other) }}"
                                maxlength="150" placeholder="Tulis nama pengirim">
                            @error('sender_other')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Action Surat <span class="text-danger">*</span></label>
                            <select class="select @error('letter_type_id') border-danger bg-danger-light @enderror"
                                name="letter_type_id" required>
                                <option value="">- Pilih Action Surat -</option>
                                @foreach ($letterTypes as $letterType)
                                    <option value="{{ $letterType->id }}"
                                        {{ (string) old('letter_type_id', $incomingLetter?->letter_type_id) === (string) $letterType->id ? 'selected' : '' }}>
                                        {{ $letterType->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('letter_type_id')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Sirkulasi <span class="text-danger">*</span></label>
                            @php
                                $selectedCirculations = old(
                                    'circulation_directorate_ids',
                                    $incomingLetter?->circulationDirectorates?->pluck('id')->toArray() ?? [],
                                );
                            @endphp
                            <div class="relative">
                                <button type="button"
                                    class="select w-full flex items-center justify-between text-left bg-white text-gray-800 @error('circulation_directorate_ids') border-danger bg-danger-light @enderror"
                                    style="text-align: left; justify-content: flex-start;"
                                    id="circulation-dropdown">
                                    <span id="circulation-selected-text"
                                        class="block truncate text-left w-full"
                                        style="text-align: left;">Pilih sirkulasi...</span>
                                </button>
                                <div id="circulation-options"
                                    class="absolute z-20 mt-1 left-0 right-0 max-h-64 overflow-auto bg-white border border-gray-200 rounded shadow-lg hidden"
                                    style="background-color: #ffffff;">
                                    <div class="p-3 space-y-2 bg-white">
                                        @foreach ($directorates as $b)
                                            @php
                                                $isChecked = in_array(
                                                    (string) $b->id,
                                                    array_map('strval', $selectedCirculations),
                                                    true,
                                                );
                                            @endphp
                                            <label class="flex items-center gap-2">
                                                <input type="checkbox" name="circulation_directorate_ids[]"
                                                    value="{{ $b->id }}" {{ $isChecked ? 'checked' : '' }}>
                                                <span>{{ $b->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @error('circulation_directorate_ids')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Prioritas <span class="text-danger">*</span></label>
                            <select class="select @error('priority') border-danger bg-danger-light @enderror"
                                name="priority">
                                <option value="">- Pilih -</option>
                                <option value="low"
                                    {{ old('priority', $incomingLetter?->priority) === 'low' ? 'selected' : '' }}>
                                    Low</option>
                                <option value="normal"
                                    {{ old('priority', $incomingLetter?->priority) === 'normal' ? 'selected' : '' }}>
                                    Normal</option>
                                <option value="high"
                                    {{ old('priority', $incomingLetter?->priority) === 'high' ? 'selected' : '' }}>
                                    High</option>
                                <option value="urgent"
                                    {{ old('priority', $incomingLetter?->priority) === 'urgent' ? 'selected' : '' }}>
                                    Urgent</option>
                            </select>
                            @error('priority')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Target Date (SLA) <span class="text-danger">*</span></label>
                            <input class="input @error('target_date') border-danger bg-danger-light @enderror"
                                type="date" name="target_date"
                                value="{{ old('target_date', $incomingLetter?->target_date?->format('Y-m-d')) }}">
                            @error('target_date')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Leader Tindak Lanjut <span class="text-danger">*</span></label>
                            <select class="select @error('target_directorate_id') border-danger bg-danger-light @enderror"
                                name="target_directorate_id" required>
                                <option value="">- Pilih Leader -</option>
                                @foreach ($directorates as $b)
                                    <option value="{{ $b->id }}"
                                        {{ (string) old('target_directorate_id', $incomingLetter?->target_directorate_id) === (string) $b->id ? 'selected' : '' }}>
                                        {{ $b->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('target_directorate_id')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                    </div>

                    <div class="my-8 border-t border-gray-200"></div>

                    <div class="grid grid-cols-1 gap-5 mt-8">
                        <div class="flex flex-col">
                            <label class="form-label">Deskripsi / Catatan</label>
                            <textarea class="textarea w-full @error('description') border-danger bg-danger-light @enderror" name="description"
                                rows="4" placeholder="Keterangan tambahan...">{{ old('description', $incomingLetter?->description) }}</textarea>
                            @error('description')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                    </div>

                    {{-- UPLOAD --}}
                    <div class="grid grid-cols-1 gap-5 mt-8">
                        <div class="flex flex-col">
                            <label class="form-label">
                                {{ isset($incomingLetter) ? 'Tambah Lampiran (PDF/JPG/PNG)' : 'Upload Surat Masuk (PDF/JPG/PNG)' }}
                                @if (!isset($incomingLetter))
                                    <span class="text-danger">*</span>
                                @endif
                            </label>

                            <input class="file-input @error('files.*') border-danger bg-danger-light @enderror"
                                type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                            @error('files.*')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror

                            <div class="mt-1 text-xs text-gray-500">
                                Bisa multiple file. Max 10MB per file.
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-7 gap-2">
                        <a href="{{ route('letter.incoming.index') }}" class="btn btn-light">
                            Cancel
                        </a>
                        @if (isset($incomingLetter))
                            @can('corsec.update')
                                @if ($isEditableStatus)
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ki-filled ki-check"></i> Update
                                    </button>
                                @endif
                            @endcan
                        @else
                            @can('corsec.create')
                                <button type="button" id="save-draft" class="btn btn-light">
                                    <i class="ki-filled ki-archive"></i> Save Draft
                                </button>
                                <button type="submit" id="submit-approval" class="btn btn-primary">
                                    <i class="ki-filled ki-check"></i> Request Approval
                                </button>
                            @endcan
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
            const form = document.getElementById('incoming-letter-form');
            const approvalInput = document.getElementById('submit_for_approval');
            const saveDraftButton = document.getElementById('save-draft');
            const submitApprovalButton = document.getElementById('submit-approval');
            const circulationDropdown = document.getElementById('circulation-dropdown');
            const circulationOptions = document.getElementById('circulation-options');
            const circulationSelectedText = document.getElementById('circulation-selected-text');
            const senderSelect = document.getElementById('sender_id');
            const senderOtherWrapper = document.getElementById('sender-other-wrapper');
            const senderOtherInput = document.getElementById('sender_other');
            const targetDirectorateSelect = form ? form.querySelector('select[name="target_directorate_id"]') : null;
            const targetDirectorateOptions = targetDirectorateSelect ? Array.from(targetDirectorateSelect.options) : [];

            if (saveDraftButton) {
                saveDraftButton.addEventListener('click', function() {
                    approvalInput.value = '0';
                    if (form.requestSubmit) {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });
            }

            if (submitApprovalButton) {
                submitApprovalButton.addEventListener('click', function() {
                    approvalInput.value = '1';
                });
            }

            function toggleSenderOther() {
                if (!senderSelect || !senderOtherWrapper || !senderOtherInput) return;
                if (senderSelect.value === 'other') {
                    senderOtherWrapper.style.display = 'flex';
                    senderOtherInput.required = true;
                } else {
                    senderOtherWrapper.style.display = 'none';
                    senderOtherInput.required = false;
                    senderOtherInput.value = '';
                }
            }

            if (senderSelect) {
                senderSelect.addEventListener('change', toggleSenderOther);
                toggleSenderOther();
            }

            function updateCirculationLabel() {
                if (!circulationSelectedText || !circulationOptions) return;
                const checkboxes = circulationOptions.querySelectorAll('input[type="checkbox"]:checked');
                const names = Array.from(checkboxes).map((item) => {
                    const label = item.closest('label');
                    return label ? label.textContent.trim() : '';
                }).filter(Boolean);
                circulationSelectedText.textContent = names.length > 0 ? names.join(', ') : 'Pilih sirkulasi...';
            }

            function updateLeaderOptions() {
                if (!targetDirectorateSelect || !circulationOptions || targetDirectorateOptions.length === 0) return;

                const selectedValue = targetDirectorateSelect.value;
                const checked = circulationOptions.querySelectorAll('input[type="checkbox"]:checked');
                const allowedIds = new Set(Array.from(checked).map((item) => item.value));

                targetDirectorateSelect.innerHTML = '';
                const placeholder = targetDirectorateOptions[0];
                if (placeholder) {
                    targetDirectorateSelect.appendChild(placeholder.cloneNode(true));
                }

                let hasSelected = false;
                targetDirectorateOptions.slice(1).forEach((option) => {
                    if (allowedIds.has(option.value)) {
                        const clone = option.cloneNode(true);
                        if (option.value === selectedValue) {
                            clone.selected = true;
                            hasSelected = true;
                        }
                        targetDirectorateSelect.appendChild(clone);
                    }
                });

                targetDirectorateSelect.disabled = allowedIds.size === 0;
                if (!hasSelected) {
                    targetDirectorateSelect.value = '';
                }
            }

            if (circulationDropdown && circulationOptions) {
                circulationDropdown.addEventListener('click', function() {
                    circulationOptions.classList.toggle('hidden');
                });

                document.addEventListener('click', function(event) {
                    if (!circulationDropdown.contains(event.target) && !circulationOptions.contains(event.target)) {
                        circulationOptions.classList.add('hidden');
                    }
                });

                circulationOptions.addEventListener('change', function() {
                    updateCirculationLabel();
                    updateLeaderOptions();
                });
                updateCirculationLabel();
                updateLeaderOptions();
            }

            if (form) {
                form.addEventListener('submit', function(event) {
                    const errors = [];
                    const maxTextLength = 255;
                    const maxFileSize = 10 * 1024 * 1024;
                    const isEdit = {{ isset($incomingLetter) ? 'true' : 'false' }};

                    const externalLetterNo = form.querySelector('input[name="external_letter_no"]');
                    const subject = form.querySelector('input[name="subject"]');
                    const sender = form.querySelector('select[name="sender_id"]');
                    const senderOther = form.querySelector('input[name="sender_other"]');
                    const letterType = form.querySelector('select[name="letter_type_id"]');
                    const receivedDate = form.querySelector('input[name="received_date"]');
                    const targetDate = form.querySelector('input[name="target_date"]');
                    const priority = form.querySelector('select[name="priority"]');
                    const targetDirectorate = form.querySelector('select[name="target_directorate_id"]');
                    const filesInput = form.querySelector('input[name="files[]"]');

                    if (externalLetterNo && externalLetterNo.value.length === 0) {
                        errors.push('Nomor surat wajib diisi.');
                    }

                    if (subject && subject.value.trim().length === 0) {
                        errors.push('Perihal wajib diisi.');
                    }

                    if (sender && sender.value.length === 0) {
                        errors.push('Pengirim wajib diisi.');
                    }
                    if (sender && sender.value === 'other') {
                        if (!senderOther || senderOther.value.trim().length === 0) {
                            errors.push('Pengirim lainnya wajib diisi.');
                        }
                    }

                    if (letterType && letterType.value.length === 0) {
                        errors.push('Jenis surat wajib diisi.');
                    }

                    const letterDate = form.querySelector('input[name="letter_date"]');
                    if (letterDate && letterDate.value && isNaN(Date.parse(letterDate.value))) {
                        errors.push('Tanggal surat wajib diisi.');
                    }

                    const summary = form.querySelector('textarea[name="summary"]');
                    if (summary && summary.value.trim().length === 0) {
                        errors.push('Ringkasan isi surat wajib diisi.');
                    }

                    if (receivedDate && receivedDate.value && isNaN(Date.parse(receivedDate.value))) {
                        errors.push('Tanggal diterima wajib diisi.');
                    }

                    if (targetDate && targetDate.value && isNaN(Date.parse(targetDate.value))) {
                        errors.push('Target tanggal wajib diisi.');
                    }

                    if (priority && priority.value) {
                        const allowed = ['low', 'normal', 'high', 'urgent'];
                        if (!allowed.includes(priority.value) && priority.value.length > 0) {
                            errors.push('Prioritas wajib dipilih.');
                        }
                    }

                    if (targetDirectorate && targetDirectorate.value.length === 0) {
                        errors.push('Leader tindak lanjut wajib dipilih.');
                    }

                    if (targetDirectorate && targetDirectorate.value) {
                        const option = targetDirectorate.querySelector(
                            `option[value="${targetDirectorate.value}"]`
                        );
                        if (!option && targetDirectorate.value.length > 0) {
                            errors.push('Leader tindak lanjut wajib dipilih.');
                        }
                    }

                    const circulationChecks = form.querySelectorAll(
                        'input[name="circulation_directorate_ids[]"]:checked'
                    );
                    if (!circulationChecks || circulationChecks.length === 0) {
                        errors.push('Sirkulasi wajib dipilih minimal 1 direktorat.');
                    }

                    if (filesInput && filesInput.files.length > 0) {
                        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                        for (const file of filesInput.files) {
                            if (file.size > maxFileSize) {
                                errors.push(`File ${file.name} melebihi 10MB.`);
                                break;
                            }
                            if (file.type && !allowedTypes.includes(file.type)) {
                                errors.push(`Format file ${file.name} tidak valid.`);
                                break;
                            }
                        }
                    }

                    if (!isEdit && filesInput && filesInput.files.length === 0) {
                        errors.push('Upload surat masuk wajib diisi.');
                    }

                    if (errors.length > 0) {
                        event.preventDefault();
                        alert(errors[0]);
                    }
                });
            }
        });
    </script>
@endpush
