@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('meeting.index') }}
@endsection

@section('content')
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
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($meetings ?? collect()) as $meeting)
                            <tr>
                                <td>{{ ($meetings->firstItem() ?? 1) + $loop->index }}</td>
                                <td>{{ optional($meeting->meeting_at)->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>{{ $typeOptions[$meeting->meeting_type] ?? $meeting->meeting_type ?? '-' }}</td>
                                <td>{{ $meeting->title }}</td>
                                <td>{{ $statusLabels[$meeting->status] ?? $meeting->status ?? '-' }}</td>
                                <td>{{ $meeting->participants_count ?? 0 }}</td>
                                <td>{{ $meeting->agendas_count ?? 0 }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-gray-500">Belum ada data meeting.</td>
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
