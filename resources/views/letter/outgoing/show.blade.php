@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.outgoing.index') }}
@endsection

@section('content')
    @php
        $permissionFlags = $permissionFlags ?? [];
        $status = $outgoingLetter->status;
        $canCorsecUpdateAction = (bool) ($permissionFlags['can_corsec_update_action'] ?? false);
        $canCorsecCreateOrUpdateAction = (bool) ($permissionFlags['can_corsec_create_or_update_action'] ?? false);
        $canEdit = (bool) ($permissionFlags['can_edit'] ?? false);
        $canDirCheckerApproval = (bool) ($permissionFlags['can_dir_checker_approval'] ?? false);
        $canDirApproverApproval = (bool) ($permissionFlags['can_dir_approver_approval'] ?? false);
        $canComplianceReview = (bool) ($permissionFlags['can_compliance_review'] ?? false);
        $canComplianceCheckerApproval = (bool) ($permissionFlags['can_compliance_checker_approval'] ?? false);
        $canComplianceApproverApproval = (bool) ($permissionFlags['can_compliance_approver_approval'] ?? false);
        $canFinalUpload = (bool) ($permissionFlags['can_final_upload'] ?? false);
        $canVerify = (bool) ($permissionFlags['can_verify'] ?? false);
        $canCancelRequest = (bool) ($permissionFlags['can_cancel_request'] ?? false);
        $canCancelApproval = (bool) ($permissionFlags['can_cancel_approval'] ?? false);
        $canDirectorNote = (bool) ($permissionFlags['can_director_note'] ?? false);
        $sortedComments = $sortedComments ?? collect();
        $statusSteps = $permissionFlags['status_steps'] ?? [
            'draft' => 'Draft',
            'waiting_dir_approval' => 'Approval EO dan DD Direktorat',
            'compliance_review' => 'Review Kepatuhan',
            'waiting_compliance_approval' => 'Approval EO dan DD Kepatuhan',
            'waiting_verification' => 'Verifikasi EO Corp Affair',
            'waiting_final_upload' => 'Final Upload',
            'waiting_cancel_approval' => 'Approval Pembatalan EO Direktorat',
            'verified' => 'Done',
            'returned' => 'Revisi',
            'cancelled' => 'Cancelled',
        ];
    @endphp

    <div class="grid gap-5 lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detail Surat Keluar #{{ $outgoingLetter->id }}</h3>
                <div class="flex gap-2">
                    <a href="{{ route('letter.outgoing.index') }}" class="btn btn-sm btn-light">
                        <i class="ki-filled ki-arrow-left"></i> Kembali
                    </a>
                    @if ($canEdit)
                        <a href="{{ route('letter.outgoing.edit', $outgoingLetter) }}" class="btn btn-sm btn-info">
                            Edit
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informasi Surat</h3>
            </div>
            <div class="card-body">
                <div class="grid gap-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">No Registrasi:</span>
                        <span class="font-medium">{{ $outgoingLetter->registration_no ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Tanggal Order:</span>
                        <span class="font-medium">
                            {{ $outgoingLetter->order_date ? $outgoingLetter->order_date->format('Y-m-d') : '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Tanggal Rencana Upload Final:</span>
                        <span class="font-medium">
                            {{ $outgoingLetter->final_upload_date ? $outgoingLetter->final_upload_date->format('Y-m-d') : '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Penerima:</span>
                        <span class="font-medium">
                            {{ $outgoingLetter->recipient?->name ?? ($outgoingLetter->recipient_other ?? '-') }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Jenis Surat:</span>
                        <span class="font-medium">{{ $outgoingLetter->letterType?->name ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Perihal:</span>
                        <span class="font-medium">{{ $outgoingLetter->subject ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Ringkasan:</span>
                        <span class="font-medium">{{ $outgoingLetter->summary ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Review Kepatuhan:</span>
                        <span class="font-medium">{{ $outgoingLetter->need_compliance_review ? 'Ya' : 'Tidak' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Status:</span>
                        <span class="badge badge-light">{{ $outgoingLetter->display_status_label }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Alasan Pembatalan:</span>
                        <span class="font-medium">{{ $outgoingLetter->cancel_reason ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Diajukan Pembatalan Oleh:</span>
                        <span class="font-medium">{{ $outgoingLetter->cancelRequestedBy?->name ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Tanggal Pengajuan Pembatalan:</span>
                        <span class="font-medium">
                            {{ $outgoingLetter->cancel_requested_at ? $outgoingLetter->cancel_requested_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') : '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Disetujui Pembatalan Oleh:</span>
                        <span class="font-medium">{{ $outgoingLetter->cancelledBy?->name ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Tanggal Pembatalan:</span>
                        <span class="font-medium">
                            {{ $outgoingLetter->cancelled_at ? $outgoingLetter->cancelled_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') : '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Draft Surat:</span>
                        <span class="font-medium">
                            @if ($outgoingLetter->draftAttachment)
                                <a class="text-primary hover:underline"
                                    href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($outgoingLetter->draftAttachment->path) }}"
                                    target="_blank" rel="noopener">
                                    {{ $outgoingLetter->draftAttachment->original_name ?? $outgoingLetter->draftAttachment->file_name }}
                                </a>
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Review Kepatuhan:</span>
                        <span class="font-medium">
                            @if ($outgoingLetter->complianceAttachment)
                                <a class="text-primary hover:underline"
                                    href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($outgoingLetter->complianceAttachment->path) }}"
                                    target="_blank" rel="noopener">
                                    {{ $outgoingLetter->complianceAttachment->original_name ?? $outgoingLetter->complianceAttachment->file_name }}
                                </a>
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Final Surat:</span>
                        <span class="font-medium">
                            @if ($outgoingLetter->finalAttachment)
                                <a class="text-primary hover:underline"
                                    href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($outgoingLetter->finalAttachment->path) }}"
                                    target="_blank" rel="noopener">
                                    {{ $outgoingLetter->finalAttachment->original_name ?? $outgoingLetter->finalAttachment->file_name }}
                                </a>
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Nomor Surat:</span>
                        <span class="font-medium">{{ $outgoingLetter->letter_no ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Rencana Tindak Lanjut</h3>
            </div>
            <div class="card-body">
                <div class="flex flex-wrap gap-2">
                    @foreach ($statusSteps as $key => $label)
                        <span
                            class="badge {{ $status === $key ? 'badge-success' : 'badge-light' }}">{{ $label }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($canCorsecUpdateAction)
            @if ($canComplianceReview)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Review Direktorat Kepatuhan</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('letter.outgoing.compliance.review', $outgoingLetter) }}"
                            enctype="multipart/form-data" class="grid gap-4 js-ajax-form"
                            data-form-type="outgoing-compliance">
                            @csrf
                            <div class="flex flex-col">
                                <label class="form-label">File Review Kepatuhan <span class="text-danger">*</span></label>
                                <input class="file-input" type="file" name="compliance_file"
                                    accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan review..."></textarea>
                            </div>
                            <div class="flex justify-end">
                                <button class="btn btn-primary" type="submit">Submit Review</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endif

        @if ($canFinalUpload)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Upload Final Surat</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('letter.outgoing.upload_final', $outgoingLetter) }}"
                        enctype="multipart/form-data" class="grid gap-4 js-ajax-form" data-form-type="outgoing-final">
                        @csrf
                        <div class="flex flex-col">
                            <label class="form-label">Tanggal Upload Final (wajib jika Simpan Draft)</label>
                            <input class="input" type="date" name="final_upload_date"
                                value="{{ old('final_upload_date', optional($outgoingLetter->final_upload_date)->format('Y-m-d')) }}">
                        </div>
                        <div class="flex flex-col">
                            <label class="form-label">Final Surat</label>
                            <input class="file-input" type="file" name="final_file" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button class="btn btn-light" type="submit" name="submit_action" value="draft">Simpan
                                Draft</button>
                            <button class="btn btn-primary" type="submit" name="submit_action" value="upload">Upload
                                Final</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if ($canCorsecCreateOrUpdateAction)
            @if ($canCancelRequest)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Ajukan Pembatalan Surat</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('letter.outgoing.cancel.request', $outgoingLetter) }}"
                            class="grid gap-4 js-ajax-form" data-form-type="outgoing-cancel-request">
                            @csrf
                            <div class="text-sm text-gray-500">
                                Permintaan pembatalan akan diproses oleh EO Direktorat.
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Alasan Pembatalan <span class="text-danger">*</span></label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tuliskan alasan pembatalan..."
                                    required></textarea>
                            </div>
                            <div class="flex justify-end">
                                <button class="btn btn-warning" type="submit">Ajukan Approval EO</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endif

        @if ($canDirectorNote)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Komentar Viewer</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('letter.outgoing.director.note', $outgoingLetter) }}"
                        class="grid gap-4 js-ajax-form" data-form-type="outgoing-director-note">
                        @csrf
                        <div class="flex flex-col">
                            <label class="form-label">Komentar Viewer (Direksi / Sekdir / Corporate Secretary) <span
                                    class="text-danger">*</span></label>
                            <textarea class="textarea w-full" name="note" rows="3"
                                placeholder="Tambahkan komentar viewer..." required></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button class="btn btn-primary" type="submit">Simpan Komentar</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if ($approvals->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Riwayat Approval</h3>
                </div>
                <div class="card-body">
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="min-w-[160px]">Status</th>
                                    <th class="min-w-[180px]">Oleh</th>
                                    <th class="min-w-[240px]">Catatan</th>
                                    <th class="min-w-[160px]">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($approvals as $approval)
                                    <tr>
                                        <td>{{ $approval->status ?? '-' }}</td>
                                        <td>
                                            {{ $approval->actor?->name ?? '-' }}
                                            @if ($approval->actor?->directorate?->name)
                                                <span
                                                    class="text-gray-500 text-xs">({{ $approval->actor->directorate->name }})</span>
                                            @endif
                                        </td>
                                        <td>{{ $approval->note ?? '-' }}</td>
                                        <td>
                                            {{ $approval->acted_at ? $approval->acted_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                    <h3 class="card-title">Riwayat Komentar</h3>
            </div>
            <div class="card-body">
                @if ($sortedComments->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="min-w-[220px]">Catatan</th>
                                    <th class="min-w-[180px]">Oleh</th>
                                    <th class="min-w-[160px]">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sortedComments as $comment)
                                    <tr>
                                        <td>{{ $comment->body ?? '-' }}</td>
                                        <td>{{ $comment->createdBy?->name ?? '-' }}</td>
                                        <td>
                                            {{ $comment->created_at ? $comment->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-gray-500 text-sm">Belum ada catatan untuk surat ini.</div>
                @endif
            </div>
        </div>

        @can('corsec.authorize')
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Approval</h3>
                </div>
                <div class="card-body">
                    @if ($canCancelApproval)
                        <form method="POST" action="{{ route('letter.outgoing.cancel.approval', $outgoingLetter) }}"
                            class="grid gap-4 js-ajax-form" data-form-type="outgoing-cancel-approval">
                            @csrf
                            <div class="text-sm text-gray-500">
                                Approval pembatalan oleh EO Direktorat
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (wajib saat reject)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan..."></textarea>
                            </div>
                            <div class="flex flex-wrap gap-2 justify-end">
                                <button class="btn btn-sm btn-danger" type="submit" name="action" value="reject">Reject
                                    Pembatalan</button>
                                <button class="btn btn-sm btn-success" type="submit" name="action" value="approve">Approve
                                    Pembatalan</button>
                            </div>
                        </form>
                    @elseif ($status === 'waiting_dir_approval' && ($canDirCheckerApproval || $canDirApproverApproval))
                        <form method="POST" action="{{ route('letter.outgoing.approval.action', $outgoingLetter) }}"
                            class="grid gap-4 js-ajax-form" data-form-type="outgoing-approval">
                            @csrf
                            <div class="text-sm text-gray-500">
                                {{ $canDirCheckerApproval ? 'Approval EO Direktorat' : 'Approval DD Direktorat' }}
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan..."></textarea>
                            </div>
                            <div class="flex flex-wrap gap-2 justify-end">
                                <button class="btn btn-sm btn-danger" type="submit" name="action"
                                    value="reject">Reject</button>
                                <button class="btn btn-sm btn-success" type="submit" name="action"
                                    value="approve">Approve</button>
                            </div>
                        </form>
                    @elseif ($status === 'waiting_compliance_approval' && ($canComplianceCheckerApproval || $canComplianceApproverApproval))
                        <form method="POST" action="{{ route('letter.outgoing.approval.action', $outgoingLetter) }}"
                            class="grid gap-4 js-ajax-form" data-form-type="outgoing-approval">
                            @csrf
                            <div class="text-sm text-gray-500">
                                {{ $canComplianceCheckerApproval ? 'Approval EO Kepatuhan' : 'Approval DD Kepatuhan' }}
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan..."></textarea>
                            </div>
                            <div class="flex flex-wrap gap-2 justify-end">
                                <button class="btn btn-sm btn-danger" type="submit" name="action"
                                    value="reject">Reject</button>
                                <button class="btn btn-sm btn-success" type="submit" name="action"
                                    value="approve">Approve</button>
                            </div>
                        </form>
                    @elseif ($canVerify)
                        <form method="POST" action="{{ route('letter.outgoing.verify.action', $outgoingLetter) }}"
                            class="grid gap-4 js-ajax-form" data-form-type="outgoing-verify">
                            @csrf
                            <div class="text-sm text-gray-500">
                                Verifikasi EO Corp Affair
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan..."></textarea>
                            </div>
                            <div class="flex flex-wrap gap-2 justify-end">
                                <button class="btn btn-sm btn-danger" type="submit" name="action"
                                    value="reject">Reject</button>
                                <button class="btn btn-sm btn-success" type="submit" name="action"
                                    value="verify">Verify</button>
                            </div>
                        </form>
                    @else
                        <div class="text-gray-500 text-sm">Belum ada aksi approval untuk status ini.</div>
                    @endif
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/corsec/incoming-validation.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.jQuery && window.CorsecIncomingValidation) {
                const $document = window.jQuery(document);
                const {
                    clearValidation,
                    showFieldError,
                } = window.CorsecIncomingValidation;

                function validateSimpleRequired($form, fields) {
                    const errors = {};
                    fields.forEach((field) => {
                        if (!$form.find(`[name="${field}"]`).val()) {
                            errors[field] = 'Field ini tidak boleh kosong.';
                        }
                    });
                    return errors;
                }

                // Track clicked submit button per form to avoid wrong action value in AJAX submit.
                $document.on('click', 'form.js-ajax-form button[type="submit"]', function() {
                    if (this.form) {
                        this.form.__lastSubmitter = this;
                    }
                });

                $document.on('submit', 'form.js-ajax-form', function(event) {
                    event.preventDefault();
                    const $form = window.jQuery(this);
                    clearValidation($form);

                    let errors = {};
                    const formType = $form.data('formType');
                    const nativeEvent = event.originalEvent || {};
                    const submitter = nativeEvent.submitter || this.__lastSubmitter || document
                        .activeElement;
                    const submitAction = submitter && submitter.name === 'submit_action' ? submitter.value :
                        'upload';

                    if (formType === 'outgoing-final') {
                        if (submitAction === 'draft') {
                            errors = validateSimpleRequired($form, ['final_upload_date']);
                        }
                        if (submitAction === 'upload') {
                            const finalFileInput = $form.find('[name="final_file"]')[0];
                            if (!finalFileInput || !finalFileInput.files || finalFileInput.files.length ===
                                0) {
                                errors.final_file = 'Field ini tidak boleh kosong.';
                            }
                        }
                    } else if (formType === 'outgoing-compliance') {
                        errors = validateSimpleRequired($form, ['compliance_file']);
                    } else if (formType === 'outgoing-cancel-request') {
                        errors = validateSimpleRequired($form, ['note']);
                    } else if (formType === 'outgoing-cancel-approval') {
                        const action = submitter && submitter.name === 'action' ? submitter.value :
                            'approve';
                        if ((action === 'reject' || action === 'return') && !$form.find('[name="note"]')
                            .val()) {
                            errors.note = 'Field ini tidak boleh kosong.';
                        }
                    }

                    if (Object.keys(errors).length > 0) {
                        Object.keys(errors).forEach((field) => {
                            showFieldError($form, field, errors[field]);
                        });
                        return;
                    }

                    const formData = new FormData(this);
                    if (submitter && submitter.name) {
                        formData.set(submitter.name, submitter.value);
                    } else if (formType === 'outgoing-final') {
                        formData.set('submit_action', 'upload');
                    }
                    this.__lastSubmitter = null;

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
                            const successMessage = response && typeof response.message ===
                                'string' &&
                                response.message.trim() !== '' ?
                                response.message : 'Berhasil disimpan.';
                            if (window.toast && typeof window.toast.success === 'function') {
                                window.toast.success(successMessage);
                                setTimeout(() => window.location.reload(), 800);
                                return;
                            }
                            alert(successMessage);
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
