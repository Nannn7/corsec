@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('workplan.index') }}
@endsection

@section('content')
    <div class="grid gap-5 lg:gap-7.5">
        {{-- <div class="card">
            <div class="card-body flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <h2 class="text-lg font-semibold text-gray-900">Ringkasan Program Kerja Direktorat</h2>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="badge badge-light">Tanggal: {{ $pageInfo['today']->format('d/m/Y') }}</span>
                        <span class="badge badge-light">Direktorat: {{ $pageInfo['directorate_name'] }}</span>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[360px]">
                    <div class="rounded-lg border border-gray-200 px-4 py-3">
                        <div class="text-xs font-medium text-gray-500">Completion Rate</div>
                        <div class="mt-1 text-2xl font-semibold text-primary">{{ $summary['completion_rate'] ?? 0 }}%</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 px-4 py-3">
                        <div class="text-xs font-medium text-gray-500">Done On Target Ratio</div>
                        <div class="mt-1 text-2xl font-semibold text-success">{{ $summary['on_target_rate'] ?? 0 }}%</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 px-4 py-3 sm:col-span-2">
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>Progress Penyelesaian Item</span>
                            <span>{{ ($summary['done_on_target'] ?? 0) + ($summary['done_over_target'] ?? 0) }} /
                                {{ $summary['total_items'] ?? 0 }}</span>
                        </div>
                        <div class="mt-2 h-2 rounded-full bg-gray-200">
                            <div class="h-2 rounded-full bg-primary"
                                style="width: {{ $summary['completion_rate'] ?? 0 }}%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div> --}}

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-2xl font-semibold text-gray-800">{{ $summary['total_programs'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">Total Program</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-2xl font-semibold text-gray-800">{{ $summary['total_items'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">Total Item</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-2xl font-semibold text-sky-600">{{ $summary['process_on_target'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">PK - Process On Target</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-2xl font-semibold text-green-600">{{ $summary['done_on_target'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">PK - Done On Target</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-2xl font-semibold text-orange-500">{{ $summary['done_over_target'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">PK - Done Over Target</div>
                </div>
            </div>
            {{-- <div class="card">
                <div class="card-body">
                    <div class="text-2xl font-semibold text-red-500">{{ $summary['pending_items'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">Pending Item</div>
                </div>
            </div> --}}
            <div class="card">
                <div class="card-body">
                    <div class="text-2xl font-semibold text-red-600">{{ $summary['undone'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">PK - Undone</div>
                </div>
            </div>
            {{-- <div class="card">
                <div class="card-body">
                    <div class="text-2xl font-semibold text-amber-600">{{ $summary['waiting_dir_approval_programs'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500">Waiting Dir Approval</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="text-2xl font-semibold text-primary">{{ $summary['pending_approvals'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">Pending Approval Queue</div>
                </div>
            </div> --}}
        </div>

        <div class="min-w-full card card-grid" data-datatable="false" data-datatable-page-size="10"
            data-datatable-state-save="true" id="workplan-table" data-api-url="{{ route('workplan.datatables') }}"
            data-base-url="{{ url('workplan') }}">
            <div class="flex-wrap py-5 card-header">
                <h3 class="card-title">Work Plan / Program Kerja</h3>
                <div class="flex flex-wrap gap-2 lg:gap-5">
                    <div class="flex">
                        <label class="input input-sm">
                            <i class="ki-filled ki-magnifier"></i>
                            <input placeholder="Search work plan..." id="search" type="text" value="">
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2.5">
                        @if (auth()->user()?->can('corsec.export') || auth()->user()?->can('corsec.create'))
                            <div class="h-[24px] border border-r-gray-200"></div>
                        @endif

                        @can('corsec.export')
                            <a id="export-btn" class="btn btn-sm btn-light" href="{{ route('workplan.export') }}">
                                Export to Excel
                            </a>
                        @endcan

                        @can('corsec.create')
                            <a class="btn btn-sm btn-primary" href="{{ route('workplan.create') }}">
                                Tambah Program Kerja
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
                                <th class="min-w-[170px]" data-datatable-column="program_no">No Program</th>
                                <th class="min-w-[150px]" data-datatable-column="date">Tanggal Input</th>
                                <th class="min-w-[200px]" data-datatable-column="directorate">Direktorat</th>
                                <th class="min-w-[140px]" data-datatable-column="year">Tahun</th>
                                <th class="min-w-[260px]" data-datatable-column="title">Program Kerja</th>
                                <th class="min-w-[120px]" data-datatable-column="total_items">Total Item</th>
                                <th class="min-w-[120px]" data-datatable-column="done_items">Done</th>
                                <th class="min-w-[140px]" data-datatable-column="pending_items">Pending</th>
                                <th class="min-w-[160px]" data-datatable-column="status">Status</th>
                                <th class="min-w-[100px] text-center" data-datatable-column="actions">Action</th>
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
    <script type="text/javascript">
        function deleteWorkplan(rowKey) {
            const element = document.querySelector('#workplan-table');
            const baseUrl = element.getAttribute('data-base-url');

            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: 'Program kerja yang dihapus tidak dapat dikembalikan!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (!result.isConfirmed) return;

                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                $.ajax(`${baseUrl}/${rowKey}`, {
                    type: 'DELETE'
                }).then((response) => {
                    Swal.fire('Terhapus!', response.message, 'success').then(() => {
                        window.location.reload();
                    });
                }).catch(() => {
                    Swal.fire('Error!', 'Terjadi kesalahan saat menghapus program kerja.', 'error');
                });
            });
        }
    </script>

    <script type="module">
        const element = document.querySelector('#workplan-table');
        const searchInput = document.getElementById('search');
        const exportBtn = document.getElementById('export-btn');
        const apiUrl = element.getAttribute('data-api-url');
        const baseUrl = element.getAttribute('data-base-url');
        const isAdmin = @json(auth()->user()?->hasRole('administrator'));

        const statusBadge = (status) => {
            const val = (status ?? '').toString().toLowerCase();
            const map = {
                draft: ['badge-light', 'Draft'],
                waiting_dir_approval: ['badge-warning', 'Waiting Dir Approval'],
                active: ['badge-info', 'Active'],
                done: ['badge-success', 'Done'],
                returned: ['badge-danger', 'Returned'],
                rejected: ['badge-danger', 'Rejected'],
            };
            const [cls, text] = map[val] ?? ['badge-light', status ?? '-'];
            return `<span class="badge ${cls}">${text}</span>`;
        };

        const dataTableOptions = {
            apiEndpoint: apiUrl,
            pageSize: 10,
            columns: {
                program_no: {
                    title: 'No Program',
                    render: (item, data) => data.program_no ?? '-',
                },
                date: {
                    title: 'Tanggal Input',
                    render: (item, data) => data.date ? window.formatTanggalIndonesia(data.date) : '-',
                },
                directorate: {
                    title: 'Direktorat',
                    render: (item, data) => data.directorate?.name ?? '-',
                },
                year: {
                    title: 'Tahun',
                    render: (item, data) => data.year ?? '-',
                },
                title: {
                    title: 'Program Kerja',
                    render: (item, data) => data.title ?? '-',
                },
                total_items: {
                    title: 'Total Item',
                    render: (item, data) => data.total_items ?? 0,
                },
                done_items: {
                    title: 'Done',
                    render: (item, data) => data.done_items ?? 0,
                },
                pending_items: {
                    title: 'Pending',
                    render: (item, data) => data.pending_items ?? 0,
                },
                status: {
                    title: 'Status',
                    render: (item, data) => statusBadge(data.status),
                },
                actions: {
                    title: 'Action',
                    render: (item, data) => {
                        const status = (data.status ?? '').toString().toLowerCase();
                        const editableStatuses = ['draft', 'returned'];
                        const deletableStatuses = ['draft', 'returned'];
                        const canEditStatus = editableStatuses.includes(status);
                        const canDeleteStatus = isAdmin || deletableStatuses.includes(status);
                        const rowKey = data.uuid ?? data.id;
                        let html = `<div class="flex flex-nowrap justify-center">`;

                        @can('corsec.read')
                            html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${rowKey}">
                                <i class="ki-outline ki-eye"></i>
                            </a>`;
                        @endcan

                        @if (auth()->user()?->can('corsec.update') && !(auth()->user()?->hasRole('viewer') && !auth()->user()?->hasRole(['administrator', 'maker', 'checker', 'approver'])))
                            if (canEditStatus) {
                                html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${rowKey}/edit">
                                    <i class="ki-outline ki-notepad-edit"></i>
                                </a>`;
                            }
                        @endif

                        @can('corsec.delete')
                            if (canDeleteStatus) {
                                html += `<a onclick="deleteWorkplan('${rowKey}')" class="btn btn-sm btn-icon btn-clear btn-danger">
                                    <i class="ki-outline ki-trash"></i>
                                </a>`;
                            }
                        @endcan

                        html += `</div>`;
                        return html;
                    },
                }
            },
        };

        let dataTable = new KTDataTable(element, dataTableOptions);

        function updateExportUrl() {
            if (!exportBtn) return;
            const url = new URL(exportBtn.href);
            const searchValue = searchInput?.value?.trim() ?? '';

            if (searchValue) url.searchParams.set('search', searchValue);
            else url.searchParams.delete('search');

            exportBtn.href = url.toString();
        }

        searchInput?.addEventListener('input', function() {
            const searchValue = this.value.trim();
            dataTable.goPage(1);
            dataTable.search(searchValue, true);
            updateExportUrl();
        });

        updateExportUrl();

        window.dataTable = dataTable;
    </script>
@endpush
