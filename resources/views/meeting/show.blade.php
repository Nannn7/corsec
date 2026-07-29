@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('meeting.show', $meeting) }}
@endsection

@section('content')
    @php
        $additionalParticipantDirectorateOptions = $directorates
            ->filter(function ($directorate) {
                return trim((string) $directorate->displayName()) !== '';
            })
            ->groupBy(function ($directorate) {
                return strtolower(preg_replace('/\s+/', ' ', trim((string) $directorate->displayName())));
            })
            ->map(function ($group) {
                $primaryDirectorate = $group->first();

                return [
                    'id' => (int) $primaryDirectorate->id,
                    'label' => preg_replace('/\s+/', ' ', trim((string) $primaryDirectorate->displayName())),
                    'member_count' => $group->count(),
                    'member_ids' => $group->pluck('id')->map(fn($id) => (int) $id)->values()->all(),
                ];
            })
            ->values();

        $additionalParticipantSelectionMap = $additionalParticipantDirectorateOptions
            ->flatMap(function (array $option) {
                return collect($option['member_ids'] ?? [])->mapWithKeys(
                    fn($memberId) => [(string) $memberId => (string) $option['id']],
                );
            })
            ->all();

        $selectedAdditionalParticipantIds = collect(old('additional_participants', []))
            ->filter(fn($id) => $id !== null && $id !== '')
            ->map(function ($id) use ($additionalParticipantSelectionMap) {
                return (string) ($additionalParticipantSelectionMap[(string) $id] ?? $id);
            })
            ->unique()
            ->values()
            ->all();

        $requiredMark = '<span class="text-danger">*</span>';
        $fieldErrorClass = fn(string $field): string => $errors->has($field) ? 'border-danger bg-danger-light' : '';
    @endphp

    <div class="grid gap-5 lg:gap-7.5">
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                <em class="hidden toastr" data-type="error" data-message=" {{ $error }}"></em>
            @endforeach
        @endif

        @if ($meeting->isDirektoratType() && $isAwaitingDirectorateResponse && $isReminderWindow)
            <div class="alert alert-warning">
                H-1 jadwal rapat. PIC/user terkait direktorat harus segera memberikan tanggapan
                <strong>On Schedule</strong>, <strong>Cancel</strong>, atau <strong>Reschedule</strong>
                sebelum {{ $meeting->directorateResponseDeadlineLabel() ?? 'deadline H-1' }}.
            </div>
        @endif

        @if ($meeting->isDirektoratType() && $isClosedNotConducted)
            <div class="alert alert-warning">
                Jadwal rapat ini tidak dijalankan karena tidak ada tanggapan dari direktorat sampai hari H.
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detail Meeting {{ $meeting->title }}</h3>
                <div class="flex gap-2">
                    <a href="{{ route('meeting.index') }}" class="btn btn-sm btn-light">
                        <i class="ki-filled ki-arrow-left"></i> Kembali
                    </a>
                    <a href="{{ route('report.index', ['module' => 'meeting']) }}" class="btn btn-sm btn-warning">
                        Tabulasi
                    </a>
                    @if ($canOpenPresentationMode)
                        <a href="{{ route('meeting.presentation', $meeting) }}" class="btn btn-sm btn-primary"
                            target="_blank" rel="noopener">
                            Mode Presentasi
                        </a>
                    @endif
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
                        @if ($meeting->isDirektoratType())
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Tanggapan Direktorat:</span>
                                <span
                                    class="font-medium">{{ $responseLabels[$meeting->directorate_response_status] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Ditanggapi Oleh:</span>
                                <span class="font-medium">{{ $meeting->directorateRespondedBy?->name ?? '-' }}</span>
                            </div>
                        @endif
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
                    <h3 class="card-title">Status Rapat</h3>
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
                    @if ($participantDisplayRows->count() > 0)
                        <div style="max-height: 24rem; overflow: auto; padding-right: 0.25rem;">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="min-w-[40px]"
                                            style="position: sticky; top: 0; z-index: 10; background: #fff;">No</th>
                                        <th class="min-w-[220px]"
                                            style="position: sticky; top: 0; z-index: 10; background: #fff;">Direktorat</th>
                                        <th class="min-w-[200px]"
                                            style="position: sticky; top: 0; z-index: 10; background: #fff;">PIC</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($participantDisplayRows as $index => $participantRow)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $participantRow['directorate'] ?? '-' }}</td>
                                            <td>{{ $participantRow['pic'] ?? '-' }}</td>
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
                        <div style="max-height: 24rem; overflow: auto; padding-right: 0.25rem;">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="min-w-[40px]"
                                            style="position: sticky; top: 0; z-index: 10; background: #fff;">No</th>
                                        <th class="min-w-[220px]"
                                            style="position: sticky; top: 0; z-index: 10; background: #fff;">Agenda</th>
                                        <th class="min-w-[180px]"
                                            style="position: sticky; top: 0; z-index: 10; background: #fff;">PIC Direktorat
                                        </th>
                                        <th class="min-w-[180px]"
                                            style="position: sticky; top: 0; z-index: 10; background: #fff;">PIC User</th>
                                        <th class="min-w-[220px]"
                                            style="position: sticky; top: 0; z-index: 10; background: #fff;">Sumber</th>
                                        <th class="min-w-[120px]"
                                            style="position: sticky; top: 0; z-index: 10; background: #fff;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($meeting->agendas as $agenda)
                                        <tr>
                                            <td>{{ $agenda->order_no ?? $loop->iteration }}</td>
                                            <td>
                                                <div class="font-medium">
                                                    {{ $agenda->title }}
                                                    @if ($agenda->sourceDecision)
                                                        <span class="badge badge-light-info ms-2">Agenda Default</span>
                                                    @endif
                                                </div>
                                                @if ($agenda->description)
                                                    <div class="text-xs text-gray-500">{{ $agenda->description }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $agenda->ownerDirectorate?->displayName() ?? '-' }}</td>
                                            <td>{{ $agenda->picUser?->name ?? '-' }}</td>
                                            <td>
                                                @if ($agenda->sourceDecision)
                                                    <div class="font-medium">
                                                        {{ $agenda->sourceDecision->meeting?->title ?? '-' }}
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ $agenda->sourceDecision->decision_key ?? '-' }}
                                                        @if ($agenda->sourceDecision->meeting?->meeting_at)
                                                            |
                                                            {{ $agenda->sourceDecision->meeting->meeting_at->format('d/m/Y H:i') }}
                                                        @endif
                                                    </div>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if ($agenda->sourceDecision)
                                                    <span
                                                        class="badge {{ $decisionStatusBadgeClasses[(string) $agenda->sourceDecision->status] ?? 'badge-light' }}">
                                                        {{ $decisionStatusLabels[(string) $agenda->sourceDecision->status] ?? ((string) $agenda->sourceDecision->status ?: '-') }}
                                                    </span>
                                                @else
                                                    -
                                                @endif
                                            </td>
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
                                                    href="{{ rtrim(url()->current(), '/') . '/materials/' . $material->getKey() . '/file' }}"
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
                            <textarea class="textarea w-full" name="note" rows="3" placeholder="Catatan untuk Corporate Secretary">{{ old('note') }}</textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary">Submit Approval</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @can('meeting.authorize')
            @if ($canCorsecApproval)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Corporate Secretary</h3>
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

        @if ($canCorsecUpdateAction && $canDirectorateResponse)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Tanggapan Jadwal Direktorat</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('meeting.directorate.response', $meeting) }}"
                        class="grid gap-4">
                        @csrf
                        <div class="text-sm text-gray-600">
                            PIC direktorat wajib memilih tanggapan sebelum input agenda/persiapan rapat.
                        </div>
                        @if ($meeting->isDirektoratType() && $meeting->directorateResponseDeadlineLabel())
                            <div class="text-sm text-gray-600">
                                Target tanggapan jadwal (H-1):
                                <span class="font-medium">{{ $meeting->directorateResponseDeadlineLabel() }}</span>
                            </div>
                        @endif
                        <div class="flex flex-col">
                            <label class="form-label">Catatan (opsional)</label>
                            <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan tanggapan..."></textarea>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="submit" class="btn btn-danger" name="action" value="cancel">Cancel</button>
                            <button type="submit" class="btn btn-warning" name="action"
                                value="reschedule">Reschedule</button>
                            <button type="submit" class="btn btn-success" name="action" value="on_schedule">On
                                Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if (
            $canCorsecUpdateAction &&
                $meeting->isDirektoratType() &&
                !$canDirectorateResponse &&
                ($isOnScheduleResponse || $isRescheduleResponse || $isCancelResponse || $isNoResponse))
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Tanggapan Jadwal Direktorat</h3>
                </div>
                <div class="card-body grid gap-2">
                    <div class="text-sm {{ $directorateResponseSummaryClass }}">
                        {{ $directorateResponseSummaryMessage }}
                        @if (!$isNoResponse)
                            <span class="font-medium">{{ $responseLabels[$directorateResponseStatus] ?? '-' }}</span>.
                        @endif
                    </div>
                    <div class="text-xs text-gray-600">
                        Ditanggapi oleh: <span
                            class="font-medium">{{ $meeting->directorateRespondedBy?->name ?? '-' }}</span>
                    </div>
                    <div class="text-xs text-gray-600">
                        Waktu tanggapan:
                        <span
                            class="font-medium">{{ $meeting->directorate_responded_at ? $meeting->directorate_responded_at->format('d/m/Y H:i') : '-' }}</span>
                    </div>
                    @if ($meeting->directorate_response_note)
                        <div class="text-xs text-gray-600">
                            Catatan:
                            <span class="font-medium">{{ $meeting->directorate_response_note }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if ($canCorsecUpdateAction)
            @if ($canDirectorateSubmit)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ $preparationCardTitle }}</h3>
                    </div>
                    <div class="card-body grid gap-0">
                        {{-- <div class="text-sm text-gray-600 mb-3">
                            {{ $preparationHelperText }}
                        </div> --}}
                        @if ($canMarkPendingDirectorate && $status !== 'pending_direktorat')
                            <form method="POST" action="{{ route('meeting.mark.pending.directorate', $meeting) }}">
                                @csrf
                                <div class="flex justify-end">
                                    <button type="submit" class="btn btn-warning">Pending PIC</button>
                                </div>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('meeting.directorate.submit', $meeting) }}"
                            enctype="multipart/form-data" class="grid gap-4">
                            @csrf
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex flex-col">
                                    <label class="form-label">Relasi Agenda Bahan</label>
                                    <select class="select" name="material_agenda_id">
                                        <option value="">- Pilih Agenda -</option>
                                        @foreach ($materialAgendaOptions as $agenda)
                                            <option value="{{ $agenda->id }}"
                                                {{ (string) old('material_agenda_id') === (string) $agenda->id ? 'selected' : '' }}>
                                                {{ $agenda->order_no ? 'Agenda #' . $agenda->order_no . ' - ' : '' }}{{ $agenda->title }}
                                                @if ($agenda->sourceDecision)
                                                    {{ ' [' . ($agenda->sourceDecision->decision_key ?? '-') . ' - ' . ($decisionStatusLabels[(string) $agenda->sourceDecision->status] ?? ((string) $agenda->sourceDecision->status ?: '-')) . ']' }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Wajib dipilih saat upload bahan rapat agar progress tiap agenda bisa tervalidasi.
                                        Agenda yang tampil hanya agenda dengan PIC user Anda.
                                    </div>
                                </div>
                                <div class="flex flex-col">
                                    <label class="form-label">Upload Bahan Rapat</label>
                                    <input class="file-input" type="file" name="material_files[]"
                                        accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx,.ppt,.pptx" multiple>
                                </div>
                            </div>

                            <div class="border border-gray-200 rounded-xl p-4 grid gap-3">
                                <div class="font-semibold text-gray-800">Undang Peserta Tambahan (Opsional)</div>
                                <div class="grid gap-2" style="max-height: 180px; overflow: auto;">
                                    @foreach ($additionalParticipantDirectorateOptions as $directorateOption)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input class="checkbox checkbox-sm" type="checkbox"
                                                name="additional_participants[]" value="{{ $directorateOption['id'] }}"
                                                {{ in_array((string) $directorateOption['id'], $selectedAdditionalParticipantIds, true) ? 'checked' : '' }}>
                                            <span>
                                                {{ $directorateOption['label'] }}
                                                @if (($directorateOption['member_count'] ?? 0) > 1)
                                                    ({{ $directorateOption['member_count'] }} unit)
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex flex-col">
                                <label class="form-label">{{ $preparationNoteLabel }}</label>
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
                                                                {{ $directorate->displayName() }}
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
                                                                @if ($optionUser->directorate)
                                                                    ({{ $optionUser->directorate->displayName() }})
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
                                <button type="submit" class="btn btn-primary">{{ $preparationSubmitLabel }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endif

        @can('meeting.authorize')
            @if ($canDirectorateCheckerApproval || $canDirectorateApproverApproval)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Approval Direktorat</h3>
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

        @if ($canCorsecUpdateAction)
            @if ($canSaveMinutes)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            {{ $meeting->isDirektoratType() ? 'Input Notulen Rapat Direktorat' : 'Input Notulen Rapat + Tindaklanjut' }}
                        </h3>
                    </div>
                    <div class="card-body">
                        @if ($meeting->isDirektoratType())
                            <form method="POST" action="{{ route('meeting.minutes.save', $meeting) }}"
                                enctype="multipart/form-data" class="grid gap-4" id="directorate-minutes-form">
                                @csrf

                                <div class="grid gap-4 xl:grid-cols-2">
                                    <div class="flex flex-col">
                                        <label class="form-label">Catatan Umum Notulen (Opsional)</label>
                                        <textarea class="textarea w-full {{ $fieldErrorClass('minutes_text') }}" name="minutes_text" rows="5"
                                            placeholder="Catatan umum rapat direktorat...">{{ old('minutes_text', $minutes?->minutes_text) }}</textarea>
                                        @error('minutes_text')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                    </div>
                                    <div class="grid gap-4 content-start">
                                        <div class="flex flex-col">
                                            <label class="form-label">Lampiran Template/Notulen (Opsional)</label>
                                            <input class="file-input {{ $fieldErrorClass('minutes_file') }}"
                                                type="file" name="minutes_file"
                                                accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx,.ppt,.pptx">
                                            @error('minutes_file')
                                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="border border-gray-200 rounded-xl p-4 grid gap-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <h4 class="font-semibold text-gray-800">Materi Pembahasan Rapat Direktorat</h4>
                                        <button type="button" class="btn btn-sm btn-light-primary"
                                            id="add-minutes-agenda-row">
                                            <i class="ki-filled ki-plus"></i> Tambah Free Text
                                        </button>
                                    </div>
                                    @error('minutes_agendas')
                                        <em class="text-sm alert text-danger">{{ $message }}</em>
                                    @enderror
                                    <div class="overflow-x-auto">
                                        <table class="table table-bordered align-top min-w-[1100px]">
                                            <thead>
                                                <tr>
                                                    <th class="min-w-[60px]">No</th>
                                                    <th class="min-w-[320px]">Materi Pembahasan {!! $requiredMark !!}
                                                    </th>
                                                    <th class="min-w-[260px]">PIC {!! $requiredMark !!}</th>
                                                    <th class="min-w-[380px]">Tindak Lanjut</th>
                                                    <th class="min-w-[90px]">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="minutes-agenda-rows">
                                                @foreach ($minutesAgendaRows as $index => $row)
                                                    @php
                                                        $agendaId = (string) ($row['agenda_id'] ?? '');
                                                        $sourceDecisionId = (string) ($row['source_decision_id'] ?? '');
                                                        $decisionId = (string) ($row['decision_id'] ?? '');
                                                        $decisionLocked = $decisionId !== '';
                                                        $sourceLocked = $sourceDecisionId !== '';
                                                        $followupEnabled =
                                                            filter_var(
                                                                $row['followup_enabled'] ?? false,
                                                                FILTER_VALIDATE_BOOLEAN,
                                                            ) || $decisionLocked;
                                                        $selectedExistingDecisionId =
                                                            (string) ($row['existing_decision_id'] ??
                                                                ($sourceDecisionId ?: ''));
                                                        $selectedStatus = (string) ($row['status'] ?? 'in_progress');
                                                        $agendaModel =
                                                            $agendaId !== ''
                                                                ? $meeting->agendas->firstWhere('id', (int) $agendaId)
                                                                : null;
                                                        $sourceDecision = $agendaModel?->sourceDecision;
                                                        $canRemove = !$decisionLocked && !$sourceLocked;
                                                    @endphp
                                                    <tr class="minutes-agenda-row"
                                                        data-followup-locked="{{ $decisionLocked ? '1' : '0' }}"
                                                        data-source-locked="{{ $sourceLocked ? '1' : '0' }}">
                                                        <td class="font-medium text-gray-700 row-number">
                                                            {{ $index + 1 }}</td>
                                                        <td>
                                                            <input type="hidden"
                                                                name="minutes_agendas[{{ $index }}][agenda_id]"
                                                                value="{{ $agendaId }}">
                                                            <input type="hidden"
                                                                name="minutes_agendas[{{ $index }}][source_decision_id]"
                                                                value="{{ $sourceDecisionId }}">
                                                            <input type="hidden"
                                                                name="minutes_agendas[{{ $index }}][description]"
                                                                value="{{ $row['description'] ?? '' }}">
                                                            <div class="grid gap-2">
                                                                <textarea class="textarea w-full minutes-agenda-title {{ $fieldErrorClass('minutes_agendas.' . $index . '.title') }}"
                                                                    rows="3" name="minutes_agendas[{{ $index }}][title]" placeholder="Materi pembahasan..."
                                                                    {{ $sourceLocked ? 'readonly' : '' }}>{{ $row['title'] ?? '' }}</textarea>
                                                                @error('minutes_agendas.' . $index . '.title')
                                                                    <em
                                                                        class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                @enderror
                                                                @if ($sourceDecision)
                                                                    <div class="text-xs text-gray-500">
                                                                        <span
                                                                            class="badge badge-light-info">Mandatory</span>
                                                                        {{ $sourceDecision->issue_key ?? ($sourceDecision->decision_key ?? '-') }}
                                                                        @if ($sourceDecision->meeting?->title)
                                                                            | {{ $sourceDecision->meeting->title }}
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td>
                                                            @if ($sourceLocked)
                                                                <input type="hidden"
                                                                    name="minutes_agendas[{{ $index }}][owner_directorate_id]"
                                                                    value="{{ $row['owner_directorate_id'] ?? '' }}">
                                                                <input type="hidden"
                                                                    name="minutes_agendas[{{ $index }}][pic_user_id]"
                                                                    value="{{ $row['pic_user_id'] ?? '' }}">
                                                                <div class="grid gap-1 text-sm">
                                                                    <div class="font-medium">
                                                                        {{ optional($directorates->firstWhere('id', (int) ($row['owner_directorate_id'] ?? 0)))->displayName() ?? '-' }}
                                                                    </div>
                                                                    <div class="text-gray-500">
                                                                        {{ optional($users->firstWhere('id', (int) ($row['pic_user_id'] ?? 0)))->name ?? '-' }}
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <div class="grid gap-2">
                                                                    <select
                                                                        class="select minutes-agenda-owner {{ $fieldErrorClass('minutes_agendas.' . $index . '.owner_directorate_id') }}"
                                                                        name="minutes_agendas[{{ $index }}][owner_directorate_id]">
                                                                        <option value="">- Pilih Direktorat -
                                                                        </option>
                                                                        @foreach ($directorates as $directorate)
                                                                            <option value="{{ $directorate->id }}"
                                                                                {{ (string) ($row['owner_directorate_id'] ?? '') === (string) $directorate->id ? 'selected' : '' }}>
                                                                                {{ $directorate->displayName() }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                    @error('minutes_agendas.' . $index .
                                                                        '.owner_directorate_id')
                                                                        <em
                                                                            class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                    @enderror
                                                                    <select
                                                                        class="select minutes-agenda-pic {{ $fieldErrorClass('minutes_agendas.' . $index . '.pic_user_id') }}"
                                                                        name="minutes_agendas[{{ $index }}][pic_user_id]">
                                                                        <option value="">- Pilih User -</option>
                                                                        @foreach ($users as $optionUser)
                                                                            <option value="{{ $optionUser->id }}"
                                                                                {{ (string) ($row['pic_user_id'] ?? '') === (string) $optionUser->id ? 'selected' : '' }}>
                                                                                {{ $optionUser->name }}
                                                                                @if ($optionUser->directorate)
                                                                                    ({{ $optionUser->directorate->displayName() }})
                                                                                @endif
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                    @error('minutes_agendas.' . $index . '.pic_user_id')
                                                                        <em
                                                                            class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                    @enderror
                                                                </div>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <input type="hidden"
                                                                name="minutes_agendas[{{ $index }}][decision_id]"
                                                                value="{{ $decisionId }}">
                                                            @if ($decisionLocked)
                                                                <input type="hidden"
                                                                    name="minutes_agendas[{{ $index }}][followup_enabled]"
                                                                    value="1">
                                                            @endif
                                                            <div class="grid gap-3">
                                                                <label class="flex items-center gap-2 text-sm">
                                                                    <input
                                                                        class="checkbox checkbox-sm minutes-followup-toggle"
                                                                        type="checkbox"
                                                                        name="minutes_agendas[{{ $index }}][followup_enabled]"
                                                                        value="1"
                                                                        {{ $followupEnabled ? 'checked' : '' }}
                                                                        {{ $decisionLocked ? 'disabled' : '' }}>
                                                                    <span>{{ $decisionLocked ? 'Tindak lanjut aktif dan tetap berjalan' : 'Buat tindak lanjut untuk materi ini' }}</span>
                                                                </label>
                                                                <div
                                                                    class="grid gap-3 minutes-followup-fields {{ $followupEnabled ? '' : 'hidden' }}">
                                                                    @if ($sourceLocked)
                                                                        <input type="hidden"
                                                                            name="minutes_agendas[{{ $index }}][existing_decision_id]"
                                                                            value="{{ $selectedExistingDecisionId }}">
                                                                        <div class="text-xs text-gray-500">
                                                                            Linked ke backlog issue:
                                                                            {{ $sourceDecision?->issue_key ?? ($sourceDecision?->decision_key ?? '-') }}
                                                                        </div>
                                                                    @else
                                                                        <div class="flex flex-col">
                                                                            <label class="form-label">Link Issue
                                                                                Existing</label>
                                                                            <select
                                                                                class="select minutes-existing-issue {{ $fieldErrorClass('minutes_agendas.' . $index . '.existing_decision_id') }}"
                                                                                name="minutes_agendas[{{ $index }}][existing_decision_id]">
                                                                                <option value="">- Optional -
                                                                                </option>
                                                                                @foreach ($linkableDecisions as $linkableDecision)
                                                                                    <option
                                                                                        value="{{ $linkableDecision->id }}"
                                                                                        {{ $selectedExistingDecisionId === (string) $linkableDecision->id ? 'selected' : '' }}>
                                                                                        {{ $linkableDecision->issue_key ?? $linkableDecision->decision_key }}
                                                                                        |
                                                                                        {{ \Illuminate\Support\Str::limit($linkableDecision->decision_text, 90) }}
                                                                                    </option>
                                                                                @endforeach
                                                                            </select>
                                                                            @error('minutes_agendas.' . $index .
                                                                                '.existing_decision_id')
                                                                                <em
                                                                                    class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                            @enderror
                                                                        </div>
                                                                    @endif
                                                                    <div class="flex flex-col">
                                                                        <label class="form-label">Update / Tindak
                                                                            Lanjut {!! $requiredMark !!}</label>
                                                                        <textarea
                                                                            class="textarea w-full minutes-decision-text {{ $fieldErrorClass('minutes_agendas.' . $index . '.decision_text') }}"
                                                                            rows="3" name="minutes_agendas[{{ $index }}][decision_text]"
                                                                            placeholder="Tindak lanjut opsional untuk materi ini..." {{ $followupEnabled ? '' : 'disabled' }}>{{ $row['decision_text'] ?? '' }}</textarea>
                                                                        @error('minutes_agendas.' . $index .
                                                                            '.decision_text')
                                                                            <em
                                                                                class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                        @enderror
                                                                    </div>
                                                                    <div class="grid gap-3 md:grid-cols-2">
                                                                        <div class="flex flex-col">
                                                                            <label class="form-label">Target
                                                                                {!! $requiredMark !!}</label>
                                                                            <input
                                                                                class="input minutes-target-date {{ $fieldErrorClass('minutes_agendas.' . $index . '.target_date') }}"
                                                                                type="date"
                                                                                name="minutes_agendas[{{ $index }}][target_date]"
                                                                                value="{{ $row['target_date'] ?? '' }}"
                                                                                {{ $followupEnabled ? '' : 'disabled' }}>
                                                                            @error('minutes_agendas.' . $index .
                                                                                '.target_date')
                                                                                <em
                                                                                    class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                            @enderror
                                                                        </div>
                                                                        <div class="flex flex-col">
                                                                            <label class="form-label">Status
                                                                                {!! $requiredMark !!}</label>
                                                                            <select
                                                                                class="select minutes-status-select {{ $fieldErrorClass('minutes_agendas.' . $index . '.status') }}"
                                                                                name="minutes_agendas[{{ $index }}][status]"
                                                                                {{ $followupEnabled ? '' : 'disabled' }}>
                                                                                @foreach (['in_progress' => 'On Progress', 'continuous' => 'Berkelanjutan', 'done' => 'Done', 'pending' => 'Pending', 'dropped' => 'Drop'] as $statusValue => $statusLabel)
                                                                                    <option value="{{ $statusValue }}"
                                                                                        {{ $selectedStatus === $statusValue ? 'selected' : '' }}>
                                                                                        {{ $statusLabel }}
                                                                                    </option>
                                                                                @endforeach
                                                                            </select>
                                                                            @error('minutes_agendas.' . $index . '.status')
                                                                                <em
                                                                                    class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                            @enderror
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="flex justify-center">
                                                                <button type="button"
                                                                    class="btn btn-xs btn-danger remove-minutes-agenda-row {{ $canRemove ? '' : 'hidden' }}">
                                                                    Hapus
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <label class="flex items-center gap-2 text-sm">
                                    <input class="checkbox checkbox-sm" type="checkbox" name="submit_for_signature"
                                        value="1" {{ old('submit_for_signature') ? 'checked' : '' }}>
                                    <span>Langsung submit untuk sirkulasi tandatangan</span>
                                </label>

                                <div class="flex justify-end">
                                    <button type="submit" class="btn btn-primary">Simpan Notulen Direktorat</button>
                                </div>
                            </form>
                        @else
                            <form method="POST" action="{{ route('meeting.minutes.save', $meeting) }}"
                                enctype="multipart/form-data" class="grid gap-4" id="minutes-form">
                                @csrf
                                <input type="hidden" name="minutes_agendas_present" value="1">

                                <div class="grid gap-4 xl:grid-cols-2">
                                    <div class="flex flex-col">
                                        <label class="form-label">Catatan Umum Notulen (Opsional)</label>
                                        <textarea class="textarea w-full {{ $fieldErrorClass('minutes_text') }}" name="minutes_text" rows="6"
                                            placeholder="Ringkasan umum meeting, kesimpulan, atau catatan tambahan...">{{ old('minutes_text', $minutes?->minutes_text) }}</textarea>
                                        @error('minutes_text')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                    </div>

                                    <div class="grid gap-4 content-start">
                                        <div class="flex flex-col">
                                            <label class="form-label">Lampiran Notulen (Opsional)</label>
                                            <input class="file-input {{ $fieldErrorClass('minutes_file') }}"
                                                type="file" name="minutes_file"
                                                accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx,.ppt,.pptx">
                                            @error('minutes_file')
                                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="border border-gray-200 rounded-xl p-4 grid gap-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <h4 class="font-semibold text-gray-800">Poin Pembahasan Rapat</h4>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-light-primary"
                                            id="add-minutes-point-row">
                                            <i class="ki-filled ki-plus"></i> Tambah Poin
                                        </button>
                                    </div>
                                    @error('minutes_agendas')
                                        <em class="text-sm alert text-danger">{{ $message }}</em>
                                    @enderror
                                    <div id="minutes-point-rows" class="grid gap-3">
                                        @foreach ($minutesAgendaRows as $index => $row)
                                            @php
                                                $agendaId = (string) ($row['agenda_id'] ?? '');
                                                $sourceDecisionId = (string) ($row['source_decision_id'] ?? '');
                                                $decisionId = (string) ($row['decision_id'] ?? '');
                                                $decisionLocked = $decisionId !== '';
                                                $sourceLocked = $sourceDecisionId !== '';
                                                $followupEnabled =
                                                    filter_var(
                                                        $row['followup_enabled'] ?? false,
                                                        FILTER_VALIDATE_BOOLEAN,
                                                    ) || $decisionLocked;
                                                $selectedExistingDecisionId =
                                                    (string) ($row['existing_decision_id'] ??
                                                        ($sourceDecisionId ?: ''));
                                                $selectedStatus = (string) ($row['status'] ?? 'pending');
                                                $agendaModel =
                                                    $agendaId !== ''
                                                        ? $meeting->agendas->firstWhere('id', (int) $agendaId)
                                                        : null;
                                                $sourceDecision = $agendaModel?->sourceDecision;
                                                $agendaPhotos = ($agendaModel?->attachables ?? collect())
                                                    ->where('category', $minutesPointPhotoCategory)
                                                    ->values();
                                                $canRemove = !$decisionLocked && !$sourceLocked;
                                            @endphp
                                            <div class="p-4 border rounded-xl border-gray-200 minutes-point-row"
                                                data-followup-locked="{{ $decisionLocked ? '1' : '0' }}"
                                                data-source-locked="{{ $sourceLocked ? '1' : '0' }}">
                                                <input type="hidden"
                                                    name="minutes_agendas[{{ $index }}][agenda_id]"
                                                    value="{{ $agendaId }}">
                                                <input type="hidden"
                                                    name="minutes_agendas[{{ $index }}][source_decision_id]"
                                                    value="{{ $sourceDecisionId }}">
                                                <input type="hidden"
                                                    name="minutes_agendas[{{ $index }}][description]"
                                                    value="{{ $row['description'] ?? '' }}">
                                                <input type="hidden"
                                                    name="minutes_agendas[{{ $index }}][decision_id]"
                                                    value="{{ $decisionId }}">

                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="grid gap-1">
                                                        <div
                                                            class="text-xs font-semibold uppercase tracking-wide text-gray-500 minutes-point-seq">
                                                            Poin {{ $index + 1 }}
                                                        </div>
                                                        @if ($sourceDecision)
                                                            <div class="text-xs text-gray-500">
                                                                <span class="badge badge-light-info">Mandatory</span>
                                                                {{ $sourceDecision->issue_key ?? ($sourceDecision->decision_key ?? '-') }}
                                                                @if ($sourceDecision->meeting?->title)
                                                                    | {{ $sourceDecision->meeting->title }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <button type="button"
                                                        class="btn btn-xs btn-danger remove-minutes-point-row {{ $canRemove ? '' : 'hidden' }}">
                                                        Hapus
                                                    </button>
                                                </div>

                                                <div class="grid gap-3">
                                                    <div class="flex flex-col">
                                                        <label class="form-label">Judul / Topik Poin <span
                                                                class="text-danger">*</span></label>
                                                        <textarea class="textarea w-full minutes-point-title {{ $fieldErrorClass('minutes_agendas.' . $index . '.title') }}"
                                                            rows="2" name="minutes_agendas[{{ $index }}][title]" placeholder="Topik pembahasan..."
                                                            {{ $sourceLocked ? 'readonly' : '' }}>{{ $row['title'] ?? '' }}</textarea>
                                                        @error('minutes_agendas.' . $index . '.title')
                                                            <em
                                                                class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                        @enderror
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <label class="form-label">Pembahasan Notulen</label>
                                                        <textarea class="textarea w-full" rows="4" name="minutes_agendas[{{ $index }}][minutes_discussion]"
                                                            placeholder="Isi pembahasan, keputusan, atau penjelasan per poin...">{{ $row['minutes_discussion'] ?? '' }}</textarea>
                                                    </div>

                                                    <div class="grid gap-3 md:grid-cols-2">
                                                        <div class="flex flex-col">
                                                            <label class="form-label">PIC Direktorat</label>
                                                            @if ($sourceLocked)
                                                                <input type="hidden"
                                                                    name="minutes_agendas[{{ $index }}][owner_directorate_id]"
                                                                    value="{{ $row['owner_directorate_id'] ?? '' }}">
                                                                <div class="input bg-light leading-[2.75rem] px-3">
                                                                    {{ optional($directorates->firstWhere('id', (int) ($row['owner_directorate_id'] ?? 0)))->displayName() ?? '-' }}
                                                                </div>
                                                            @else
                                                                <select
                                                                    class="select {{ $fieldErrorClass('minutes_agendas.' . $index . '.owner_directorate_id') }}"
                                                                    name="minutes_agendas[{{ $index }}][owner_directorate_id]">
                                                                    <option value="">- Pilih Direktorat -</option>
                                                                    @foreach ($directorates as $directorate)
                                                                        <option value="{{ $directorate->id }}"
                                                                            {{ (string) ($row['owner_directorate_id'] ?? '') === (string) $directorate->id ? 'selected' : '' }}>
                                                                            {{ $directorate->displayName() }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                                @error('minutes_agendas.' . $index .
                                                                    '.owner_directorate_id')
                                                                    <em
                                                                        class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                @enderror
                                                            @endif
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <label class="form-label">PIC User
                                                                {!! $followupEnabled ? $requiredMark : '' !!}</label>
                                                            @if ($sourceLocked)
                                                                <input type="hidden"
                                                                    name="minutes_agendas[{{ $index }}][pic_user_id]"
                                                                    value="{{ $row['pic_user_id'] ?? '' }}">
                                                                <div class="input bg-light leading-[2.75rem] px-3">
                                                                    {{ optional($users->firstWhere('id', (int) ($row['pic_user_id'] ?? 0)))->name ?? '-' }}
                                                                </div>
                                                            @else
                                                                <select
                                                                    class="select {{ $fieldErrorClass('minutes_agendas.' . $index . '.pic_user_id') }}"
                                                                    name="minutes_agendas[{{ $index }}][pic_user_id]">
                                                                    <option value="">- Pilih User -</option>
                                                                    @foreach ($users as $optionUser)
                                                                        <option value="{{ $optionUser->id }}"
                                                                            {{ (string) ($row['pic_user_id'] ?? '') === (string) $optionUser->id ? 'selected' : '' }}>
                                                                            {{ $optionUser->name }}
                                                                            @if ($optionUser->directorate)
                                                                                ({{ $optionUser->directorate->displayName() }})
                                                                            @endif
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                                @error('minutes_agendas.' . $index . '.pic_user_id')
                                                                    <em
                                                                        class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                @enderror
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="grid gap-3 lg:grid-cols-2">
                                                        <div class="flex flex-col">
                                                            <label class="form-label">Foto Dokumentasi (Opsional)</label>
                                                            <input
                                                                class="file-input {{ $fieldErrorClass('minutes_agendas.' . $index . '.photo_files') }}"
                                                                type="file"
                                                                name="minutes_agendas[{{ $index }}][photo_files][]"
                                                                accept=".jpg,.jpeg,.png" multiple>
                                                            @error('minutes_agendas.' . $index . '.photo_files')
                                                                <em
                                                                    class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                            @enderror
                                                            @foreach ($errors->get('minutes_agendas.' . $index . '.photo_files.*') as $messages)
                                                                @foreach ($messages as $message)
                                                                    <em
                                                                        class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                @endforeach
                                                            @endforeach
                                                            <small class="mt-1 text-xs text-gray-500">
                                                                Unggah foto bukti atau dokumentasi untuk poin ini bila
                                                                diperlukan.
                                                            </small>
                                                        </div>
                                                        <div class="grid gap-2">
                                                            <label class="form-label">Foto Tersimpan</label>
                                                            @if ($agendaPhotos->count() > 0)
                                                                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                                                    @foreach ($agendaPhotos as $photoAttachable)
                                                                        @php
                                                                            $photoAttachment =
                                                                                $photoAttachable->attachment;
                                                                        @endphp
                                                                        @if ($photoAttachment)
                                                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk($photoAttachment->disk ?? 'public')->url($photoAttachment->path) }}"
                                                                                target="_blank" rel="noopener"
                                                                                class="block overflow-hidden rounded-xl border border-gray-200">
                                                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk($photoAttachment->disk ?? 'public')->url($photoAttachment->path) }}"
                                                                                    alt="{{ $photoAttachment->original_name ?? $photoAttachment->file_name }}"
                                                                                    class="h-32 w-full object-cover">
                                                                            </a>
                                                                        @endif
                                                                    @endforeach
                                                                </div>
                                                            @else
                                                                <div class="text-sm text-gray-500">Belum ada foto untuk
                                                                    poin ini.</div>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="grid gap-3">
                                                        <label class="flex items-center gap-2 text-sm">
                                                            <input
                                                                class="checkbox checkbox-sm minutes-point-followup-toggle"
                                                                type="checkbox"
                                                                name="minutes_agendas[{{ $index }}][followup_enabled]"
                                                                value="1" {{ $followupEnabled ? 'checked' : '' }}
                                                                {{ $decisionLocked ? 'disabled' : '' }}>
                                                            <span>{{ $decisionLocked ? 'Tindak lanjut aktif dan tetap berjalan' : 'Buat tindak lanjut untuk poin ini' }}</span>
                                                        </label>
                                                        <div
                                                            class="grid gap-3 minutes-point-followup-fields {{ $followupEnabled ? '' : 'hidden' }}">
                                                            @if ($decisionLocked)
                                                                <input type="hidden"
                                                                    name="minutes_agendas[{{ $index }}][followup_enabled]"
                                                                    value="1">
                                                            @endif
                                                            @if ($sourceLocked)
                                                                <input type="hidden"
                                                                    name="minutes_agendas[{{ $index }}][existing_decision_id]"
                                                                    value="{{ $selectedExistingDecisionId }}">
                                                                <div class="text-xs text-gray-500">
                                                                    Linked ke backlog issue:
                                                                    {{ $sourceDecision?->issue_key ?? ($sourceDecision?->decision_key ?? '-') }}
                                                                </div>
                                                            @else
                                                                <div class="flex flex-col">
                                                                    <label class="form-label">Link Issue Existing</label>
                                                                    <select
                                                                        class="select {{ $fieldErrorClass('minutes_agendas.' . $index . '.existing_decision_id') }}"
                                                                        name="minutes_agendas[{{ $index }}][existing_decision_id]"
                                                                        {{ $followupEnabled ? '' : 'disabled' }}>
                                                                        <option value="">- Buat issue baru -</option>
                                                                        @foreach ($linkableDecisions as $linkableDecision)
                                                                            <option value="{{ $linkableDecision->id }}"
                                                                                {{ $selectedExistingDecisionId === (string) $linkableDecision->id ? 'selected' : '' }}>
                                                                                {{ $linkableDecision->issue_key ?? $linkableDecision->decision_key }}
                                                                                |
                                                                                {{ \Illuminate\Support\Str::limit($linkableDecision->decision_text, 90) }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                    @error('minutes_agendas.' . $index .
                                                                        '.existing_decision_id')
                                                                        <em
                                                                            class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                    @enderror
                                                                </div>
                                                            @endif
                                                            <div class="flex flex-col">
                                                                <label class="form-label">Tindak Lanjut
                                                                    {!! $followupEnabled ? $requiredMark : '' !!}</label>
                                                                <textarea class="textarea w-full {{ $fieldErrorClass('minutes_agendas.' . $index . '.decision_text') }}"
                                                                    rows="3" name="minutes_agendas[{{ $index }}][decision_text]"
                                                                    placeholder="Isi tindak lanjut dari poin ini..." {{ $followupEnabled ? '' : 'disabled' }}>{{ $row['decision_text'] ?? '' }}</textarea>
                                                                @error('minutes_agendas.' . $index . '.decision_text')
                                                                    <em
                                                                        class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                @enderror
                                                            </div>
                                                            <div class="grid gap-3 md:grid-cols-2">
                                                                <div class="flex flex-col">
                                                                    <label class="form-label">Target
                                                                        {!! $followupEnabled ? $requiredMark : '' !!}</label>
                                                                    <input
                                                                        class="input {{ $fieldErrorClass('minutes_agendas.' . $index . '.target_date') }}"
                                                                        type="date"
                                                                        name="minutes_agendas[{{ $index }}][target_date]"
                                                                        value="{{ $row['target_date'] ?? '' }}"
                                                                        {{ $followupEnabled ? '' : 'disabled' }}>
                                                                    @error('minutes_agendas.' . $index . '.target_date')
                                                                        <em
                                                                            class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                    @enderror
                                                                </div>
                                                                <div class="flex flex-col">
                                                                    <label class="form-label">Status
                                                                        {!! $followupEnabled ? $requiredMark : '' !!}</label>
                                                                    <select
                                                                        class="select {{ $fieldErrorClass('minutes_agendas.' . $index . '.status') }}"
                                                                        name="minutes_agendas[{{ $index }}][status]"
                                                                        {{ $followupEnabled ? '' : 'disabled' }}>
                                                                        @foreach (['pending' => 'Pending', 'in_progress' => 'Proses', 'continuous' => 'Berkelanjutan', 'done' => 'Done', 'dropped' => 'Drop'] as $statusValue => $statusLabel)
                                                                            <option value="{{ $statusValue }}"
                                                                                {{ $selectedStatus === $statusValue ? 'selected' : '' }}>
                                                                                {{ $statusLabel }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                    @error('minutes_agendas.' . $index . '.status')
                                                                        <em
                                                                            class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="border border-gray-200 rounded-xl p-4 grid gap-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <h4 class="font-semibold text-gray-800">Tindak Lanjut Tambahan (Opsional)</h4>
                                            <div class="text-sm text-gray-500">
                                                Gunakan jika ada action item yang tidak melekat ke poin rapat tertentu.
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-light-primary"
                                            id="add-decision-row">
                                            <i class="ki-filled ki-plus"></i> Tambah Item
                                        </button>
                                    </div>
                                    <div id="decision-rows" class="grid gap-3">
                                        @foreach ($minutesDecisionRows as $index => $decision)
                                            @php
                                            @endphp
                                            <div class="p-3 border rounded-xl border-gray-200 decision-row">
                                                <input type="hidden" name="decisions[{{ $index }}][id]"
                                                    value="{{ old('decisions.' . $index . '.id', $decision['id'] ?? '') }}">
                                                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                                    <div class="flex flex-col md:col-span-2 xl:col-span-3">
                                                        <label class="form-label">Link Issue Existing</label>
                                                        <select
                                                            class="select {{ $fieldErrorClass('decisions.' . $index . '.existing_decision_id') }}"
                                                            name="decisions[{{ $index }}][existing_decision_id]">
                                                            <option value="">- Buat issue baru -</option>
                                                            @foreach ($linkableDecisions as $linkableDecision)
                                                                <option value="{{ $linkableDecision->id }}"
                                                                    {{ (string) old('decisions.' . $index . '.existing_decision_id', $decision['existing_decision_id'] ?? '') === (string) $linkableDecision->id ? 'selected' : '' }}>
                                                                    {{ $linkableDecision->issue_key ?? $linkableDecision->decision_key }}
                                                                    |
                                                                    {{ \Illuminate\Support\Str::limit($linkableDecision->decision_text, 90) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @error('decisions.' . $index . '.existing_decision_id')
                                                            <em
                                                                class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                        @enderror
                                                    </div>
                                                    <div class="flex flex-col md:col-span-2 xl:col-span-3">
                                                        <label class="form-label">Item Tindaklanjut</label>
                                                        <textarea class="textarea w-full {{ $fieldErrorClass('decisions.' . $index . '.decision_text') }}" rows="2"
                                                            name="decisions[{{ $index }}][decision_text]">{{ old('decisions.' . $index . '.decision_text', $decision['decision_text'] ?? '') }}</textarea>
                                                        @error('decisions.' . $index . '.decision_text')
                                                            <em
                                                                class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                        @enderror
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <label class="form-label">PIC Direktorat
                                                            {!! trim((string) old('decisions.' . $index . '.decision_text', $decision['decision_text'] ?? '')) !== ''
                                                                ? $requiredMark
                                                                : '' !!}</label>
                                                        <select
                                                            class="select {{ $fieldErrorClass('decisions.' . $index . '.owner_directorate_id') }}"
                                                            name="decisions[{{ $index }}][owner_directorate_id]">
                                                            <option value="">- Pilih Direktorat -</option>
                                                            @foreach ($directorates as $directorate)
                                                                <option value="{{ $directorate->id }}"
                                                                    {{ (string) old('decisions.' . $index . '.owner_directorate_id', $decision['owner_directorate_id'] ?? '') === (string) $directorate->id ? 'selected' : '' }}>
                                                                    {{ $directorate->displayName() }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @error('decisions.' . $index . '.owner_directorate_id')
                                                            <em
                                                                class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                        @enderror
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <label class="form-label">PIC User {!! trim((string) old('decisions.' . $index . '.decision_text', $decision['decision_text'] ?? '')) !== ''
                                                            ? $requiredMark
                                                            : '' !!}</label>
                                                        <select
                                                            class="select {{ $fieldErrorClass('decisions.' . $index . '.pic_user_id') }}"
                                                            name="decisions[{{ $index }}][pic_user_id]">
                                                            <option value="">- Pilih User -</option>
                                                            @foreach ($users as $optionUser)
                                                                <option value="{{ $optionUser->id }}"
                                                                    {{ (string) old('decisions.' . $index . '.pic_user_id', $decision['pic_user_id'] ?? '') === (string) $optionUser->id ? 'selected' : '' }}>
                                                                    {{ $optionUser->name }}
                                                                    @if ($optionUser->directorate)
                                                                        ({{ $optionUser->directorate->displayName() }})
                                                                    @endif
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @error('decisions.' . $index . '.pic_user_id')
                                                            <em
                                                                class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                        @enderror
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <label class="form-label">Status Issue
                                                            {!! trim((string) old('decisions.' . $index . '.decision_text', $decision['decision_text'] ?? '')) !== ''
                                                                ? $requiredMark
                                                                : '' !!}</label>
                                                        <select
                                                            class="select {{ $fieldErrorClass('decisions.' . $index . '.status') }}"
                                                            name="decisions[{{ $index }}][status]">
                                                            @foreach (['pending' => 'Pending', 'in_progress' => 'Proses', 'continuous' => 'Berkelanjutan', 'done' => 'Done', 'dropped' => 'Drop'] as $statusValue => $statusLabel)
                                                                <option value="{{ $statusValue }}"
                                                                    {{ (string) old('decisions.' . $index . '.status', $decision['status'] ?? 'pending') === $statusValue ? 'selected' : '' }}>
                                                                    {{ $statusLabel }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @error('decisions.' . $index . '.status')
                                                            <em
                                                                class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                        @enderror
                                                    </div>
                                                    <div class="flex flex-col md:col-span-2 xl:col-span-3">
                                                        <label class="form-label">Target Penyelesaian
                                                            {!! trim((string) old('decisions.' . $index . '.decision_text', $decision['decision_text'] ?? '')) !== ''
                                                                ? $requiredMark
                                                                : '' !!}</label>
                                                        <input
                                                            class="input {{ $fieldErrorClass('decisions.' . $index . '.target_date') }}"
                                                            type="date"
                                                            name="decisions[{{ $index }}][target_date]"
                                                            value="{{ old('decisions.' . $index . '.target_date', $decision['target_date'] ?? '') }}">
                                                        @error('decisions.' . $index . '.target_date')
                                                            <em
                                                                class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                                        @enderror
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
                        @endif
                    </div>
                </div>
            @endif
        @endif

        @if ($canCorsecUpdateAction)
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
                                <label class="form-label">
                                    {{ $meeting->isDirektoratType() ? 'Upload File Notulen Final (Opsional)' : 'Upload Notulen Final' }}
                                    @if (!$meeting->isDirektoratType())
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <input class="file-input" type="file" name="final_minutes_file"
                                    accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx,.ppt,.pptx"
                                    @if (!$meeting->isDirektoratType()) required @endif>
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (Opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Catatan finalisasi notulen...">{{ old('note') }}</textarea>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="btn btn-primary">
                                    {{ $meeting->isDirektoratType() ? 'Finalisasi Notulen' : 'Upload Notulen Final' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Notulen Rapat</h3>
                @if ($meeting->isDirektoratType())
                    <a href="{{ route('meeting.minutes.template', $meeting) }}" class="btn btn-sm btn-success">
                        Download Template
                    </a>
                @endif
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
                        @if ($meeting->isDirektoratType() && $meeting->agendas->count() > 0)
                            <div>
                                <div class="text-gray-600 mb-2">Materi Pembahasan + Tindak Lanjut:</div>
                                <div class="overflow-x-auto">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th class="min-w-[40px]">No</th>
                                                <th class="min-w-[280px]">Materi Pembahasan</th>
                                                <th class="min-w-[180px]">PIC</th>
                                                <th class="min-w-[260px]">Tindak Lanjut</th>
                                                <th class="min-w-[120px]">Target</th>
                                                <th class="min-w-[120px]">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($meeting->agendas as $agenda)
                                                @php
                                                    $agendaDecision = $decisionsByAgendaId->get((int) $agenda->id);
                                                @endphp
                                                <tr>
                                                    <td>{{ $agenda->order_no ?? $loop->iteration }}</td>
                                                    <td>
                                                        <div class="font-medium">{{ $agenda->title }}</div>
                                                        @if ($agenda->sourceDecision)
                                                            <div class="text-xs text-gray-500">
                                                                Mandatory:
                                                                {{ $agenda->sourceDecision->issue_key ?? ($agenda->sourceDecision->decision_key ?? '-') }}
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div>{{ $agenda->ownerDirectorate?->displayName() ?? '-' }}</div>
                                                        <div class="text-xs text-gray-500">
                                                            {{ $agenda->picUser?->name ?? '-' }}</div>
                                                    </td>
                                                    <td>{{ $agendaDecision?->decision_text ?? '-' }}</td>
                                                    <td>{{ $agendaDecision?->target_date ? $agendaDecision->target_date->format('d/m/Y') : '-' }}
                                                    </td>
                                                    <td>
                                                        @if ($agendaDecision)
                                                            <span
                                                                class="badge {{ $decisionStatusBadgeClasses[(string) $agendaDecision->status] ?? 'badge-light' }}">
                                                                {{ $decisionStatusLabels[(string) $agendaDecision->status] ?? ((string) $agendaDecision->status ?: '-') }}
                                                            </span>
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
                        @endif
                        @if (!$meeting->isDirektoratType() && $minutesAgendaDisplayRows->count() > 0)
                            <div>
                                <div class="text-gray-600 mb-2">Poin Pembahasan Rapat:</div>
                                <div class="grid gap-3">
                                    @foreach ($minutesAgendaDisplayRows as $agenda)
                                        @php
                                            $agendaDecision = $decisionsByAgendaId->get((int) $agenda->id);
                                            $agendaPhotos = ($agenda->attachables ?? collect())
                                                ->where('category', $minutesPointPhotoCategory)
                                                ->values();
                                        @endphp
                                        <div class="p-4 border rounded-xl border-gray-200 grid gap-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <div class="text-xs uppercase tracking-wide text-gray-500">
                                                        Poin {{ $agenda->order_no ?? $loop->iteration }}
                                                    </div>
                                                    <div class="font-semibold text-gray-800">{{ $agenda->title }}</div>
                                                </div>
                                                @if ($agendaDecision)
                                                    <span
                                                        class="badge {{ $decisionStatusBadgeClasses[(string) $agendaDecision->status] ?? 'badge-light' }}">
                                                        {{ $decisionStatusLabels[(string) $agendaDecision->status] ?? ((string) $agendaDecision->status ?: '-') }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-sm whitespace-pre-wrap">
                                                {{ $agenda->minutes_discussion ?: '-' }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                PIC:
                                                {{ $agenda->picUser?->name ?? ($agenda->ownerDirectorate?->displayName() ?? '-') }}
                                            </div>
                                            @if ($agendaPhotos->count() > 0)
                                                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                                    @foreach ($agendaPhotos as $photoAttachable)
                                                        @php
                                                            $photoAttachment = $photoAttachable->attachment;
                                                        @endphp
                                                        @if ($photoAttachment)
                                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk($photoAttachment->disk ?? 'public')->url($photoAttachment->path) }}"
                                                                target="_blank" rel="noopener"
                                                                class="block overflow-hidden rounded-xl border border-gray-200">
                                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk($photoAttachment->disk ?? 'public')->url($photoAttachment->path) }}"
                                                                    alt="{{ $photoAttachment->original_name ?? $photoAttachment->file_name }}"
                                                                    class="h-36 w-full object-cover">
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if ($agendaDecision)
                                                <div class="p-3 rounded-xl bg-light border border-gray-200">
                                                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">
                                                        Tindak Lanjut
                                                    </div>
                                                    <div class="font-medium text-gray-800">
                                                        {{ $agendaDecision->decision_text }}
                                                    </div>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        Target:
                                                        {{ $agendaDecision->target_date ? $agendaDecision->target_date->format('d/m/Y') : '-' }}
                                                        | PIC:
                                                        {{ $agendaDecision->picUser?->name ?? ($agendaDecision->ownerDirectorate?->displayName() ?? '-') }}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div>
                            <div class="text-gray-600 mb-1">
                                {{ $meeting->isDirektoratType() ? 'Catatan Umum Notulen:' : 'Ringkasan Notulen:' }}
                            </div>
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
                @if (($crossMeetingOpenDecisions ?? collect())->count() > 0)
                    <div class="mb-5">
                        <div class="font-semibold text-gray-800 mb-2">Backlog Lintas Rapat (Belum Selesai)</div>
                        <div class="overflow-x-auto">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="min-w-[120px]">Issue Key</th>
                                        <th class="min-w-[220px]">Sumber Rapat</th>
                                        <th class="min-w-[260px]">Tindaklanjut</th>
                                        <th class="min-w-[160px]">PIC</th>
                                        <th class="min-w-[120px]">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($crossMeetingOpenDecisions as $openDecision)
                                        <tr>
                                            <td>
                                                <div class="font-medium">{{ $openDecision->issue_key ?? '-' }}</div>
                                                <div class="text-xs text-gray-500">
                                                    {{ $openDecision->decision_key ?? '-' }}</div>
                                            </td>
                                            <td>
                                                {{ $openDecision->meeting?->title ?? '-' }}
                                                @if ($openDecision->meeting?->meeting_at)
                                                    <div class="text-xs text-gray-500">
                                                        {{ $openDecision->meeting->meeting_at->format('d/m/Y H:i') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>{{ $openDecision->decision_text }}</td>
                                            <td>{{ $openDecision->picUser?->name ?? ($openDecision->ownerDirectorate?->displayName() ?? '-') }}
                                            </td>
                                            <td>
                                                <span
                                                    class="badge {{ $decisionStatusBadgeClasses[(string) $openDecision->status] ?? 'badge-light' }}">{{ $decisionStatusLabels[(string) $openDecision->status] ?? ((string) $openDecision->status ?: '-') }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($meeting->decisions->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="min-w-[40px]">No</th>
                                    <th class="min-w-[140px]">Issue Key</th>
                                    <th class="min-w-[260px]">Tindaklanjut</th>
                                    <th class="min-w-[180px]">PIC</th>
                                    <th class="min-w-[180px]">Support</th>
                                    <th class="min-w-[160px]">Tgl Radir Awal / Frekuensi</th>
                                    <th class="min-w-[130px]">Target</th>
                                    <th class="min-w-[120px]">Progress</th>
                                    <th class="min-w-[220px]">Update Terkini</th>
                                    <th class="min-w-[130px]">Aging</th>
                                    <th class="min-w-[140px]">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($meeting->decisions as $index => $decision)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <div class="font-medium">{{ $decision->issue_key ?? '-' }}</div>
                                            <div class="text-xs text-gray-500">{{ $decision->decision_key ?? '-' }}</div>
                                        </td>
                                        <td>{{ $decision->decision_text }}</td>
                                        <td>
                                            <div>{{ $decision->ownerDirectorate?->displayName() ?? '-' }}</div>
                                            <div class="text-xs text-gray-500">{{ $decision->picUser?->name ?? '-' }}
                                            </div>
                                        </td>
                                        <td>
                                            -
                                        </td>
                                        <td>
                                            <div>{{ $decision->first_discussed_at?->format('d/m/Y') ?? '-' }}</div>
                                            <div class="text-xs text-gray-500">{{ $decision->discussion_count ?? 0 }}x
                                            </div>
                                        </td>
                                        <td>{{ $decision->target_date ? $decision->target_date->format('d/m/Y') : '-' }}
                                        </td>
                                        <td>{{ $decisionProgressById[$decision->id] ?? ((string) $decision->status === 'done' ? 100 : 0) }}%
                                        </td>
                                        <td>
                                            <div>
                                                {{ \Illuminate\Support\Str::limit($decision->latest_update_note ?? '-', 120) }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ $decision->latest_update_at?->format('d/m/Y') ?? '-' }}
                                            </div>
                                        </td>
                                        <td>
                                            @if ($decision->aging_bucket)
                                                <div>
                                                    {{ $agingLabels[$decision->aging_bucket] ?? strtoupper($decision->aging_bucket) }}
                                                </div>
                                                <div class="text-xs text-gray-500">{{ $decision->aging_days ?? 0 }} hari
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <span
                                                class="badge {{ $decisionStatusBadgeClasses[(string) $decision->status] ?? 'badge-light' }}">{{ $decisionStatusLabels[(string) $decision->status] ?? ((string) $decision->status ?: '-') }}</span>
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

        @if ($canInputFollowup)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Update Progress Tindaklanjut</h3>
                </div>
                <div class="card-body grid gap-4">
                    @foreach ($meeting->decisions as $decision)
                        @if ($decisionCanUpdateById[$decision->id] ?? false)
                            <form method="POST" action="{{ route('meeting.decision.update', [$meeting, $decision]) }}"
                                enctype="multipart/form-data"
                                class="p-4 border rounded-xl border-gray-200 grid gap-4 followup-update-form">
                                @csrf
                                <div class="font-medium text-gray-800">{{ $decision->decision_text }}</div>
                                <div class="text-xs text-gray-500">
                                    Issue:
                                    {{ $decision->issue_key ?? '-' }} |
                                    Target:
                                    {{ $decision->target_date ? $decision->target_date->format('d/m/Y') : '-' }} |
                                    PIC:
                                    {{ $decision->picUser?->name ?? ($decision->ownerDirectorate?->displayName() ?? '-') }}
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex flex-col">
                                        <label class="form-label">Jenis Update <span class="text-danger">*</span></label>
                                        <select class="select js-update-type {{ $fieldErrorClass('update_type') }}"
                                            name="update_type" required>
                                            <option value="progress"
                                                {{ old('update_type', 'progress') === 'progress' ? 'selected' : '' }}>
                                                Progress</option>
                                            <option value="continuous"
                                                {{ old('update_type') === 'continuous' ? 'selected' : '' }}>Berkelanjutan
                                            </option>
                                            <option value="done" {{ old('update_type') === 'done' ? 'selected' : '' }}>
                                                Selesai</option>
                                            <option value="drop" {{ old('update_type') === 'drop' ? 'selected' : '' }}>
                                                Drop</option>
                                        </select>
                                        @error('update_type')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                    </div>
                                    <div class="flex flex-col js-progress-wrap">
                                        <label class="form-label">Progress (%)</label>
                                        <input class="input {{ $fieldErrorClass('progress_percent') }}" type="number"
                                            min="0" max="100" name="progress_percent"
                                            value="{{ old('progress_percent', $decisionProgressById[$decision->id] ?? 0) }}">
                                        @error('progress_percent')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="form-label">Tanggal Realisasi <span
                                                class="text-danger">*</span></label>
                                        <input class="input {{ $fieldErrorClass('happened_at') }}" type="date"
                                            name="happened_at" value="{{ old('happened_at', now()->format('Y-m-d')) }}"
                                            required>
                                        @error('happened_at')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="form-label">Sesuai Target? <span
                                                class="text-danger">*</span></label>
                                        <select class="select js-on-target {{ $fieldErrorClass('is_on_target') }}"
                                            name="is_on_target" required>
                                            <option value="1"
                                                {{ old('is_on_target', '1') === '1' ? 'selected' : '' }}>Ya</option>
                                            <option value="0" {{ old('is_on_target') === '0' ? 'selected' : '' }}>
                                                Tidak</option>
                                        </select>
                                        @error('is_on_target')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                    </div>
                                    <div
                                        class="flex flex-col md:col-span-2 js-reason-wrap {{ old('is_on_target') === '0' || $errors->has('reason') ? '' : 'hidden' }}">
                                        <label class="form-label">Alasan Tidak Sesuai Target <span
                                                class="text-danger">*</span></label>
                                        <textarea class="textarea w-full {{ $fieldErrorClass('reason') }}" name="reason" rows="2"
                                            placeholder="Wajib diisi jika tidak sesuai target">{{ old('reason') }}</textarea>
                                        @error('reason')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                    </div>
                                    <div class="flex flex-col md:col-span-2">
                                        <label class="form-label">Catatan</label>
                                        <textarea class="textarea w-full {{ $fieldErrorClass('note') }}" name="note" rows="2"
                                            placeholder="Catatan update progress">{{ old('note') }}</textarea>
                                        @error('note')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                    </div>
                                    <div class="flex flex-col md:col-span-2">
                                        <label class="form-label">Bukti Progress <span
                                                class="text-danger">*</span></label>
                                        <input class="file-input {{ $fieldErrorClass('evidence_files') }}"
                                            type="file" name="evidence_files[]"
                                            accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx,.ppt,.pptx" multiple
                                            required>
                                        @error('evidence_files')
                                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                        @enderror
                                        @foreach ($errors->get('evidence_files.*') as $messages)
                                            @foreach ($messages as $message)
                                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                                            @endforeach
                                        @endforeach
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
                            Tindaklanjut dapat ditandai selesai setelah semua item statusnya
                            <strong>done/dropped/berkelanjutan</strong>.
                        </div>
                    @endif
                </div>
            </div>
        @endif

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
                                        <td>
                                            {{ match ((string) $update->update_type) {
                                                'done' => 'Selesai',
                                                'continuous' => 'Berkelanjutan',
                                                'drop' => 'Drop',
                                                default => 'Progress',
                                            } }}
                                        </td>
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
                                            @if ($approval->actor?->directorate)
                                                <span
                                                    class="text-xs text-gray-500">({{ $approval->actor->directorate->displayName() }})</span>
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

        @if ($canDirectorNote)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Komentar Direksi</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('meeting.director.note', $meeting) }}" class="grid gap-4">
                        @csrf
                        <div class="flex flex-col">
                            <label class="form-label">Komentar Direksi<span class="text-danger">*</span></label>
                            <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan komentar viewer..." required></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button class="btn btn-primary" type="submit">Simpan Komentar</button>
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
                                    <th class="min-w-[280px]">Catatan</th>
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
                    <div class="text-sm text-gray-500">Belum ada catatan untuk meeting ini.</div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @php
        $directorateJsOptions = $additionalParticipantDirectorateOptions
            ->map(
                fn($option) => [
                    'id' => $option['id'],
                    'name' =>
                        $option['label'] .
                        (($option['member_count'] ?? 0) > 1 ? ' (' . $option['member_count'] . ' unit)' : ''),
                ],
            )
            ->values();

        $userJsOptions = $users
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'directorate' => $u->directorate?->displayName(),
                ];
            })
            ->values();
        $existingIssueJsOptions = $linkableDecisions
            ->map(function ($decision) {
                return [
                    'id' => $decision->id,
                    'label' => trim(
                        (string) (($decision->issue_key ?? ($decision->decision_key ?? '-')) .
                            ' | ' .
                            \Illuminate\Support\Str::limit($decision->decision_text, 90)),
                    ),
                ];
            })
            ->values();
    @endphp
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const directorateOptions = @json($directorateJsOptions);
            const userOptions = @json($userJsOptions);
            const existingIssueOptions = @json($existingIssueJsOptions);

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

            const buildExistingIssueOptions = () => {
                let html = '<option value="">- Buat issue baru -</option>';
                existingIssueOptions.forEach((item) => {
                    html += `<option value="${item.id}">${item.label}</option>`;
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

            const minutesAgendaRows = document.getElementById('minutes-agenda-rows');
            const addMinutesAgendaButton = document.getElementById('add-minutes-agenda-row');
            if (minutesAgendaRows && addMinutesAgendaButton) {
                const renumberMinutesAgendaRows = () => {
                    minutesAgendaRows.querySelectorAll('.minutes-agenda-row').forEach((row, index) => {
                        const rowNumber = row.querySelector('.row-number');
                        if (rowNumber) {
                            rowNumber.textContent = String(index + 1);
                        }

                        row.querySelectorAll('[name]').forEach((input) => {
                            input.name = input.name.replace(/minutes_agendas\[\d+\]/,
                                `minutes_agendas[${index}]`);
                        });
                    });
                };

                const syncMinutesFollowupState = (row) => {
                    const isLocked = row.dataset.followupLocked === '1';
                    const toggle = row.querySelector('.minutes-followup-toggle');
                    const fieldsWrap = row.querySelector('.minutes-followup-fields');
                    if (!toggle || !fieldsWrap) {
                        return;
                    }

                    const enabled = isLocked || toggle.checked;
                    fieldsWrap.classList.toggle('hidden', !enabled);
                    fieldsWrap.querySelectorAll('textarea, select, input:not([type="hidden"])').forEach((
                        field) => {
                        if (isLocked) {
                            return;
                        }
                        field.disabled = !enabled;
                    });
                };

                const buildMinutesAgendaRow = (index) => {
                    const wrapper = document.createElement('tr');
                    wrapper.className = 'minutes-agenda-row';
                    wrapper.dataset.followupLocked = '0';
                    wrapper.dataset.sourceLocked = '0';
                    wrapper.innerHTML = `
                        <td class="font-medium text-gray-700 row-number">${index + 1}</td>
                        <td>
                            <input type="hidden" name="minutes_agendas[${index}][agenda_id]" value="">
                            <input type="hidden" name="minutes_agendas[${index}][source_decision_id]" value="">
                            <input type="hidden" name="minutes_agendas[${index}][description]" value="">
                            <textarea class="textarea w-full minutes-agenda-title" rows="3"
                                name="minutes_agendas[${index}][title]"
                                placeholder="Materi pembahasan..."></textarea>
                        </td>
                        <td>
                            <div class="grid gap-2">
                                <select class="select minutes-agenda-owner" name="minutes_agendas[${index}][owner_directorate_id]">
                                    ${buildDirectorateOptions()}
                                </select>
                                <select class="select minutes-agenda-pic" name="minutes_agendas[${index}][pic_user_id]">
                                    ${buildUserOptions()}
                                </select>
                            </div>
                        </td>
                        <td>
                            <input type="hidden" name="minutes_agendas[${index}][decision_id]" value="">
                            <div class="grid gap-3">
                                <label class="flex items-center gap-2 text-sm">
                                    <input class="checkbox checkbox-sm minutes-followup-toggle" type="checkbox"
                                        name="minutes_agendas[${index}][followup_enabled]" value="1">
                                    <span>Buat tindak lanjut untuk materi ini</span>
                                </label>
                                <div class="grid gap-3 minutes-followup-fields hidden">
                                    <div class="flex flex-col">
                                        <label class="form-label">Link Issue Existing</label>
                                        <select class="select minutes-existing-issue" name="minutes_agendas[${index}][existing_decision_id]" disabled>
                                            ${buildExistingIssueOptions()}
                                        </select>
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="form-label">Update / Tindak Lanjut <span class="text-danger">*</span></label>
                                        <textarea class="textarea w-full minutes-decision-text" rows="3"
                                            name="minutes_agendas[${index}][decision_text]"
                                            placeholder="Tindak lanjut opsional untuk materi ini..."
                                            disabled></textarea>
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div class="flex flex-col">
                                            <label class="form-label">Target <span class="text-danger">*</span></label>
                                            <input class="input minutes-target-date" type="date"
                                                name="minutes_agendas[${index}][target_date]" disabled>
                                        </div>
                                        <div class="flex flex-col">
                                            <label class="form-label">Status <span class="text-danger">*</span></label>
                                            <select class="select minutes-status-select" name="minutes_agendas[${index}][status]" disabled>
                                                <option value="in_progress">On Progress</option>
                                                <option value="continuous">Berkelanjutan</option>
                                                <option value="done">Done</option>
                                                <option value="pending">Pending</option>
                                                <option value="dropped">Drop</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="flex justify-center">
                                <button type="button" class="btn btn-xs btn-danger remove-minutes-agenda-row">Hapus</button>
                            </div>
                        </td>
                    `;

                    return wrapper;
                };

                addMinutesAgendaButton.addEventListener('click', function() {
                    const index = minutesAgendaRows.querySelectorAll('.minutes-agenda-row').length;
                    const row = buildMinutesAgendaRow(index);
                    minutesAgendaRows.appendChild(row);
                    renumberMinutesAgendaRows();
                    syncMinutesFollowupState(row);
                });

                minutesAgendaRows.querySelectorAll('.minutes-agenda-row').forEach((row) => {
                    syncMinutesFollowupState(row);
                });

                minutesAgendaRows.addEventListener('change', function(event) {
                    const toggle = event.target.closest('.minutes-followup-toggle');
                    if (!toggle) {
                        return;
                    }
                    const row = toggle.closest('.minutes-agenda-row');
                    if (row) {
                        syncMinutesFollowupState(row);
                    }
                });

                minutesAgendaRows.addEventListener('click', function(event) {
                    const removeButton = event.target.closest('.remove-minutes-agenda-row');
                    if (!removeButton) {
                        return;
                    }

                    const row = removeButton.closest('.minutes-agenda-row');
                    if (!row) {
                        return;
                    }

                    if (row.dataset.followupLocked === '1' || row.dataset.sourceLocked === '1') {
                        return;
                    }

                    row.remove();
                    renumberMinutesAgendaRows();
                });
            }

            const minutesPointRows = document.getElementById('minutes-point-rows');
            const addMinutesPointButton = document.getElementById('add-minutes-point-row');
            if (minutesPointRows && addMinutesPointButton) {
                const renumberMinutesPointRows = () => {
                    minutesPointRows.querySelectorAll('.minutes-point-row').forEach((row, index) => {
                        const sequenceLabel = row.querySelector('.minutes-point-seq');
                        if (sequenceLabel) {
                            sequenceLabel.textContent = `Poin ${index + 1}`;
                        }

                        row.querySelectorAll('[name]').forEach((input) => {
                            input.name = input.name.replace(/minutes_agendas\[\d+\]/,
                                `minutes_agendas[${index}]`);
                        });
                    });
                };

                const syncMinutesPointFollowupState = (row) => {
                    const isLocked = row.dataset.followupLocked === '1';
                    const toggle = row.querySelector('.minutes-point-followup-toggle');
                    const fieldsWrap = row.querySelector('.minutes-point-followup-fields');
                    if (!toggle || !fieldsWrap) {
                        return;
                    }

                    const enabled = isLocked || toggle.checked;
                    fieldsWrap.classList.toggle('hidden', !enabled);
                    fieldsWrap.querySelectorAll('textarea, select, input:not([type="hidden"])').forEach((
                        field) => {
                        if (isLocked) {
                            return;
                        }
                        field.disabled = !enabled;
                    });
                };

                const buildMinutesPointRow = (index) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'p-4 border rounded-xl border-gray-200 minutes-point-row';
                    wrapper.dataset.followupLocked = '0';
                    wrapper.dataset.sourceLocked = '0';
                    wrapper.innerHTML = `
                        <input type="hidden" name="minutes_agendas[${index}][agenda_id]" value="">
                        <input type="hidden" name="minutes_agendas[${index}][source_decision_id]" value="">
                        <input type="hidden" name="minutes_agendas[${index}][description]" value="">
                        <input type="hidden" name="minutes_agendas[${index}][decision_id]" value="">

                        <div class="flex items-start justify-between gap-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 minutes-point-seq">Poin ${index + 1}</div>
                            <button type="button" class="btn btn-xs btn-danger remove-minutes-point-row">Hapus</button>
                        </div>

                        <div class="grid gap-3">
                            <div class="flex flex-col">
                                <label class="form-label">Judul / Topik Poin <span class="text-danger">*</span></label>
                                <textarea class="textarea w-full minutes-point-title" rows="2"
                                    name="minutes_agendas[${index}][title]"
                                    placeholder="Topik pembahasan..."></textarea>
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Pembahasan Notulen</label>
                                <textarea class="textarea w-full" rows="4"
                                    name="minutes_agendas[${index}][minutes_discussion]"
                                    placeholder="Isi pembahasan, keputusan, atau penjelasan per poin..."></textarea>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="flex flex-col">
                                    <label class="form-label">PIC Direktorat</label>
                                    <select class="select" name="minutes_agendas[${index}][owner_directorate_id]">
                                        ${buildDirectorateOptions()}
                                    </select>
                                </div>
                                <div class="flex flex-col">
                                    <label class="form-label">PIC User</label>
                                    <select class="select" name="minutes_agendas[${index}][pic_user_id]">
                                        ${buildUserOptions()}
                                    </select>
                                </div>
                            </div>
                            <div class="grid gap-3 lg:grid-cols-2">
                                <div class="flex flex-col">
                                    <label class="form-label">Foto Dokumentasi (Opsional)</label>
                                    <input class="file-input" type="file"
                                        name="minutes_agendas[${index}][photo_files][]"
                                        accept=".jpg,.jpeg,.png" multiple>
                                    <small class="mt-1 text-xs text-gray-500">
                                        Unggah foto bukti atau dokumentasi untuk poin ini bila diperlukan.
                                    </small>
                                </div>
                                <div class="grid gap-2">
                                    <label class="form-label">Foto Tersimpan</label>
                                    <div class="text-sm text-gray-500">Belum ada foto untuk poin ini.</div>
                                </div>
                            </div>
                            <div class="grid gap-3">
                                <label class="flex items-center gap-2 text-sm">
                                    <input class="checkbox checkbox-sm minutes-point-followup-toggle" type="checkbox"
                                        name="minutes_agendas[${index}][followup_enabled]" value="1">
                                    <span>Buat tindak lanjut untuk poin ini</span>
                                </label>
                                <div class="grid gap-3 minutes-point-followup-fields hidden">
                                    <div class="flex flex-col">
                                        <label class="form-label">Link Issue Existing</label>
                                        <select class="select" name="minutes_agendas[${index}][existing_decision_id]" disabled>
                                            ${buildExistingIssueOptions()}
                                        </select>
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="form-label">Tindak Lanjut <span class="text-danger">*</span></label>
                                        <textarea class="textarea w-full" rows="3"
                                            name="minutes_agendas[${index}][decision_text]"
                                            placeholder="Isi tindak lanjut dari poin ini..."
                                            disabled></textarea>
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div class="flex flex-col">
                                            <label class="form-label">Target <span class="text-danger">*</span></label>
                                            <input class="input" type="date"
                                                name="minutes_agendas[${index}][target_date]" disabled>
                                        </div>
                                        <div class="flex flex-col">
                                            <label class="form-label">Status <span class="text-danger">*</span></label>
                                            <select class="select" name="minutes_agendas[${index}][status]" disabled>
                                                <option value="pending">Pending</option>
                                                <option value="in_progress">Proses</option>
                                                <option value="continuous">Berkelanjutan</option>
                                                <option value="done">Done</option>
                                                <option value="dropped">Drop</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    return wrapper;
                };

                addMinutesPointButton.addEventListener('click', function() {
                    const index = minutesPointRows.querySelectorAll('.minutes-point-row').length;
                    const row = buildMinutesPointRow(index);
                    minutesPointRows.appendChild(row);
                    renumberMinutesPointRows();
                    syncMinutesPointFollowupState(row);
                });

                minutesPointRows.querySelectorAll('.minutes-point-row').forEach((row) => {
                    syncMinutesPointFollowupState(row);
                });

                minutesPointRows.addEventListener('change', function(event) {
                    const toggle = event.target.closest('.minutes-point-followup-toggle');
                    if (!toggle) {
                        return;
                    }

                    const row = toggle.closest('.minutes-point-row');
                    if (row) {
                        syncMinutesPointFollowupState(row);
                    }
                });

                minutesPointRows.addEventListener('click', function(event) {
                    const removeButton = event.target.closest('.remove-minutes-point-row');
                    if (!removeButton) {
                        return;
                    }

                    const row = removeButton.closest('.minutes-point-row');
                    if (!row) {
                        return;
                    }

                    if (row.dataset.followupLocked === '1' || row.dataset.sourceLocked === '1') {
                        return;
                    }

                    row.remove();
                    renumberMinutesPointRows();
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
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            <div class="flex flex-col md:col-span-2 xl:col-span-3">
                                <label class="form-label">Link Issue Existing</label>
                                <select class="select" name="decisions[${index}][existing_decision_id]">
                                    ${buildExistingIssueOptions()}
                                </select>
                            </div>
                            <div class="flex flex-col md:col-span-2 xl:col-span-3">
                                <label class="form-label">Item Tindaklanjut</label>
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
                            <div class="flex flex-col">
                                <label class="form-label">Status Issue <span class="text-danger">*</span></label>
                                <select class="select" name="decisions[${index}][status]">
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">Proses</option>
                                    <option value="continuous">Berkelanjutan</option>
                                    <option value="done">Done</option>
                                    <option value="dropped">Drop</option>
                                </select>
                            </div>
                            <div class="flex flex-col md:col-span-2 xl:col-span-3">
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
