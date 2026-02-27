@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('meeting.index') }}
@endsection

@section('content')
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

        <div class="min-w-full card card-grid" data-datatable="false" data-datatable-page-size="10"
            data-datatable-state-save="true" id="meeting-table" data-api-url="{{ route('meeting.datatables') }}"
            data-base-url="{{ url('meeting') }}">
            <div class="flex-wrap py-5 card-header">
                <h3 class="card-title">Daftar Meeting</h3>
                <div class="flex flex-wrap gap-2 lg:gap-5">
                    <div class="flex">
                        <label class="input input-sm">
                            <i class="ki-filled ki-magnifier"></i>
                            <input placeholder="Cari meeting..." id="search" type="text" value="">
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2.5">
                        @if (auth()->user()?->can('corsec.export') || auth()->user()?->can('corsec.create'))
                            <div class="h-[24px] border border-r-gray-200"></div>
                        @endif
                        @can('corsec.export')
                            <a id="export-btn" class="btn btn-sm btn-light" href="{{ route('meeting.export') }}">
                                Export to Excel
                            </a>
                        @endcan
                        @can('corsec.create')
                            <a href="{{ route('meeting.create') }}" class="btn btn-sm btn-primary">
                                Tambah Meeting
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="scrollable-x-auto">
                    <table class="table text-sm font-medium text-gray-700 align-middle table-auto table-border"
                        data-datatable-table="true">
                        <thead>
                            <tr>
                                <th class="min-w-[60px]" data-datatable-column="row_number">No</th>
                                <th class="min-w-[170px]" data-datatable-column="meeting_at">Jadwal</th>
                                <th class="min-w-[170px]" data-datatable-column="meeting_type">Kategori</th>
                                <th class="min-w-[260px]" data-datatable-column="title">Judul</th>
                                <th class="min-w-[220px]" data-datatable-column="status">Status</th>
                                <th class="min-w-[90px]" data-datatable-column="participants_count">Peserta</th>
                                <th class="min-w-[90px]" data-datatable-column="agendas_count">Agenda</th>
                                <th class="min-w-[110px] text-center" data-datatable-column="actions">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>

                <div
                    class="flex-col gap-3 justify-center font-medium text-gray-600 card-footer md:justify-between md:flex-row text-2sm">
                    <div class="flex gap-2 items-center">
                        Show
                        <select class="w-16 select select-sm" data-datatable-size="true" name="perpage"></select>
                        per page
                    </div>
                    <div class="flex gap-4 items-center">
                        <span data-datatable-info="true"></span>
                        <div class="pagination" data-datatable-pagination="true"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        const bootstrapMeetingTable = () => {
            const element = document.querySelector('#meeting-table');
            if (!element) {
                return;
            }

            const searchInput = document.getElementById('search');
            const exportBtn = document.getElementById('export-btn');
            const apiUrl = element.getAttribute('data-api-url');
            const baseUrl = element.getAttribute('data-base-url');
            const isAdmin = @json(auth()->user()?->hasRole('administrator'));
            const actorId = @json((int) (auth()->id() ?? 0));

            const statusBadgeClass = (status) => {
                const val = (status ?? '').toString().toLowerCase();
                const map = {
                    draft: 'badge-light',
                    waiting_corsec_approval: 'badge-warning',
                    waiting_direktorat_approval: 'badge-warning',
                    returned_by_corsec: 'badge-danger',
                    returned_by_direktorat: 'badge-danger',
                    jadwal_terkirim: 'badge-info',
                    pending_direktorat: 'badge-info',
                    data_terkirim: 'badge-info',
                    proses_pembuatan_notulen: 'badge-primary',
                    proses_sirkulasi_tandatangan: 'badge-primary',
                    proses_tindaklanjut_hasil_rapat: 'badge-primary',
                    notulen_final: 'badge-success',
                    done_tindaklanjut_hasil_rapat: 'badge-success',
                };

                return map[val] ?? 'badge-light';
            };

            const editableStatuses = ['draft', 'returned_by_corsec', 'returned_by_direktorat'];
            const dataTableOptions = {
                apiEndpoint: apiUrl,
                pageSize: 10,
                columns: {
                    row_number: {
                        title: 'No',
                        render: (item, data) => data.row_number ?? '-',
                    },
                    meeting_at: {
                        title: 'Jadwal',
                        render: (item, data) => data.meeting_at ? window.formatTanggalWaktuIndonesia(data
                            .meeting_at) : '-',
                    },
                    meeting_type: {
                        title: 'Kategori',
                        render: (item, data) => data.meeting_type_label ?? '-',
                    },
                    title: {
                        title: 'Judul',
                        render: (item, data) => data.title ?? '-',
                    },
                    status: {
                        title: 'Status',
                        render: (item, data) =>
                            `<span class="badge ${statusBadgeClass(data.status)}">${data.status_label ?? '-'}</span>`,
                    },
                    participants_count: {
                        title: 'Peserta',
                        render: (item, data) => data.participants_count ?? 0,
                    },
                    agendas_count: {
                        title: 'Agenda',
                        render: (item, data) => data.agendas_count ?? 0,
                    },
                    actions: {
                        title: 'Action',
                        render: (item, data) => {
                            const status = (data.status ?? '').toString().toLowerCase();
                            const canEdit = (isAdmin || Number(data.created_by) === Number(actorId)) &&
                                editableStatuses.includes(status);
                            const rowKey = data.uuid ?? data.id;
                            let html = `<div class="flex flex-nowrap justify-center">`;

                            @can('corsec.read')
                                html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${rowKey}" title="Detail">
                                    <i class="ki-outline ki-eye"></i>
                                </a>`;
                            @endcan

                            @if (auth()->user()?->can('corsec.update') &&
                                    !(auth()->user()?->hasRole('viewer') &&
                                        !auth()->user()
                                            ?->hasRole(['administrator', 'maker', 'checker', 'approver'])
                                    ))
                                if (canEdit) {
                                    html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${rowKey}/edit" title="Edit">
                                        <i class="ki-outline ki-notepad-edit"></i>
                                    </a>`;
                                }
                            @endif

                            html += `</div>`;
                            return html;
                        },
                    },
                },
            };

            let dataTable = new KTDataTable(element, dataTableOptions);

            const updateExportUrl = () => {
                if (!exportBtn) {
                    return;
                }

                const url = new URL(exportBtn.href);
                const searchValue = searchInput?.value?.trim() ?? '';

                if (searchValue) {
                    url.searchParams.set('search', searchValue);
                } else {
                    url.searchParams.delete('search');
                }

                exportBtn.href = url.toString();
            };

            searchInput?.addEventListener('input', function() {
                const searchValue = this.value.trim();
                dataTable.goPage(1);
                dataTable.search(searchValue, true);
                updateExportUrl();
            });

            updateExportUrl();
            window.dataTable = dataTable;
        };

        bootstrapMeetingTable();
    </script>
@endpush
