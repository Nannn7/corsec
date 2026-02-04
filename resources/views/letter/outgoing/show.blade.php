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

        $checkerApproved =
            $approvals
                ->where('status', 'approved')
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Direktorat Approved');
                })
                ->count() > 0;
        $canCheckerDirApproval = $status === 'waiting_dir_approval' && !$checkerApproved && ($isAdmin || $isChecker);
        $canApproverApproval = $status === 'waiting_dir_approval' && $checkerApproved && ($isAdmin || $isApprover);

        $checkerComplianceApproved =
            $approvals
                ->where('status', 'approved')
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Kepatuhan Approved');
                })
                ->count() > 0;
        $corpSecretaryCode = config('corsec.eo_corp_affair_directorate_code', '');
        $corpDirectorateName = \Illuminate\Support\Str::lower((string) ($user?->directorate?->name ?? ''));
        $isCorpSecretaryDirectorate =
            $user &&
            (($corpSecretaryCode !== '' && $user->directorate?->code === $corpSecretaryCode) ||
                ($corpDirectorateName !== '' &&
                    \Illuminate\Support\Str::contains($corpDirectorateName, 'corporate secretary')));
        $corpRoleNames = $user?->roles?->pluck('name')->map(function ($name) {
            return \Illuminate\Support\Str::lower((string) $name);
        }) ?? collect();
        $corpPositionName = \Illuminate\Support\Str::lower((string) ($user?->position?->name ?? ''));
        $isCorpSecretaryStaffPosition =
            $corpPositionName !== '' && \Illuminate\Support\Str::contains($corpPositionName, 'staff');
        $isCorpSecretaryMaker =
            $isCorpSecretaryDirectorate &&
            $corpRoleNames->contains(function ($name) {
                return \Illuminate\Support\Str::contains($name, 'maker');
            });
        $isCorpSecretaryApprover =
            $isCorpSecretaryDirectorate &&
            $corpRoleNames->contains(function ($name) {
                return \Illuminate\Support\Str::contains($name, 'approver');
            });
        $isCorpSecretaryChecker =
            $isCorpSecretaryDirectorate &&
            $corpRoleNames->contains(function ($name) {
                return \Illuminate\Support\Str::contains($name, 'checker');
            });
        $isCorpSecretaryMakerStaff = $isCorpSecretaryMaker && $isCorpSecretaryStaffPosition;
        $complianceDirectorateCode = config('corsec.compliance_directorate_code', '');
        $directorateName = \Illuminate\Support\Str::lower((string) ($user?->directorate?->name ?? ''));
        $isComplianceDirectorate =
            $user &&
            (($complianceDirectorateCode !== '' && $user->directorate?->code === $complianceDirectorateCode) ||
                ($directorateName !== '' &&
                    (\Illuminate\Support\Str::contains($directorateName, 'compliance') ||
                        \Illuminate\Support\Str::contains($directorateName, 'kepatuhan'))));
        $positionName = \Illuminate\Support\Str::lower((string) ($user?->position?->name ?? ''));
        $isComplianceStaff = $isComplianceDirectorate && $positionName !== '' && \Illuminate\Support\Str::contains($positionName, 'staff');
        $canComplianceCheckerApproval =
            $status === 'waiting_compliance_approval' &&
            !$checkerComplianceApproved &&
            ($isAdmin || ($isComplianceDirectorate && $isChecker));
        $canComplianceApproverApproval =
            $status === 'waiting_compliance_approval' &&
            $checkerComplianceApproved &&
            ($isAdmin || ($isComplianceDirectorate && $isApprover));

        $canComplianceReview = $status === 'compliance_review' && ($isAdmin || $isComplianceStaff);
        $canNumbering = $status === 'numbering' && $isCorpSecretaryMakerStaff;
        $canFinalUpload = $status === 'final_uploaded' && $isCorpSecretaryMakerStaff;

        $corpSecretaryCheckerApproved =
            $approvals
                ->where('status', 'approved')
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Corp Affair Approved');
                })
                ->count() > 0;
        $canCorpSecretaryCheckerVerify = $status === 'waiting_verification' && !$corpSecretaryCheckerApproved && $isCorpSecretaryChecker;
        $canCorpSecretaryApproverVerify = $status === 'waiting_verification' && $corpSecretaryCheckerApproved && $isCorpSecretaryApprover;
        $canVerify = $canCorpSecretaryCheckerVerify || $canCorpSecretaryApproverVerify;

        $statusSteps = [
            'draft' => 'Draft',
            'waiting_dir_approval' => 'Waiting Dir Approval',
            'compliance_review' => 'Compliance Review',
            'waiting_compliance_approval' => 'Waiting Compliance Approval',
            'numbering' => 'Numbering',
            'waiting_verification' => 'Waiting Verification',
            'final_uploaded' => 'Final Uploaded',
            'verified' => 'Verified',
            'returned' => 'Returned',
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
                        <span class="text-gray-600">Perihal:</span>
                        <span class="font-medium">{{ $outgoingLetter->subject ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Ringkasan:</span>
                        <span class="font-medium">{{ $outgoingLetter->summary ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Sirkulasi Kepatuhan:</span>
                        <span class="font-medium">{{ $outgoingLetter->need_compliance_review ? 'Y' : 'N' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Status:</span>
                        <span class="badge badge-light">{{ $outgoingLetter->status ?? '-' }}</span>
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
                        <span class="text-gray-600">Draft Kepatuhan:</span>
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
                        <span class="badge {{ $status === $key ? 'badge-success' : 'badge-light' }}">{{ $label }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($status === 'compliance_review' && $canComplianceReview)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Review Kepatuhan</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('letter.outgoing.compliance.review', $outgoingLetter) }}"
                        enctype="multipart/form-data" class="grid gap-4 js-ajax-form" data-form-type="outgoing-compliance">
                        @csrf
                        <div class="flex flex-col">
                            <label class="form-label">Upload Draft Review <span class="text-danger">*</span></label>
                            <input class="file-input" type="file" name="compliance_draft" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="flex flex-col">
                            <label class="form-label">Catatan</label>
                            <textarea class="textarea w-full" name="note" rows="3" placeholder="Catatan review..."></textarea>
                        </div>
                        <div class="flex flex-wrap gap-2 justify-end">
                            <button class="btn btn-danger" type="submit" name="action" value="reject">Reject</button>
                            <button class="btn btn-primary" type="submit" name="action" value="submit">Submit Review</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if ($status === 'numbering' && $canNumbering)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Input Nomor Surat</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('letter.outgoing.numbering', $outgoingLetter) }}"
                        class="grid gap-4 js-ajax-form" data-form-type="outgoing-numbering">
                        @csrf
                        <div class="flex flex-col">
                            <label class="form-label">Nomor Surat <span class="text-danger">*</span></label>
                            <input class="input" type="text" name="letter_no" placeholder="Nomor surat...">
                        </div>
                        <div class="flex flex-col">
                            <label class="form-label">Catatan</label>
                            <textarea class="textarea w-full" name="note" rows="2" placeholder="Catatan..."></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button class="btn btn-primary" type="submit">Simpan & Kirim</button>
                        </div>
                    </form>
                </div>
            </div>
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
                                                <span class="text-gray-500 text-xs">({{ $approval->actor->directorate->name }})</span>
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

        @can('corsec.authorize')
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Approval</h3>
                </div>
                <div class="card-body">
                    @if ($status === 'waiting_dir_approval' && ($canCheckerDirApproval || $canApproverApproval))
                        <form method="POST" action="{{ route('letter.outgoing.approval.action', $outgoingLetter) }}"
                            class="grid gap-4 js-ajax-form" data-form-type="outgoing-approval">
                            @csrf
                            <div class="text-sm text-gray-500">
                                {{ $canCheckerDirApproval ? 'Approval EO Direktorat' : 'Approval DD Direktorat' }}
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan..."></textarea>
                            </div>
                            <div class="flex flex-wrap gap-2 justify-end">
                                <button class="btn btn-sm btn-danger" type="submit" name="action" value="reject">Reject</button>
                                <button class="btn btn-sm btn-success" type="submit" name="action" value="approve">Approve</button>
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
                                <button class="btn btn-sm btn-danger" type="submit" name="action" value="reject">Reject</button>
                                <button class="btn btn-sm btn-success" type="submit" name="action" value="approve">Approve</button>
                            </div>
                        </form>
                    @elseif ($canVerify)
                        <form method="POST" action="{{ route('letter.outgoing.verify.action', $outgoingLetter) }}"
                            class="grid gap-4 js-ajax-form" data-form-type="outgoing-verify">
                            @csrf
                            <div class="text-sm text-gray-500">
                                {{ $canCorpSecretaryCheckerVerify ? 'Approval EO Corporate Secretary' : 'Approval DD Corporate Secretary' }}
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan..."></textarea>
                            </div>
                            <div class="flex flex-wrap gap-2 justify-end">
                                <button class="btn btn-sm btn-danger" type="submit" name="action" value="reject">Reject</button>
                                <button class="btn btn-sm btn-success" type="submit" name="action" value="verify">Verify</button>
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

                $document.on('submit', 'form.js-ajax-form', function(event) {
                    event.preventDefault();
                    const $form = window.jQuery(this);
                    clearValidation($form);

                    let errors = {};
                    const formType = $form.data('formType');

                    if (formType === 'outgoing-compliance') {
                        const submitter = document.activeElement;
                        const isReject =
                            submitter &&
                            submitter.getAttribute('name') === 'action' &&
                            submitter.value === 'reject';
                        errors = isReject
                            ? validateSimpleRequired($form, ['note'])
                            : validateSimpleRequired($form, ['compliance_draft']);
                    }
                    if (formType === 'outgoing-numbering') {
                        errors = validateSimpleRequired($form, ['letter_no']);
                    }
                    if (formType === 'outgoing-final') {
                        errors = validateSimpleRequired($form, ['final_file']);
                    }

                    if (Object.keys(errors).length > 0) {
                        Object.keys(errors).forEach((field) => {
                            showFieldError($form, field, errors[field]);
                        });
                        return;
                    }

                    const formData = new FormData(this);
                    const submitter = document.activeElement;
                    if (submitter && submitter.name) {
                        formData.set(submitter.name, submitter.value);
                    }

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
                            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                                const serverErrors = xhr.responseJSON.errors;
                                Object.keys(serverErrors).forEach((field) => {
                                    showFieldError($form, field, serverErrors[field][0]);
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
