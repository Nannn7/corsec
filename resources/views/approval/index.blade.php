@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('approval.index') }}
@endsection

@section('content')
    <div class="grid">
        <div class="min-w-full card card-grid" data-datatable="false" data-datatable-page-size="10"
            data-datatable-state-save="false" id="approval-table" data-api-url="{{ route('approval.datatables') }}"
            data-base-url="{{ url('approval') }}">
            <div class="flex-wrap py-5 card-header">
                <h3 class="card-title">Daftar Permintaan Persetujuan</h3>
                <div class="flex gap-1 lg:gap-2.5">
                    <label class="input input-sm">
                        <i class="ki-filled ki-magnifier"></i>
                        <input placeholder="Search Approval" id="search" type="text" value="">
                    </label>
                    <div class="h-[24px] border border-r-gray-200"></div>
                    <select class="select select-sm min-w-[150px]" id="filter-module">
                        <option value="">Semua Menu</option>
                        @foreach ($modules as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <select class="select select-sm min-w-[150px]" id="filter-action">
                        <option value="">Semua Aksi</option>
                        @foreach ($actions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="card-body">
                <div class="scrollable-x-auto">
                    <table class="table text-sm font-medium text-gray-700 align-middle table-auto table-border"
                        data-datatable-table="true">
                        <thead>
                            <tr>
                                <th class="min-w-[80px]" data-datatable-column="id">
                                    <span class="sort">
                                        <span class="sort-label">ID</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>
                                <th class="min-w-[140px]" data-datatable-column="model">
                                    <span class="sort">
                                        <span class="sort-label">Module</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>
                                <th class="min-w-[120px]" data-datatable-column="action">
                                    <span class="sort">
                                        <span class="sort-label">Aksi</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>
                                <th class="min-w-[120px]" data-datatable-column="status">
                                    <span class="sort">
                                        <span class="sort-label">Status</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>
                                <th class="min-w-[260px]" data-datatable-column="description">
                                    <span class="sort">
                                        <span class="sort-label">Deskripsi</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>
                                <th class="min-w-[140px]" data-datatable-column="created_by_name">
                                    <span class="sort">
                                        <span class="sort-label">Dibuat Oleh</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>
                                <th class="min-w-[260px]" data-datatable-column="created_at">
                                    <span class="sort">
                                        <span class="sort-label">Tanggal Dibuat</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>
                                <th class="min-w-[160px]" data-datatable-column="authorized_by_name">
                                    <span class="sort">
                                        <span class="sort-label">Diotorisasi Oleh</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>
                                <th class="min-w-[260px]" data-datatable-column="authorized_at">
                                    <span class="sort">
                                        <span class="sort-label">Tanggal Otorisasi</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>
                                <th class="min-w-[80px] text-center" data-datatable-column="actions">Action</th>
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
        const element = document.querySelector('#approval-table');
        const searchInput = document.getElementById('search');
        const apiUrl = element.getAttribute('data-api-url');
        const baseUrl = element.getAttribute('data-base-url');
        const filterModule = document.getElementById('filter-module');
        const filterAction = document.getElementById('filter-action');

        const dataTableOptions = {
            apiEndpoint: apiUrl,
            pageSize: 10,
            columns: {
                id: {
                    title: 'ID',
                },
                model: {
                    title: 'Module',
                },
                action: {
                    title: 'Aksi',
                    render: (item, data) => {
                        const text = data.action ? data.action.charAt(0).toUpperCase() + data.action.slice(1) : '-';
                        return `<span class="badge ${data.action_badge ?? 'badge-secondary'}">${text}</span>`;
                    },
                },
                status: {
                    title: 'Status',
                    render: (item, data) => {
                        const text = data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : '-';
                        return `<span class="badge ${data.status_badge ?? 'badge-secondary'}">${text}</span>`;
                    },
                },
                description: {
                    title: 'Deskripsi',
                    render: (item, data) => data.description ?? '-',
                },
                created_by_name: {
                    title: 'Dibuat Oleh',
                    render: (item, data) => data.created_by_name ?? '-',
                },
                created_at: {
                    title: 'Tanggal Dibuat',
                    render: (item, data) => data.created_at ? window.formatTanggalWaktuIndonesia(data.created_at) : '-',
                },
                authorized_by_name: {
                    title: 'Diotorisasi Oleh',
                    render: (item, data) => data.authorized_by_name ?? '-',
                },
                authorized_at: {
                    title: 'Tanggal Otorisasi',
                    render: (item, data) => data.authorized_at ? window.formatTanggalWaktuIndonesia(data
                        .authorized_at) : '-',
                },
                actions: {
                    title: 'Action',
                    render: (item, data) => {
                        return `
                            <a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${data.id}">
                                <i class="ki-outline ki-eye"></i>
                            </a>
                        `;
                    },
                },
            },
        };

        let dataTable = new KTDataTable(element, dataTableOptions);

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchValue = this.value.trim();
                dataTable.goPage(1);
                dataTable.search(searchValue, true);
            });
        }

        function applyFilters() {
            dataTable.setFilter({
                column: 'model',
                type: 'text',
                value: filterModule ? filterModule.value : ''
            });
            dataTable.setFilter({
                column: 'action',
                type: 'text',
                value: filterAction ? filterAction.value : ''
            });
            dataTable.redraw(1);
        }

        if (filterModule) {
            filterModule.addEventListener('change', applyFilters);
        }
        if (filterAction) {
            filterAction.addEventListener('change', applyFilters);
        }

        window.dataTable = dataTable;
    </script>
@endpush
