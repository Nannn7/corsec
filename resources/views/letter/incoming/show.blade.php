@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.incoming.show', $incomingLetter) }}
@endsection

@section('content')
    @php
        $user = auth()->user();
        $status = $incomingLetter->status;
        $isAdmin = $user?->hasRole('administrator');
        $isChecker = $user?->hasRole('checker');
        $isApprover = $user?->hasRole('approver');
        $eoDirectorateCode = config('corsec.eo_corp_affair_directorate_code', '');
        $isEoCorpAffairDirectorate =
            $user && $eoDirectorateCode !== '' && $user->directorate?->code === $eoDirectorateCode;
        $isEoCorpAffairActor = $isEoCorpAffairDirectorate && ($isChecker || $isApprover);
        $isTargetDirectorate =
            $user &&
            $incomingLetter->target_directorate_id &&
            (int) $user->directorate_id === (int) $incomingLetter->target_directorate_id;
        $isMonitoringDirectorate =
            $user && $incomingLetter->circulationDirectorates?->contains('id', (int) $user->directorate_id);
        $canDirectorateUpdate =
            in_array($status, ['dispatched', 'in_progress', 'returned'], true) &&
            !$isEoCorpAffairActor &&
            ($isAdmin || ($isTargetDirectorate && $incomingLetter->authorized_status === 'authorized'));
        $checkerApproved =
            $approvals
                ->where('status', 'approved')
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Direktorat Approved');
                })
                ->count() > 0;
        $userHasEoDirApproval =
            $user &&
            $approvals
                ->where('acted_by', $user->id)
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Direktorat Approved') ||
                        \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Direktorat Returned');
                })
                ->count() > 0;
        $userHasDdDirApproval =
            $user &&
            $approvals
                ->where('acted_by', $user->id)
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'DD Direktorat Approved') ||
                        \Illuminate\Support\Str::startsWith((string) $approval->note, 'DD Direktorat Returned');
                })
                ->count() > 0;
        $userHasEoCorpAffairApproval =
            $user &&
            $approvals
                ->where('acted_by', $user->id)
                ->filter(function ($approval) {
                    return \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Corp Affair Approved') ||
                        \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Corp Affair Returned');
                })
                ->count() > 0;
        $canCheckerDirApproval =
            $status === 'waiting_dir_approval' && !$checkerApproved && ($isAdmin || $isChecker) && !$userHasEoDirApproval;
        $canApproverApproval =
            $status === 'waiting_dir_approval' && $checkerApproved && ($isAdmin || $isApprover) && !$userHasDdDirApproval;
        $canCheckerApproval =
            ($incomingLetter->authorized_status === 'pending' ||
                in_array($status, ['on_approval', 'waiting_verification'], true)) &&
            ($isAdmin || $isEoCorpAffairActor) &&
            !$userHasEoCorpAffairApproval;
        $statusSteps = [
            'draft' => 'Draft',
            'on_approval' => 'On Approval',
            'dispatched' => 'Dispatched',
            'in_progress' => 'In Progress',
            'waiting_dir_approval' => 'Waiting Dir Approval',
            'waiting_verification' => 'Waiting Verification',
            'verified' => 'Verified',
            'returned' => 'Returned',
            'rejected' => 'Rejected',
        ];
        $incomingFiles = $incomingLetter->attachables?->where('category', 'incoming')->values() ?? collect();
        $evidenceFiles = $incomingLetter->attachables?->where('category', 'evidence')->values() ?? collect();
        $socialMaterialFiles =
            $incomingLetter->attachables?->where('category', 'social_material')->values() ?? collect();
        $directorateMap = $directorates?->keyBy('id') ?? collect();
    @endphp
    <div class="grid gap-5 lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detail Surat Masuk #{{ $incomingLetter->id }}</h3>
                <div class="flex gap-2">
                    <a href="{{ route('letter.incoming.index') }}" class="btn btn-sm btn-light">
                        <i class="ki-filled ki-arrow-left"></i> Kembali
                    </a>
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
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Action Surat:</span>
                            <span class="font-medium">{{ $incomingLetter->letterType?->name ?? '-' }}</span>
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
                            <span class="text-gray-600">Target Date:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->target_date ? $incomingLetter->target_date->format('Y-m-d') : '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Status:</span>
                            <span class="badge badge-light">{{ $incomingLetter->status ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($isAdmin || $isTargetDirectorate)
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
                                    $isActive =
                                        $status === $key ||
                                        ($key === 'on_approval' && $incomingLetter->authorized_status === 'pending');
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
                                $invitationDirectorateValue = $followupDetail['directorate'] ?? null;
                                $invitationDirectorateLabel = '-';
                                if (!is_null($invitationDirectorateValue) && $invitationDirectorateValue !== '') {
                                    $invitationDirectorateLabel = $invitationDirectorateValue;
                                    if (is_numeric($invitationDirectorateValue)) {
                                        $invitationDirectorateLabel =
                                            $directorateMap->get((int) $invitationDirectorateValue)?->name ??
                                            $invitationDirectorateValue;
                                    }
                                }
                            @endphp
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">NIK:</span>
                                <span class="font-medium">{{ $followupDetail['nik'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Nama:</span>
                                <span class="font-medium">{{ $followupDetail['name'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Direktorat:</span>
                                <span class="font-medium">{{ $invitationDirectorateLabel }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Jabatan:</span>
                                <span class="font-medium">{{ $followupDetail['position'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Pendaftaran:</span>
                                <span class="font-medium">
                                    {{ ($followupDetail['registration'] ?? '') === 'sudah' ? 'Sudah' : (($followupDetail['registration'] ?? '') === 'belum' ? 'Belum' : '-') }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Catatan Pendaftaran:</span>
                                <span class="font-medium">{{ $followupDetail['note'] ?? '-' }}</span>
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
                        @endif
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Catatan:</span>
                            <span class="font-medium">{{ $incomingLetter->followup_note ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @can('corsec.update')
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
                                            style="background-color: #ffffff;">
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
                                            style="background-color: #ffffff;">
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
                                            style="background-color: #ffffff;">
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

                                <div class="flex flex-col followup-field hidden" data-followup="invitation">
                                    <label class="form-label">NIK <span class="text-danger">*</span></label>
                                    <input class="input" type="text" name="followup_invitation_nik"
                                        id="followup-invitation-nik" maxlength="16" inputmode="numeric" pattern="[0-9]*"
                                        value="{{ old('followup_invitation_nik', $incomingLetter->followup_detail['nik'] ?? '') }}"
                                        placeholder="NIK peserta...">
                                </div>
                                <div class="flex flex-col followup-field hidden" data-followup="invitation">
                                    <label class="form-label">Nama Peserta <span class="text-danger">*</span></label>
                                    <input class="input" type="text" name="followup_invitation_name"
                                        id="followup-invitation-name"
                                        value="{{ old('followup_invitation_name', $incomingLetter->followup_detail['name'] ?? '') }}"
                                        placeholder="Nama peserta...">
                                </div>
                                <div class="flex flex-col followup-field hidden" data-followup="invitation">
                                    <label class="form-label">Direktorat</label>
                                    <input class="input" type="text" name="followup_invitation_directorate"
                                        id="followup-invitation-directorate"
                                        value="{{ old('followup_invitation_directorate', $incomingLetter->followup_detail['directorate'] ?? '') }}"
                                        placeholder="Direktorat peserta...">
                                </div>
                                <div class="flex flex-col followup-field hidden" data-followup="invitation">
                                    <label class="form-label">Jabatan</label>
                                    <input class="input" type="text" name="followup_invitation_position"
                                        id="followup-invitation-position"
                                        value="{{ old('followup_invitation_position', $incomingLetter->followup_detail['position'] ?? '') }}"
                                        placeholder="Jabatan peserta...">
                                </div>
                                <div class="flex flex-col followup-field hidden" data-followup="invitation">
                                    <label class="form-label">Status Pendaftaran</label>
                                    <select class="select" name="followup_invitation_registration">
                                        <option value="">- Pilih -</option>
                                        <option value="sudah"
                                            {{ old('followup_invitation_registration', $incomingLetter->followup_detail['registration'] ?? '') === 'sudah' ? 'selected' : '' }}>
                                            Sudah
                                        </option>
                                        <option value="belum"
                                            {{ old('followup_invitation_registration', $incomingLetter->followup_detail['registration'] ?? '') === 'belum' ? 'selected' : '' }}>
                                            Belum
                                        </option>
                                    </select>
                                </div>
                                <div class="flex flex-col followup-field hidden" data-followup="invitation">
                                    <label class="form-label">Catatan</label>
                                    <textarea class="textarea w-full" name="followup_invitation_note" rows="3" placeholder="Catatan peserta...">{{ old('followup_invitation_note', $incomingLetter->followup_detail['note'] ?? '') }}</textarea>
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

                                <div class="flex flex-col">
                                    <label class="form-label">Target Date (SLA)</label>
                                    <input class="input" type="date" name="target_date"
                                        value="{{ old('target_date', $incomingLetter->target_date?->format('Y-m-d')) }}">
                                </div>
                            </div>

                            <div class="flex flex-col">
                                <label class="form-label">Catatan</label>
                                <textarea class="textarea w-full" name="followup_note" rows="3" placeholder="Tambahkan catatan...">{{ old('followup_note', $incomingLetter->followup_note) }}</textarea>
                            </div>

                            <div class="flex flex-col">
                                <label class="form-label">Upload Hasil (PDF/JPG/PNG)</label>
                                <input class="file-input" type="file" name="evidence_files[]" multiple
                                    accept=".pdf,.jpg,.jpeg,.png">
                            </div>

                            <div class="flex justify-end gap-2">
                                <button class="btn btn-light" type="submit" name="submit_for_approval" value="0">
                                    Simpan Draft
                                </button>
                                <button class="btn btn-primary" type="submit" name="submit_for_approval" value="1">
                                    Submit Approval
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan

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
                                                'EO Corp Affair Approved',
                                                'EO Corp Affair Returned',
                                                'Verifikasi EO Corp Affair',
                                            ];

                                            foreach ($knownLabels as $known) {
                                                if (\Illuminate\Support\Str::startsWith($noteText, $known)) {
                                                    $label = $known;
                                                    $userNote = trim((string) \Illuminate\Support\Str::after($noteText, $known));
                                                    if (\Illuminate\Support\Str::startsWith($userNote, '-')) {
                                                        $userNote = trim((string) \Illuminate\Support\Str::after($userNote, '-'));
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

        @can('corsec.authorize')
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Approval</h3>
                </div>
                <div class="card-body">
                    @if (
                        ($incomingLetter->authorized_status === 'pending' || $incomingLetter->status === 'on_approval') &&
                            $canCheckerApproval)
                        <form method="POST" action="{{ route('letter.incoming.approval.action', $incomingLetter) }}"
                            class="grid gap-4 js-ajax-form" data-form-type="approval">
                            @csrf
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
                    @elseif ($incomingLetter->status === 'waiting_dir_approval' && ($canCheckerDirApproval || $canApproverApproval))
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
                    @elseif ($incomingLetter->status === 'waiting_verification' && $canCheckerApproval)
                        <form method="POST" action="{{ route('letter.incoming.verify.action', $incomingLetter) }}"
                            class="grid gap-4 js-ajax-form" data-form-type="verify">
                            @csrf
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

            function toggleFollowupFields() {
                const selected = followupSelect ? followupSelect.value : '';
                followupFields.forEach((field) => {
                    if (field.dataset.followup === selected) {
                        field.classList.remove('hidden');
                    } else {
                        field.classList.add('hidden');
                    }
                });
            }

            if (followupSelect) {
                toggleFollowupFields();
                followupSelect.addEventListener('change', toggleFollowupFields);
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

            const invitationNikInput = document.getElementById('followup-invitation-nik');
            const invitationNameInput = document.getElementById('followup-invitation-name');
            const invitationDirectorateInput = document.getElementById('followup-invitation-directorate');
            const invitationPositionInput = document.getElementById('followup-invitation-position');
            const invitationLookupUrl = @json(route('letter.incoming.lookup-user'));
            let invitationLookupTimer = null;

            function fillInvitationFields(data) {
                if (!data) return;
                if (invitationNameInput && data.name) {
                    invitationNameInput.value = data.name;
                }
                if (invitationDirectorateInput) {
                    if (data.directorate_name) {
                        invitationDirectorateInput.value = data.directorate_name;
                    } else if (data.directorate_id) {
                        invitationDirectorateInput.value = String(data.directorate_id);
                    }
                }
                if (invitationPositionInput && data.position) {
                    invitationPositionInput.value = data.position;
                }
            }

            function lookupInvitationUser(nik) {
                if (!invitationLookupUrl || !nik) return;
                fetch(`${invitationLookupUrl}?nik=${encodeURIComponent(nik)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    })
                    .then((response) => (response.ok ? response.json() : null))
                    .then((payload) => {
                        if (payload && payload.success) {
                            fillInvitationFields(payload.data);
                        }
                    })
                    .catch(() => {});
            }

            if (invitationNikInput) {
                invitationNikInput.addEventListener('input', function(event) {
                    const nik = event.target.value.trim();
                    if (invitationLookupTimer) {
                        clearTimeout(invitationLookupTimer);
                    }
                    if (!nik) {
                        return;
                    }
                    invitationLookupTimer = setTimeout(() => {
                        lookupInvitationUser(nik);
                    }, 400);
                });
            }

            if (window.jQuery && window.CorsecIncomingValidation) {
                const $document = window.jQuery(document);
                const {
                    clearValidation,
                    showFieldError,
                } = window.CorsecIncomingValidation;

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
                        if (!$form.find('[name="followup_invitation_nik"]').val()) {
                            errors.followup_invitation_nik = 'Field ini tidak boleh kosong.';
                        }
                        if (!$form.find('[name="followup_invitation_name"]').val()) {
                            errors.followup_invitation_name = 'Field ini tidak boleh kosong.';
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

                    const submitForApproval = $form.data('submitForApproval');
                    if (submitForApproval === '1') {
                        const filesInput = $form.find('[name="evidence_files[]"]')[0];
                        if (!filesInput || filesInput.files.length === 0) {
                            errors['evidence_files[]'] = 'Harap upload file.';
                        }
                    }

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
                        success: function() {
                            const reloadPage = () => window.location.reload();
                            if (window.toast && typeof window.toast.success === 'function') {
                                window.toast.success('Berhasil disimpan.');
                                setTimeout(reloadPage, 800);
                                return;
                            }
                            alert('Berhasil disimpan.');
                            reloadPage();
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
                            // fallback
                            alert('Gagal memproses. Coba lagi ya.');
                        }
                    });
                });
            }
        });
    </script>
@endpush
