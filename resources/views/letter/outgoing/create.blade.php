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
        $isEditableStatus = !$outgoingLetter || in_array($outgoingLetter->status, ['draft', 'returned'], true);
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
                            <label class="form-label">No. Registrasi</label>
                            <input class="input" type="text" name="registration_no" readonly
                                value="{{ old('registration_no', $outgoingLetter?->registration_no ?? 'Auto Generated') }}">
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Tanggal Order <span class="text-danger">*</span></label>
                            <input class="input" type="date" name="order_date" readonly
                                value="{{ old('order_date', $outgoingLetter?->order_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Penerima <span class="text-danger">*</span></label>
                            <select class="select" name="recipient_id" id="recipient_id" required>
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
                        </div>

                        <div class="flex flex-col" id="recipient-other-wrapper" style="display: none;">
                            <label class="form-label">Penerima Lainnya <span class="text-danger">*</span></label>
                            <input class="input" type="text" name="recipient_other" id="recipient_other"
                                value="{{ old('recipient_other', $outgoingLetter?->recipient_other) }}" maxlength="150"
                                placeholder="Tulis nama penerima">
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Perihal <span class="text-danger">*</span></label>
                            <input class="input" type="text" name="subject"
                                value="{{ old('subject', $outgoingLetter?->subject) }}" maxlength="255"
                                placeholder="Perihal surat..." required>
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Ringkasan Isi Surat <span class="text-danger">*</span></label>
                            <textarea class="textarea w-full" name="summary" rows="2" placeholder="Ringkasan isi surat..." required>{{ old('summary', $outgoingLetter?->summary) }}</textarea>
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Jenis Perihal <span class="text-danger">*</span></label>
                            <select class="select" name="perihal_type" id="perihal_type" required>
                                <option value="">- Pilih -</option>
                                <option value="tanggapan_surat_masuk"
                                    {{ old('perihal_type', $outgoingLetter?->perihal_type) === 'tanggapan_surat_masuk' ? 'selected' : '' }}>
                                    Tanggapan Surat Masuk
                                </option>
                                <option value="rutinitas"
                                    {{ old('perihal_type', $outgoingLetter?->perihal_type) === 'rutinitas' ? 'selected' : '' }}>
                                    Rutinitas
                                </option>
                                <option value="insidentil"
                                    {{ old('perihal_type', $outgoingLetter?->perihal_type) === 'insidentil' ? 'selected' : '' }}>
                                    Insidentil
                                </option>
                            </select>
                        </div>

                        <div class="flex flex-col perihal-field hidden" data-perihal="tanggapan_surat_masuk">
                            <label class="form-label">Surat Masuk</label>
                            <select class="select" name="perihal_incoming_letter_id">
                                <option value="">- Pilih Surat Masuk -</option>
                                @foreach ($incomingLetters as $incomingLetter)
                                    <option value="{{ $incomingLetter->id }}"
                                        {{ (string) old('perihal_incoming_letter_id', $outgoingLetter?->perihal_incoming_letter_id) === (string) $incomingLetter->id ? 'selected' : '' }}>
                                        {{ $incomingLetter->registration_no }} - {{ $incomingLetter->subject }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex flex-col perihal-field hidden" data-perihal="rutinitas">
                            <label class="form-label">Perihal (Free Text)</label>
                            <input class="input" type="text" name="perihal_text"
                                value="{{ old('perihal_text', $outgoingLetter?->perihal_text) }}"
                                placeholder="Perihal rutinitas...">
                        </div>

                        <div class="flex flex-col perihal-field hidden" data-perihal="insidentil">
                            <label class="form-label">Perihal (Free Text)</label>
                            <input class="input" type="text" name="perihal_text"
                                value="{{ old('perihal_text', $outgoingLetter?->perihal_text) }}"
                                placeholder="Perihal insidentil...">
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Sirkulasi Kepatuhan <span class="text-danger">*</span></label>
                            <select class="select" name="need_compliance_review" required>
                                <option value="">- Pilih -</option>
                                <option value="1"
                                    {{ (string) old('need_compliance_review', $outgoingLetter?->need_compliance_review) === '1' ? 'selected' : '' }}>
                                    Ya</option>
                                <option value="0"
                                    {{ (string) old('need_compliance_review', $outgoingLetter?->need_compliance_review) === '0' ? 'selected' : '' }}>
                                    Tidak</option>
                            </select>
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">
                                {{ isset($outgoingLetter) ? 'Tambah Draft Surat (PDF/JPG/PNG)' : 'Upload Draft Surat (PDF/JPG/PNG)' }}
                                @if (!isset($outgoingLetter))
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            <input class="file-input" type="file" name="draft_file" accept=".pdf,.jpg,.jpeg,.png">
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Catatan</label>
                            <textarea class="textarea w-full" name="note" rows="2" placeholder="Catatan tambahan...">{{ old('note', $outgoingLetter?->note) }}</textarea>
                        </div>
                    </div>

                    <div class="flex justify-end mt-7 gap-2">
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
    <script src="{{ asset('js/corsec/incoming-validation.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('outgoing-letter-form');
            const approvalInput = document.getElementById('submit_for_approval');
            const saveDraftButton = document.getElementById('save-draft');
            const submitApprovalButton = document.getElementById('submit-approval');
            const recipientSelect = document.getElementById('recipient_id');
            const recipientOtherWrapper = document.getElementById('recipient-other-wrapper');
            const recipientOtherInput = document.getElementById('recipient_other');
            const perihalSelect = document.getElementById('perihal_type');
            const perihalFields = document.querySelectorAll('.perihal-field');

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

            function togglePerihalFields() {
                const selected = perihalSelect ? perihalSelect.value : '';
                perihalFields.forEach((field) => {
                    if (field.dataset.perihal === selected) {
                        field.classList.remove('hidden');
                    } else {
                        field.classList.add('hidden');
                    }
                });
            }

            if (recipientSelect) {
                recipientSelect.addEventListener('change', toggleRecipientOther);
                toggleRecipientOther();
            }

            if (perihalSelect) {
                perihalSelect.addEventListener('change', togglePerihalFields);
                togglePerihalFields();
            }

            if (window.jQuery && window.CorsecIncomingValidation) {
                const $document = window.jQuery(document);
                const {
                    clearValidation,
                    showFieldError,
                } = window.CorsecIncomingValidation;

                function validateOutgoingForm($form) {
                    const errors = {};
                    const isEdit = {{ isset($outgoingLetter) ? 'true' : 'false' }};
                    const requiredMessage = 'Field ini tidak boleh kosong.';

                    if (!$form.find('[name="recipient_id"]').val()) {
                        errors.recipient_id = requiredMessage;
                    }
                    if (!$form.find('[name="subject"]').val()) {
                        errors.subject = requiredMessage;
                    }
                    if (!$form.find('[name="summary"]').val()) {
                        errors.summary = requiredMessage;
                    }
                    if (!$form.find('[name="perihal_type"]').val()) {
                        errors.perihal_type = requiredMessage;
                    }
                    if (!$form.find('[name="need_compliance_review"]').val()) {
                        errors.need_compliance_review = requiredMessage;
                    }

                    const perihalType = $form.find('[name="perihal_type"]').val();
                    if (perihalType === 'tanggapan_surat_masuk' &&
                        !$form.find('[name="perihal_incoming_letter_id"]').val()) {
                        errors.perihal_incoming_letter_id = requiredMessage;
                    }
                    if ((perihalType === 'rutinitas' || perihalType === 'insidentil') &&
                        !$form.find('[name="perihal_text"]').val()) {
                        errors.perihal_text = requiredMessage;
                    }

                    const filesInput = $form.find('[name="draft_file"]')[0];
                    if (!isEdit && filesInput && filesInput.files.length === 0) {
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
                        success: function() {
                            if (window.toast && typeof window.toast.success === 'function') {
                                window.toast.success('Berhasil disimpan.');
                                setTimeout(() => window.location.reload(), 800);
                                return;
                            }
                            alert('Berhasil disimpan.');
                            window.location.reload();
                        },
                        error: function(xhr) {
                            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON
                                .errors) {
                                const serverErrors = xhr.responseJSON.errors;
                                Object.keys(serverErrors).forEach((field) => {
                                    showFieldError($form, field, serverErrors[field][
                                    0]);
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
