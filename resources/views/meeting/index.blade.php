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

    <div class="grid gap-5">
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

        <div class="min-w-full card card-grid" id="meeting-table">
            <div class="flex-wrap py-5 card-header">
                <h3 class="card-title">Daftar Meeting</h3>
                <div class="flex flex-wrap gap-2 lg:gap-5">
                    <div class="flex">
                        <label class="input input-sm">
                            <i class="ki-filled ki-magnifier"></i>
                            <input placeholder="Cari meeting..." id="meeting-search" type="text" value="">
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2.5">
                        @can('corsec.create')
                            <a href="{{ route('meeting.create') }}" class="btn btn-sm btn-primary">
                                <i class="ki-filled ki-plus"></i> Input Meeting
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="scrollable-x-auto">
                    <table class="table text-sm font-medium text-gray-700 align-middle table-auto table-border">
                    <thead>
                        <tr>
                            <th class="min-w-[60px]">No</th>
                            <th class="min-w-[170px]">Jadwal</th>
                            <th class="min-w-[170px]">Kategori</th>
                            <th class="min-w-[260px]">Judul</th>
                            <th class="min-w-[220px]">Status</th>
                            <th class="min-w-[90px]">Peserta</th>
                            <th class="min-w-[90px]">Agenda</th>
                            <th class="min-w-[110px] text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($meetings ?? collect()) as $meeting)
                            <tr data-meeting-row="true">
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
                                <td class="text-center">
                                    <div class="flex flex-nowrap justify-center">
                                        <a href="{{ route('meeting.show', $meeting) }}" class="btn btn-sm btn-icon btn-clear btn-info"
                                            title="Detail">
                                            <i class="ki-outline ki-eye"></i>
                                        </a>
                                        @can('corsec.update')
                                            @if (
                                                ($isAdmin || (int) ($meeting->created_by ?? 0) === (int) ($actor?->id ?? 0)) &&
                                                    in_array((string) ($meeting->status ?? ''), ['draft', 'returned_by_corsec', 'returned_by_direktorat'], true))
                                                <a href="{{ route('meeting.edit', $meeting) }}"
                                                    class="btn btn-sm btn-icon btn-clear btn-info" title="Edit">
                                                    <i class="ki-outline ki-notepad-edit"></i>
                                                </a>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty-row="true">
                                <td colspan="8" class="text-center text-gray-500">Belum ada data meeting.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </div>
            @if (($meetings ?? null) && method_exists($meetings, 'links'))
                <div class="flex flex-col gap-3 justify-between items-start card-footer md:flex-row md:items-center">
                    <div class="font-medium text-gray-600 text-2sm">
                        Menampilkan {{ $meetings->firstItem() ?? 0 }}-{{ $meetings->lastItem() ?? 0 }} dari
                        {{ $meetings->total() }} data
                    </div>
                    <div>
                        {{ $meetings->withQueryString()->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('meeting-search');
            const table = document.getElementById('meeting-table');
            if (!searchInput || !table) {
                return;
            }

            const rows = Array.from(table.querySelectorAll('tbody tr[data-meeting-row="true"]'));
            const emptyRow = table.querySelector('tbody tr[data-empty-row="true"]');

            searchInput.addEventListener('input', function() {
                const keyword = this.value.toLowerCase().trim();
                let visibleCount = 0;

                rows.forEach((row) => {
                    const text = (row.textContent || '').toLowerCase();
                    const visible = keyword === '' || text.includes(keyword);
                    row.classList.toggle('hidden', !visible);
                    if (visible) visibleCount++;
                });

                if (emptyRow) {
                    emptyRow.classList.toggle('hidden', visibleCount !== 0);
                }
            });
        });
    </script>
@endpush
