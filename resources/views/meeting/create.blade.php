@extends('layouts.main')

@section('breadcrumbs')
    @if (isset($meeting))
        {{ Breadcrumbs::render('meeting.edit', $meeting) }}
    @else
        {{ Breadcrumbs::render('meeting.create') }}
    @endif
@endsection

@section('content')
    @php
        $meeting = $meeting ?? null;
        $isEdit = isset($meeting);
        $isEditableStatus =
            !$isEdit ||
            in_array((string) $meeting->status, ['draft', 'returned_by_corsec', 'returned_by_direktorat'], true);
        $meetingAt = $meeting?->meeting_at;
        $selectedMeetingType = old('meeting_type', $meeting?->meeting_type);

        $selectedDirectorates = old(
            'participants',
            $isEdit
                ? $meeting->participants
                    ->pluck('directorate_id')
                    ->filter()
                    ->map(fn($id) => (string) $id)
                    ->values()
                    ->all()
                : [],
        );
        $selectedUsers = old(
            'participant_users',
            $isEdit
                ? $meeting->participants->pluck('user_id')->filter()->map(fn($id) => (string) $id)->values()->all()
                : [],
        );

        $agendas = old('agendas');
        if (!is_array($agendas)) {
            if ($isEdit) {
                $agendas = $meeting->agendas
                    ->map(function ($agenda) {
                        return [
                            'title' => $agenda->title,
                            'description' => $agenda->description,
                            'owner_directorate_id' => $agenda->owner_directorate_id,
                            'pic_user_id' => $agenda->pic_user_id,
                        ];
                    })
                    ->toArray();
            } else {
                $agendas = [];
            }
        }

        if (count($agendas) === 0) {
            $agendas[] = [
                'title' => '',
                'description' => '',
                'owner_directorate_id' => '',
                'pic_user_id' => '',
            ];
        }

        $meetingDates = old('meeting_dates');
        if (!is_array($meetingDates)) {
            $meetingDates = [];
        }
        if (count($meetingDates) === 0 && !$isEdit && old('meeting_date')) {
            $meetingDates[] = old('meeting_date');
        }

        $directorateOptions = $directorates
            ->map(
                fn($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                ],
            )
            ->values();

        $userOptions = $users
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'directorate' => $u->directorate?->name,
                ];
            })
            ->values();

        $direktoratTypeCodes = collect($direktoratTypeCodes ?? [\Modules\Corsec\Models\Meeting::TYPE_DIREKTORAT])
            ->map(fn($code) => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $mandatoryDirectorateIds = collect($mandatoryDirectorateIds ?? [])
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $isDirektoratMeetingSelected = in_array(
            strtolower(trim((string) $selectedMeetingType)),
            $direktoratTypeCodes,
            true,
        );
    @endphp

    <div class="grid gap-5 lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ $isEdit ? 'Edit Jadwal Rapat' : 'Input Rencana Jadwal Rapat' }}</h3>
                <a href="{{ route('meeting.index') }}" class="btn btn-sm btn-light">
                    <i class="ki-filled ki-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ $isEdit ? route('meeting.update', $meeting) : route('meeting.store') }}"
                    id="meeting-form" class="grid gap-5">
                    @csrf
                    @if ($isEdit)
                        @method('PUT')
                    @endif

                    <input type="hidden" name="submit_for_approval" id="submit_for_approval"
                        value="{{ old('submit_for_approval', 0) }}">

                    @if ($errors->any())
                        <div class="rounded-lg border border-danger/30 bg-danger-light p-3">
                            <div class="text-sm font-medium text-danger mb-1">Gagal menyimpan meeting:</div>
                            <ul class="list-disc pl-5 text-sm text-danger space-y-0.5">
                                @foreach ($errors->all() as $errorMessage)
                                    <li>{{ $errorMessage }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="flex flex-col">
                            <label class="form-label">Kategori Rapat <span class="text-danger">*</span></label>
                            <select class="select @error('meeting_type') border-danger bg-danger-light @enderror"
                                name="meeting_type" id="meeting_type" required>
                                <option value="">- Pilih Kategori -</option>
                                @foreach ($typeOptions as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ $selectedMeetingType === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('meeting_type')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                        <div class="flex flex-col">
                            <label class="form-label">Judul Rapat <span class="text-danger">*</span></label>
                            <input class="input @error('title') border-danger bg-danger-light @enderror" type="text"
                                name="title" maxlength="255" value="{{ old('title', $meeting?->title) }}"
                                placeholder="Contoh: Rapat Koordinasi Bulanan" required>
                            @error('title')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                        <div class="flex flex-col" id="single-meeting-date-wrap">
                            <label class="form-label">Tanggal Rapat <span class="text-danger">*</span></label>
                            <input class="input @error('meeting_date') border-danger bg-danger-light @enderror"
                                type="date" name="meeting_date"
                                value="{{ old('meeting_date', optional($meetingAt)->format('Y-m-d')) }}">
                            @error('meeting_date')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                        <div class="flex flex-col">
                            <label class="form-label">Jam Rapat</label>
                            <input class="input @error('meeting_time') border-danger bg-danger-light @enderror"
                                type="time" name="meeting_time"
                                value="{{ old('meeting_time', optional($meetingAt)->format('H:i')) }}">
                            @error('meeting_time')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Tempat Rapat</label>
                            <input class="input @error('location') border-danger bg-danger-light @enderror" type="text"
                                name="location" maxlength="255" value="{{ old('location', $meeting?->location) }}"
                                placeholder="Contoh: Ruang Rapat Lantai 10">
                            @error('location')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Deskripsi Rapat</label>
                            <textarea class="textarea w-full @error('description') border-danger bg-danger-light @enderror" rows="3"
                                name="description" placeholder="Ringkasan tujuan atau konteks rapat...">{{ old('description', $meeting?->description) }}</textarea>
                            @error('description')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        @if (!$isEdit)
                            <div class="flex flex-col md:col-span-2 hidden" id="batch-meeting-dates-wrap">
                                <div class="border border-gray-200 rounded-xl p-4 grid gap-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <div class="font-semibold text-gray-800">Batch Jadwal Rapat (Khusus Direktorat)
                                            </div>
                                            <div class="text-xs text-gray-500">Bisa input beberapa tanggal sekaligus
                                                (maksimal 31).</div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-light-primary"
                                            id="add-meeting-date-row">
                                            <i class="ki-filled ki-plus"></i> Tambah Tanggal
                                        </button>
                                    </div>
                                    <div id="meeting-date-rows" class="grid gap-2">
                                        @foreach ($meetingDates as $index => $meetingDate)
                                            <div class="flex items-center gap-2 meeting-date-row">
                                                <input
                                                    class="input flex-1 @error('meeting_dates.' . $index) border-danger bg-danger-light @enderror"
                                                    type="date" name="meeting_dates[]" value="{{ $meetingDate }}">
                                                <button type="button"
                                                    class="btn btn-xs btn-danger remove-meeting-date-row">Hapus</button>
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('meeting_dates')
                                        <em class="text-sm alert text-danger">{{ $message }}</em>
                                    @enderror
                                    @error('meeting_date')
                                        <em class="text-sm alert text-danger">{{ $message }}</em>
                                    @enderror
                                    @foreach ($errors->get('meeting_dates.*') as $messages)
                                        @foreach ($messages as $message)
                                            <em class="text-sm alert text-danger">{{ $message }}</em>
                                        @endforeach
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <div class="card card-grid border border-gray-200">
                            <div class="card-header py-3">
                                <h4 class="card-title text-sm">Pilih Peserta Rapat</h4>
                            </div>
                            <div class="card-body max-h-[260px] overflow-auto grid gap-2">
                                @foreach ($directorates as $directorate)
                                    @php
                                        $isMandatoryDirectorate = in_array(
                                            (int) $directorate->id,
                                            $mandatoryDirectorateIds,
                                            true,
                                        );
                                        $isOperationalDirectorate = (bool) ($directorate->is_meeting_operational ?? false);
                                        $isCheckedDirectorate =
                                            in_array((string) $directorate->id, $selectedDirectorates, true) ||
                                            ($isDirektoratMeetingSelected && $isMandatoryDirectorate);
                                    @endphp
                                    <label class="flex items-center gap-2 text-sm">
                                        <input class="checkbox checkbox-sm" type="checkbox" name="participants[]"
                                            value="{{ $directorate->id }}"
                                            data-is-mandatory="{{ $isMandatoryDirectorate ? '1' : '0' }}"
                                            {{ $isCheckedDirectorate ? 'checked' : '' }}
                                            {{ $isDirektoratMeetingSelected && $isMandatoryDirectorate ? 'disabled' : '' }}>
                                        <span>
                                            {{ $directorate->name }}
                                            @if (!$isOperationalDirectorate)
                                                <span class="text-gray-500">(Monitoring Only)</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('participants')
                                <div class="px-3 pb-3">
                                    <em class="text-sm alert text-danger">{{ $message }}</em>
                                </div>
                            @enderror
                        </div>
                        <div class="card card-grid border border-gray-200" id="participant-users-card">
                            <div class="card-header py-3">
                                <h4 class="card-title text-sm">Pilih User (Opsional)</h4>
                            </div>
                            <div class="card-body max-h-[260px] overflow-auto grid gap-2">
                                @foreach ($users as $optionUser)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input class="checkbox checkbox-sm" type="checkbox" name="participant_users[]"
                                            value="{{ $optionUser->id }}"
                                            {{ in_array((string) $optionUser->id, $selectedUsers, true) ? 'checked' : '' }}>
                                        <span>
                                            {{ $optionUser->name }}
                                            @if ($optionUser->directorate?->name)
                                                <span class="text-gray-500">({{ $optionUser->directorate->name }})</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-5" id="agenda-section">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <h4 class="font-semibold text-gray-800">Agenda Rapat</h4>
                            <button type="button" class="btn btn-sm btn-light-primary" id="add-agenda-row">
                                <i class="ki-filled ki-plus"></i> Tambah Agenda
                            </button>
                        </div>

                        <div id="agenda-rows" class="grid gap-4">
                            @foreach ($agendas as $index => $agenda)
                                <div class="p-4 border rounded-xl border-gray-200 agenda-row">
                                    <div class="flex justify-between items-center mb-3">
                                        <div class="font-medium text-gray-800">Agenda #{{ $index + 1 }}</div>
                                        <button type="button"
                                            class="btn btn-xs btn-danger remove-agenda-row">Hapus</button>
                                    </div>
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="flex flex-col md:col-span-2">
                                            <label class="form-label">Agenda <span class="text-danger">*</span></label>
                                            <input
                                                class="input @error('agendas.' . $index . '.title') border-danger bg-danger-light @enderror"
                                                type="text" name="agendas[{{ $index }}][title]"
                                                value="{{ old('agendas.' . $index . '.title', $agenda['title'] ?? '') }}"
                                                required>
                                        </div>
                                        <div class="flex flex-col md:col-span-2">
                                            <label class="form-label">Deskripsi</label>
                                            <textarea class="textarea w-full @error('agendas.' . $index . '.description') border-danger bg-danger-light @enderror"
                                                rows="2" name="agendas[{{ $index }}][description]">{{ old('agendas.' . $index . '.description', $agenda['description'] ?? '') }}</textarea>
                                        </div>
                                        <div class="flex flex-col">
                                            <label class="form-label">PIC Direktorat</label>
                                            <select
                                                class="select @error('agendas.' . $index . '.owner_directorate_id') border-danger bg-danger-light @enderror"
                                                name="agendas[{{ $index }}][owner_directorate_id]">
                                                <option value="">- Pilih Direktorat -</option>
                                                @foreach ($directorates as $directorate)
                                                    <option value="{{ $directorate->id }}"
                                                        {{ (string) old('agendas.' . $index . '.owner_directorate_id', $agenda['owner_directorate_id'] ?? '') === (string) $directorate->id ? 'selected' : '' }}>
                                                        {{ $directorate->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="flex flex-col">
                                            <label class="form-label">PIC User</label>
                                            <select
                                                class="select @error('agendas.' . $index . '.pic_user_id') border-danger bg-danger-light @enderror"
                                                name="agendas[{{ $index }}][pic_user_id]">
                                                <option value="">- Pilih User -</option>
                                                @foreach ($users as $optionUser)
                                                    <option value="{{ $optionUser->id }}"
                                                        {{ (string) old('agendas.' . $index . '.pic_user_id', $agenda['pic_user_id'] ?? '') === (string) $optionUser->id ? 'selected' : '' }}>
                                                        {{ $optionUser->name }}
                                                        @if ($optionUser->directorate?->name)
                                                            ({{ $optionUser->directorate->name }})
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('agendas')
                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                        @enderror
                    </div>

                    <div class="flex flex-col">
                        <label class="form-label">Catatan Submit (Opsional)</label>
                        <textarea class="textarea w-full @error('submit_note') border-danger bg-danger-light @enderror" name="submit_note"
                            rows="3" placeholder="Tambahkan catatan untuk otorisator...">{{ old('submit_note') }}</textarea>
                        @error('submit_note')
                            <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="submit" class="btn btn-light" data-submit-mode="draft"
                            {{ !$isEditableStatus ? 'disabled' : '' }}>
                            Simpan Draft
                        </button>
                        <button type="submit" class="btn btn-primary" data-submit-mode="approval"
                            {{ !$isEditableStatus ? 'disabled' : '' }}>
                            Submit Approval
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const DIREKTORAT_TYPES = new Set(@json($direktoratTypeCodes));
            const agendaRows = document.getElementById('agenda-rows');
            const addAgendaButton = document.getElementById('add-agenda-row');
            const submitInput = document.getElementById('submit_for_approval');
            const meetingForm = document.getElementById('meeting-form');
            const meetingTypeSelect = document.getElementById('meeting_type');
            const singleMeetingDateWrap = document.getElementById('single-meeting-date-wrap');
            const singleMeetingDateInput = singleMeetingDateWrap ? singleMeetingDateWrap.querySelector(
                'input[name="meeting_date"]') : null;
            const participantUsersCard = document.getElementById('participant-users-card');
            const agendaSection = document.getElementById('agenda-section');
            const batchMeetingDatesWrap = document.getElementById('batch-meeting-dates-wrap');
            const meetingDateRows = document.getElementById('meeting-date-rows');
            const addMeetingDateRowButton = document.getElementById('add-meeting-date-row');

            if (!agendaRows || !addAgendaButton || !meetingForm || !meetingTypeSelect) {
                return;
            }

            const directorateOptions = @json($directorateOptions);
            const userOptions = @json($userOptions);
            const mandatoryDirectorateIds = new Set(@json($mandatoryDirectorateIds));
            const participantDirectorateCheckboxes = meetingForm.querySelectorAll('input[name="participants[]"]');

            const buildSelectOptions = (options, placeholder) => {
                let html = `<option value=\"\">${placeholder}</option>`;
                options.forEach((item) => {
                    html += `<option value=\"${item.id}\">${item.name}</option>`;
                });
                return html;
            };

            const buildUserSelectOptions = (options, placeholder) => {
                let html = `<option value=\"\">${placeholder}</option>`;
                options.forEach((item) => {
                    const label = item.directorate ? `${item.name} (${item.directorate})` : item.name;
                    html += `<option value=\"${item.id}\">${label}</option>`;
                });
                return html;
            };

            const normalizeMeetingType = (value) => String(value ?? '').trim().toLowerCase();

            const toggleMandatoryDirectorateParticipants = (isDirektoratMeeting) => {
                participantDirectorateCheckboxes.forEach((checkbox) => {
                    const directorateId = Number(checkbox.value);
                    if (!mandatoryDirectorateIds.has(directorateId)) {
                        return;
                    }

                    if (isDirektoratMeeting) {
                        checkbox.checked = true;
                        checkbox.disabled = true;
                        return;
                    }

                    checkbox.disabled = false;
                });
            };

            const setSectionDisabled = (section, disabled) => {
                if (!section) {
                    return;
                }
                section.querySelectorAll('input, select, textarea, button').forEach((field) => {
                    if (field.id === 'add-agenda-row' || field.id === 'add-meeting-date-row') {
                        field.disabled = disabled;
                        return;
                    }
                    field.disabled = disabled;
                });
            };

            const renumberAgendaRows = () => {
                agendaRows.querySelectorAll('.agenda-row').forEach((row, index) => {
                    const titleNode = row.querySelector('.font-medium');
                    if (titleNode) {
                        titleNode.textContent = `Agenda #${index + 1}`;
                    }

                    row.querySelectorAll('[name]').forEach((input) => {
                        input.name = input.name.replace(/agendas\\[\\d+\\]/,
                            `agendas[${index}]`);
                    });
                });
            };

            const buildMeetingDateRow = (value = '') => {
                const wrapper = document.createElement('div');
                wrapper.className = 'flex items-center gap-2 meeting-date-row';
                wrapper.innerHTML = `
                    <input class="input flex-1" type="date" name="meeting_dates[]" value="${value}">
                    <button type="button" class="btn btn-xs btn-danger remove-meeting-date-row">Hapus</button>
                `;
                return wrapper;
            };

            const ensureMeetingDateRow = () => {
                if (!meetingDateRows) {
                    return;
                }
                if (meetingDateRows.querySelectorAll('.meeting-date-row').length === 0) {
                    meetingDateRows.appendChild(buildMeetingDateRow(''));
                }
            };

            const toggleDirektoratMode = () => {
                const isDirektoratMeeting = DIREKTORAT_TYPES.has(normalizeMeetingType(meetingTypeSelect.value));
                toggleMandatoryDirectorateParticipants(isDirektoratMeeting);

                if (participantUsersCard) {
                    participantUsersCard.classList.toggle('hidden', isDirektoratMeeting);
                    participantUsersCard.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                        checkbox.disabled = isDirektoratMeeting;
                        if (isDirektoratMeeting) {
                            checkbox.checked = false;
                        }
                    });
                }

                if (agendaSection) {
                    agendaSection.classList.toggle('hidden', isDirektoratMeeting);
                    setSectionDisabled(agendaSection, isDirektoratMeeting);
                    if (!isDirektoratMeeting) {
                        agendaSection.querySelectorAll('input[name*="[title]"]').forEach((input) => {
                            input.required = true;
                        });
                    }
                }

                if (batchMeetingDatesWrap) {
                    batchMeetingDatesWrap.classList.toggle('hidden', !isDirektoratMeeting);
                    setSectionDisabled(batchMeetingDatesWrap, !isDirektoratMeeting);
                    if (isDirektoratMeeting) {
                        ensureMeetingDateRow();
                    }
                }

                if (singleMeetingDateWrap && batchMeetingDatesWrap) {
                    singleMeetingDateWrap.classList.toggle('hidden', isDirektoratMeeting);
                }
                if (singleMeetingDateInput && batchMeetingDatesWrap) {
                    singleMeetingDateInput.disabled = isDirektoratMeeting;
                }
            };

            addAgendaButton.addEventListener('click', function() {
                const index = agendaRows.querySelectorAll('.agenda-row').length;
                const wrapper = document.createElement('div');
                wrapper.className = 'p-4 border rounded-xl border-gray-200 agenda-row';
                wrapper.innerHTML = `
                    <div class="flex justify-between items-center mb-3">
                        <div class="font-medium text-gray-800">Agenda #${index + 1}</div>
                        <button type="button" class="btn btn-xs btn-danger remove-agenda-row">Hapus</button>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Agenda <span class="text-danger">*</span></label>
                            <input class="input" type="text" name="agendas[${index}][title]" required>
                        </div>
                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="textarea w-full" rows="2" name="agendas[${index}][description]"></textarea>
                        </div>
                        <div class="flex flex-col">
                            <label class="form-label">PIC Direktorat</label>
                            <select class="select" name="agendas[${index}][owner_directorate_id]">
                                ${buildSelectOptions(directorateOptions, '- Pilih Direktorat -')}
                            </select>
                        </div>
                        <div class="flex flex-col">
                            <label class="form-label">PIC User</label>
                            <select class="select" name="agendas[${index}][pic_user_id]">
                                ${buildUserSelectOptions(userOptions, '- Pilih User -')}
                            </select>
                        </div>
                    </div>
                `;
                agendaRows.appendChild(wrapper);
            });

            agendaRows.addEventListener('click', function(event) {
                const removeButton = event.target.closest('.remove-agenda-row');
                if (!removeButton) {
                    return;
                }
                const row = removeButton.closest('.agenda-row');
                if (row && agendaRows.querySelectorAll('.agenda-row').length > 1) {
                    row.remove();
                    renumberAgendaRows();
                }
            });

            if (addMeetingDateRowButton && meetingDateRows) {
                addMeetingDateRowButton.addEventListener('click', function() {
                    if (meetingDateRows.querySelectorAll('.meeting-date-row').length >= 31) {
                        return;
                    }
                    meetingDateRows.appendChild(buildMeetingDateRow(''));
                });

                meetingDateRows.addEventListener('click', function(event) {
                    const removeButton = event.target.closest('.remove-meeting-date-row');
                    if (!removeButton) {
                        return;
                    }

                    const row = removeButton.closest('.meeting-date-row');
                    if (!row) {
                        return;
                    }

                    if (meetingDateRows.querySelectorAll('.meeting-date-row').length <= 1) {
                        const input = row.querySelector('input[name="meeting_dates[]"]');
                        if (input) {
                            input.value = '';
                        }
                        return;
                    }

                    row.remove();
                });
            }

            meetingForm.querySelectorAll('button[data-submit-mode]').forEach((button) => {
                button.addEventListener('click', function() {
                    submitInput.value = this.getAttribute('data-submit-mode') === 'approval' ? '1' :
                        '0';
                });
            });

            meetingTypeSelect.addEventListener('change', toggleDirektoratMode);
            toggleDirektoratMode();
        });
    </script>
@endpush
