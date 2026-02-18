@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.outgoing.index') }}
@endsection

@section('content')
    @php
        $user = auth()->user();
        $status = $outgoingLetter->status;
        $isAdmin = $user?->hasRole('administrator');
        $isChecker = $user?->hasRole('checker');
        $isApprover = $user?->hasRole('approver');
        $canEdit =
            in_array($status, ['draft', 'returned'], true) &&
            ($isAdmin || ($user && (int) $outgoingLetter->requester_directorate_id === (int) $user->directorate_id));
        $isRequesterDirectorate =
            $user && (int) $outgoingLetter->requester_directorate_id === (int) $user->directorate_id;
        $requesterRoleNames =
            $user?->roles?->pluck('name')->map(function ($name) {
                return \Illuminate\Support\Str::lower((string) $name);
            }) ?? collect();
        $corpSecretaryCode = config('corsec.eo_corp_affair_directorate_code', '');
        $corpDirectorateName = \Illuminate\Support\Str::lower((string) ($user?->directorate?->name ?? ''));
        $isCorpSecretaryDirectorate =
            $user &&
            (($corpSecretaryCode !== '' && $user->directorate?->code === $corpSecretaryCode) ||
                ($corpDirectorateName !== '' &&
                    \Illuminate\Support\Str::contains($corpDirectorateName, 'corporate secretary')));
        $complianceCode = config('corsec.compliance_directorate_code', '');
        $complianceDirectorateName = \Illuminate\Support\Str::lower((string) ($user?->directorate?->name ?? ''));
        $isComplianceDirectorate =
            $user &&
            (($complianceCode !== '' && $user->directorate?->code === $complianceCode) ||
                ($complianceDirectorateName !== '' &&
                    (\Illuminate\Support\Str::contains($complianceDirectorateName, 'kepatuhan') ||
                        \Illuminate\Support\Str::contains($complianceDirectorateName, 'compliance'))));

        $checkerApprovedDir =
            $approvals
                ->where('status', 'approved')
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Direktorat Approved');
                })
                ->count() > 0;
        $checkerApprovedCompliance =
            $approvals
                ->where('status', 'approved')
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Kepatuhan Approved');
                })
                ->count() > 0;

        $userHasDirCheckerAction =
            $user &&
            $approvals
                ->where('acted_by', $user->id)
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Direktorat Approved') ||
                        \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Direktorat Returned');
                })
                ->count() > 0;
        $userHasDirApproverAction =
            $user &&
            $approvals
                ->where('acted_by', $user->id)
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'DD Direktorat Approved') ||
                        \Illuminate\Support\Str::startsWith((string) $approval->note, 'DD Direktorat Returned');
                })
                ->count() > 0;
        $userHasComplianceCheckerAction =
            $user &&
            $approvals
                ->where('acted_by', $user->id)
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Kepatuhan Approved') ||
                        \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Kepatuhan Returned');
                })
                ->count() > 0;
        $userHasComplianceApproverAction =
            $user &&
            $approvals
                ->where('acted_by', $user->id)
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'DD Kepatuhan Approved') ||
                        \Illuminate\Support\Str::startsWith((string) $approval->note, 'DD Kepatuhan Returned');
                })
                ->count() > 0;

        $canDirCheckerApproval =
            $status === 'waiting_dir_approval' &&
            !$checkerApprovedDir &&
            ($isAdmin || ($isRequesterDirectorate && $isChecker)) &&
            !$userHasDirCheckerAction;
        $canDirApproverApproval =
            $status === 'waiting_dir_approval' &&
            $checkerApprovedDir &&
            ($isAdmin || ($isRequesterDirectorate && $isApprover)) &&
            !$userHasDirApproverAction;

        $corpPositionName = \Illuminate\Support\Str::lower((string) ($user?->position?->name ?? ''));
        $isCorpSecretaryChecker =
            $isCorpSecretaryDirectorate &&
            $requesterRoleNames->contains(function ($name) {
                return \Illuminate\Support\Str::contains($name, 'checker');
            });

        $isComplianceMakerStaff =
            $isComplianceDirectorate &&
            $requesterRoleNames->contains(function ($name) {
                return \Illuminate\Support\Str::contains($name, 'maker');
            }) &&
            $corpPositionName !== '' &&
            \Illuminate\Support\Str::contains($corpPositionName, 'staff');
        $canComplianceReview = $status === 'compliance_review' && ($isAdmin || $isComplianceMakerStaff);

        $canComplianceCheckerApproval =
            $status === 'waiting_compliance_approval' &&
            !$checkerApprovedCompliance &&
            ($isAdmin || ($isComplianceDirectorate && $isChecker)) &&
            !$userHasComplianceCheckerAction;
        $canComplianceApproverApproval =
            $status === 'waiting_compliance_approval' &&
            $checkerApprovedCompliance &&
            ($isAdmin || ($isComplianceDirectorate && $isApprover)) &&
            !$userHasComplianceApproverAction;

        $isRequesterDirectorateMakerStaff =
            $user &&
            (int) $outgoingLetter->requester_directorate_id === (int) $user->directorate_id &&
            $requesterRoleNames->contains(function ($name) {
                return \Illuminate\Support\Str::contains($name, 'maker');
            }) &&
            $corpPositionName !== '' &&
            \Illuminate\Support\Str::contains($corpPositionName, 'staff');
        $canFinalUpload = $status === 'waiting_final_upload' && ($isAdmin || $isRequesterDirectorateMakerStaff);
        $canVerify = $status === 'waiting_verification' && ($isAdmin || $isCorpSecretaryChecker);

        $statusSteps = [
            'draft' => 'Draft',
            'waiting_dir_approval' => 'Approval EO dan DD Direktorat',
            'compliance_review' => 'Review Kepatuhan',
            'waiting_compliance_approval' => 'Approval EO dan DD Kepatuhan',
            'waiting_verification' => 'Verifikasi EO Corp Affair',
            'waiting_final_upload' => 'Final Upload',
            'verified' => 'Done',
            'returned' => 'Revisi',
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

        @can('corsec.update')
            @if ($canComplianceReview)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Review Direktorat Kepatuhan</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('letter.outgoing.compliance.review', $outgoingLetter) }}"
                            enctype="multipart/form-data" class="grid gap-4 js-ajax-form" data-form-type="outgoing-compliance">
                            @csrf
                            <div class="flex flex-col">
                                <label class="form-label">File Review Kepatuhan <span class="text-danger">*</span></label>
                                <input class="file-input" type="file" name="compliance_file" accept=".pdf,.jpg,.jpeg,.png">
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
        @endcan

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
                            <label class="form-label">Final Surat <span class="text-danger">*</span></label>
                            <input class="file-input" type="file" name="final_file" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="flex justify-end">
                            <button class="btn btn-primary" type="submit">Upload Final</button>
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
                <h3 class="card-title">Riwayat Catatan</h3>
            </div>
            <div class="card-body">
                @php
                    $comments = $outgoingLetter->comments->sortByDesc('created_at')->values();
                @endphp

                @if ($comments->count() > 0)
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
                                @foreach ($comments as $comment)
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
                    @if ($status === 'waiting_dir_approval' && ($canDirCheckerApproval || $canDirApproverApproval))
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

                    if (formType === 'outgoing-final') {
                        errors = validateSimpleRequired($form, ['final_file']);
                    } else if (formType === 'outgoing-compliance') {
                        errors = validateSimpleRequired($form, ['compliance_file']);
                    }

                    if (Object.keys(errors).length > 0) {
                        Object.keys(errors).forEach((field) => {
                            showFieldError($form, field, errors[field]);
                        });
                        return;
                    }

                    const formData = new FormData(this);
                    const nativeEvent = event.originalEvent || {};
                    const submitter = nativeEvent.submitter || this.__lastSubmitter || document
                        .activeElement;
                    if (submitter && submitter.name) {
                        formData.set(submitter.name, submitter.value);
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
