@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.incoming.show', $incomingLetter) }}
@endsection

@section('content')
    @php
        $permissionFlags = $permissionFlags ?? [];
        $status = $incomingLetter->status;
        $canViewerNote = (bool) ($permissionFlags['can_viewer_note'] ?? false);
        $canDirectorateUpdate = (bool) ($permissionFlags['can_directorate_update'] ?? false);
        $canCheckerDirApproval = (bool) ($permissionFlags['can_checker_dir_approval'] ?? false);
        $canApproverApproval = (bool) ($permissionFlags['can_approver_approval'] ?? false);
        $canCheckerApproval = (bool) ($permissionFlags['can_checker_approval'] ?? false);
        $canCorsecValidation = (bool) ($permissionFlags['can_corsec_validation'] ?? false);
        $canAddMonitoring = (bool) ($permissionFlags['can_add_monitoring'] ?? false);
        $canEdit = (bool) ($permissionFlags['can_edit'] ?? false);
        $validationRequested = (bool) $incomingLetter->corp_secretary_validation_requested_at;
        $validationOverdue = $incomingLetter->isCorpSecretaryValidationOverdue();
        $validationCompletedLate = $incomingLetter->isCorpSecretaryValidatedLate();
        $sortedComments = $sortedComments ?? collect();
        $statusSteps = [
            'draft' => 'Draft',
            'dispatched' => 'Dispatched',
            'in_progress' => 'In Progress',
            'waiting_dir_approval' => 'Waiting Dir Approval',
            'waiting_response_letter' => 'Waiting Response Letter',
            'waiting_verification' => 'Waiting Validation',
            'verified' => 'Verified',
            'returned' => 'Returned',
            'rejected' => 'Rejected',
        ];
        $incomingFiles = $incomingLetter->attachables?->where('category', 'incoming')->values() ?? collect();
        $evidenceFiles = $incomingLetter->attachables?->where('category', 'evidence')->values() ?? collect();
        $socialMaterialFiles =
            $incomingLetter->attachables?->where('category', 'social_material')->values() ?? collect();
        $lainnyaFiles = $incomingLetter->attachables?->where('category', 'lainnya_evidence')->values() ?? collect();
        $directorateMap = $directorates?->keyBy('id') ?? collect();
        $responseOutgoingLetter = $responseOutgoingLetter ?? null;
        $canCreateOutgoingFromIncoming = (bool) ($permissionFlags['can_create_outgoing_from_incoming'] ?? false);
        $responseLetterProgressBadgeLabel = null;
        $responseLetterProgressBadgeClass = 'badge-light';
        if ($incomingLetter->followup_action === 'response_letter') {
            if ($responseOutgoingLetter) {
                $outgoingStatus = (string) ($responseOutgoingLetter->status ?? '');
                if ($outgoingStatus === 'verified') {
                    $responseLetterProgressBadgeLabel = 'Selesai via Surat Keluar';
                    $responseLetterProgressBadgeClass = 'badge-success';
                } elseif ($outgoingStatus === 'returned') {
                    $responseLetterProgressBadgeLabel = 'Perlu Revisi Surat Jawaban';
                    $responseLetterProgressBadgeClass = 'badge-danger';
                } else {
                    $responseLetterProgressBadgeLabel =
                        'Diproses di Surat Keluar' .
                        ($responseOutgoingLetter->display_status_label
                            ? ' (' . $responseOutgoingLetter->display_status_label . ')'
                            : '');
                    $responseLetterProgressBadgeClass = 'badge-info';
                }
            } elseif ($status === 'waiting_response_letter') {
                $responseLetterProgressBadgeLabel = 'Menunggu Pembuatan Surat Jawaban';
                $responseLetterProgressBadgeClass = 'badge-warning';
            } elseif ($status === 'verified') {
                $responseLetterProgressBadgeLabel = 'Selesai';
                $responseLetterProgressBadgeClass = 'badge-success';
            } else {
                $responseLetterProgressBadgeLabel = 'Belum Dimulai';
                $responseLetterProgressBadgeClass = 'badge-light';
            }
        }
    @endphp
    <div class="grid gap-5 lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detail Surat Masuk {{ $incomingLetter->external_letter_no }}</h3>
                <div class="flex gap-2">
                    <a href="{{ route('letter.incoming.index') }}" class="btn btn-sm btn-light">
                        <i class="ki-filled ki-arrow-left"></i> Kembali
                    </a>
                    @if ($canEdit)
                        <a href="{{ route('letter.incoming.edit', $incomingLetter) }}" class="btn btn-sm btn-info">
                            <i class="ki-filled ki-notepad-edit"></i> Edit Surat
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-2 lg:gap-7.5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informasi Surat</h3>
                </div>
                <div class="card-body">
                    <div class="grid gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">No Registrasi:</span>
                            <span class="font-medium">{{ $incomingLetter->registration_no ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Nomor Surat:</span>
                            <span class="font-medium">{{ $incomingLetter->external_letter_no ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Tanggal Surat:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->letter_date ? $incomingLetter->letter_date->format('Y-m-d') : '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Tanggal Terima:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->received_date ? $incomingLetter->received_date->format('Y-m-d') : '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Perihal:</span>
                            <span class="font-medium">{{ $incomingLetter->subject ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Ringkasan:</span>
                            <span class="font-medium">{{ $incomingLetter->summary ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informasi Pengirim & Status</h3>
                </div>
                <div class="card-body">
                    <div class="grid gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Pengirim:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->sender?->name ?? ($incomingLetter->sender_other ?? ($incomingLetter->getAttribute('sender') ?? '-')) }}
                            </span>
                        </div>
                        @if ($incomingLetter->customerBranch)
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Cabang Nasabah/Debitur:</span>
                                <span class="font-medium">
                                    {{ $incomingLetter->customerBranch->code }} -
                                    {{ $incomingLetter->customerBranch->name }}
                                </span>
                            </div>
                        @endif
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Jenis Surat:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->letter_type_other ?? ($incomingLetter->letterType?->name ?? '-') }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Sirkulasi:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->circulationDirectorates?->pluck('name')->implode(', ') ?: '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Leader:</span>
                            <span class="font-medium">{{ $incomingLetter->targetDirectorate?->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Due Date:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->target_date ? $incomingLetter->target_date->format('Y-m-d') : '-' }}
                            </span>
                        </div>
                        @if ($incomingLetter->register_due_date)
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Due Date Register:</span>
                                <span class="font-medium">
                                    {{ $incomingLetter->register_due_date->format('Y-m-d') }}
                                </span>
                            </div>
                        @endif
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Status:</span>
                            <div class="flex items-center gap-2">
                                <span class="badge badge-light">
                                    {{ $statusSteps[$incomingLetter->status] ?? ($incomingLetter->status ?? '-') }}
                                </span>
                                @if ($validationOverdue)
                                    <span class="badge badge-danger">Belum divalidasi EO Corp Secretary</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Validasi EO Corp Secretary:</span>
                            <span class="font-medium">
                                @if ($incomingLetter->corp_secretary_validated_at && $validationCompletedLate)
                                    <span class="badge badge-warning">Terlambat divalidasi</span>
                                @elseif ($incomingLetter->corp_secretary_validated_at)
                                    <span class="badge badge-success">Sudah divalidasi</span>
                                @elseif ($validationRequested)
                                    <span class="badge {{ $validationOverdue ? 'badge-danger' : 'badge-warning' }}">
                                        {{ $validationOverdue ? 'Terlambat divalidasi' : 'Belum validasi' }}
                                    </span>
                                @else
                                    -
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($canAddMonitoring)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Tambah Monitoring Direktorat</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('letter.incoming.monitoring.add', $incomingLetter) }}"
                        class="grid gap-4 js-ajax-form" data-form-type="monitoring">
                        @csrf
                        <div class="flex flex-col">
                            <label class="form-label">Direktorat Monitoring <span class="text-danger">*</span></label>
                            <select
                                class="select @error('monitoring_directorate_id') border-danger bg-danger-light @enderror"
                                name="monitoring_directorate_id" required>
                                <option value="">- Pilih Direktorat -</option>
                                @foreach ($directorates as $directorate)
                                    @if ((int) $directorate->id !== (int) $incomingLetter->target_directorate_id)
                                        <option value="{{ $directorate->id }}">{{ $directorate->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('monitoring_directorate_id')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                        <div class="flex flex-col">
                            <label class="form-label">Catatan (opsional)</label>
                            <textarea class="textarea w-full @error('monitoring_note') border-danger bg-danger-light @enderror"
                                name="monitoring_note" rows="3" placeholder="Catatan tambahan...">{{ old('monitoring_note') }}</textarea>
                            @error('monitoring_note')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                        <div class="flex justify-end">
                            <button class="btn btn-primary" type="submit">
                                <i class="ki-filled ki-check"></i> Tambah Monitoring
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Rencana Tindak Lanjut</h3>
            </div>
            <div class="card-body">
                <div class="grid gap-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Action/Status:</span>
                        <div class="flex flex-wrap gap-2 justify-end">
                            @foreach ($statusSteps as $key => $label)
                                @php
                                    $isActive = $status === $key;
                                    $badgeClass = $isActive ? 'badge-success' : 'badge-light';
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ $label }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Upload Surat Masuk:</span>
                        <div class="text-right">
                            @if ($incomingFiles->count() > 0)
                                <div class="flex flex-col gap-1">
                                    @foreach ($incomingFiles as $incomingFile)
                                        @php
                                            $attachment = $incomingFile->attachment;
                                        @endphp
                                        @if ($attachment)
                                            <a class="text-primary hover:underline"
                                                href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path) }}"
                                                target="_blank" rel="noopener">
                                                {{ $attachment->original_name ?? $attachment->file_name }}
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <span class="text-gray-500 text-sm">Belum ada upload.</span>
                            @endif
                        </div>
                    </div>
                    @if ($evidenceFiles->count() > 0)
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Upload Hasil Tindak Lanjut:</span>
                            <div class="text-right">
                                <div class="flex flex-col gap-1">
                                    @foreach ($evidenceFiles as $evidence)
                                        @php
                                            $attachment = $evidence->attachment;
                                        @endphp
                                        @if ($attachment)
                                            <a class="text-primary hover:underline"
                                                href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path) }}"
                                                target="_blank" rel="noopener">
                                                {{ $attachment->original_name ?? $attachment->file_name }}
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($incomingLetter->description)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Deskripsi</h3>
                </div>
                <div class="card-body">
                    <p class="text-gray-700">{{ $incomingLetter->description }}</p>
                </div>
            </div>
        @endif

        @if ($incomingLetter->followup_action)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Rencana Tindak Lanjut</h3>
                </div>
                <div class="card-body">
                    <div class="grid gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Jenis Tindak Lanjut:</span>
                            <span class="font-medium">
                                @php
                                    $followupLabels = [
                                        'meeting' => 'Meeting Koordinasi',
                                        'response_letter' => 'Surat Jawaban',
                                        'socialization' => 'Sosialisasi',
                                        'invitation' => 'Peserta Undangan',
                                        'review' => 'Review / New Ketentuan',
                                        'lainnya' => 'Lainnya (Ditindaklanjuti di Luar Aplikasi)',
                                    ];
                                @endphp
                                {{ $followupLabels[$incomingLetter->followup_action] ?? $incomingLetter->followup_action }}
                            </span>
                        </div>
                        @php
                            $followupDetail = $incomingLetter->followup_detail ?? [];
                        @endphp
                        @if ($incomingLetter->followup_action === 'meeting')
                            @php
                                $participantsValue = $followupDetail['participants'] ?? [];
                                $participantNames = [];
                                if (is_array($participantsValue)) {
                                    foreach ($participantsValue as $participantId) {
                                        $name = $directorateMap->get((int) $participantId)?->name;
                                        $participantNames[] = $name ?: $participantId;
                                    }
                                } elseif (!is_null($participantsValue)) {
                                    $participantNames = [trim((string) $participantsValue)];
                                }
                                $participantLabel =
                                    count($participantNames) > 0 ? implode(', ', $participantNames) : '-';
                            @endphp
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Peserta:</span>
                                <span class="font-medium">{{ $participantLabel }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Tanggal:</span>
                                <span class="font-medium">{{ $followupDetail['date'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Waktu:</span>
                                <span class="font-medium">{{ $followupDetail['time'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Tempat:</span>
                                <span class="font-medium">{{ $followupDetail['location'] ?? '-' }}</span>
                            </div>
                        @elseif ($incomingLetter->followup_action === 'response_letter')
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Target Jawaban:</span>
                                <span class="font-medium">{{ $followupDetail['target_date'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Surat Jawaban:</span>
                                <span class="font-medium">
                                    @if ($responseOutgoingLetter)
                                        <a class="text-primary hover:underline"
                                            href="{{ route('letter.outgoing.show', $responseOutgoingLetter) }}">
                                            {{ $responseOutgoingLetter->registration_no ?? 'ID #' . $responseOutgoingLetter->id }}
                                        </a>
                                    @else
                                        <span class="text-warning">Belum dibuat</span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Status Surat Jawaban:</span>
                                <span class="font-medium">
                                    {{ $responseOutgoingLetter?->display_status_label ?? '-' }}
                                </span>
                            </div>
                            @if ($responseLetterProgressBadgeLabel)
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Progress Surat Jawaban:</span>
                                    <span class="badge {{ $responseLetterProgressBadgeClass }}">
                                        {{ $responseLetterProgressBadgeLabel }}
                                    </span>
                                </div>
                            @endif
                            @if ($responseOutgoingLetter?->finalAttachment)
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Final Surat Jawaban:</span>
                                    <span class="font-medium">
                                        <a class="text-primary hover:underline"
                                            href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($responseOutgoingLetter->finalAttachment->path) }}"
                                            target="_blank" rel="noopener">
                                            {{ $responseOutgoingLetter->finalAttachment->original_name ?? $responseOutgoingLetter->finalAttachment->file_name }}
                                        </a>
                                    </span>
                                </div>
                            @endif
                            @if ($canCreateOutgoingFromIncoming)
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Aksi:</span>
                                    <span class="font-medium">
                                        <a class="btn btn-sm btn-primary"
                                            href="{{ route('letter.outgoing.create', ['incoming_letter_id' => $incomingLetter->id]) }}">
                                            Buat Surat Jawaban
                                        </a>
                                    </span>
                                </div>
                            @endif
                        @elseif ($incomingLetter->followup_action === 'socialization')
                            @php
                                $participantsValue = $followupDetail['participants'] ?? [];
                                $participantNames = [];
                                if (is_array($participantsValue)) {
                                    foreach ($participantsValue as $participantId) {
                                        $name = $directorateMap->get((int) $participantId)?->name;
                                        $participantNames[] = $name ?: $participantId;
                                    }
                                } elseif (!is_null($participantsValue)) {
                                    $participantNames = [trim((string) $participantsValue)];
                                }
                                $participantLabel =
                                    count($participantNames) > 0 ? implode(', ', $participantNames) : '-';
                                $coordinatedValue = $followupDetail['coordinated_directorate'] ?? [];
                                $coordinatedNames = [];
                                if (is_array($coordinatedValue)) {
                                    foreach ($coordinatedValue as $coordinatedId) {
                                        $name = $directorateMap->get((int) $coordinatedId)?->name;
                                        $coordinatedNames[] = $name ?: $coordinatedId;
                                    }
                                } elseif (!is_null($coordinatedValue)) {
                                    $coordinatedNames = [trim((string) $coordinatedValue)];
                                }
                                $coordinatedLabel =
                                    count($coordinatedNames) > 0 ? implode(', ', $coordinatedNames) : '-';
                            @endphp
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Peserta:</span>
                                <span class="font-medium">{{ $participantLabel }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Tanggal:</span>
                                <span class="font-medium">{{ $followupDetail['date'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Tempat:</span>
                                <span class="font-medium">{{ $followupDetail['location'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Bahan:</span>
                                <span class="font-medium">
                                    @if ($socialMaterialFiles->count() > 0)
                                        <span class="flex flex-col gap-1 items-end">
                                            @foreach ($socialMaterialFiles as $material)
                                                @php
                                                    $attachment = $material->attachment;
                                                @endphp
                                                @if ($attachment)
                                                    <a class="text-primary hover:underline"
                                                        href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path) }}"
                                                        target="_blank" rel="noopener">
                                                        {{ $attachment->original_name ?? $attachment->file_name }}
                                                    </a>
                                                @endif
                                            @endforeach
                                        </span>
                                    @else
                                        {{ $followupDetail['material'] ?? '-' }}
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Koordinasi Direktorat:</span>
                                <span class="font-medium">{{ $coordinatedLabel }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Catatan:</span>
                                <span class="font-medium">{{ $followupDetail['note'] ?? '-' }}</span>
                            </div>
                        @elseif ($incomingLetter->followup_action === 'invitation')
                            @php
                                $invitationParticipants = $followupDetail['participants'] ?? [];
                                if (!is_array($invitationParticipants) || $invitationParticipants === []) {
                                    $invitationParticipants = [
                                        [
                                            'nik' => $followupDetail['nik'] ?? null,
                                            'name' => $followupDetail['name'] ?? null,
                                            'directorate' => $followupDetail['directorate'] ?? null,
                                            'position' => $followupDetail['position'] ?? null,
                                            'registration_status' => $followupDetail['registration'] ?? null,
                                            'pic_name' => null,
                                            'pic_contact' => null,
                                            'registration_deadline' => null,
                                            'note' => $followupDetail['note'] ?? null,
                                        ],
                                    ];
                                }
                            @endphp
                            <div class="flex flex-col gap-4">
                                @foreach ($invitationParticipants as $participantIndex => $participant)
                                    @php
                                        $invitationDirectorateValue = $participant['directorate'] ?? null;
                                        $invitationDirectorateLabel = '-';
                                        if (
                                            !is_null($invitationDirectorateValue) &&
                                            $invitationDirectorateValue !== ''
                                        ) {
                                            $invitationDirectorateLabel = $invitationDirectorateValue;
                                            if (is_numeric($invitationDirectorateValue)) {
                                                $invitationDirectorateLabel =
                                                    $directorateMap->get((int) $invitationDirectorateValue)?->name ??
                                                    $invitationDirectorateValue;
                                            }
                                        }
                                    @endphp
                                    <div class="rounded border border-gray-200 p-4">
                                        <div class="mb-3 font-semibold text-gray-800">Peserta {{ $participantIndex + 1 }}
                                        </div>
                                        <div class="grid gap-3 lg:grid-cols-2">
                                            <div class="flex justify-between items-center">
                                                <span class="text-gray-600">NIK:</span>
                                                <span class="font-medium">{{ $participant['nik'] ?? '-' }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-gray-600">Nama:</span>
                                                <span class="font-medium">{{ $participant['name'] ?? '-' }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-gray-600">Direktorat:</span>
                                                <span class="font-medium">{{ $invitationDirectorateLabel }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-gray-600">Jabatan:</span>
                                                <span class="font-medium">{{ $participant['position'] ?? '-' }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-gray-600">Status Pendaftaran:</span>
                                                <span class="font-medium">
                                                    {{ ($participant['registration_status'] ?? '') === 'sudah' ? 'Sudah' : (($participant['registration_status'] ?? '') === 'belum' ? 'Belum' : '-') }}
                                                </span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-gray-600">Nama PIC:</span>
                                                <span class="font-medium">{{ $participant['pic_name'] ?? '-' }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-gray-600">Contact PIC:</span>
                                                <span class="font-medium">{{ $participant['pic_contact'] ?? '-' }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-gray-600">Deadline Pendaftaran:</span>
                                                <span
                                                    class="font-medium">{{ $participant['registration_deadline'] ?? '-' }}</span>
                                            </div>
                                            <div class="flex justify-between items-start gap-3 lg:col-span-2">
                                                <span class="text-gray-600 shrink-0">Catatan:</span>
                                                <span
                                                    class="font-medium text-right">{{ $participant['note'] ?? '-' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @elseif ($incomingLetter->followup_action === 'review')
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Nomor Peraturan:</span>
                                <span class="font-medium">{{ $followupDetail['regulation_number'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Judul Peraturan:</span>
                                <span class="font-medium">{{ $followupDetail['regulation_title'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Tanggal Upload:</span>
                                <span class="font-medium">{{ $followupDetail['upload_date'] ?? '-' }}</span>
                            </div>
                            @if (!empty($followupDetail['target_date']))
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Target Update SisDur:</span>
                                    <span class="font-medium">{{ $followupDetail['target_date'] }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Catatan:</span>
                                <span class="font-medium">{{ $followupDetail['note'] ?? '-' }}</span>
                            </div>
                        @elseif ($incomingLetter->followup_action === 'lainnya')
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Tanggal Tindak Lanjut:</span>
                                <span class="font-medium">{{ $followupDetail['date'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Catatan:</span>
                                <span class="font-medium">{{ $followupDetail['note'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Upload Surat:</span>
                                <span class="font-medium">
                                    @if ($lainnyaFiles->count() > 0)
                                        <span class="flex flex-col gap-1 items-end">
                                            @foreach ($lainnyaFiles as $lainnyaFile)
                                                @php
                                                    $lainnyaAttachment = $lainnyaFile->attachment;
                                                @endphp
                                                @if ($lainnyaAttachment)
                                                    <a class="text-primary hover:underline"
                                                        href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($lainnyaAttachment->path) }}"
                                                        target="_blank" rel="noopener">
                                                        {{ $lainnyaAttachment->original_name ?? $lainnyaAttachment->file_name }}
                                                    </a>
                                                @endif
                                            @endforeach
                                        </span>
                                    @else
                                        {{ $followupDetail['file'] ?? '-' }}
                                    @endif
                                </span>
                            </div>
                        @endif
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Catatan:</span>
                            <span class="font-medium">{{ $incomingLetter->followup_note ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($validationRequested || $incomingLetter->corp_secretary_validated_at)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Corporate Secretary</h3>
                </div>
                <div class="card-body">
                    <div class="grid gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Requested At:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->corp_secretary_validation_requested_at ? $incomingLetter->corp_secretary_validation_requested_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') : '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Status:</span>
                            <span class="font-medium">
                                @if ($incomingLetter->corp_secretary_validated_at && $validationCompletedLate)
                                    <span class="badge badge-warning">Sudah divalidasi, melewati batas waktu</span>
                                @elseif ($incomingLetter->corp_secretary_validated_at)
                                    <span class="badge badge-success">Sudah divalidasi</span>
                                @elseif ($validationOverdue)
                                    <span class="badge badge-danger">Terlambat divalidasi</span>
                                @else
                                    <span class="badge badge-warning">Belum divalidasi</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Validator:</span>
                            <span class="font-medium">{{ $incomingLetter->corpSecretaryValidatedBy?->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Validated At:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->corp_secretary_validated_at ? $incomingLetter->corp_secretary_validated_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') : '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-start gap-3">
                            <span class="text-gray-600 shrink-0">Komentar Validasi:</span>
                            <span
                                class="font-medium text-right">{{ $incomingLetter->corp_secretary_validation_comment ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($canViewerNote)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Komentar Viewer</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('letter.incoming.director.note', $incomingLetter) }}"
                        class="grid gap-4 js-ajax-form" data-form-type="incoming-viewer-note">
                        @csrf
                        <div class="flex flex-col">
                            <label class="form-label">Komentar Viewer (Direksi / Sekdir / Corporate Secretary) <span
                                    class="text-danger">*</span></label>
                            <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan komentar viewer..." required></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary">Simpan Komentar</button>
                        </div>
                    </form>
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
                                    <th class="min-w-[280px]">Komentar</th>
                                    <th class="min-w-[200px]">Oleh</th>
                                    <th class="min-w-[170px]">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sortedComments as $comment)
                                    <tr>
                                        <td>{{ $comment->body ?? '-' }}</td>
                                        <td>{{ $comment->createdBy?->name ?? '-' }}</td>
                                        <td>{{ $comment->created_at ? $comment->created_at->format('d/m/Y H:i') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-sm text-gray-500">Belum ada komentar untuk surat masuk ini.</div>
                @endif
            </div>
        </div>

        @if ($canDirectorateUpdate)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Input Tindak Lanjut Direktorat</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('letter.incoming.directorate.update', $incomingLetter) }}"
                        enctype="multipart/form-data" class="grid gap-4 js-ajax-form" data-form-type="followup"
                        id="followup-form">
                        @csrf
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="flex flex-col lg:col-span-2">
                                <label class="form-label">Tindak Lanjut <span class="text-danger">*</span></label>
                                <select class="select" name="followup_action" id="followup_action" required>
                                    <option value="">- Pilih Tindak Lanjut -</option>
                                    <option value="meeting"
                                        {{ old('followup_action', $incomingLetter->followup_action) === 'meeting' ? 'selected' : '' }}>
                                        Meeting Koordinasi</option>
                                    <option value="response_letter"
                                        {{ old('followup_action', $incomingLetter->followup_action) === 'response_letter' ? 'selected' : '' }}>
                                        Surat Jawaban</option>
                                    <option value="socialization"
                                        {{ old('followup_action', $incomingLetter->followup_action) === 'socialization' ? 'selected' : '' }}>
                                        Sosialisasi</option>
                                    <option value="invitation"
                                        {{ old('followup_action', $incomingLetter->followup_action) === 'invitation' ? 'selected' : '' }}>
                                        Peserta Undangan</option>
                                    <option value="review"
                                        {{ old('followup_action', $incomingLetter->followup_action) === 'review' ? 'selected' : '' }}>
                                        Review / New Ketentuan</option>
                                    <option value="lainnya"
                                        {{ old('followup_action', $incomingLetter->followup_action) === 'lainnya' ? 'selected' : '' }}>
                                        Lainnya</option>
                                </select>
                            </div>

                            <div class="flex flex-col followup-field hidden" data-followup="meeting"
                                data-field="followup_meeting_participants">
                                <label class="form-label">Peserta Meeting <span class="text-danger">*</span></label>
                                @php
                                    $meetingParticipants = old(
                                        'followup_meeting_participants',
                                        $incomingLetter->followup_detail['participants'] ?? [],
                                    );
                                    if (!is_array($meetingParticipants)) {
                                        $meetingParticipants = array_filter(
                                            array_map('trim', explode(',', (string) $meetingParticipants)),
                                        );
                                    }
                                    $meetingParticipantIds = array_map('strval', $meetingParticipants);
                                @endphp
                                <div class="relative">
                                    <button type="button"
                                        class="select w-full flex items-center justify-between text-left bg-white text-gray-800"
                                        style="text-align: left; justify-content: flex-start;"
                                        id="meeting-participants-dropdown">
                                        <span id="meeting-participants-selected-text"
                                            class="block truncate text-left w-full" style="text-align: left;">Pilih
                                            peserta...</span>
                                    </button>
                                    <div id="meeting-participants-options"
                                        class="absolute z-20 mt-1 left-0 right-0 max-h-64 overflow-auto bg-white border border-gray-200 rounded shadow-lg hidden"
                                        style="background-color: #ffffff; max-height: 16rem; overflow-y: auto;">
                                        <div class="p-3 space-y-2 bg-white">
                                            @foreach ($directorates ?? [] as $directorate)
                                                <label class="flex items-center gap-2">
                                                    <input type="checkbox" name="followup_meeting_participants[]"
                                                        value="{{ $directorate->id }}"
                                                        {{ in_array((string) $directorate->id, $meetingParticipantIds, true) ? 'checked' : '' }}>
                                                    <span>{{ $directorate->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col followup-field hidden" data-followup="meeting">
                                <label class="form-label">Tanggal Meeting <span class="text-danger">*</span></label>
                                <input class="input" type="date" name="followup_meeting_date"
                                    value="{{ old('followup_meeting_date', $incomingLetter->followup_detail['date'] ?? '') }}">
                            </div>
                            <div class="flex flex-col followup-field hidden" data-followup="meeting">
                                <label class="form-label">Waktu Meeting <span class="text-danger">*</span></label>
                                <input class="input" type="time" name="followup_meeting_time"
                                    value="{{ old('followup_meeting_time', $incomingLetter->followup_detail['time'] ?? '') }}">
                            </div>
                            <div class="flex flex-col followup-field hidden" data-followup="meeting">
                                <label class="form-label">Tempat Meeting <span class="text-danger">*</span></label>
                                <input class="input" type="text" name="followup_meeting_location"
                                    value="{{ old('followup_meeting_location', $incomingLetter->followup_detail['location'] ?? '') }}"
                                    placeholder="Lokasi...">
                            </div>

                            <div class="flex flex-col followup-field hidden" data-followup="response_letter">
                                <label class="form-label">Target Surat Jawaban <span class="text-danger">*</span></label>
                                <input class="input" type="date" name="followup_response_target_date"
                                    min="{{ now()->format('Y-m-d') }}"
                                    value="{{ old('followup_response_target_date', $incomingLetter->followup_detail['target_date'] ?? '') }}">
                            </div>

                            <div class="flex flex-col followup-field hidden" data-followup="socialization"
                                data-field="followup_social_participants">
                                <label class="form-label">Peserta Sosialisasi <span class="text-danger">*</span></label>
                                @php
                                    $socialParticipants = old(
                                        'followup_social_participants',
                                        $incomingLetter->followup_detail['participants'] ?? [],
                                    );
                                    if (!is_array($socialParticipants)) {
                                        $socialParticipants = array_filter(
                                            array_map('trim', explode(',', (string) $socialParticipants)),
                                        );
                                    }
                                    $socialParticipantIds = array_map('strval', $socialParticipants);
                                @endphp
                                <div class="relative">
                                    <button type="button"
                                        class="select w-full flex items-center justify-between text-left bg-white text-gray-800"
                                        style="text-align: left; justify-content: flex-start;"
                                        id="social-participants-dropdown">
                                        <span id="social-participants-selected-text"
                                            class="block truncate text-left w-full" style="text-align: left;">Pilih
                                            peserta...</span>
                                    </button>
                                    <div id="social-participants-options"
                                        class="absolute z-20 mt-1 left-0 right-0 max-h-64 overflow-auto bg-white border border-gray-200 rounded shadow-lg hidden"
                                        style="background-color: #ffffff; max-height: 16rem; overflow-y: auto;">
                                        <div class="p-3 space-y-2 bg-white">
                                            @foreach ($directorates ?? [] as $directorate)
                                                <label class="flex items-center gap-2">
                                                    <input type="checkbox" name="followup_social_participants[]"
                                                        value="{{ $directorate->id }}"
                                                        {{ in_array((string) $directorate->id, $socialParticipantIds, true) ? 'checked' : '' }}>
                                                    <span>{{ $directorate->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col followup-field hidden" data-followup="socialization">
                                <label class="form-label">Tanggal Sosialisasi <span class="text-danger">*</span></label>
                                <input class="input" type="date" name="followup_social_date"
                                    value="{{ old('followup_social_date', $incomingLetter->followup_detail['date'] ?? '') }}">
                            </div>
                            <div class="flex flex-col followup-field hidden" data-followup="socialization">
                                <label class="form-label">Tempat Sosialisasi</label>
                                <input class="input" type="text" name="followup_social_location"
                                    value="{{ old('followup_social_location', $incomingLetter->followup_detail['location'] ?? '') }}"
                                    placeholder="Lokasi...">
                            </div>
                            <div class="flex flex-col followup-field hidden" data-followup="socialization">
                                <label class="form-label">Bahan Sosialisasi</label>
                                <input class="file-input" type="file" name="followup_social_material"
                                    accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div class="flex flex-col followup-field hidden" data-followup="socialization">
                                <label class="form-label">Koordinasi Direktorat</label>
                                @php
                                    $coordinatedDirectorates = old(
                                        'followup_social_directorate',
                                        $incomingLetter->followup_detail['coordinated_directorate'] ?? [],
                                    );
                                    if (!is_array($coordinatedDirectorates)) {
                                        $coordinatedDirectorates = array_filter(
                                            array_map('trim', explode(',', (string) $coordinatedDirectorates)),
                                        );
                                    }
                                    $coordinatedDirectorateIds = array_map('strval', $coordinatedDirectorates);
                                @endphp
                                <div class="relative">
                                    <button type="button"
                                        class="select w-full flex items-center justify-between text-left bg-white text-gray-800"
                                        style="text-align: left; justify-content: flex-start;"
                                        id="social-coordination-dropdown">
                                        <span id="social-coordination-selected-text"
                                            class="block truncate text-left w-full" style="text-align: left;">Pilih
                                            direktorat...</span>
                                    </button>
                                    <div id="social-coordination-options"
                                        class="absolute z-20 mt-1 left-0 right-0 max-h-64 overflow-auto bg-white border border-gray-200 rounded shadow-lg hidden"
                                        style="background-color: #ffffff; max-height: 16rem; overflow-y: auto;">
                                        <div class="p-3 space-y-2 bg-white">
                                            @foreach ($directorates ?? [] as $directorate)
                                                <label class="flex items-center gap-2">
                                                    <input type="checkbox" name="followup_social_directorate[]"
                                                        value="{{ $directorate->id }}"
                                                        {{ in_array((string) $directorate->id, $coordinatedDirectorateIds, true) ? 'checked' : '' }}>
                                                    <span>{{ $directorate->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col followup-field hidden" data-followup="socialization">
                                <label class="form-label">Catatan Sosialisasi</label>
                                <textarea class="textarea w-full" name="followup_social_note" rows="3" placeholder="Catatan sosialisasi...">{{ old('followup_social_note', $incomingLetter->followup_detail['note'] ?? '') }}</textarea>
                            </div>

                            <div class="flex flex-col followup-field hidden lg:col-span-2" data-followup="invitation">
                                <div class="flex items-center justify-between gap-3">
                                    <label class="form-label">Peserta Undangan <span class="text-danger">*</span></label>
                                    <button class="btn btn-sm btn-success" type="button"
                                        id="add-invitation-participant">
                                        Tambah Peserta
                                    </button>
                                </div>
                                @php
                                    $defaultInvitationParticipants =
                                        $incomingLetter->followup_detail['participants'] ?? [];
                                    if (
                                        !is_array($defaultInvitationParticipants) ||
                                        $defaultInvitationParticipants === []
                                    ) {
                                        $defaultInvitationParticipants = [
                                            [
                                                'nik' => $incomingLetter->followup_detail['nik'] ?? '',
                                                'name' => $incomingLetter->followup_detail['name'] ?? '',
                                                'directorate' => $incomingLetter->followup_detail['directorate'] ?? '',
                                                'position' => $incomingLetter->followup_detail['position'] ?? '',
                                                'registration_status' =>
                                                    $incomingLetter->followup_detail['registration'] ?? '',
                                                'pic_name' => '',
                                                'pic_contact' => '',
                                                'registration_deadline' => '',
                                                'note' => $incomingLetter->followup_detail['note'] ?? '',
                                            ],
                                        ];
                                    }
                                    $invitationParticipants = old(
                                        'followup_invitation_participants',
                                        $defaultInvitationParticipants,
                                    );
                                @endphp
                                <div id="invitation-participants-container" class="mt-4 grid gap-4">
                                    @foreach ($invitationParticipants as $participantIndex => $participant)
                                        <div class="rounded border border-gray-200 p-4 invitation-participant-item"
                                            data-index="{{ $participantIndex }}">
                                            <div class="mb-3 flex items-center justify-between gap-3">
                                                <div class="font-semibold text-gray-800">Peserta
                                                    {{ $participantIndex + 1 }}</div>
                                                <button class="btn btn-xs btn-danger remove-invitation-participant"
                                                    type="button">
                                                    Hapus
                                                </button>
                                            </div>
                                            <div class="grid gap-4 lg:grid-cols-2">
                                                <div class="flex flex-col">
                                                    <label class="form-label">NIK</label>
                                                    <input class="input invitation-nik-input" type="text"
                                                        name="followup_invitation_participants[{{ $participantIndex }}][nik]"
                                                        maxlength="16" inputmode="numeric" pattern="[0-9]*"
                                                        value="{{ $participant['nik'] ?? '' }}"
                                                        placeholder="NIK peserta...">
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="form-label">Nama Peserta <span
                                                            class="text-danger">*</span></label>
                                                    <input class="input invitation-name-input" type="text"
                                                        name="followup_invitation_participants[{{ $participantIndex }}][name]"
                                                        value="{{ $participant['name'] ?? '' }}"
                                                        placeholder="Nama peserta...">
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="form-label">Direktorat</label>
                                                    <input class="input invitation-directorate-input" type="text"
                                                        name="followup_invitation_participants[{{ $participantIndex }}][directorate]"
                                                        value="{{ $participant['directorate'] ?? '' }}"
                                                        placeholder="Direktorat peserta...">
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="form-label">Jabatan</label>
                                                    <input class="input invitation-position-input" type="text"
                                                        name="followup_invitation_participants[{{ $participantIndex }}][position]"
                                                        value="{{ $participant['position'] ?? '' }}"
                                                        placeholder="Jabatan peserta...">
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="form-label">Status Pendaftaran</label>
                                                    <select class="select invitation-registration-status"
                                                        name="followup_invitation_participants[{{ $participantIndex }}][registration_status]">
                                                        <option value="">- Pilih -</option>
                                                        <option value="sudah"
                                                            {{ ($participant['registration_status'] ?? '') === 'sudah' ? 'selected' : '' }}>
                                                            Sudah</option>
                                                        <option value="belum"
                                                            {{ ($participant['registration_status'] ?? '') === 'belum' ? 'selected' : '' }}>
                                                            Belum</option>
                                                    </select>
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="form-label">Nama PIC</label>
                                                    <input class="input invitation-pic-name-input" type="text"
                                                        name="followup_invitation_participants[{{ $participantIndex }}][pic_name]"
                                                        value="{{ $participant['pic_name'] ?? '' }}"
                                                        placeholder="Nama PIC...">
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="form-label">Nomor Contact PIC</label>
                                                    <input class="input invitation-pic-contact-input" type="text"
                                                        name="followup_invitation_participants[{{ $participantIndex }}][pic_contact]"
                                                        value="{{ $participant['pic_contact'] ?? '' }}"
                                                        placeholder="Nomor contact PIC...">
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="form-label">Tanggal Deadline Pendaftaran</label>
                                                    <input class="input invitation-registration-deadline-input"
                                                        type="date"
                                                        name="followup_invitation_participants[{{ $participantIndex }}][registration_deadline]"
                                                        value="{{ $participant['registration_deadline'] ?? '' }}">
                                                </div>
                                                <div class="flex flex-col lg:col-span-2">
                                                    <label class="form-label">Catatan</label>
                                                    <textarea class="textarea w-full" name="followup_invitation_participants[{{ $participantIndex }}][note]"
                                                        rows="3" placeholder="Catatan peserta...">{{ $participant['note'] ?? '' }}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex flex-col followup-field hidden" data-followup="review">
                                <label class="form-label">Nomor Peraturan <span class="text-danger">*</span></label>
                                <input class="input" type="text" name="followup_review_regulation_number"
                                    value="{{ old('followup_review_regulation_number', $incomingLetter->followup_detail['regulation_number'] ?? '') }}"
                                    placeholder="Nomor peraturan...">
                            </div>
                            <div class="flex flex-col followup-field hidden" data-followup="review">
                                <label class="form-label">Judul Peraturan <span class="text-danger">*</span></label>
                                <input class="input" type="text" name="followup_review_regulation_title"
                                    value="{{ old('followup_review_regulation_title', $incomingLetter->followup_detail['regulation_title'] ?? '') }}"
                                    placeholder="Judul peraturan...">
                            </div>
                            <div class="flex flex-col followup-field hidden" data-followup="review">
                                <label class="form-label">Tanggal Upload <span class="text-danger">*</span></label>
                                <input class="input" type="date" name="followup_review_upload_date"
                                    value="{{ old('followup_review_upload_date', $incomingLetter->followup_detail['upload_date'] ?? '') }}">
                            </div>
                            <div class="flex flex-col followup-field hidden" data-followup="review">
                                <label class="form-label">Catatan</label>
                                <textarea class="textarea w-full" name="followup_review_note" rows="3" placeholder="Catatan review...">{{ old('followup_review_note', $incomingLetter->followup_detail['note'] ?? '') }}</textarea>
                            </div>

                            <div class="flex flex-col followup-field hidden" data-followup="lainnya">
                                <label class="form-label">Tanggal Tindak Lanjut <span class="text-danger">*</span></label>
                                <input class="input" type="date" name="followup_lainnya_date"
                                    id="followup_lainnya_date"
                                    value="{{ old('followup_lainnya_date', $incomingLetter->followup_detail['date'] ?? now()->format('Y-m-d')) }}">
                            </div>
                            <div class="flex flex-col followup-field hidden" data-followup="lainnya">
                                <label class="form-label">Catatan <span class="text-danger">*</span></label>
                                <textarea class="textarea w-full" name="followup_lainnya_note" rows="3"
                                    placeholder="Jelaskan tindak lanjut yang sudah dilakukan di luar aplikasi...">{{ old('followup_lainnya_note', $incomingLetter->followup_detail['note'] ?? '') }}</textarea>
                            </div>
                            <div class="flex flex-col followup-field hidden lg:col-span-2" data-followup="lainnya">
                                <label class="form-label">Upload Surat <span class="text-danger">*</span></label>
                                <input class="file-input" type="file" name="followup_lainnya_file"
                                    accept=".pdf,.jpg,.jpeg,.png">
                                @if (!empty($incomingLetter->followup_detail['file'] ?? null))
                                    <small class="text-xs text-gray-500 mt-1">
                                        File saat ini: {{ $incomingLetter->followup_detail['file'] }}
                                    </small>
                                @endif
                            </div>

                            <div class="flex flex-col">
                                <label class="form-label">Due Date</label>
                                <input class="input" type="date" name="target_date"
                                    value="{{ old('target_date', $incomingLetter->target_date?->format('Y-m-d')) }}">
                            </div>
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Catatan</label>
                            <textarea class="textarea w-full" name="followup_note" rows="3" placeholder="Tambahkan catatan...">{{ old('followup_note', $incomingLetter->followup_note) }}</textarea>
                        </div>

                        <div class="flex flex-col" id="evidence-upload-wrapper">
                            <label class="form-label">Upload Draft/Hasil (PDF/JPG/PNG)</label>
                            <input class="file-input" type="file" name="evidence_files[]" multiple
                                accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-xs text-gray-500 mt-1" id="evidence-upload-note" style="display:none;">
                                Untuk tindak lanjut Surat Jawaban, final dokumen diselesaikan lewat form Surat Keluar.
                            </small>
                            <small class="text-xs text-gray-500 mt-1" id="evidence-upload-note-lainnya"
                                style="display:none;">
                                Untuk tindak lanjut Lainnya, upload bukti pada kolom "Upload Surat" di atas.
                            </small>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button class="btn btn-light" type="submit" name="submit_for_approval" value="0">
                                Simpan Draft
                            </button>
                            <button class="btn btn-primary" type="submit" name="submit_for_approval" value="1">
                                Submit Approval Direktorat
                            </button>
                        </div>
                    </form>
                </div>
<<<<<<< HEAD
=======
            </div>
>>>>>>> 7e84cce245b01817c83717d179fd74b0a8e5fcf2
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
                                        @php
                                            $noteText = $approval->note ?? '';
                                            $label = '';
                                            $userNote = '';

                                            $knownLabels = [
                                                'EO Direktorat Approved',
                                                'EO Direktorat Returned',
                                                'DD Direktorat Approved',
                                                'DD Direktorat Returned',
                                                'Corporate Secretary Approved',
                                                'Corporate Secretary Returned',
                                                'EO Corp Affair Approved',
                                                'EO Corp Affair Returned',
                                            ];

                                            foreach ($knownLabels as $known) {
                                                if (\Illuminate\Support\Str::startsWith($noteText, $known)) {
                                                    $label = $known;
                                                    $userNote = trim(
                                                        (string) \Illuminate\Support\Str::after($noteText, $known),
                                                    );
                                                    if (\Illuminate\Support\Str::startsWith($userNote, '-')) {
                                                        $userNote = trim(
                                                            (string) \Illuminate\Support\Str::after($userNote, '-'),
                                                        );
                                                    }
                                                    break;
                                                }
                                            }

                                            if ($label === '' && $noteText !== '') {
                                                $userNote = $noteText;
                                            }
                                        @endphp
                                        <td>{{ $noteText !== '' ? $noteText : '-' }}</td>
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

        @if ($canCheckerDirApproval || $canApproverApproval)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Approval</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('letter.incoming.approval.action', $incomingLetter) }}"
                        class="grid gap-4 js-ajax-form" data-form-type="approval">
                        @csrf
                        <div class="text-sm text-gray-500">
                            {{ $canCheckerDirApproval ? 'Approval EO' : 'Approval DD' }}
                        </div>
                        <div class="flex flex-col">
                            <label class="form-label">Catatan (opsional)</label>
                            <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan..."></textarea>
                        </div>
                        <div class="flex flex-wrap gap-2 justify-end">
                            <button class="btn btn-sm btn-danger" type="submit" name="action" value="reject">
                                <i class="ki-filled ki-cross"></i> Reject
                            </button>
                            <button class="btn btn-sm btn-success" type="submit" name="action" value="approve">
                                <i class="ki-filled ki-check"></i> Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if ($canCorsecValidation)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Corporate Secretary</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('letter.incoming.verify.action', $incomingLetter) }}"
                        class="grid gap-4 js-ajax-form" data-form-type="verify">
                        @csrf
                        <div class="flex flex-col">
                            <label class="form-label">Komentar Validasi <span class="text-danger">*</span></label>
                            <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan komentar validasi..."
                                required></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button class="btn btn-sm btn-success" type="submit" name="action" value="validate">
                                <i class="ki-filled ki-check"></i> Simpan Validasi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/corsec/incoming-validation.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const followupSelect = document.getElementById('followup_action');
            const followupFields = document.querySelectorAll('.followup-field');
            const meetingDropdown = document.getElementById('meeting-participants-dropdown');
            const meetingOptions = document.getElementById('meeting-participants-options');
            const meetingSelectedText = document.getElementById('meeting-participants-selected-text');
            const socialDropdown = document.getElementById('social-participants-dropdown');
            const socialOptions = document.getElementById('social-participants-options');
            const socialSelectedText = document.getElementById('social-participants-selected-text');
            const socialCoordinationDropdown = document.getElementById('social-coordination-dropdown');
            const socialCoordinationOptions = document.getElementById('social-coordination-options');
            const socialCoordinationSelectedText = document.getElementById('social-coordination-selected-text');
            const evidenceUploadWrapper = document.getElementById('evidence-upload-wrapper');
            const evidenceUploadNote = document.getElementById('evidence-upload-note');
            const evidenceUploadNoteLainnya = document.getElementById('evidence-upload-note-lainnya');
            const evidenceFileInput = evidenceUploadWrapper ? evidenceUploadWrapper.querySelector(
                'input[name="evidence_files[]"]') : null;
            const lainnyaDateInput = document.getElementById('followup_lainnya_date');
            const invitationParticipantsContainer = document.getElementById('invitation-participants-container');
            const addInvitationParticipantButton = document.getElementById('add-invitation-participant');
            const invitationLookupUrl = @json(route('letter.incoming.lookup-user'));
            let invitationLookupTimer = null;

            function toggleFollowupFields() {
                const selected = followupSelect ? followupSelect.value : '';
                followupFields.forEach((field) => {
                    if (field.dataset.followup === selected) {
                        field.classList.remove('hidden');
                    } else {
                        field.classList.add('hidden');
                    }
                });

                if (evidenceUploadWrapper) {
                    evidenceUploadWrapper.style.display = '';
                }

                if (evidenceUploadNote) {
                    evidenceUploadNote.style.display = selected === 'response_letter' ? '' : 'none';
                }

                if (evidenceUploadNoteLainnya) {
                    evidenceUploadNoteLainnya.style.display = selected === 'lainnya' ? '' : 'none';
                }

                if (evidenceFileInput) {
                    const hideEvidenceInput = selected === 'response_letter' || selected === 'lainnya';
                    evidenceFileInput.style.display = hideEvidenceInput ? 'none' : '';
                    if (hideEvidenceInput) {
                        evidenceFileInput.value = '';
                    }
                }

                if (lainnyaDateInput && selected === 'lainnya' && !lainnyaDateInput.value) {
                    const today = new Date();
                    const yyyy = today.getFullYear();
                    const mm = String(today.getMonth() + 1).padStart(2, '0');
                    const dd = String(today.getDate()).padStart(2, '0');
                    lainnyaDateInput.value = `${yyyy}-${mm}-${dd}`;
                }
            }

            if (followupSelect) {
                toggleFollowupFields();
                followupSelect.addEventListener('change', toggleFollowupFields);
            }

            const followupFormEl = document.getElementById('followup-form');
            if (followupFormEl && window.jQuery) {
                window.jQuery(followupFormEl).data('hasExistingLainnyaFile',
                    @json(!empty($incomingLetter->followup_detail['file'] ?? null)));
            }

            function updateMeetingParticipantsLabel() {
                if (!meetingSelectedText || !meetingOptions) return;
                const checkboxes = meetingOptions.querySelectorAll('input[type="checkbox"]:checked');
                const names = Array.from(checkboxes).map((item) => {
                    const label = item.closest('label');
                    return label ? label.textContent.trim() : '';
                }).filter(Boolean);
                meetingSelectedText.textContent = names.length > 0 ? names.join(', ') : 'Pilih peserta...';
            }

            if (meetingDropdown && meetingOptions) {
                meetingDropdown.addEventListener('click', function() {
                    meetingOptions.classList.toggle('hidden');
                });

                document.addEventListener('click', function(event) {
                    if (!meetingDropdown.contains(event.target) && !meetingOptions.contains(event.target)) {
                        meetingOptions.classList.add('hidden');
                    }
                });

                meetingOptions.addEventListener('change', updateMeetingParticipantsLabel);
                updateMeetingParticipantsLabel();
            }

            function updateSocialParticipantsLabel() {
                if (!socialSelectedText || !socialOptions) return;
                const checkboxes = socialOptions.querySelectorAll('input[type="checkbox"]:checked');
                const names = Array.from(checkboxes).map((item) => {
                    const label = item.closest('label');
                    return label ? label.textContent.trim() : '';
                }).filter(Boolean);
                socialSelectedText.textContent = names.length > 0 ? names.join(', ') : 'Pilih peserta...';
            }

            if (socialDropdown && socialOptions) {
                socialDropdown.addEventListener('click', function() {
                    socialOptions.classList.toggle('hidden');
                });

                document.addEventListener('click', function(event) {
                    if (!socialDropdown.contains(event.target) && !socialOptions.contains(event.target)) {
                        socialOptions.classList.add('hidden');
                    }
                });

                socialOptions.addEventListener('change', updateSocialParticipantsLabel);
                updateSocialParticipantsLabel();
            }

            function updateSocialCoordinationLabel() {
                if (!socialCoordinationSelectedText || !socialCoordinationOptions) return;
                const checkboxes = socialCoordinationOptions.querySelectorAll('input[type="checkbox"]:checked');
                const names = Array.from(checkboxes).map((item) => {
                    const label = item.closest('label');
                    return label ? label.textContent.trim() : '';
                }).filter(Boolean);
                socialCoordinationSelectedText.textContent = names.length > 0 ? names.join(', ') :
                    'Pilih direktorat...';
            }

            if (socialCoordinationDropdown && socialCoordinationOptions) {
                socialCoordinationDropdown.addEventListener('click', function() {
                    socialCoordinationOptions.classList.toggle('hidden');
                });

                document.addEventListener('click', function(event) {
                    if (!socialCoordinationDropdown.contains(event.target) &&
                        !socialCoordinationOptions.contains(event.target)) {
                        socialCoordinationOptions.classList.add('hidden');
                    }
                });

                socialCoordinationOptions.addEventListener('change', updateSocialCoordinationLabel);
                updateSocialCoordinationLabel();
            }

            function invitationParticipantTemplate(index) {
                return `
                    <div class="rounded border border-gray-200 p-4 invitation-participant-item" data-index="${index}">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div class="font-semibold text-gray-800">Peserta ${index + 1}</div>
                            <button class="btn btn-xs btn-light-danger remove-invitation-participant" type="button">Hapus</button>
                        </div>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="flex flex-col">
                                <label class="form-label">NIK</label>
                                <input class="input invitation-nik-input" type="text" name="followup_invitation_participants[${index}][nik]" maxlength="16" inputmode="numeric" pattern="[0-9]*" placeholder="NIK peserta...">
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Nama Peserta <span class="text-danger">*</span></label>
                                <input class="input invitation-name-input" type="text" name="followup_invitation_participants[${index}][name]" placeholder="Nama peserta...">
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Direktorat</label>
                                <input class="input invitation-directorate-input" type="text" name="followup_invitation_participants[${index}][directorate]" placeholder="Direktorat peserta...">
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Jabatan</label>
                                <input class="input invitation-position-input" type="text" name="followup_invitation_participants[${index}][position]" placeholder="Jabatan peserta...">
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Status Pendaftaran</label>
                                <select class="select invitation-registration-status" name="followup_invitation_participants[${index}][registration_status]">
                                    <option value="">- Pilih -</option>
                                    <option value="sudah">Sudah</option>
                                    <option value="belum">Belum</option>
                                </select>
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Nama PIC</label>
                                <input class="input invitation-pic-name-input" type="text" name="followup_invitation_participants[${index}][pic_name]" placeholder="Nama PIC...">
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Nomor Contact PIC</label>
                                <input class="input invitation-pic-contact-input" type="text" name="followup_invitation_participants[${index}][pic_contact]" placeholder="Nomor contact PIC...">
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Tanggal Deadline Pendaftaran</label>
                                <input class="input invitation-registration-deadline-input" type="date" name="followup_invitation_participants[${index}][registration_deadline]">
                            </div>
                            <div class="flex flex-col lg:col-span-2">
                                <label class="form-label">Catatan</label>
                                <textarea class="textarea w-full" name="followup_invitation_participants[${index}][note]" rows="3" placeholder="Catatan peserta..."></textarea>
                            </div>
                        </div>
                    </div>
                `;
            }

            function reindexInvitationParticipants() {
                if (!invitationParticipantsContainer) return;
                const items = invitationParticipantsContainer.querySelectorAll('.invitation-participant-item');
                items.forEach((item, index) => {
                    item.dataset.index = String(index);
                    const title = item.querySelector('.font-semibold');
                    if (title) {
                        title.textContent = `Peserta ${index + 1}`;
                    }
                    item.querySelectorAll('input, select, textarea').forEach((field) => {
                        if (!field.name) return;
                        field.name = field.name.replace(/followup_invitation_participants\[\d+\]/,
                            `followup_invitation_participants[${index}]`);
                    });
                });
            }

            function fillInvitationRow(row, data) {
                if (!row || !data) return;
                const nameInput = row.querySelector('.invitation-name-input');
                const directorateInput = row.querySelector('.invitation-directorate-input');
                const positionInput = row.querySelector('.invitation-position-input');

                if (nameInput && data.name) {
                    nameInput.value = data.name;
                }
                if (directorateInput) {
                    if (data.directorate_name) {
                        directorateInput.value = data.directorate_name;
                    } else if (data.directorate_id) {
                        directorateInput.value = String(data.directorate_id);
                    }
                }
                if (positionInput && data.position) {
                    positionInput.value = data.position;
                }
            }

            function lookupInvitationUser(row, nik) {
                if (!invitationLookupUrl || !nik) return;
                fetch(`${invitationLookupUrl}?nik=${encodeURIComponent(nik)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    })
                    .then((response) => (response.ok ? response.json() : null))
                    .then((payload) => {
                        if (payload && payload.success) {
                            fillInvitationRow(row, payload.data);
                        }
                    })
                    .catch(() => {});
            }

            if (addInvitationParticipantButton && invitationParticipantsContainer) {
                addInvitationParticipantButton.addEventListener('click', function() {
                    const index = invitationParticipantsContainer.querySelectorAll(
                        '.invitation-participant-item').length;
                    invitationParticipantsContainer.insertAdjacentHTML('beforeend',
                        invitationParticipantTemplate(index));
                    reindexInvitationParticipants();
                });
            }

            if (invitationParticipantsContainer) {
                invitationParticipantsContainer.addEventListener('click', function(event) {
                    const removeButton = event.target.closest('.remove-invitation-participant');
                    if (!removeButton) return;

                    const items = invitationParticipantsContainer.querySelectorAll(
                        '.invitation-participant-item');
                    if (items.length <= 1) {
                        return;
                    }

                    removeButton.closest('.invitation-participant-item')?.remove();
                    reindexInvitationParticipants();
                });

                invitationParticipantsContainer.addEventListener('input', function(event) {
                    const nikInput = event.target.closest('.invitation-nik-input');
                    if (!nikInput) return;

                    const nik = nikInput.value.trim();
                    if (invitationLookupTimer) {
                        clearTimeout(invitationLookupTimer);
                    }
                    if (!nik) {
                        return;
                    }

                    const row = nikInput.closest('.invitation-participant-item');
                    invitationLookupTimer = setTimeout(() => {
                        lookupInvitationUser(row, nik);
                    }, 400);
                });
            }

            if (window.jQuery && window.CorsecIncomingValidation) {
                const $document = window.jQuery(document);
                const {
                    clearValidation,
                    showFieldError,
                    validateFileSizes,
                    uploadFailureMessage,
                } = window.CorsecIncomingValidation;
                const uploadSizeOptions = {
                    maxBytes: @json(\Modules\Corsec\Support\UploadRule::maxFileSizeKb() * 1024),
                    label: @json(\Modules\Corsec\Support\UploadRule::label()),
                };

                function validateMonitoringForm($form) {
                    const errors = {};
                    const value = $form.find('[name="monitoring_directorate_id"]').val();
                    if (!value) {
                        errors.monitoring_directorate_id = 'Silahkan pilih minimal 1.';
                    }
                    return errors;
                }

                function validateFollowupForm($form) {
                    const errors = {};
                    const action = $form.find('[name="followup_action"]').val();
                    if (!action) {
                        errors.followup_action = 'Field ini tidak boleh kosong.';
                        return errors;
                    }

                    if (action === 'meeting') {
                        const participantsCount = $form.find(
                            '[name="followup_meeting_participants[]"]:checked'
                        ).length;
                        if (participantsCount === 0) {
                            errors.followup_meeting_participants = 'Silahkan pilih minimal 1.';
                        }
                        if (!$form.find('[name="followup_meeting_date"]').val()) {
                            errors.followup_meeting_date = 'Field ini tidak boleh kosong.';
                        }
                        if (!$form.find('[name="followup_meeting_time"]').val()) {
                            errors.followup_meeting_time = 'Field ini tidak boleh kosong.';
                        }
                        if (!$form.find('[name="followup_meeting_location"]').val()) {
                            errors.followup_meeting_location = 'Field ini tidak boleh kosong.';
                        }
                    }

                    if (action === 'response_letter') {
                        if (!$form.find('[name="followup_response_target_date"]').val()) {
                            errors.followup_response_target_date = 'Field ini tidak boleh kosong.';
                        }
                    }

                    if (action === 'socialization') {
                        const participantsCount = $form.find(
                            '[name="followup_social_participants[]"]:checked'
                        ).length;
                        if (participantsCount === 0) {
                            errors.followup_social_participants = 'Silahkan pilih minimal 1.';
                        }
                        if (!$form.find('[name="followup_social_date"]').val()) {
                            errors.followup_social_date = 'Field ini tidak boleh kosong.';
                        }
                    }

                    if (action === 'invitation') {
                        const invitationRows = $form.find('.invitation-participant-item');
                        if (invitationRows.length === 0) {
                            errors.followup_invitation_participants = 'Silahkan tambahkan minimal 1 peserta.';
                        }

                        invitationRows.each(function(index) {
                            const $row = window.jQuery(this);
                            const name = ($row.find('.invitation-name-input').val() || '').trim();
                            const status = ($row.find('.invitation-registration-status').val() || '')
                                .trim();
                            const picName = ($row.find('.invitation-pic-name-input').val() || '').trim();
                            const picContact = ($row.find('.invitation-pic-contact-input').val() || '')
                                .trim();
                            const deadline = ($row.find('.invitation-registration-deadline-input').val() ||
                                '').trim();

                            if (!name) {
                                errors[`followup_invitation_participants.${index}.name`] =
                                    'Nama peserta wajib diisi.';
                            }

                            if (status === 'sudah') {
                                if (!picName) {
                                    errors[`followup_invitation_participants.${index}.pic_name`] =
                                        'Nama PIC wajib diisi jika peserta sudah terdaftar.';
                                }
                                if (!picContact) {
                                    errors[`followup_invitation_participants.${index}.pic_contact`] =
                                        'Nomor contact PIC wajib diisi jika peserta sudah terdaftar.';
                                }
                                if (!deadline) {
                                    errors[
                                            `followup_invitation_participants.${index}.registration_deadline`
                                        ] =
                                        'Tanggal deadline pendaftaran wajib diisi jika peserta sudah terdaftar.';
                                }
                            }
                        });

                        if (Object.keys(errors).some((field) => field.startsWith(
                                'followup_invitation_participants'))) {
                            errors.followup_action = 'Lengkapi data peserta undangan terlebih dahulu.';
                        }
                    }

                    if (action === 'review') {
                        if (!$form.find('[name="followup_review_regulation_number"]').val()) {
                            errors.followup_review_regulation_number = 'Field ini tidak boleh kosong.';
                        }
                        if (!$form.find('[name="followup_review_regulation_title"]').val()) {
                            errors.followup_review_regulation_title = 'Field ini tidak boleh kosong.';
                        }
                        if (!$form.find('[name="followup_review_upload_date"]').val()) {
                            errors.followup_review_upload_date = 'Field ini tidak boleh kosong.';
                        }
                    }

                    if (action === 'lainnya') {
                        if (!$form.find('[name="followup_lainnya_note"]').val()) {
                            errors.followup_lainnya_note = 'Field ini tidak boleh kosong.';
                        }
                        const lainnyaFileInput = $form.find('[name="followup_lainnya_file"]')[0];
                        const hasExistingLainnyaFile = $form.data('hasExistingLainnyaFile') === true;
                        if ((!lainnyaFileInput || lainnyaFileInput.files.length === 0) && !hasExistingLainnyaFile) {
                            errors.followup_lainnya_file = 'Harap upload surat.';
                        }
                    }

                    const submitForApproval = $form.data('submitForApproval');
                    if (submitForApproval === '1' && action !== 'response_letter' && action !== 'lainnya') {
                        const filesInput = $form.find('[name="evidence_files[]"]')[0];
                        if (!filesInput || filesInput.files.length === 0) {
                            errors['evidence_files[]'] = 'Harap upload file.';
                        }
                    }

                    Object.assign(errors, validateFileSizes($form, uploadSizeOptions));

                    return errors;
                }

                $document.on('click', 'form.js-ajax-form button[type="submit"]', function() {
                    const $form = window.jQuery(this).closest('form');
                    const name = window.jQuery(this).attr('name');
                    if (name) {
                        $form.data('submitterName', name);
                        $form.data('submitterValue', window.jQuery(this).val());
                    }
                    if ($form.attr('id') === 'followup-form' && name === 'submit_for_approval') {
                        $form.data('submitForApproval', window.jQuery(this).val());
                    }
                });

                $document.on('submit', 'form.js-ajax-form', function(event) {
                    event.preventDefault();
                    const $form = window.jQuery(this);
                    const formType = $form.data('formType');
                    clearValidation($form);

                    let errors = {};
                    if (formType === 'monitoring') {
                        errors = validateMonitoringForm($form);
                    }
                    if (formType === 'followup') {
                        errors = validateFollowupForm($form);
                    }

                    if (Object.keys(errors).length > 0) {
                        Object.keys(errors).forEach((field) => {
                            showFieldError($form, field, errors[field]);
                        });
                        return;
                    }

                    const formData = new FormData(this);
                    const submitterName = $form.data('submitterName');
                    const submitterValue = $form.data('submitterValue');
                    if (submitterName) {
                        formData.set(submitterName, submitterValue);
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
                        success: function(response) {
                            const reloadPage = () => window.location.reload();
                            const successMessage = response && typeof response.message ===
                                'string' &&
                                response.message.trim() !== '' ?
                                response.message : 'Berhasil disimpan.';
                            if (window.toast && typeof window.toast.success === 'function') {
                                window.toast.success(successMessage);
                                setTimeout(reloadPage, 800);
                                return;
                            }
                            Swal.fire('Berhasil', successMessage, 'success').then(reloadPage);
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
                            Swal.fire('Error!', uploadFailureMessage(xhr,
                                    'Gagal memproses surat masuk.', uploadSizeOptions),
                                'error');
                        }
                    });
                });
            }
        });
    </script>
@endpush
