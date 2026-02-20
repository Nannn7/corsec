@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('meeting.index') }}
@endsection

@section('content')
    @php
        $statusBadgeClass = function (string $status): string {
            return match ($status) {
                'draft' => 'badge-light',
                'waiting_corsec_approval', 'waiting_direktorat_approval' => 'badge-warning',
                'returned_by_corsec', 'returned_by_direktorat' => 'badge-danger',
                'jadwal_terkirim', 'pending_direktorat', 'data_terkirim' => 'badge-info',
                'proses_pembuatan_notulen', 'proses_sirkulasi_tandatangan', 'proses_tindaklanjut_hasil_rapat' => 'badge-primary',
                'notulen_final', 'done_tindaklanjut_hasil_rapat' => 'badge-success',
                default => 'badge-light',
            };
        };
        $actor = auth()->user();
        $isAdmin = $actor?->hasRole('administrator') ?? false;
    @endphp

    <div class="space-y-6">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="card">
                <div class="card-body">
                    <div class="text-xs uppercase text-gray-500">Total Meeting</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-800">{{ $summary['total'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs uppercase text-gray-500">Menunggu Corsec</div>
                    <div class="mt-1 text-2xl font-semibold text-amber-600">{{ $summary['waiting_corsec_approval'] ?? 0 }}
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs uppercase text-gray-500">Menunggu Direktorat</div>
                    <div class="mt-1 text-2xl font-semibold text-orange-600">
                        {{ $summary['waiting_direktorat_approval'] ?? 0 }}
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs uppercase text-gray-500">Followup Berjalan</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-600">{{ $summary['followup_open'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-xs uppercase text-gray-500">Followup Selesai</div>
                    <div class="mt-1 text-2xl font-semibold text-emerald-600">{{ $summary['done_followup'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Meeting</h3>
                @can('corsec.create')
                    <a href="{{ route('meeting.create') }}" class="btn btn-sm btn-primary">
                        <i class="ki-filled ki-plus"></i> Input Meeting
                    </a>
                @endcan
            </div>
            <div class="card-body overflow-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Jadwal</th>
                            <th>Kategori</th>
                            <th>Judul</th>
                            <th>Status</th>
                            <th>Peserta</th>
                            <th>Agenda</th>
                            <th class="min-w-[160px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($meetings ?? collect()) as $meeting)
                            <tr>
                                <td>{{ ($meetings->firstItem() ?? 1) + $loop->index }}</td>
                                <td>{{ optional($meeting->meeting_at)->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>{{ $typeOptions[$meeting->meeting_type] ?? $meeting->meeting_type ?? '-' }}</td>
                                <td>{{ $meeting->title }}</td>
                                <td>
                                    <span class="badge {{ $statusBadgeClass((string) ($meeting->status ?? '')) }}">
                                        {{ $statusLabels[$meeting->status] ?? $meeting->status ?? '-' }}
                                    </span>
                                </td>
                                <td>{{ $meeting->participants_count ?? 0 }}</td>
                                <td>{{ $meeting->agendas_count ?? 0 }}</td>
                                <td>
                                    <div class="flex gap-2">
                                        <a href="{{ route('meeting.show', $meeting) }}" class="btn btn-sm btn-light-primary">
                                            Detail
                                        </a>
                                        @can('corsec.update')
                                            @if (
                                                ($isAdmin || (int) ($meeting->created_by ?? 0) === (int) ($actor?->id ?? 0)) &&
                                                    in_array((string) ($meeting->status ?? ''), ['draft', 'returned_by_corsec', 'returned_by_direktorat'], true))
                                                <a href="{{ route('meeting.edit', $meeting) }}" class="btn btn-sm btn-info">Edit</a>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-gray-500">Belum ada data meeting.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (($meetings ?? null) && method_exists($meetings, 'links'))
                <div class="card-footer">
                    {{ $meetings->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
