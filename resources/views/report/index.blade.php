@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('report.index') }}
@endsection

@section('content')
    @php
        $report = $report ?? [];
        $filters = $filters ?? [];
        $rows = $report['rows'] ?? null;
        $statusOptions = $report['statusOptions'] ?? [];
        $statusBadgeClasses = $report['statusBadgeClasses'] ?? [];
        $meetingTypeOptions = $report['meetingTypeOptions'] ?? [];
        $formatDate = function ($value, string $format = 'd/m/Y') {
            if (!$value) {
                return '-';
            }

            if ($value instanceof \Illuminate\Support\Carbon) {
                return $value->format($format);
            }

            try {
                return \Illuminate\Support\Carbon::parse($value)->format($format);
            } catch (\Throwable) {
                return (string) $value;
            }
        };
    @endphp

    <div class="grid gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">{{ $report['title'] ?? 'Reporting Corsec' }}</h2>
                <div class="text-sm text-gray-500">{{ $report['description'] ?? 'Rekap lintas modul Corsec.' }}</div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach ($moduleTabs as $moduleKey => $moduleLabel)
                <a href="{{ route('report.index', ['module' => $moduleKey]) }}"
                    class="btn btn-sm {{ $activeModule === $moduleKey ? 'btn-primary' : 'btn-light' }}">
                    {{ $moduleLabel }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('report.index') }}" class="card">
            <input type="hidden" name="module" value="{{ $activeModule }}">
            <div class="card-header">
                <h3 class="card-title">Filter Reporting</h3>
            </div>
            <div class="card-body grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="flex flex-col">
                    <label class="form-label">Kata Kunci</label>
                    <input class="input" type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}"
                        placeholder="Cari data report">
                </div>
                <div class="flex flex-col">
                    <label class="form-label">Direktorat</label>
                    <select class="select" name="directorate_id">
                        <option value="">- Semua -</option>
                        @foreach ($directorates as $directorate)
                            <option value="{{ $directorate->id }}"
                                {{ (string) ($filters['directorate_id'] ?? '') === (string) $directorate->id ? 'selected' : '' }}>
                                {{ $directorate->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col">
                    <label class="form-label">Status</label>
                    <select class="select" name="status">
                        <option value="">- Semua -</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if ($activeModule === 'meeting')
                    <div class="flex flex-col">
                        <label class="form-label">Jenis Rapat</label>
                        <select class="select" name="meeting_type">
                            <option value="">- Semua -</option>
                            @foreach ($meetingTypeOptions as $value => $label)
                                <option value="{{ $value }}"
                                    {{ ($filters['meeting_type'] ?? '') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="flex flex-col">
                    <label class="form-label">Aging</label>
                    <select class="select" name="aging_bucket">
                        <option value="">- Semua -</option>
                        @foreach ($agingLabels as $value => $label)
                            <option value="{{ $value }}"
                                {{ ($filters['aging_bucket'] ?? '') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col">
                    <label class="form-label">Periode Dari</label>
                    <input class="input" type="date" name="period_start" value="{{ $filters['period_start'] ?? '' }}">
                </div>
                <div class="flex flex-col">
                    <label class="form-label">Periode Sampai</label>
                    <input class="input" type="date" name="period_end" value="{{ $filters['period_end'] ?? '' }}">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm">
                        <input class="checkbox checkbox-sm" type="checkbox" name="only_open" value="1"
                            {{ !empty($filters['only_open']) ? 'checked' : '' }}>
                        <span>Hanya data open</span>
                    </label>
                </div>
            </div>
            <div class="card-footer flex justify-end gap-2">
                <a href="{{ route('report.index', ['module' => $activeModule]) }}" class="btn btn-light">Reset</a>
                <button type="submit" class="btn btn-primary">Terapkan Filter</button>
            </div>
        </form>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            @foreach ($report['summaryCards'] ?? [] as $card)
                <div class="card">
                    <div class="card-body">
                        <div class="text-xs uppercase text-gray-500">{{ $card['label'] }}</div>
                        <div class="mt-1 text-2xl font-semibold {{ $card['tone'] ?? 'text-gray-800' }}">
                            {{ $card['value'] ?? 0 }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Progress dan Status</h3>
                </div>
                <div class="card-body grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($report['statusCards'] ?? [] as $card)
                        <div class="rounded-xl border border-gray-200 px-4 py-3">
                            <div class="text-xs uppercase text-gray-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-xl font-semibold {{ $card['tone'] ?? 'text-gray-800' }}">
                                {{ $card['value'] ?? 0 }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">SLA dan Aging</h3>
                </div>
                <div class="card-body grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($report['slaCards'] ?? [] as $card)
                        <div class="rounded-xl border border-gray-200 px-4 py-3">
                            <div class="text-xs uppercase text-gray-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-xl font-semibold {{ $card['tone'] ?? 'text-gray-800' }}">
                                {{ $card['value'] ?? 0 }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="card-footer grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                    @foreach ($report['agingCards'] ?? [] as $card)
                        <div class="rounded-xl bg-gray-50 px-3 py-2">
                            <div class="text-xs uppercase text-gray-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-lg font-semibold {{ $card['tone'] ?? 'text-gray-800' }}">
                                {{ $card['value'] ?? 0 }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Tabulasi Keseluruhan</h3>
            </div>
            <div class="card-body">
                @if ($rows && $rows->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    @if ($activeModule === 'incoming')
                                        <th class="min-w-[140px]">No Registrasi</th>
                                        <th class="min-w-[260px]">Surat</th>
                                        <th class="min-w-[180px]">Direktorat</th>
                                        <th class="min-w-[150px]">Jenis</th>
                                        <th class="min-w-[130px]">Due Date</th>
                                        <th class="min-w-[140px]">Status</th>
                                        <th class="min-w-[130px]">Aging</th>
                                    @elseif ($activeModule === 'outgoing')
                                        <th class="min-w-[140px]">No Registrasi</th>
                                        <th class="min-w-[260px]">Surat</th>
                                        <th class="min-w-[180px]">Direktorat</th>
                                        <th class="min-w-[150px]">Jenis</th>
                                        <th class="min-w-[160px]">Terkait Surat Masuk</th>
                                        <th class="min-w-[130px]">Due Date</th>
                                        <th class="min-w-[140px]">Status</th>
                                        <th class="min-w-[130px]">Aging</th>
                                    @elseif ($activeModule === 'meeting')
                                        <th class="min-w-[120px]">Issue Key</th>
                                        <th class="min-w-[280px]">Tindak Lanjut</th>
                                        <th class="min-w-[220px]">Rapat</th>
                                        <th class="min-w-[180px]">PIC</th>
                                        <th class="min-w-[130px]">Target</th>
                                        <th class="min-w-[140px]">Status</th>
                                        <th class="min-w-[130px]">Aging</th>
                                    @else
                                        <th class="min-w-[240px]">Program</th>
                                        <th class="min-w-[260px]">Item</th>
                                        <th class="min-w-[180px]">Direktorat</th>
                                        <th class="min-w-[130px]">Target</th>
                                        <th class="min-w-[140px]">Status</th>
                                        <th class="min-w-[130px]">Aging</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    @php
                                        $badgeClass = $statusBadgeClasses[(string) ($row->status ?? '')] ?? 'badge-light';
                                        $statusLabel = $statusOptions[(string) ($row->status ?? '')] ?? ($row->display_status_label ?? ($row->status ?? '-'));
                                        $agingBucket = $row->report_aging_bucket ?? $row->aging_bucket ?? null;
                                        $agingDays = $row->report_aging_days ?? $row->aging_days ?? null;
                                    @endphp
                                    <tr>
                                        @if ($activeModule === 'incoming')
                                            <td>
                                                <div class="font-medium">{{ $row->registration_no ?? '-' }}</div>
                                                <div class="text-xs text-gray-500">{{ $row->external_letter_no ?? '-' }}</div>
                                            </td>
                                            <td>
                                                <a class="font-medium text-primary hover:underline"
                                                    href="{{ route('letter.incoming.show', $row) }}">
                                                    {{ $row->subject ?? '-' }}
                                                </a>
                                                <div class="text-xs text-gray-500">
                                                    {{ \Illuminate\Support\Str::limit($row->summary ?? '-', 120) }}
                                                </div>
                                            </td>
                                            <td>{{ $row->targetDirectorate?->displayName() ?? '-' }}</td>
                                            <td>{{ $row->letterType?->name ?? '-' }}</td>
                                            <td>{{ $formatDate($row->report_due_date ?? null) }}</td>
                                            <td>
                                                <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                                            </td>
                                            <td>
                                                @if ($agingBucket)
                                                    <div>{{ $agingLabels[$agingBucket] ?? strtoupper((string) $agingBucket) }}</div>
                                                    <div class="text-xs text-gray-500">{{ $agingDays ?? 0 }} hari</div>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        @elseif ($activeModule === 'outgoing')
                                            <td>
                                                <div class="font-medium">{{ $row->registration_no ?? '-' }}</div>
                                                <div class="text-xs text-gray-500">{{ $row->letter_no ?? '-' }}</div>
                                            </td>
                                            <td>
                                                <a class="font-medium text-primary hover:underline"
                                                    href="{{ route('letter.outgoing.show', $row) }}">
                                                    {{ $row->subject ?? '-' }}
                                                </a>
                                                <div class="text-xs text-gray-500">
                                                    {{ \Illuminate\Support\Str::limit($row->summary ?? '-', 120) }}
                                                </div>
                                            </td>
                                            <td>{{ $row->requesterDirectorate?->displayName() ?? '-' }}</td>
                                            <td>{{ $row->letterType?->name ?? '-' }}</td>
                                            <td>
                                                @if ($row->perihalIncomingLetter)
                                                    <div>{{ $row->perihalIncomingLetter->registration_no ?? '-' }}</div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ \Illuminate\Support\Str::limit($row->perihalIncomingLetter->subject ?? '-', 80) }}
                                                    </div>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $formatDate($row->report_due_date ?? null) }}</td>
                                            <td>
                                                <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                                            </td>
                                            <td>
                                                @if ($agingBucket)
                                                    <div>{{ $agingLabels[$agingBucket] ?? strtoupper((string) $agingBucket) }}</div>
                                                    <div class="text-xs text-gray-500">{{ $agingDays ?? 0 }} hari</div>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        @elseif ($activeModule === 'meeting')
                                            <td>
                                                <div class="font-medium">{{ $row->issue_key ?? '-' }}</div>
                                                <div class="text-xs text-gray-500">{{ $row->decision_key ?? '-' }}</div>
                                            </td>
                                            <td>
                                                <div class="font-medium text-gray-800">{{ $row->decision_text ?? '-' }}</div>
                                                <div class="text-xs text-gray-500">
                                                    {{ \Illuminate\Support\Str::limit($row->latest_update_note ?? '-', 120) }}
                                                </div>
                                            </td>
                                            <td>
                                                @if ($row->meeting)
                                                    <a class="text-primary hover:underline" href="{{ route('meeting.show', $row->meeting) }}">
                                                        {{ $row->meeting->title }}
                                                    </a>
                                                    <div class="text-xs text-gray-500">
                                                        {{ $meetingTypeOptions[$row->meeting->meeting_type] ?? $row->meeting->meeting_type }}
                                                        @if ($row->meeting->meeting_at)
                                                            | {{ $row->meeting->meeting_at->format('d/m/Y H:i') }}
                                                        @endif
                                                    </div>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <div>{{ $row->ownerDirectorate?->displayName() ?? '-' }}</div>
                                                <div class="text-xs text-gray-500">{{ $row->picUser?->name ?? '-' }}</div>
                                            </td>
                                            <td>{{ $formatDate($row->target_date ?? null) }}</td>
                                            <td>
                                                <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                                            </td>
                                            <td>
                                                @if ($agingBucket)
                                                    <div>{{ $agingLabels[$agingBucket] ?? strtoupper((string) $agingBucket) }}</div>
                                                    <div class="text-xs text-gray-500">{{ $agingDays ?? 0 }} hari</div>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        @else
                                            <td>
                                                @if ($row->program)
                                                    <a class="text-primary hover:underline" href="{{ route('workplan.show', $row->program) }}">
                                                        {{ $row->program->title }}
                                                    </a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <div class="font-medium text-gray-800">{{ $row->title ?? '-' }}</div>
                                                <div class="text-xs text-gray-500">
                                                    {{ \Illuminate\Support\Str::limit($row->description ?? '-', 120) }}
                                                </div>
                                            </td>
                                            <td>{{ $row->program?->directorate?->displayName() ?? '-' }}</td>
                                            <td>{{ $formatDate($row->target_date ?? null) }}</td>
                                            <td>
                                                <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                                            </td>
                                            <td>
                                                @if ($agingBucket)
                                                    <div>{{ $agingLabels[$agingBucket] ?? strtoupper((string) $agingBucket) }}</div>
                                                    <div class="text-xs text-gray-500">{{ $agingDays ?? 0 }} hari</div>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $rows->links() }}
                    </div>
                @else
                    <div class="text-sm text-gray-500">Belum ada data reporting yang cocok dengan filter saat ini.</div>
                @endif
            </div>
        </div>
    </div>
@endsection
