@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('meeting.tabulation') }}
@endsection

@section('content')
    @php
        $filters = $filters ?? [];
        $supportsSupportUserAssignments = (bool) ($supportsSupportUserAssignments ?? false);
        $agingLabels = [
            'cat_1' => 'CAT 1 (< 30 hari)',
            'cat_2' => 'CAT 2 (30 - 90 hari)',
            'cat_3' => 'CAT 3 (91 - 180 hari)',
            'cat_4' => 'CAT 4 (181 - 270 hari)',
            'cat_5' => 'CAT 5 (> 270 hari)',
        ];
        $statusBadgeClass = fn(string $status) => match ($status) {
            'pending' => 'badge-warning',
            'in_progress' => 'badge-info',
            'continuous' => 'badge-primary',
            'done' => 'badge-success',
            'dropped' => 'badge-danger',
            default => 'badge-light',
        };
        $statusLabel = fn(string $status) => match ($status) {
            'pending' => 'Pending',
            'in_progress' => 'Proses',
            'continuous' => 'Berkelanjutan',
            'done' => 'Done',
            'dropped' => 'Drop',
            default => $status ?: '-',
        };
        $getDecisionSupportUsers = function ($decision) use ($supportsSupportUserAssignments) {
            if (!$supportsSupportUserAssignments) {
                return collect();
            }

            return $decision->supportUsers ?? collect();
        };
    @endphp

    <div class="grid gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Tabulasi Tindak Lanjut Rapat</h2>
                <div class="text-sm text-gray-500">Ringkasan issue lintas rapat berdasarkan issue terakhir pada tiap family.</div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('meeting.index') }}" class="btn btn-sm btn-light">Kembali ke Daftar Meeting</a>
            </div>
        </div>

        <form method="GET" action="{{ route('meeting.tabulation') }}" class="card">
            <div class="card-header">
                <h3 class="card-title">Filter Tabulasi</h3>
            </div>
            <div class="card-body grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="flex flex-col">
                    <label class="form-label">Kata Kunci</label>
                    <input class="input" type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}"
                        placeholder="Issue key / tindak lanjut / update">
                </div>
                <div class="flex flex-col">
                    <label class="form-label">Jenis Rapat</label>
                    <select class="select" name="meeting_type">
                        <option value="">- Semua -</option>
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['meeting_type'] ?? '') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
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
                    <label class="form-label">Status Issue</label>
                    <select class="select" name="status">
                        <option value="">- Semua -</option>
                        @foreach (['pending' => 'Pending', 'in_progress' => 'Proses', 'continuous' => 'Berkelanjutan', 'done' => 'Done', 'dropped' => 'Drop'] as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col">
                    <label class="form-label">Aging</label>
                    <select class="select" name="aging_bucket">
                        <option value="">- Semua -</option>
                        @foreach ($agingLabels as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['aging_bucket'] ?? '') === $value ? 'selected' : '' }}>
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
                        <span>Hanya issue open</span>
                    </label>
                </div>
            </div>
            <div class="card-footer flex justify-end gap-2">
                <a href="{{ route('meeting.tabulation') }}" class="btn btn-light">Reset</a>
                <button type="submit" class="btn btn-primary">Terapkan Filter</button>
            </div>
        </form>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="card">
                <div class="card-body">
                    <div class="text-xs uppercase text-gray-500">Total Issue</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-800">{{ $summary['total'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs uppercase text-gray-500">Issue Open</div>
                    <div class="mt-1 text-2xl font-semibold text-amber-600">{{ $summary['open'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs uppercase text-gray-500">Berkelanjutan</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-700">{{ $summary['continuous'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs uppercase text-gray-500">Done</div>
                    <div class="mt-1 text-2xl font-semibold text-emerald-600">{{ $summary['done'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs uppercase text-gray-500">Drop</div>
                    <div class="mt-1 text-2xl font-semibold text-rose-600">{{ $summary['dropped'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Issue</h3>
            </div>
            <div class="card-body">
                @if ($tabulations->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="min-w-[120px]">Issue Key</th>
                                    <th class="min-w-[280px]">Issue / Tindak Lanjut</th>
                                    <th class="min-w-[200px]">Rapat Terakhir</th>
                                    <th class="min-w-[180px]">PIC</th>
                                    <th class="min-w-[180px]">Support</th>
                                    <th class="min-w-[130px]">Tgl Radir Awal</th>
                                    <th class="min-w-[90px]">Frekuensi</th>
                                    <th class="min-w-[110px]">Progress</th>
                                    <th class="min-w-[120px]">Status</th>
                                    <th class="min-w-[120px]">Aging</th>
                                    <th class="min-w-[240px]">Update Terkini</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tabulations as $decision)
                                    <tr>
                                        <td>
                                            <div class="font-medium">{{ $decision->issue_key ?? '-' }}</div>
                                            <div class="text-xs text-gray-500">{{ $decision->decision_key ?? '-' }}</div>
                                        </td>
                                        <td>
                                            <div class="font-medium text-gray-800">{{ $decision->decision_text }}</div>
                                            @if ($decision->target_date)
                                                <div class="mt-1 text-xs text-gray-500">
                                                    Target latest: {{ $decision->target_date->format('d/m/Y') }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($decision->meeting)
                                                <a class="text-primary hover:underline"
                                                    href="{{ route('meeting.show', $decision->meeting) }}">
                                                    {{ $decision->meeting->title }}
                                                </a>
                                                <div class="text-xs text-gray-500">
                                                    {{ $typeOptions[$decision->meeting->meeting_type] ?? $decision->meeting->meeting_type }}
                                                    @if ($decision->meeting->meeting_at)
                                                        | {{ $decision->meeting->meeting_at->format('d/m/Y H:i') }}
                                                    @endif
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ $decision->ownerDirectorate?->displayName() ?? '-' }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ $decision->picUser?->name ?? '-' }}
                                            </div>
                                        </td>
                                        <td>
                                            @php($supportUsers = $getDecisionSupportUsers($decision))
                                            @if ($decision->supportDirectorates->count() > 0)
                                                <div class="flex flex-col gap-1">
                                                    @foreach ($decision->supportDirectorates as $supportDirectorate)
                                                        <span class="text-xs text-gray-700">{{ $supportDirectorate->displayName() }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if ($supportUsers->count() > 0)
                                                <div class="flex flex-col gap-1 {{ $decision->supportDirectorates->count() > 0 ? 'mt-2' : '' }}">
                                                    @foreach ($supportUsers as $supportUser)
                                                        <span class="text-xs text-gray-700">
                                                            {{ $supportUser->name }}
                                                            @if ($supportUser->directorate)
                                                                <span class="text-gray-500">({{ $supportUser->directorate->displayName() }})</span>
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if ($decision->supportDirectorates->count() === 0 && $supportUsers->count() === 0)
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $decision->first_discussed_at?->format('d/m/Y') ?? '-' }}</td>
                                        <td>{{ $decision->discussion_count ?? 0 }}x</td>
                                        <td>{{ $decision->latest_progress_percent ?? 0 }}%</td>
                                        <td>
                                            <span class="badge {{ $statusBadgeClass((string) $decision->status) }}">
                                                {{ $statusLabel((string) $decision->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if ($decision->aging_bucket)
                                                <div>{{ $agingLabels[$decision->aging_bucket] ?? strtoupper($decision->aging_bucket) }}</div>
                                                <div class="text-xs text-gray-500">{{ $decision->aging_days ?? 0 }} hari</div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <div class="text-sm text-gray-800">
                                                {{ \Illuminate\Support\Str::limit($decision->latest_update_note ?? '-', 140) }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ $decision->latest_update_at?->format('d/m/Y') ?? '-' }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $tabulations->links() }}
                    </div>
                @else
                    <div class="text-sm text-gray-500">Belum ada issue yang cocok dengan filter tabulasi.</div>
                @endif
            </div>
        </div>
    </div>
@endsection
