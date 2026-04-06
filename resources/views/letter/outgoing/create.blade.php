@extends('layouts.main')

@section('breadcrumbs')
    @if (isset($outgoingLetter))
        {{ Breadcrumbs::render('letter.outgoing.index') }}
    @else
        {{ Breadcrumbs::render('letter.outgoing.index') }}
    @endif
@endsection

@section('content')
    @php
        $outgoingLetter = $outgoingLetter ?? null;
        $prefillIncomingLetterId = $prefillIncomingLetterId ?? null;
        $isEditableStatus = !$outgoingLetter || in_array($outgoingLetter->status, ['draft', 'returned'], true);
        $selectedLetterTypeId = old('letter_type_id', $outgoingLetter?->letter_type_id);
        $selectedPerihalType = old(
            'perihal_type',
            $outgoingLetter?->perihal_type ?? ($prefillIncomingLetterId ? 'tanggapan_surat_masuk' : ''),
        );
        $selectedPerihalIncomingLetterId = old(
            'perihal_incoming_letter_id',
            $outgoingLetter?->perihal_incoming_letter_id ?? $prefillIncomingLetterId,
        );
        $needComplianceReview = (string) old(
            'need_compliance_review',
            isset($outgoingLetter) ? (int) $outgoingLetter->need_compliance_review : 0,
        );
        $showDetailFields = isset($outgoingLetter) || !empty($selectedLetterTypeId);
    @endphp
    <div class="grid gap-5 mx-auto w-full lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-filled ki-document text-primary"></i>
                    {{ isset($outgoingLetter) ? 'Edit Surat Keluar' : 'Input Surat Keluar' }}
                </h3>
                <a href="{{ route('letter.outgoing.index') }}" class="btn btn-sm btn-info">
                    <i class="ki-filled ki-exit-left"></i> Back
                </a>
            </div>

            <div class="card-body">
                <form id="outgoing-letter-form" method="POST"
                    action="{{ isset($outgoingLetter) ? route('letter.outgoing.update', $outgoingLetter) : route('letter.outgoing.store') }}"
                    enctype="multipart/form-data" class="js-ajax-form" data-form-type="outgoing">
                    @csrf
                    @if (isset($outgoingLetter))
                        @method('PUT')
                    @else
                        <input type="hidden" name="submit_for_approval" id="submit_for_approval" value="1">
                    @endif

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="flex flex-col">
                            <label class="form-label">Jenis Surat <span class="text-danger">*</span></label>
                            <select class="select @error('letter_type_id') border-danger bg-danger-light @enderror"
                                name="letter_type_id" id="letter_type_id" required>
                                <option value="">- Pilih Jenis Surat -</option>
                                @foreach ($letterTypes as $letterType)
                                    <option value="{{ $letterType->id }}"
                                        {{ (string) old('letter_type_id', $outgoingLetter?->letter_type_id) === (string) $letterType->id ? 'selected' : '' }}>
                                        {{ $letterType->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('letter_type_id')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">No. Registrasi</label>
                            <input class="input" type="text" id="registration_no" name="registration_no" readonly
                                value="{{ old('registration_no', $outgoingLetter?->registration_no ?? ($showDetailFields ? 'Auto Generated' : 'Pilih jenis surat terlebih dahulu')) }}">
                        </div>
                    </div>

                    <div id="outgoing-form-fields" style="{{ $showDetailFields ? '' : 'display:none;' }}"
                        class="mt-8 grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="flex flex-col">
                            <label class="form-label">Tanggal Order <span class="text-danger">*</span></label>
                            <input class="input @error('order_date') border-danger bg-danger-light @enderror" type="date"
                                name="order_date" id="order_date" readonly
                                value="{{ old('order_date', $outgoingLetter?->order_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                            @error('order_date')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Penerima <span class="text-danger">*</span></label>
                            <select class="select @error('recipient_id') border-danger bg-danger-light @enderror"
                                name="recipient_id" id="recipient_id" required>
                                <option value="">- Pilih Penerima -</option>
                                @foreach ($senders as $sender)
                                    <option value="{{ $sender->id }}"
                                        {{ (string) old('recipient_id', $outgoingLetter?->recipient_id) === (string) $sender->id ? 'selected' : '' }}>
                                        {{ $sender->name }}
                                    </option>
                                @endforeach
                                <option value="other"
                                    {{ old('recipient_id', $outgoingLetter?->recipient_id ?? ($outgoingLetter?->recipient_other ? 'other' : '')) === 'other' ? 'selected' : '' }}>
                                    Other
                                </option>
                            </select>
                            @error('recipient_id')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col" id="recipient-other-wrapper" style="display: none;">
                            <label class="form-label">Penerima Lainnya <span class="text-danger">*</span></label>
                            <input class="input @error('recipient_other') border-danger bg-danger-light @enderror"
                                type="text" name="recipient_other" id="recipient_other"
                                value="{{ old('recipient_other', $outgoingLetter?->recipient_other) }}" maxlength="150"
                                placeholder="Tulis nama penerima">
                            @error('recipient_other')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Perihal <span class="text-danger">*</span></label>
                            <input class="input @error('subject') border-danger bg-danger-light @enderror" type="text"
                                name="subject" id="subject" value="{{ old('subject', $outgoingLetter?->subject) }}"
                                maxlength="255" placeholder="Perihal surat..." required>
                            @error('subject')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Ringkasan Isi Surat <span class="text-danger">*</span></label>
                            <textarea class="textarea w-full @error('summary') border-danger bg-danger-light @enderror" name="summary"
                                id="summary" rows="3" placeholder="Ringkasan isi surat..." required>{{ old('summary', $outgoingLetter?->summary) }}</textarea>
                            @error('summary')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Sirkulasi Kepatuhan?</label>
                            <select class="select @error('need_compliance_review') border-danger bg-danger-light @enderror"
                                name="need_compliance_review" id="need_compliance_review">
                                <option value="0" {{ $needComplianceReview === '0' ? 'selected' : '' }}>Tidak</option>
                                <option value="1" {{ $needComplianceReview === '1' ? 'selected' : '' }}>Ya</option>
                            </select>
                            @error('need_compliance_review')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Jenis Perihal <span class="text-danger">*</span></label>
                            <select class="select @error('perihal_type') border-danger bg-danger-light @enderror"
                                name="perihal_type" id="perihal_type" required>
                                <option value="">- Pilih -</option>
                                <option value="tanggapan_surat_masuk"
                                    {{ $selectedPerihalType === 'tanggapan_surat_masuk' ? 'selected' : '' }}>
                                    Tanggapan Surat Masuk
                                </option>
                                <option value="rutinitas" {{ $selectedPerihalType === 'rutinitas' ? 'selected' : '' }}>
                                    Rutinitas
                                </option>
                                <option value="insidentil" {{ $selectedPerihalType === 'insidentil' ? 'selected' : '' }}>
                                    Insidentil
                                </option>
                            </select>
                            @error('perihal_type')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <input type="hidden" name="perihal_text" id="perihal_text_unified"
                            value="{{ old('perihal_text', $outgoingLetter?->perihal_text) }}">

                        <div class="flex flex-col perihal-field hidden" data-perihal="tanggapan_surat_masuk">
                            <label class="form-label">Surat Masuk</label>
                            <select
                                class="select @error('perihal_incoming_letter_id') border-danger bg-danger-light @enderror"
                                name="perihal_incoming_letter_id" id="perihal_incoming_letter_id">
                                <option value="">- Pilih Surat Masuk -</option>
                                @foreach ($incomingLetters as $incomingLetter)
                                    <option value="{{ $incomingLetter->id }}"
                                        {{ (string) $selectedPerihalIncomingLetterId === (string) $incomingLetter->id ? 'selected' : '' }}>
                                        {{ $incomingLetter->external_letter_no }} - {{ $incomingLetter->subject }}
                                    </option>
                                @endforeach
                            </select>
                            @error('perihal_incoming_letter_id')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col perihal-field hidden" data-perihal="rutinitas">
                            <label class="form-label">Keterangan Rutinitas</label>
                            <input class="input @error('perihal_text') border-danger bg-danger-light @enderror"
                                type="text" name="perihal_text_rutinitas" id="perihal_text_rutinitas"
                                value="{{ old('perihal_text_rutinitas', $selectedPerihalType === 'rutinitas' ? old('perihal_text', $outgoingLetter?->perihal_text) : '') }}"
                                placeholder="Perihal rutinitas...">
                            @error('perihal_text')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col perihal-field hidden" data-perihal="insidentil">
                            <label class="form-label">Keterangan Insidentil</label>
                            <input class="input @error('perihal_text') border-danger bg-danger-light @enderror"
                                type="text" name="perihal_text_insidentil" id="perihal_text_insidentil"
                                value="{{ old('perihal_text_insidentil', $selectedPerihalType === 'insidentil' ? old('perihal_text', $outgoingLetter?->perihal_text) : '') }}"
                                placeholder="Perihal insidentil...">
                            @error('perihal_text')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Status</label>
                            <input class="input" type="text" readonly
                                value="{{ isset($outgoingLetter) ? $outgoingLetter->display_status_label : 'Draft' }}">
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">
                                {{ isset($outgoingLetter) ? 'Tambah Draft Surat (PDF/JPG/PNG)' : 'Upload Draft Surat (PDF/JPG/PNG)' }}
                            </label>
                            <div class="mb-1 text-xs text-gray-500">Wajib saat submit approval. Saat save draft boleh dikosongkan.</div>
                            <input class="file-input @error('draft_file') border-danger bg-danger-light @enderror"
                                type="file" name="draft_file" accept=".pdf,.jpg,.jpeg,.png">
                            @error('draft_file')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                    </div>

                    <div id="outgoing-form-extra" style="{{ $showDetailFields ? '' : 'display:none;' }}">
                        <div class="border-t border-gray-200" style="margin: 24px 0;"></div>

                        <div class="grid grid-cols-1 gap-5">
                            <div class="flex flex-col">
                                <label class="form-label">Catatan</label>
                                <textarea class="textarea w-full @error('note') border-danger bg-danger-light @enderror" name="note"
                                    rows="3" placeholder="Catatan tambahan...">{{ old('note', $outgoingLetter?->note) }}</textarea>
                                @error('note')
                                    <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div id="outgoing-form-actions" class="flex justify-end mt-8 gap-2"
                        style="{{ $showDetailFields ? '' : 'display:none;' }}">
                        <a href="{{ route('letter.outgoing.index') }}" class="btn btn-light">
                            Cancel
                        </a>
                        @if (isset($outgoingLetter))
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
                                    <i class="ki-filled ki-check"></i> Submit Approval
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
            const form = document.getElementById('outgoing-letter-form');
            const approvalInput = document.getElementById('submit_for_approval');
            const saveDraftButton = document.getElementById('save-draft');
            const submitApprovalButton = document.getElementById('submit-approval');
            const isEdit = {{ isset($outgoingLetter) ? 'true' : 'false' }};
            const registrationPreviewUrl = @json(route('letter.outgoing.registration_preview'));
            const incomingPreviewUrl = @json(route('letter.outgoing.incoming_preview'));
            const letterTypeSelect = document.getElementById('letter_type_id');
            const registrationInput = document.getElementById('registration_no');
            const orderDateInput = document.getElementById('order_date');
            const outgoingFormFields = document.getElementById('outgoing-form-fields');
            const outgoingFormExtra = document.getElementById('outgoing-form-extra');
            const outgoingFormActions = document.getElementById('outgoing-form-actions');
            const recipientSelect = document.getElementById('recipient_id');
            const recipientOtherWrapper = document.getElementById('recipient-other-wrapper');
            const recipientOtherInput = document.getElementById('recipient_other');
            const perihalSelect = document.getElementById('perihal_type');
            const perihalFields = document.querySelectorAll('.perihal-field');
            const perihalTextUnifiedInput = document.getElementById('perihal_text_unified');
            const perihalRutinitasInput = document.getElementById('perihal_text_rutinitas');
            const perihalInsidentilInput = document.getElementById('perihal_text_insidentil');
            const incomingLetterSelect = document.getElementById('perihal_incoming_letter_id');
            const subjectInput = document.getElementById('subject');
            const summaryInput = document.getElementById('summary');
            const incomingPreviewCache = {};

            async function refreshRegistrationPreview() {
                if (isEdit || !registrationInput) return;

                const hasLetterType = !!(letterTypeSelect && letterTypeSelect.value);
                if (!hasLetterType) {
                    registrationInput.value = 'Pilih jenis surat terlebih dahulu';
                    return;
                }

                const params = new URLSearchParams({
                    letter_type_id: letterTypeSelect.value
                });

                if (orderDateInput && orderDateInput.value) {
                    params.set('order_date', orderDateInput.value);
                }

                try {
                    const response = await fetch(`${registrationPreviewUrl}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Failed to preview registration number');
                    }

                    const payload = await response.json();
                    registrationInput.value = payload.registration_no || 'Auto Generated';
                } catch (error) {
                    registrationInput.value = 'Auto Generated';
                }
            }

            function toggleFormByLetterType() {
                const hasLetterType = !!(letterTypeSelect && letterTypeSelect.value);

                if (!isEdit) {
                    if (outgoingFormFields) outgoingFormFields.style.display = hasLetterType ? '' : 'none';
                    if (outgoingFormExtra) outgoingFormExtra.style.display = hasLetterType ? '' : 'none';
                    if (outgoingFormActions) outgoingFormActions.style.display = hasLetterType ? 'flex' : 'none';
                }

                if (hasLetterType || isEdit) {
                    refreshRegistrationPreview();
                } else if (registrationInput) {
                    registrationInput.value = 'Pilih jenis surat terlebih dahulu';
                }
            }

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

            if (letterTypeSelect) {
                letterTypeSelect.addEventListener('change', toggleFormByLetterType);
            }

            if (orderDateInput) {
                orderDateInput.addEventListener('change', refreshRegistrationPreview);
            }

            function toggleRecipientOther() {
                if (!recipientSelect || !recipientOtherWrapper || !recipientOtherInput) return;
                if (recipientSelect.value === 'other') {
                    recipientOtherWrapper.style.display = 'flex';
                    recipientOtherInput.required = true;
                } else {
                    recipientOtherWrapper.style.display = 'none';
                    recipientOtherInput.required = false;
                    recipientOtherInput.value = '';
                }
            }

            async function fetchIncomingPreview(incomingLetterId) {
                if (!incomingLetterId) return null;
                if (incomingPreviewCache[incomingLetterId]) {
                    return incomingPreviewCache[incomingLetterId];
                }

                const params = new URLSearchParams({
                    incoming_letter_id: incomingLetterId,
                });

                const response = await fetch(`${incomingPreviewUrl}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to preview incoming letter');
                }

                const payload = await response.json();
                incomingPreviewCache[incomingLetterId] = payload;
                return payload;
            }

            function applyIncomingRecipient(payload) {
                if (!recipientSelect) return;

                const recipientId = payload && payload.recipient_id ? String(payload.recipient_id) : '';
                const hasRecipientOption = recipientId !== '' && Array.from(recipientSelect.options).some((
                        option) =>
                    option.value === recipientId);

                if (hasRecipientOption) {
                    recipientSelect.value = recipientId;
                    if (recipientOtherInput) recipientOtherInput.value = '';
                } else {
                    recipientSelect.value = 'other';
                    if (recipientOtherInput) {
                        recipientOtherInput.value = payload && payload.recipient_other ? payload.recipient_other :
                            '';
                    }
                }

                toggleRecipientOther();
            }

            async function consumeIncomingLetterData(forceFill = false) {
                const isTanggapanSuratMasuk = perihalSelect && perihalSelect.value === 'tanggapan_surat_masuk';
                if (!isTanggapanSuratMasuk || !incomingLetterSelect || !incomingLetterSelect.value) {
                    return;
                }

                try {
                    const payload = await fetchIncomingPreview(incomingLetterSelect.value);
                    if (!payload) return;

                    if (subjectInput && (forceFill || !subjectInput.value.trim())) {
                        subjectInput.value = payload.subject || '';
                    }

                    if (summaryInput && (forceFill || !summaryInput.value.trim())) {
                        summaryInput.value = payload.summary || '';
                    }

                    if (forceFill || !recipientSelect || !recipientSelect.value || recipientSelect.value ===
                        'other') {
                        applyIncomingRecipient(payload);
                    }
                } catch (error) {
                    // Keep form usable even if preview request fails.
                }
            }

            function togglePerihalFields() {
                const selected = perihalSelect ? perihalSelect.value : '';
                perihalFields.forEach((field) => {
                    if (field.dataset.perihal === selected) {
                        field.classList.remove('hidden');
                    } else {
                        field.classList.add('hidden');
                    }
                });

                if (selected === 'tanggapan_surat_masuk') {
                    consumeIncomingLetterData(false);
                }

                syncPerihalTextUnified();
            }

            function syncPerihalTextUnified() {
                if (!perihalTextUnifiedInput) return;
                const selected = perihalSelect ? perihalSelect.value : '';
                let value = '';

                if (selected === 'rutinitas' && perihalRutinitasInput) {
                    value = perihalRutinitasInput.value || '';
                } else if (selected === 'insidentil' && perihalInsidentilInput) {
                    value = perihalInsidentilInput.value || '';
                }

                perihalTextUnifiedInput.value = value;
            }

            if (recipientSelect) {
                recipientSelect.addEventListener('change', toggleRecipientOther);
                toggleRecipientOther();
            }

            if (perihalSelect) {
                perihalSelect.addEventListener('change', togglePerihalFields);
                togglePerihalFields();
            }

            if (perihalRutinitasInput) {
                perihalRutinitasInput.addEventListener('input', syncPerihalTextUnified);
            }
            if (perihalInsidentilInput) {
                perihalInsidentilInput.addEventListener('input', syncPerihalTextUnified);
            }

            if (incomingLetterSelect) {
                incomingLetterSelect.addEventListener('change', function() {
                    consumeIncomingLetterData(true);
                });
            }

            toggleFormByLetterType();
            consumeIncomingLetterData(false);

            if (window.jQuery && window.CorsecIncomingValidation) {
                const $document = window.jQuery(document);
                const {
                    clearValidation,
                    showFieldError,
                } = window.CorsecIncomingValidation;
                const indexUrl = @json(route('letter.outgoing.index'));

                function validateOutgoingForm($form) {
                    const errors = {};
                    const requiredMessage = 'Field ini tidak boleh kosong.';
                    const submitForApproval = !isEdit && approvalInput && approvalInput.value === '1';

                    if (!$form.find('[name="order_date"]').val()) {
                        errors.order_date = requiredMessage;
                    }
                    if (!$form.find('[name="recipient_id"]').val()) {
                        errors.recipient_id = requiredMessage;
                    }
                    const recipientValue = $form.find('[name="recipient_id"]').val();
                    if (recipientValue === 'other' && !$form.find('[name="recipient_other"]').val()) {
                        errors.recipient_other = requiredMessage;
                    }
                    if (!$form.find('[name="subject"]').val()) {
                        errors.subject = requiredMessage;
                    }
                    if (!$form.find('[name="letter_type_id"]').val()) {
                        errors.letter_type_id = requiredMessage;
                    }
                    if (!$form.find('[name="summary"]').val()) {
                        errors.summary = requiredMessage;
                    }
                    if (!$form.find('[name="perihal_type"]').val()) {
                        errors.perihal_type = requiredMessage;
                    }
                    const perihalType = $form.find('[name="perihal_type"]').val();
                    if (perihalType === 'tanggapan_surat_masuk' &&
                        !$form.find('[name="perihal_incoming_letter_id"]').val()) {
                        errors.perihal_incoming_letter_id = requiredMessage;
                    }
                    if (perihalType === 'rutinitas' || perihalType === 'insidentil') {
                        const inputName = perihalType === 'rutinitas' ? 'perihal_text_rutinitas' :
                            'perihal_text_insidentil';
                        const perihalText = ($form.find(`[name="${inputName}"]`).val() || '').trim();
                        const unifiedField = $form.find('[name="perihal_text"]');
                        if (unifiedField.length > 0) {
                            unifiedField.val(perihalText);
                        }
                        if (!perihalText) {
                            errors.perihal_text = requiredMessage;
                        }
                    } else {
                        const unifiedField = $form.find('[name="perihal_text"]');
                        if (unifiedField.length > 0) {
                            unifiedField.val('');
                        }
                    }

                    const filesInput = $form.find('[name="draft_file"]')[0];
                    if (submitForApproval && filesInput && filesInput.files.length === 0) {
                        errors.draft_file = 'Harap upload file.';
                    }

                    return errors;
                }

                $document.on('submit', 'form.js-ajax-form', function(event) {
                    event.preventDefault();
                    const $form = window.jQuery(this);
                    clearValidation($form);

                    const errors = validateOutgoingForm($form);
                    if (Object.keys(errors).length > 0) {
                        Object.keys(errors).forEach((field) => {
                            showFieldError($form, field, errors[field]);
                        });
                        return;
                    }

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
                            if (window.toast && typeof window.toast.success === 'function') {
                                window.toast.success('Berhasil disimpan.');
                                setTimeout(function() {
                                    if (approvalInput && approvalInput.value === '1') {
                                        window.location.href = indexUrl;
                                        return;
                                    }
                                    window.location.reload();
                                }, 600);
                                return;
                            }
                            alert('Berhasil disimpan.');
                            if (approvalInput && approvalInput.value === '1') {
                                window.location.href = indexUrl;
                                return;
                            }
                            window.location.reload();
                        },
                        error: function(xhr) {
                            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON
                                .errors) {
                                const serverErrors = xhr.responseJSON.errors;
                                Object.keys(serverErrors).forEach((field) => {
                                    showFieldError($form, field, serverErrors[field][
                                        0
                                    ]);
                                });
                                return;
                            }
                            alert('Gagal memproses. Coba lagi ya.');
                        }
                    });
                });
            }
        });
    </script>
@endpush
