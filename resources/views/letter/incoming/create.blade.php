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
                    enctype="multipart/form-data" class="js-ajax-form" data-form-type="incoming">
                    @csrf
                    @if (isset($incomingLetter))
                        @method('PUT')
                    @endif
                    <input type="hidden" name="submit_for_approval" id="submit_for_approval" value="1">

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
                                rows="3" placeholder="Ringkasan isi surat..." required>{{ old('summary', $incomingLetter?->summary) }}</textarea>
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
                                value="{{ old('sender_other', $incomingLetter?->sender_other) }}" maxlength="150"
                                placeholder="Tulis nama pengirim">
                            @error('sender_other')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col" id="customer-branch-wrapper" style="display: none;">
                            <label class="form-label">Cabang Nasabah/Debitur <span class="text-danger">*</span></label>
                            <select class="select @error('customer_branch_id') border-danger bg-danger-light @enderror"
                                name="customer_branch_id" id="customer_branch_id">
                                <option value="">- Pilih Cabang -</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}"
                                        {{ (string) old('customer_branch_id', $incomingLetter?->customer_branch_id) === (string) $branch->id ? 'selected' : '' }}>
                                        {{ $branch->code }} - {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_branch_id')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Jenis Surat <span class="text-danger">*</span></label>
                            <select class="select @error('letter_type_id') border-danger bg-danger-light @enderror"
                                name="letter_type_id" id="letter_type_id" required>
                                <option value="">- Pilih Jenis Surat -</option>
                                @foreach ($letterTypes as $letterType)
                                    <option value="{{ $letterType->id }}"
                                        {{ (string) old('letter_type_id', $incomingLetter?->letter_type_id) === (string) $letterType->id ? 'selected' : '' }}>
                                        {{ $letterType->name }}
                                    </option>
                                @endforeach
                                <option value="other"
                                    {{ old('letter_type_id', $incomingLetter?->letter_type_id ?? ($incomingLetter?->letter_type_other ? 'other' : '')) === 'other' ? 'selected' : '' }}>
                                    Other
                                </option>
                            </select>
                            @error('letter_type_id')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col" id="letter-type-other-wrapper" style="display: none;">
                            <label class="form-label">Action Surat Lainnya <span class="text-danger">*</span></label>
                            <input class="input @error('letter_type_other') border-danger bg-danger-light @enderror"
                                type="text" name="letter_type_other" id="letter_type_other"
                                value="{{ old('letter_type_other', $incomingLetter?->letter_type_other) }}"
                                maxlength="150" placeholder="Tulis action surat">
                            @error('letter_type_other')
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
                                <button type="button" data-field="circulation_directorate_ids"
                                    class="select w-full flex items-center justify-between text-left bg-white text-gray-800 @error('circulation_directorate_ids') border-danger bg-danger-light @enderror"
                                    style="text-align: left; justify-content: flex-start;" id="circulation-dropdown">
                                    <span id="circulation-selected-text" class="block truncate text-left w-full"
                                        style="text-align: left;">Pilih sirkulasi...</span>
                                </button>
                                <div id="circulation-options"
                                    class="absolute z-20 mt-1 left-0 right-0 max-h-64 overflow-auto bg-white border border-gray-200 rounded shadow-lg hidden"
                                    style="background-color: #ffffff; max-height: 16rem; overflow-y: auto;">
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
                            <label class="form-label">Due Date Letter<span class="text-danger">*</span></label>
                            <input class="input @error('target_date') border-danger bg-danger-light @enderror"
                                type="date" name="target_date" min="{{ now()->format('Y-m-d') }}"
                                value="{{ old('target_date', $incomingLetter?->target_date?->format('Y-m-d')) }}">
                            @error('target_date')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Due Date Register<span class="text-danger">*</span></label>
                            <input class="input @error('register_due_date') border-danger bg-danger-light @enderror"
                                type="date" name="register_due_date" id="register_due_date"
                                value="{{ old('register_due_date', $incomingLetter?->register_due_date?->format('Y-m-d')) }}">
                            <div class="mt-1 text-xs text-gray-500">Wajib diisi jika surat yang diterima berupa undangan.
                            </div>
                            @error('register_due_date')
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
                                {{ isset($incomingLetter) ? 'Tambah Lampiran (PDF/JPG/PNG/XLS/XLSX)' : 'Upload Surat Masuk (PDF/JPG/PNG/XLS/XLSX)' }}
                                @if (!isset($incomingLetter))
                                    <span class="text-danger">*</span>
                                @endif
                            </label>

                            <input class="file-input @error('files.*') border-danger bg-danger-light @enderror"
                                type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx">
                            @error('files.*')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror

                            <div class="mt-1 text-xs text-gray-500">
                                Bisa multiple file. Max {{ \Modules\Corsec\Support\UploadRule::label() }} per file.
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
                                    <button type="button" id="save-draft" class="btn btn-light">
                                        <i class="ki-filled ki-archive"></i> Update Draft
                                    </button>
                                    <button type="submit" id="submit-approval" class="btn btn-primary">
                                        <i class="ki-filled ki-check"></i> Submit
                                    </button>
                                @endif
                            @endcan
                        @else
                            @can('corsec.create')
                                <button type="button" id="save-draft" class="btn btn-light">
                                    <i class="ki-filled ki-archive"></i> Save Draft
                                </button>
                                <button type="submit" id="submit-approval" class="btn btn-primary">
                                    <i class="ki-filled ki-check"></i> Submit
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
    <script src="{{ asset('js/corsec/incoming-validation.js') }}"></script>
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
            const customerBranchWrapper = document.getElementById('customer-branch-wrapper');
            const customerBranchSelect = document.getElementById('customer_branch_id');
            const customerSenderId = @json($customerSenderId ? (string) $customerSenderId : null);
            const targetDirectorateSelect = form ? form.querySelector('select[name="target_directorate_id"]') :
                null;
            const targetDirectorateOptions = targetDirectorateSelect ? Array.from(targetDirectorateSelect.options) :
                [];
            const letterTypeSelect = document.getElementById('letter_type_id');
            const letterTypeOtherWrapper = document.getElementById('letter-type-other-wrapper');
            const letterTypeOtherInput = document.getElementById('letter_type_other');
            const registerDueDateInput = document.getElementById('register_due_date');

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

            function toggleCustomerBranch() {
                if (!senderSelect || !customerBranchWrapper || !customerBranchSelect) return;
                if (customerSenderId && senderSelect.value === customerSenderId) {
                    customerBranchWrapper.style.display = 'flex';
                    customerBranchSelect.required = true;
                } else {
                    customerBranchWrapper.style.display = 'none';
                    customerBranchSelect.required = false;
                    customerBranchSelect.value = '';
                }
            }

            if (senderSelect) {
                senderSelect.addEventListener('change', toggleSenderOther);
                senderSelect.addEventListener('change', toggleCustomerBranch);
                toggleSenderOther();
                toggleCustomerBranch();
            }

            function toggleLetterTypeOther() {
                if (!letterTypeSelect || !letterTypeOtherWrapper || !letterTypeOtherInput) return;
                if (letterTypeSelect.value === 'other') {
                    letterTypeOtherWrapper.style.display = 'flex';
                    letterTypeOtherInput.required = true;
                } else {
                    letterTypeOtherWrapper.style.display = 'none';
                    letterTypeOtherInput.required = false;
                    letterTypeOtherInput.value = '';
                }
            }

            function isInvitationLetter() {
                const subjectValue = form ? (form.querySelector('[name="subject"]')?.value || '').toLowerCase() :
                    '';
                const letterTypeLabel = letterTypeSelect && letterTypeSelect.selectedIndex >= 0 ?
                    (letterTypeSelect.options[letterTypeSelect.selectedIndex]?.text || '').toLowerCase() : '';
                const letterTypeOtherValue = letterTypeOtherInput ? (letterTypeOtherInput.value || '')
                    .toLowerCase() : '';

                return subjectValue.includes('undangan') ||
                    letterTypeLabel.includes('undangan') ||
                    letterTypeOtherValue.includes('undangan');
            }

            if (letterTypeSelect) {
                letterTypeSelect.addEventListener('change', toggleLetterTypeOther);
                toggleLetterTypeOther();
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
                if (!targetDirectorateSelect || !circulationOptions || targetDirectorateOptions.length === 0)
                    return;

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
                    if (!circulationDropdown.contains(event.target) && !circulationOptions.contains(event
                            .target)) {
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
                if (window.jQuery && window.CorsecIncomingValidation) {
                    const $document = window.jQuery(document);
                    const {
                        clearValidation,
                        showFieldError,
                        validateFileSizes,
                        uploadFailureMessage,
                    } = window.CorsecIncomingValidation;
                    const indexUrl = @json(route('letter.incoming.index'));
                    const uploadSizeOptions = {
                        maxBytes: @json(\Modules\Corsec\Support\UploadRule::maxFileSizeKb() * 1024),
                        label: @json(\Modules\Corsec\Support\UploadRule::label()),
                    };
                    const now = new Date();
                    const todayYmd = [
                        now.getFullYear(),
                        String(now.getMonth() + 1).padStart(2, '0'),
                        String(now.getDate()).padStart(2, '0')
                    ].join('-');

                    function validateIncomingForm($form) {
                        const errors = {};
                        const isEdit = {{ isset($incomingLetter) ? 'true' : 'false' }};
                        const requiredMessage = 'Field ini tidak boleh kosong.';

                        if (!$form.find('[name="external_letter_no"]').val()) {
                            errors.external_letter_no = requiredMessage;
                        }
                        if (!$form.find('[name="letter_date"]').val()) {
                            errors.letter_date = requiredMessage;
                        }
                        if (!$form.find('[name="subject"]').val()) {
                            errors.subject = requiredMessage;
                        }
                        if (!$form.find('[name="summary"]').val()) {
                            errors.summary = requiredMessage;
                        }
                        const senderValue = $form.find('[name="sender_id"]').val();
                        if (!senderValue) {
                            errors.sender_id = requiredMessage;
                        }
                        if (senderValue === 'other' && !$form.find('[name="sender_other"]').val()) {
                            errors.sender_other = requiredMessage;
                        }
                        if (customerSenderId && senderValue === customerSenderId &&
                            !$form.find('[name="customer_branch_id"]').val()) {
                            errors.customer_branch_id = requiredMessage;
                        }
                        const letterTypeValue = $form.find('[name="letter_type_id"]').val();
                        if (!letterTypeValue) {
                            errors.letter_type_id = requiredMessage;
                        }
                        if (letterTypeValue === 'other' && !$form.find('[name="letter_type_other"]').val()) {
                            errors.letter_type_other = requiredMessage;
                        }
                        if (isInvitationLetter() && !$form.find('[name="register_due_date"]').val()) {
                            errors.register_due_date = 'Due date register wajib diisi untuk surat undangan.';
                        }
                        const targetDateValue = $form.find('[name="target_date"]').val();
                        if (targetDateValue && targetDateValue < todayYmd) {
                            errors.target_date = 'Due date tidak boleh kurang dari hari ini.';
                        }
                        if (!$form.find('[name="target_directorate_id"]').val()) {
                            errors.target_directorate_id = requiredMessage;
                        }
                        const circulationCount = $form.find(
                            '[name="circulation_directorate_ids[]"]:checked'
                        ).length;
                        if (circulationCount === 0) {
                            errors.circulation_directorate_ids = 'Silahkan pilih minimal 1.';
                        }

                        const targetValue = $form.find('[name="target_directorate_id"]').val();
                        if (targetValue) {
                            const selectedCirculationIds = $form.find(
                                '[name="circulation_directorate_ids[]"]:checked'
                            ).map(function() {
                                return window.jQuery(this).val();
                            }).get();
                            if (selectedCirculationIds.length > 0 &&
                                !selectedCirculationIds.includes(String(targetValue))) {
                                errors.target_directorate_id =
                                    'Leader tindak lanjut harus termasuk di daftar sirkulasi.';
                            }
                        }

                        const filesInput = $form.find('[name="files[]"]')[0];
                        if (!isEdit && filesInput && filesInput.files.length === 0) {
                            errors.files = 'Harap upload file.';
                        }

                        Object.assign(errors, validateFileSizes($form, uploadSizeOptions));

                        return errors;
                    }

                    $document.on('submit', 'form.js-ajax-form', function(event) {
                        event.preventDefault();
                        const $form = window.jQuery(this);
                        if ($form.data('submitting')) {
                            return;
                        }

                        clearValidation($form);

                        const errors = validateIncomingForm($form);
                        if (Object.keys(errors).length > 0) {
                            Object.keys(errors).forEach((field) => {
                                showFieldError($form, field, errors[field]);
                            });
                            return;
                        }

                        $form.data('submitting', true);
                        const $submitButtons = $form.find('button[type="submit"], button[type="button"]');
                        $submitButtons.prop('disabled', true).addClass('disabled');

                        const formData = new FormData(this);
                        window.jQuery.ajax({
                            url: $form.attr('action'),
                            method: $form.attr('method') || 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': $form.find('input[name="_token"]').val()
                            },
                            success: function(response) {
                                if (response && response.redirect_url) {
                                    window.location.href = response.redirect_url;
                                    return;
                                }
                                if (window.toast && typeof window.toast.success ===
                                    'function') {
                                    window.toast.success('Berhasil disimpan.');
                                    setTimeout(function() {
                                        if (approvalInput && approvalInput.value ===
                                            '1') {
                                            window.location.href = indexUrl;
                                            return;
                                        }
                                        window.location.reload();
                                    }, 600);
                                    return;
                                }
                                Swal.fire('Berhasil', 'Data berhasil disimpan.', 'success').then(
                                    function() {
                                        if (approvalInput && approvalInput.value === '1') {
                                            window.location.href = indexUrl;
                                            return;
                                        }
                                        window.location.reload();
                                    });
                                return;
                            },
                            error: function(xhr) {
                                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON
                                    .errors) {
                                    const serverErrors = xhr.responseJSON.errors;
                                    Object.keys(serverErrors).forEach((field) => {
                                        showFieldError($form, field, serverErrors[field]
                                            [0]);
                                    });
                                } else {
                                    Swal.fire('Error!', uploadFailureMessage(xhr,
                                        'Gagal memproses surat masuk.', uploadSizeOptions), 'error');
                                }
                                $form.data('submitting', false);
                                $submitButtons.prop('disabled', false).removeClass('disabled');
                            }
                        });
                    });
                }
            }
        });
    </script>
@endpush
