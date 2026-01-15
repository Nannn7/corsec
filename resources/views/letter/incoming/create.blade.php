@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.incoming.create') }}
@endsection

@section('content')
    <div class="grid gap-5 mx-auto w-full lg:gap-7.5">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-filled ki-document text-primary"></i>
                    Input Surat Masuk
                </h3>
                <a href="{{ route('letter.incoming.index') }}" class="btn btn-sm btn-info">
                    <i class="ki-filled ki-exit-left"></i> Back
                </a>
            </div>

            <div class="card-body">
                <form id="incoming-letter-form" method="POST" action="{{ route('letter.incoming.store') }}"
                    enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="submit_for_approval" id="submit_for_approval" value="1">

                    {{-- INFO SURAT --}}
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="flex flex-col">
                            <label class="form-label">No Surat Eksternal <span class="text-danger">*</span></label>
                            <input class="input @error('external_letter_no') border-danger bg-danger-light @enderror"
                                type="text" name="external_letter_no" value="{{ old('external_letter_no') }}"
                                maxlength="255" placeholder="Contoh: 001/ABC/I/2026">
                            @error('external_letter_no')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Tanggal Diterima <span class="text-danger">*</span></label>
                            <input class="input @error('received_date') border-danger bg-danger-light @enderror"
                                type="date" name="received_date" value="{{ old('received_date') }}">
                            @error('received_date')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Perihal <span class="text-danger">*</span></label>
                            <input class="input @error('subject') border-danger bg-danger-light @enderror" type="text"
                                name="subject" value="{{ old('subject') }}" maxlength="255"
                                placeholder="Contoh: Permohonan informasi / Undangan / dll" required>
                            @error('subject')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Pengirim <span class="text-danger">*</span></label>
                            <input class="input @error('sender') border-danger bg-danger-light @enderror" type="text"
                                name="sender" value="{{ old('sender') }}" maxlength="255"
                                placeholder="Nama instansi / perusahaan / orang">
                            @error('sender')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Prioritas <span class="text-danger">*</span></label>
                            <select class="select @error('priority') border-danger bg-danger-light @enderror"
                                name="priority">
                                <option value="">- Pilih -</option>
                                <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Low</option>
                                <option value="normal" {{ old('priority') === 'normal' ? 'selected' : '' }}>Normal</option>
                                <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>High</option>
                                <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                            </select>
                            @error('priority')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Target Date (SLA) <span class="text-danger">*</span></label>
                            <input class="input @error('target_date') border-danger bg-danger-light @enderror"
                                type="date" name="target_date" value="{{ old('target_date') }}">
                            @error('target_date')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Deskripsi / Catatan</label>
                            <textarea class="textarea @error('description') border-danger bg-danger-light @enderror" name="description"
                                rows="4" placeholder="Keterangan tambahan...">{{ old('description') }}</textarea>
                            @error('description')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col md:pt-6">
                            <label class="form-label">Direktorat / Unit Tujuan <span class="text-danger">*</span></label>
                            <select class="select @error('target_directorate_id') border-danger bg-danger-light @enderror"
                                name="target_directorate_id">
                                <option value="">- Pilih Direktorat -</option>
                                @foreach ($directorates as $b)
                                    <option value="{{ $b->id }}"
                                        {{ (string) old('target_directorate_id') === (string) $b->id ? 'selected' : '' }}>
                                        {{ $b->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('target_directorate_id')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                    </div>

                    <div class="my-7 border-t border-gray-200"></div>

                    {{-- UPLOAD --}}
                    <div class="grid grid-cols-1 gap-5 mt-8">
                        <div class="flex flex-col">
                            <label class="form-label">Upload Surat Masuk (PDF/JPG/PNG) <span
                                    class="text-danger">*</span></label>

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
                        @can('corsec.create')
                            <button type="button" id="save-draft" class="btn btn-light">
                                <i class="ki-filled ki-archive"></i> Save Draft
                            </button>
                            <button type="submit" id="submit-approval" class="btn btn-primary">
                                <i class="ki-filled ki-check"></i> Request Approval
                            </button>
                        @endcan
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

            if (form) {
                form.addEventListener('submit', function(event) {
                    const errors = [];
                    const maxTextLength = 255;
                    const maxFileSize = 10 * 1024 * 1024;

                    const externalLetterNo = form.querySelector('input[name="external_letter_no"]');
                    const subject = form.querySelector('input[name="subject"]');
                    const sender = form.querySelector('input[name="sender"]');
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

                    if (sender && sender.value.length > maxTextLength) {
                        errors.push('Pengirim wajib diisi.');
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

                    if (targetDirectorate && targetDirectorate.value) {
                        const option = targetDirectorate.querySelector(
                            `option[value="${targetDirectorate.value}"]`
                        );
                        if (!option && targetDirectorate.value.length > 0) {
                            errors.push('Direktorat tujuan wajib dipilih.');
                        }
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

                    if (errors.length > 0) {
                        event.preventDefault();
                        alert(errors[0]);
                    }
                });
            }
        });
    </script>
@endpush
