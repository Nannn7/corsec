@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('meeting.show', $meeting) }}
@endsection

@section('content')
    @php

        $user = auth()->user();
        $status = (string) ($meeting->status ?? '');

        $isAdmin = $user?->hasRole('administrator') ?? false;
        $isChecker = $user?->hasRole('checker') ?? false;
        $isApprover = $user?->hasRole('approver') ?? false;
        $actorUserId = (int) ($user?->id ?? 0);
        $actorDirectorateId = (int) ($user?->directorate_id ?? 0);

        $corpCode = (string) config('corsec.eo_corp_affair_directorate_code', '');
        $actorDirectorateCode = (string) ($user?->directorate?->code ?? '');
        $actorDirectorateName = \Illuminate\Support\Str::lower((string) ($user?->directorate?->name ?? ''));
        $isCorsecDirectorate =
            $user &&
            (($corpCode !== '' && $actorDirectorateCode === $corpCode) ||
                ($actorDirectorateName !== '' &&
                    \Illuminate\Support\Str::contains($actorDirectorateName, 'corporate secretary')));

        $participantDirectorateIds = $meeting->participants
            ->pluck('directorate_id')
            ->filter()
            ->map(fn($id) => (int) $id);
        $agendaDirectorateIds = $meeting->agendas->pluck('owner_directorate_id')->filter()->map(fn($id) => (int) $id);
        $decisionDirectorateIds = $meeting->decisions
            ->pluck('owner_directorate_id')
            ->filter()
            ->map(fn($id) => (int) $id);
        $targetDirectorateIds = $participantDirectorateIds
            ->merge($agendaDirectorateIds)
            ->merge($decisionDirectorateIds)
            ->unique()
            ->values();

        $isAssignedUser =
            $meeting->participants->contains(function ($participant) use ($actorUserId) {
                return (int) ($participant->user_id ?? 0) === $actorUserId;
            }) ||
            $meeting->agendas->contains(function ($agenda) use ($actorUserId) {
                return (int) ($agenda->pic_user_id ?? 0) === $actorUserId;
            }) ||
            $meeting->decisions->contains(function ($decision) use ($actorUserId) {
                return (int) ($decision->pic_user_id ?? 0) === $actorUserId;
            });

        $canDirectorateActor =
            $isAdmin ||
            $isAssignedUser ||
            ($actorDirectorateId > 0 && $targetDirectorateIds->contains($actorDirectorateId));

        $canEdit =
            ($isAdmin || (int) ($meeting->created_by ?? 0) === $actorUserId) &&
            in_array($status, ['draft', 'returned_by_corsec', 'returned_by_direktorat'], true);

        $canSubmitPlan =
            ($isAdmin || (int) ($meeting->created_by ?? 0) === $actorUserId) &&
            in_array($status, ['draft', 'returned_by_corsec', 'returned_by_direktorat'], true);

        $corsecActionNotePrefixes = [
            'EO Corp Affair Approved',
            'EO Corp Affair Returned',
            // backward-compat for historical notes before label simplification
            'EO Corp Affair Approved',
            'EO Corp Affair Returned',
        ];
        $userHasCorsecAction =
            $user &&
            $approvals->where('acted_by', $user->id)->contains(function ($approval) use ($corsecActionNotePrefixes) {
                $note = (string) $approval->note;
                foreach ($corsecActionNotePrefixes as $prefix) {
                    if (\Illuminate\Support\Str::startsWith($note, $prefix)) {
                        return true;
                    }
                }
                return false;
            });

        $canCorsecApproval =
            $status === 'waiting_corsec_approval' &&
            ($isAdmin || ($isChecker && $isCorsecDirectorate)) &&
            !$userHasCorsecAction;

        $canMarkPendingDirectorate =
            $canDirectorateActor && in_array($status, ['jadwal_terkirim', 'returned_by_direktorat'], true);
        $canDirectorateSubmit =
            $canDirectorateActor &&
            in_array($status, ['jadwal_terkirim', 'pending_direktorat', 'returned_by_direktorat'], true);

        $latestPendingDirectorateApproval = $approvals
            ->where('status', 'pending')
            ->filter(function ($approval) {
                return \Illuminate\Support\Str::contains(
                    \Illuminate\Support\Str::lower((string) $approval->note),
                    'direktorat',
                );
            })
            ->sortByDesc('id')
            ->first();

        $currentRoundStartedAt = $latestPendingDirectorateApproval?->created_at;
        $checkerApprovedInRound = false;
        $hasActedCheckerInRound = false;
        $hasActedApproverInRound = false;

        if ($currentRoundStartedAt) {
            $checkerApprovedInRound = $approvals
                ->where('status', 'approved')
                ->filter(function ($approval) use ($currentRoundStartedAt) {
                    return $approval->created_at &&
                        $approval->created_at->greaterThanOrEqualTo($currentRoundStartedAt) &&
                        \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Direktorat Approved');
                })
                ->isNotEmpty();

            $hasActedCheckerInRound = $approvals
                ->where('acted_by', $actorUserId)
                ->whereIn('status', ['approved', 'returned'])
                ->filter(function ($approval) use ($currentRoundStartedAt) {
                    return $approval->created_at &&
                        $approval->created_at->greaterThanOrEqualTo($currentRoundStartedAt) &&
                        (\Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Direktorat Approved') ||
                            \Illuminate\Support\Str::startsWith((string) $approval->note, 'EO Direktorat Returned'));
                })
                ->isNotEmpty();

            $hasActedApproverInRound = $approvals
                ->where('acted_by', $actorUserId)
                ->whereIn('status', ['approved', 'returned'])
                ->filter(function ($approval) use ($currentRoundStartedAt) {
                    return $approval->created_at &&
                        $approval->created_at->greaterThanOrEqualTo($currentRoundStartedAt) &&
                        (\Illuminate\Support\Str::startsWith((string) $approval->note, 'DD Direktorat Approved') ||
                            \Illuminate\Support\Str::startsWith((string) $approval->note, 'DD Direktorat Returned'));
                })
                ->isNotEmpty();
        }

        $positionName = \Illuminate\Support\Str::lower((string) ($user?->position?->name ?? ''));
        $isExecutiveOfficer =
            $positionName !== '' && \Illuminate\Support\Str::contains($positionName, 'executive officer');
        $isDeputyDirector = $positionName !== '' && \Illuminate\Support\Str::contains($positionName, 'deputy director');

        $canDirectorateCheckerApproval =
            $status === 'waiting_direktorat_approval' &&
            !$checkerApprovedInRound &&
            ($isAdmin || ($isChecker && $isExecutiveOfficer && $canDirectorateActor)) &&
            !$hasActedCheckerInRound;

        $canDirectorateApproverApproval =
            $status === 'waiting_direktorat_approval' &&
            $checkerApprovedInRound &&
            ($isAdmin || ($isApprover && $isDeputyDirector && $canDirectorateActor)) &&
            !$hasActedApproverInRound;

        $canManageMinutes = $isAdmin || $isCorsecDirectorate || (int) ($meeting->created_by ?? 0) === $actorUserId;
        $canSaveMinutes = $canManageMinutes && in_array($status, ['data_terkirim', 'proses_pembuatan_notulen'], true);
        $canFinalizeMinutes =
            $canManageMinutes && in_array($status, ['proses_pembuatan_notulen', 'proses_sirkulasi_tandatangan'], true);
        $canInputFollowup = in_array($status, ['notulen_final', 'proses_tindaklanjut_hasil_rapat'], true);

        $canUpdateDecision = function ($decision) use (
            $isAdmin,
            $actorUserId,
            $actorDirectorateId,
            $canDirectorateActor,
        ) {
            if ($isAdmin) {
                return true;
            }
            $picUserId = (int) ($decision->pic_user_id ?? 0);
            if ($picUserId > 0) {
                return $picUserId === $actorUserId;
            }
            $ownerDirectorateId = (int) ($decision->owner_directorate_id ?? 0);
            if ($ownerDirectorateId > 0 && $actorDirectorateId > 0) {
                return $ownerDirectorateId === $actorDirectorateId;
            }
            return $canDirectorateActor;
        };

        $allDecisionsDone =
            $meeting->decisions->count() > 0 &&
            $meeting->decisions->every(function ($decision) {
                return in_array((string) ($decision->status ?? ''), ['done', 'dropped'], true);
            });
        $canCompleteFollowup =
            $canManageMinutes &&
            in_array($status, ['notulen_final', 'proses_tindaklanjut_hasil_rapat'], true) &&
            $allDecisionsDone;

        $statusBadgeClass = match ($status) {
            'draft' => 'badge-light',
            'waiting_corsec_approval', 'waiting_direktorat_approval' => 'badge-warning',
            'returned_by_corsec', 'returned_by_direktorat' => 'badge-danger',
            'jadwal_terkirim', 'pending_direktorat', 'data_terkirim' => 'badge-info',
            'proses_pembuatan_notulen',
            'proses_sirkulasi_tandatangan',
            'proses_tindaklanjut_hasil_rapat'
                => 'badge-primary',
            'notulen_final', 'done_tindaklanjut_hasil_rapat' => 'badge-success',
            default => 'badge-light',
        };

        $decisionStatusClass = fn(string $decisionStatus) => match ($decisionStatus) {
            'pending' => 'badge-warning',
            'in_progress' => 'badge-info',
            'done' => 'badge-success',
            'dropped' => 'badge-danger',
            default => 'badge-light',
        };
        $decisionStatusLabel = fn(string $decisionStatus) => match ($decisionStatus) {
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'done' => 'Done',
            'dropped' => 'Dropped',
            default => $decisionStatus ?: '-',
        };

        $statusSteps = [
            'draft' => 'Draft Jadwal Rapat',
            'waiting_corsec_approval' => 'Approval EO Corp Affair',
            'jadwal_terkirim' => 'Jadwal Terkirim',
            'pending_direktorat' => 'Pending Direktorat',
            'waiting_direktorat_approval' => 'Approval EO + DD Direktorat',
            'data_terkirim' => 'Data/Bahan Terkirim',
            'proses_pembuatan_notulen' => 'Input Notulen + Tindaklanjut',
            'proses_sirkulasi_tandatangan' => 'Sirkulasi Tandatangan',
            'notulen_final' => 'Notulen Final',
            'proses_tindaklanjut_hasil_rapat' => 'Progress Tindaklanjut',
            'done_tindaklanjut_hasil_rapat' => 'Done',
            'returned_by_corsec' => 'Revisi Corsec',
            'returned_by_direktorat' => 'Revisi Direktorat',
        ];

        $additionalAgendas = old('additional_agendas', []);
        if (!is_array($additionalAgendas)) {
            $additionalAgendas = [];
        }

        $minutes = $meeting->minutes;
        $minutesDecisionRows = old('decisions');
        if (!is_array($minutesDecisionRows)) {
            $minutesDecisionRows = $meeting->decisions
                ->map(function ($decision) {
                    return [
                        'id' => $decision->id,
                        'decision_text' => $decision->decision_text,
                        'owner_directorate_id' => $decision->owner_directorate_id,
                        'pic_user_id' => $decision->pic_user_id,
                        'target_date' => optional($decision->target_date)->format('Y-m-d'),
                    ];
                })
                ->values()
                ->all();
        }
        if (count($minutesDecisionRows) === 0) {
            $minutesDecisionRows[] = [
                'id' => '',
                'decision_text' => '',
                'owner_directorate_id' => '',
                'pic_user_id' => '',
                'target_date' => '',
            ];
        }

        $decisionUpdates = $meeting->decisions
            ->flatMap(function ($decision) {
                return $decision->updates->map(function ($update) use ($decision) {
                    return [
                        'decision' => $decision,
                        'update' => $update,
                    ];
                });
            })
            ->sortByDesc(function ($row) {
                return $row['update']->created_at;
            })
            ->values();
    @endphp

    <div class="grid gap-5 lg:gap-7.5">
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                <em class="hidden toastr" data-type="error" data-message=" {{ $error }}"></em>
            @endforeach
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detail Meeting #{{ $meeting->id }}</h3>
                <div class="flex gap-2">
                    <a href="{{ route('meeting.index') }}" class="btn btn-sm btn-light">
                        <i class="ki-filled ki-arrow-left"></i> Kembali
                    </a>
                    @if ($canEdit)
                        <a href="{{ route('meeting.edit', $meeting) }}" class="btn btn-sm btn-info">Edit</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-2 lg:gap-7.5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informasi Rapat</h3>
                </div>
                <div class="card-body">
                    <div class="grid gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Kategori:</span>
                            <span
                                class="font-medium">{{ $typeOptions[$meeting->meeting_type] ?? ($meeting->meeting_type ?? '-') }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Judul:</span>
                            <span class="font-medium">{{ $meeting->title ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Tanggal/Jam:</span>
                            <span
                                class="font-medium">{{ $meeting->meeting_at ? $meeting->meeting_at->format('d/m/Y H:i') : '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Lokasi:</span>
                            <span class="font-medium">{{ $meeting->location ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Deskripsi:</span>
                            <span class="font-medium">{{ $meeting->description ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Status:</span>
                            <span
                                class="badge {{ $statusBadgeClass }}">{{ $statusLabels[$status] ?? ($status ?? '-') }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Pembuat:</span>
                            <span class="font-medium">{{ $meeting->createdBy?->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Update Terakhir:</span>
                            <span
                                class="font-medium">{{ $meeting->updated_at ? $meeting->updated_at->format('d/m/Y H:i') : '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Flow Rapat</h3>
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
        </div>

        <div class="grid gap-5 lg:grid-cols-2 lg:gap-7.5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Peserta Rapat</h3>
                </div>
                <div class="card-body">
                    @if ($meeting->participants->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="min-w-[40px]">No</th>
                                        <th class="min-w-[220px]">Direktorat</th>
                                        <th class="min-w-[200px]">User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($meeting->participants as $index => $participant)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $participant->directorate?->name ?? '-' }}</td>
                                            <td>{{ $participant->participantUser?->name ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-sm text-gray-500">Belum ada peserta rapat.</div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Agenda Rapat</h3>
                </div>
                <div class="card-body">
                    @if ($meeting->agendas->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="min-w-[40px]">No</th>
                                        <th class="min-w-[220px]">Agenda</th>
                                        <th class="min-w-[180px]">PIC Direktorat</th>
                                        <th class="min-w-[180px]">PIC User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($meeting->agendas as $agenda)
                                        <tr>
                                            <td>{{ $agenda->order_no ?? $loop->iteration }}</td>
                                            <td>
                                                <div class="font-medium">{{ $agenda->title }}</div>
                                                @if ($agenda->description)
                                                    <div class="text-xs text-gray-500">{{ $agenda->description }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $agenda->ownerDirectorate?->name ?? '-' }}</td>
                                            <td>{{ $agenda->picUser?->name ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-sm text-gray-500">Belum ada agenda rapat.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Bahan Rapat (Direktorat)</h3>
            </div>
            <div class="card-body">
                @if ($meeting->materials->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="min-w-[40px]">No</th>
                                    <th class="min-w-[240px]">Agenda</th>
                                    <th class="min-w-[260px]">File</th>
                                    <th class="min-w-[180px]">Uploader</th>
                                    <th class="min-w-[160px]">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($meeting->materials as $index => $material)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $material->agenda?->title ?? '-' }}</td>
                                        <td>
                                            @if ($material->attachment)
                                                <a class="text-primary hover:underline"
                                                    href="{{ \Illuminate\Support\Facades\Storage::disk($material->attachment->disk ?? 'public')->url($material->attachment->path) }}"
                                                    target="_blank" rel="noopener">
                                                    {{ $material->attachment->original_name ?? $material->attachment->file_name }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $material->uploader?->name ?? '-' }}</td>
                                        <td>{{ $material->uploaded_at ? $material->uploaded_at->format('d/m/Y H:i') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-sm text-gray-500">Belum ada bahan rapat yang diupload.</div>
                @endif
            </div>
        </div>

        @if ($canSubmitPlan)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Submit Jadwal Rapat</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('meeting.submit', $meeting) }}" class="grid gap-4">
                        @csrf
                        <div class="flex flex-col">
                            <label class="form-label">Catatan (opsional)</label>
                            <textarea class="textarea w-full" name="note" rows="3" placeholder="Catatan untuk EO Corp Affair">{{ old('note') }}</textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary">Submit Approval</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @can('corsec.authorize')
            @if ($canCorsecApproval)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Approval EO Corp Affair</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('meeting.corsec.approval', $meeting) }}" class="grid gap-4">
                            @csrf
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan approval..."></textarea>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="submit" class="btn btn-danger" name="action" value="return">Return</button>
                                <button type="submit" class="btn btn-success" name="action"
                                    value="approve">Approve</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan

        @can('corsec.update')
            @if ($canDirectorateSubmit)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Persiapan Rapat Direktorat</h3>
                    </div>
                    <div class="card-body grid gap-5">
                        @if ($canMarkPendingDirectorate && $status !== 'pending_direktorat')
                            <form method="POST" action="{{ route('meeting.mark.pending.directorate', $meeting) }}">
                                @csrf
                                <div class="flex justify-end">
                                    <button type="submit" class="btn btn-light-primary">Set Status Pending
                                        Direktorat</button>
                                </div>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('meeting.directorate.submit', $meeting) }}"
                            enctype="multipart/form-data" class="grid gap-4">
                            @csrf
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex flex-col">
                                    <label class="form-label">Relasi Agenda Bahan (Opsional)</label>
                                    <select class="select" name="material_agenda_id">
                                        <option value="">- Pilih Agenda -</option>
                                        @foreach ($meeting->agendas as $agenda)
                                            <option value="{{ $agenda->id }}"
                                                {{ (string) old('material_agenda_id') === (string) $agenda->id ? 'selected' : '' }}>
                                                {{ $agenda->order_no ? 'Agenda #' . $agenda->order_no . ' - ' : '' }}{{ $agenda->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex flex-col">
                                    <label class="form-label">Upload Bahan Rapat</label>
                                    <input class="file-input" type="file" name="material_files[]"
                                        accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx,.ppt,.pptx" multiple>
                                </div>
                            </div>

                            <div class="flex flex-col">
                                <label class="form-label">Catatan Direktorat (Opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Catatan progres persiapan rapat...">{{ old('note') }}</textarea>
                            </div>

                            <div class="border border-gray-200 rounded-xl p-4 grid gap-4">
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="font-semibold text-gray-800">Agenda Tambahan (Opsional)</h4>
                                    <button type="button" class="btn btn-sm btn-light-primary" id="add-prep-agenda-row">
                                        <i class="ki-filled ki-plus"></i> Tambah Agenda
                                    </button>
                                </div>
                                <div id="prep-agenda-rows" class="grid gap-3">
                                    @foreach ($additionalAgendas as $index => $agenda)
                                        <div class="p-3 border rounded-xl border-gray-200 prep-agenda-row">
                                            <div class="grid gap-3 md:grid-cols-2">
                                                <div class="flex flex-col md:col-span-2">
                                                    <label class="form-label">Agenda Tambahan</label>
                                                    <input class="input" type="text"
                                                        name="additional_agendas[{{ $index }}][title]"
                                                        value="{{ old('additional_agendas.' . $index . '.title', $agenda['title'] ?? '') }}">
                                                </div>
                                                <div class="flex flex-col md:col-span-2">
                                                    <label class="form-label">Deskripsi</label>
                                                    <textarea class="textarea w-full" rows="2" name="additional_agendas[{{ $index }}][description]">{{ old('additional_agendas.' . $index . '.description', $agenda['description'] ?? '') }}</textarea>
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="form-label">PIC Direktorat</label>
                                                    <select class="select"
                                                        name="additional_agendas[{{ $index }}][owner_directorate_id]">
                                                        <option value="">- Pilih Direktorat -</option>
                                                        @foreach ($directorates as $directorate)
                                                            <option value="{{ $directorate->id }}"
                                                                {{ (string) old('additional_agendas.' . $index . '.owner_directorate_id', $agenda['owner_directorate_id'] ?? '') === (string) $directorate->id ? 'selected' : '' }}>
                                                                {{ $directorate->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="form-label">PIC User</label>
                                                    <select class="select"
                                                        name="additional_agendas[{{ $index }}][pic_user_id]">
                                                        <option value="">- Pilih User -</option>
                                                        @foreach ($users as $optionUser)
                                                            <option value="{{ $optionUser->id }}"
                                                                {{ (string) old('additional_agendas.' . $index . '.pic_user_id', $agenda['pic_user_id'] ?? '') === (string) $optionUser->id ? 'selected' : '' }}>
                                                                {{ $optionUser->name }}
                                                                @if ($optionUser->directorate?->name)
                                                                    ({{ $optionUser->directorate->name }})
                                                                @endif
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="flex justify-end mt-2">
                                                <button type="button"
                                                    class="btn btn-xs btn-danger remove-prep-agenda-row">Hapus</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="btn btn-primary">Submit Persiapan Direktorat</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan

        @can('corsec.authorize')
            @if ($canDirectorateCheckerApproval || $canDirectorateApproverApproval)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Approval EO + DD Direktorat</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('meeting.directorate.approval', $meeting) }}"
                            class="grid gap-4">
                            @csrf
                            <div class="text-sm text-gray-600">
                                {{ $canDirectorateCheckerApproval ? 'Tahap approval EO Direktorat.' : 'Tahap approval DD Direktorat.' }}
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan approval..."></textarea>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="submit" class="btn btn-danger" name="action" value="return">Return</button>
                                <button type="submit" class="btn btn-success" name="action"
                                    value="approve">Approve</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan

        @can('corsec.update')
            @if ($canSaveMinutes)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Input Notulen Rapat + Tindaklanjut</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('meeting.minutes.save', $meeting) }}"
                            enctype="multipart/form-data" class="grid gap-4" id="minutes-form">
                            @csrf

                            <div class="flex flex-col">
                                <label class="form-label">Notulen Rapat <span class="text-danger">*</span></label>
                                <textarea class="textarea w-full" name="minutes_text" rows="6" placeholder="Ringkasan notulen rapat...">{{ old('minutes_text', $minutes?->minutes_text) }}</textarea>
                            </div>

                            <div class="flex flex-col">
                                <label class="form-label">Lampiran Notulen (Opsional)</label>
                                <input class="file-input" type="file" name="minutes_file"
                                    accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx,.ppt,.pptx">
                            </div>

                            <div class="border border-gray-200 rounded-xl p-4 grid gap-4">
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="font-semibold text-gray-800">Tindaklanjut Hasil Rapat</h4>
                                    <button type="button" class="btn btn-sm btn-light-primary" id="add-decision-row">
                                        <i class="ki-filled ki-plus"></i> Tambah Item
                                    </button>
                                </div>
                                <div id="decision-rows" class="grid gap-3">
                                    @foreach ($minutesDecisionRows as $index => $decision)
                                        <div class="p-3 border rounded-xl border-gray-200 decision-row">
                                            <input type="hidden" name="decisions[{{ $index }}][id]"
                                                value="{{ old('decisions.' . $index . '.id', $decision['id'] ?? '') }}">
                                            <div class="grid gap-3 md:grid-cols-2">
                                                <div class="flex flex-col md:col-span-2">
                                                    <label class="form-label">Item Tindaklanjut <span
                                                            class="text-danger">*</span></label>
                                                    <textarea class="textarea w-full" rows="2" name="decisions[{{ $index }}][decision_text]">{{ old('decisions.' . $index . '.decision_text', $decision['decision_text'] ?? '') }}</textarea>
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="form-label">PIC Direktorat</label>
                                                    <select class="select"
                                                        name="decisions[{{ $index }}][owner_directorate_id]">
                                                        <option value="">- Pilih Direktorat -</option>
                                                        @foreach ($directorates as $directorate)
                                                            <option value="{{ $directorate->id }}"
                                                                {{ (string) old('decisions.' . $index . '.owner_directorate_id', $decision['owner_directorate_id'] ?? '') === (string) $directorate->id ? 'selected' : '' }}>
                                                                {{ $directorate->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="form-label">PIC User</label>
                                                    <select class="select"
                                                        name="decisions[{{ $index }}][pic_user_id]">
                                                        <option value="">- Pilih User -</option>
                                                        @foreach ($users as $optionUser)
                                                            <option value="{{ $optionUser->id }}"
                                                                {{ (string) old('decisions.' . $index . '.pic_user_id', $decision['pic_user_id'] ?? '') === (string) $optionUser->id ? 'selected' : '' }}>
                                                                {{ $optionUser->name }}
                                                                @if ($optionUser->directorate?->name)
                                                                    ({{ $optionUser->directorate->name }})
                                                                @endif
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="flex flex-col md:col-span-2">
                                                    <label class="form-label">Target Penyelesaian <span
                                                            class="text-danger">*</span></label>
                                                    <input class="input" type="date"
                                                        name="decisions[{{ $index }}][target_date]"
                                                        value="{{ old('decisions.' . $index . '.target_date', $decision['target_date'] ?? '') }}">
                                                </div>
                                            </div>
                                            <div class="flex justify-end mt-2">
                                                <button type="button"
                                                    class="btn btn-xs btn-danger remove-decision-row">Hapus</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex flex-col">
                                <label class="form-label">Catatan (Opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Catatan notulen untuk tim...">{{ old('note') }}</textarea>
                            </div>

                            <label class="flex items-center gap-2 text-sm">
                                <input class="checkbox checkbox-sm" type="checkbox" name="submit_for_signature"
                                    value="1" {{ old('submit_for_signature') ? 'checked' : '' }}>
                                <span>Langsung submit untuk sirkulasi tandatangan</span>
                            </label>

                            <div class="flex justify-end">
                                <button type="submit" class="btn btn-primary">Simpan Notulen</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan

        @can('corsec.update')
            @if ($canFinalizeMinutes)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Finalisasi Notulen</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('meeting.minutes.finalize', $meeting) }}"
                            enctype="multipart/form-data" class="grid gap-4">
                            @csrf
                            <div class="flex flex-col">
                                <label class="form-label">Upload Notulen Final <span class="text-danger">*</span></label>
                                <input class="file-input" type="file" name="final_minutes_file"
                                    accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx,.ppt,.pptx" required>
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (Opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Catatan finalisasi notulen...">{{ old('note') }}</textarea>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="btn btn-primary">Upload Notulen Final</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Notulen Rapat</h3>
            </div>
            <div class="card-body">
                @if ($minutes)
                    <div class="grid gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Status Notulen:</span>
                            <span class="font-medium">{{ $minutes->status ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Draft Notulen:</span>
                            <span class="font-medium">
                                @if ($minutes->minutesAttachment)
                                    <a class="text-primary hover:underline"
                                        href="{{ \Illuminate\Support\Facades\Storage::disk($minutes->minutesAttachment->disk ?? 'public')->url($minutes->minutesAttachment->path) }}"
                                        target="_blank" rel="noopener">
                                        {{ $minutes->minutesAttachment->original_name ?? $minutes->minutesAttachment->file_name }}
                                    </a>
                                @else
                                    -
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Notulen Final:</span>
                            <span class="font-medium">
                                @if ($minutes->finalMinutesAttachment)
                                    <a class="text-primary hover:underline"
                                        href="{{ \Illuminate\Support\Facades\Storage::disk($minutes->finalMinutesAttachment->disk ?? 'public')->url($minutes->finalMinutesAttachment->path) }}"
                                        target="_blank" rel="noopener">
                                        {{ $minutes->finalMinutesAttachment->original_name ?? $minutes->finalMinutesAttachment->file_name }}
                                    </a>
                                @else
                                    -
                                @endif
                            </span>
                        </div>
                        <div>
                            <div class="text-gray-600 mb-1">Ringkasan Notulen:</div>
                            <div class="p-3 border rounded-xl border-gray-200 text-sm whitespace-pre-wrap">
                                {{ $minutes->minutes_text ?: '-' }}
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-sm text-gray-500">Notulen rapat belum diinput.</div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Tabulasi Tindaklanjut Hasil Rapat</h3>
            </div>
            <div class="card-body">
                @if ($meeting->decisions->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="min-w-[40px]">No</th>
                                    <th class="min-w-[280px]">Tindaklanjut</th>
                                    <th class="min-w-[180px]">PIC Direktorat</th>
                                    <th class="min-w-[180px]">PIC User</th>
                                    <th class="min-w-[130px]">Target</th>
                                    <th class="min-w-[120px]">Progress</th>
                                    <th class="min-w-[140px]">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($meeting->decisions as $index => $decision)
                                    @php
                                        $latestUpdate = $decision->updates->sortByDesc('id')->first();
                                        $progressValue =
                                            $latestUpdate?->progress_percent ??
                                            ((string) $decision->status === 'done' ? 100 : 0);
                                    @endphp
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $decision->decision_text }}</td>
                                        <td>{{ $decision->ownerDirectorate?->name ?? '-' }}</td>
                                        <td>{{ $decision->picUser?->name ?? '-' }}</td>
                                        <td>{{ $decision->target_date ? $decision->target_date->format('d/m/Y') : '-' }}
                                        </td>
                                        <td>{{ $progressValue }}%</td>
                                        <td>
                                            <span
                                                class="badge {{ $decisionStatusClass((string) $decision->status) }}">{{ $decisionStatusLabel((string) $decision->status) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-sm text-gray-500">Belum ada item tindaklanjut rapat.</div>
                @endif
            </div>
        </div>

        @can('corsec.update')
            @if ($canInputFollowup && $meeting->decisions->count() > 0)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Update Progress Tindaklanjut</h3>
                    </div>
                    <div class="card-body grid gap-4">
                        @foreach ($meeting->decisions as $decision)
                            @php
                                $decisionStatus = (string) ($decision->status ?? '');
                                $canUpdateThisDecision =
                                    $canUpdateDecision($decision) &&
                                    !in_array($decisionStatus, ['done', 'dropped'], true);
                            @endphp
                            @if ($canUpdateThisDecision)
                                <form method="POST" action="{{ route('meeting.decision.update', [$meeting, $decision]) }}"
                                    enctype="multipart/form-data"
                                    class="p-4 border rounded-xl border-gray-200 grid gap-4 followup-update-form">
                                    @csrf
                                    <div class="font-medium text-gray-800">{{ $decision->decision_text }}</div>
                                    <div class="text-xs text-gray-500">
                                        Target: {{ $decision->target_date ? $decision->target_date->format('d/m/Y') : '-' }} |
                                        PIC: {{ $decision->picUser?->name ?? ($decision->ownerDirectorate?->name ?? '-') }}
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="flex flex-col">
                                            <label class="form-label">Jenis Update <span class="text-danger">*</span></label>
                                            <select class="select js-update-type" name="update_type" required>
                                                <option value="progress">Progress</option>
                                                <option value="done">Selesai</option>
                                            </select>
                                        </div>
                                        <div class="flex flex-col js-progress-wrap">
                                            <label class="form-label">Progress (%)</label>
                                            <input class="input" type="number" min="0" max="100"
                                                name="progress_percent" value="0">
                                        </div>
                                        <div class="flex flex-col">
                                            <label class="form-label">Tanggal Realisasi <span
                                                    class="text-danger">*</span></label>
                                            <input class="input" type="date" name="happened_at"
                                                value="{{ now()->format('Y-m-d') }}" required>
                                        </div>
                                        <div class="flex flex-col">
                                            <label class="form-label">Sesuai Target? <span
                                                    class="text-danger">*</span></label>
                                            <select class="select js-on-target" name="is_on_target" required>
                                                <option value="1">Ya</option>
                                                <option value="0">Tidak</option>
                                            </select>
                                        </div>
                                        <div class="flex flex-col md:col-span-2 js-reason-wrap hidden">
                                            <label class="form-label">Alasan Tidak Sesuai Target <span
                                                    class="text-danger">*</span></label>
                                            <textarea class="textarea w-full" name="reason" rows="2" placeholder="Wajib diisi jika tidak sesuai target"></textarea>
                                        </div>
                                        <div class="flex flex-col md:col-span-2">
                                            <label class="form-label">Catatan</label>
                                            <textarea class="textarea w-full" name="note" rows="2" placeholder="Catatan update progress"></textarea>
                                        </div>
                                        <div class="flex flex-col md:col-span-2">
                                            <label class="form-label">Bukti Progress <span
                                                    class="text-danger">*</span></label>
                                            <input class="file-input" type="file" name="evidence_files[]"
                                                accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx,.ppt,.pptx" multiple
                                                required>
                                        </div>
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="submit" class="btn btn-primary">Submit Update</button>
                                    </div>
                                </form>
                            @endif
                        @endforeach

                        @if ($canCompleteFollowup)
                            <form method="POST" action="{{ route('meeting.followup.complete', $meeting) }}">
                                @csrf
                                <div class="flex justify-end">
                                    <button type="submit" class="btn btn-success">Tandai Tindaklanjut Selesai</button>
                                </div>
                            </form>
                        @elseif (in_array($status, ['notulen_final', 'proses_tindaklanjut_hasil_rapat'], true))
                            <div class="text-sm text-gray-500">
                                Tindaklanjut dapat ditandai selesai setelah semua item statusnya <strong>done/dropped</strong>.
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @endcan

        @if ($decisionUpdates->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Riwayat Progress Tindaklanjut</h3>
                </div>
                <div class="card-body">
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="min-w-[240px]">Item Tindaklanjut</th>
                                    <th class="min-w-[120px]">Jenis</th>
                                    <th class="min-w-[90px]">Progress</th>
                                    <th class="min-w-[130px]">Realisasi</th>
                                    <th class="min-w-[130px]">On Target</th>
                                    <th class="min-w-[240px]">Catatan</th>
                                    <th class="min-w-[190px]">Updater</th>
                                    <th class="min-w-[220px]">Bukti</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($decisionUpdates as $row)
                                    @php
                                        $decision = $row['decision'];
                                        $update = $row['update'];
                                    @endphp
                                    <tr>
                                        <td>{{ $decision->decision_text }}</td>
                                        <td>{{ (string) $update->update_type === 'done' ? 'Selesai' : 'Progress' }}</td>
                                        <td>{{ $update->progress_percent ?? 0 }}%</td>
                                        <td>{{ $update->happened_at ? $update->happened_at->format('d/m/Y') : '-' }}</td>
                                        <td>
                                            @if (is_null($update->is_on_target))
                                                -
                                            @else
                                                {{ $update->is_on_target ? 'Ya' : 'Tidak' }}
                                            @endif
                                        </td>
                                        <td>
                                            {{ $update->note ?? '-' }}
                                            @if (!$update->is_on_target && $update->reason)
                                                <div class="text-xs text-danger mt-1">Alasan: {{ $update->reason }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $update->updater?->name ?? '-' }}</td>
                                        <td>
                                            @if ($update->attachables->count() > 0)
                                                <div class="flex flex-col gap-1">
                                                    @foreach ($update->attachables as $attachable)
                                                        @if ($attachable->attachment)
                                                            <a class="text-primary hover:underline text-xs"
                                                                href="{{ \Illuminate\Support\Facades\Storage::disk($attachable->attachment->disk ?? 'public')->url($attachable->attachment->path) }}"
                                                                target="_blank" rel="noopener">
                                                                {{ $attachable->attachment->original_name ?? $attachable->attachment->file_name }}
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
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
                                    <th class="min-w-[140px]">Status</th>
                                    <th class="min-w-[220px]">Aktor</th>
                                    <th class="min-w-[260px]">Catatan</th>
                                    <th class="min-w-[170px]">Waktu</th>
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
                                                    class="text-xs text-gray-500">({{ $approval->actor->directorate->name }})</span>
                                            @endif
                                        </td>
                                        <td>{{ $approval->note ?? '-' }}</td>
                                        <td>
                                            {{ $approval->acted_at ? $approval->acted_at->format('d/m/Y H:i') : ($approval->created_at ? $approval->created_at->format('d/m/Y H:i') : '-') }}
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
                    $comments = $meeting->comments->sortByDesc('created_at')->values();
                @endphp
                @if ($comments->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="min-w-[280px]">Catatan</th>
                                    <th class="min-w-[200px]">Oleh</th>
                                    <th class="min-w-[170px]">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($comments as $comment)
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
                    <div class="text-sm text-gray-500">Belum ada catatan untuk meeting ini.</div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @php
        $directorateJsOptions = $directorates->map(fn($d) => ['id' => $d->id, 'name' => $d->name])->values();

        $userJsOptions = $users
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'directorate' => $u->directorate?->name,
                ];
            })
            ->values();
    @endphp
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const directorateOptions = @json($directorateJsOptions);
            const userOptions = @json($userJsOptions);

            const buildDirectorateOptions = () => {
                let html = '<option value="">- Pilih Direktorat -</option>';
                directorateOptions.forEach((item) => {
                    html += `<option value="${item.id}">${item.name}</option>`;
                });
                return html;
            };

            const buildUserOptions = () => {
                let html = '<option value="">- Pilih User -</option>';
                userOptions.forEach((item) => {
                    const label = item.directorate ? `${item.name} (${item.directorate})` : item.name;
                    html += `<option value="${item.id}">${label}</option>`;
                });
                return html;
            };

            const prepAgendaRows = document.getElementById('prep-agenda-rows');
            const addPrepAgendaButton = document.getElementById('add-prep-agenda-row');
            if (prepAgendaRows && addPrepAgendaButton) {
                const renumberPrepAgendaRows = () => {
                    prepAgendaRows.querySelectorAll('.prep-agenda-row').forEach((row, index) => {
                        row.querySelectorAll('[name]').forEach((input) => {
                            input.name = input.name.replace(/additional_agendas\[\d+\]/,
                                `additional_agendas[${index}]`);
                        });
                    });
                };

                addPrepAgendaButton.addEventListener('click', function() {
                    const index = prepAgendaRows.querySelectorAll('.prep-agenda-row').length;
                    const wrapper = document.createElement('div');
                    wrapper.className = 'p-3 border rounded-xl border-gray-200 prep-agenda-row';
                    wrapper.innerHTML = `
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="flex flex-col md:col-span-2">
                                <label class="form-label">Agenda Tambahan</label>
                                <input class="input" type="text" name="additional_agendas[${index}][title]">
                            </div>
                            <div class="flex flex-col md:col-span-2">
                                <label class="form-label">Deskripsi</label>
                                <textarea class="textarea w-full" rows="2" name="additional_agendas[${index}][description]"></textarea>
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">PIC Direktorat</label>
                                <select class="select" name="additional_agendas[${index}][owner_directorate_id]">
                                    ${buildDirectorateOptions()}
                                </select>
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">PIC User</label>
                                <select class="select" name="additional_agendas[${index}][pic_user_id]">
                                    ${buildUserOptions()}
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end mt-2">
                            <button type="button" class="btn btn-xs btn-danger remove-prep-agenda-row">Hapus</button>
                        </div>
                    `;
                    prepAgendaRows.appendChild(wrapper);
                });

                prepAgendaRows.addEventListener('click', function(event) {
                    const removeButton = event.target.closest('.remove-prep-agenda-row');
                    if (!removeButton) {
                        return;
                    }
                    const row = removeButton.closest('.prep-agenda-row');
                    if (row) {
                        row.remove();
                        renumberPrepAgendaRows();
                    }
                });
            }

            const decisionRows = document.getElementById('decision-rows');
            const addDecisionButton = document.getElementById('add-decision-row');
            if (decisionRows && addDecisionButton) {
                const renumberDecisionRows = () => {
                    decisionRows.querySelectorAll('.decision-row').forEach((row, index) => {
                        row.querySelectorAll('[name]').forEach((input) => {
                            input.name = input.name.replace(/decisions\[\d+\]/,
                                `decisions[${index}]`);
                        });
                    });
                };

                addDecisionButton.addEventListener('click', function() {
                    const index = decisionRows.querySelectorAll('.decision-row').length;
                    const wrapper = document.createElement('div');
                    wrapper.className = 'p-3 border rounded-xl border-gray-200 decision-row';
                    wrapper.innerHTML = `
                        <input type="hidden" name="decisions[${index}][id]" value="">
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="flex flex-col md:col-span-2">
                                <label class="form-label">Item Tindaklanjut <span class="text-danger">*</span></label>
                                <textarea class="textarea w-full" rows="2" name="decisions[${index}][decision_text]"></textarea>
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">PIC Direktorat</label>
                                <select class="select" name="decisions[${index}][owner_directorate_id]">
                                    ${buildDirectorateOptions()}
                                </select>
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">PIC User</label>
                                <select class="select" name="decisions[${index}][pic_user_id]">
                                    ${buildUserOptions()}
                                </select>
                            </div>
                            <div class="flex flex-col md:col-span-2">
                                <label class="form-label">Target Penyelesaian <span class="text-danger">*</span></label>
                                <input class="input" type="date" name="decisions[${index}][target_date]">
                            </div>
                        </div>
                        <div class="flex justify-end mt-2">
                            <button type="button" class="btn btn-xs btn-danger remove-decision-row">Hapus</button>
                        </div>
                    `;
                    decisionRows.appendChild(wrapper);
                });

                decisionRows.addEventListener('click', function(event) {
                    const removeButton = event.target.closest('.remove-decision-row');
                    if (!removeButton) {
                        return;
                    }
                    const row = removeButton.closest('.decision-row');
                    if (row && decisionRows.querySelectorAll('.decision-row').length > 1) {
                        row.remove();
                        renumberDecisionRows();
                    }
                });
            }

            document.querySelectorAll('.followup-update-form').forEach((form) => {
                const updateType = form.querySelector('.js-update-type');
                const progressWrap = form.querySelector('.js-progress-wrap');
                const progressInput = form.querySelector('[name="progress_percent"]');
                const onTargetSelect = form.querySelector('.js-on-target');
                const reasonWrap = form.querySelector('.js-reason-wrap');
                const reasonInput = form.querySelector('[name="reason"]');

                if (!updateType || !progressWrap || !progressInput || !onTargetSelect || !reasonWrap || !
                    reasonInput) {
                    return;
                }

                const syncUpdateType = () => {
                    if (updateType.value === 'done') {
                        progressInput.value = '100';
                        progressInput.setAttribute('readonly', 'readonly');
                    } else {
                        progressInput.removeAttribute('readonly');
                    }
                };

                const syncOnTarget = () => {
                    if (onTargetSelect.value === '0') {
                        reasonWrap.classList.remove('hidden');
                        reasonInput.setAttribute('required', 'required');
                    } else {
                        reasonWrap.classList.add('hidden');
                        reasonInput.removeAttribute('required');
                    }
                };

                updateType.addEventListener('change', syncUpdateType);
                onTargetSelect.addEventListener('change', syncOnTarget);
                syncUpdateType();
                syncOnTarget();
            });
        });
    </script>
@endpush
