@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('corsec.directorate') }}
@endsection

@section('content')
    @php($permissionFlags = $permissionFlags ?? [])
    <div class="grid">
        <div class="min-w-full card card-grid" data-datatable="false" data-datatable-page-size="10"
            data-datatable-state-save="false" id="directorate-table" data-api-url="{{ route('directorate.datatables') }}">
            <div class="flex-wrap py-5 card-header">
                <h3 class="card-title">
                    Daftar Directorate
                </h3>
                <div class="flex flex-wrap gap-2 lg:gap-5">
                    <div class="flex">
                        <label class="input input-sm"> <i class="ki-filled ki-magnifier"> </i>
                            <input placeholder="Search Directorate" id="search" type="text" value="">
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2.5">
                        <div class="h-[24px] border border-r-gray-200"></div>
                        @if ($permissionFlags['can_export'] ?? false)
                            <a id="export-btn" class="btn btn-sm btn-light" href="{{ route('directorate.export') }}"> Export to
                                Excel </a>
                        @endif
                        @if ($permissionFlags['can_create'] ?? false)
                            <a class="btn btn-sm btn-primary" href="{{ route('directorate.create') }}"> Tambah Directorate </a>
                        @endif
                        @if ($permissionFlags['can_delete'] ?? false)
                            <button class="hidden btn btn-sm btn-danger" id="deleteSelected"
                                onclick="deleteSelectedRows()">Delete Selected</button>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="scrollable-x-auto">
                    <table class="table text-sm font-medium text-gray-700 align-middle table-auto table-border"
                        data-datatable-table="true">
                        <thead>
                            <tr>
                                <th class="w-14">
                                    <input class="checkbox checkbox-sm" data-datatable-check="true" type="checkbox" />
                                </th>
                                <th class="min-w-[100px]" data-datatable-column="code">
                                    <span class="sort"> <span class="sort-label"> Kode Direktorat </span>
                                        <span class="sort-icon"> </span> </span>
                                </th>
                                <th class="min-w-[250px]" data-datatable-column="name">
                                    <span class="sort"> <span class="sort-label"> Nama Direktorat </span>
                                        <span class="sort-icon"> </span> </span>
                                </th>
                                <th class="min-w-[220px]" data-datatable-column="tabulation_label">
                                    <span class="sort"> <span class="sort-label"> Label Tabulasi </span>
                                        <span class="sort-icon"> </span> </span>
                                </th>
                                <th class="min-w-[250px]" data-datatable-column="description">
                                    <span class="sort"> <span class="sort-label"> Deskripsi </span>
                                        <span class="sort-icon"> </span> </span>
                                </th>
                                <th class="min-w-[180px]" data-datatable-column="is_meeting_operational">
                                    <span class="sort-label"> Tipe Unit Meeting </span>
                                </th>
                                <th class="min-w-[100px]" data-datatable-column="status">
                                    <span class="sort-label"> Status </span>
                                </th>
                                <th class="min-w-[50px] text-center" data-datatable-column="actions">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
                <div
                    class="flex-col gap-3 justify-center font-medium text-gray-600 card-footer md:justify-between md:flex-row text-2sm">
                    <div class="flex gap-2 items-center">
                        Show
                        <select class="w-16 select select-sm" data-datatable-size="true" name="perpage"> </select> per page
                    </div>
                    <div class="flex gap-4 items-center">
                        <span data-datatable-info="true"> </span>
                        <div class="pagination" data-datatable-pagination="true">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="text/javascript">
        function deleteData(rowKey) {
            Swal.fire({
                title: 'Apakah anda yakin?',
                text: "Anda tidak dapat mengembalikan ini!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });

                    $.ajax(`directorate/${rowKey}`, {
                        type: 'DELETE'
                    }).then((response) => {
                        swal.fire('Deleted!', response.message, 'success').then(() => {
                            window.location.reload();
                        });
                    }).catch((error) => {
                        console.error('Error:', error);
                        Swal.fire('Error!', 'An error occurred while deleting the directorate.', 'error');
                    });
                }
            })
        }

        function deleteSelectedRows() {
            const selectedCheckboxes = document.querySelectorAll('input[data-datatable-row-check="true"]:checked');
            if (selectedCheckboxes.length === 0) {
                Swal.fire('Warning', 'Please select at least one row to delete.', 'warning');
                return;
            }

            Swal.fire({
                title: 'Apakah anda yakin?',
                text: `Anda akan menghapus ${selectedCheckboxes.length} data yang dipilih. Tindakan ini tidak dapat dibatalkan!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus Semua!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const ids = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);

                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });

                    $.ajax('{{ route('directorate.deleteMultiple') }}', {
                        type: 'POST',
                        data: {
                            ids: ids
                        }
                    }).then((response) => {
                        swal.fire('Deleted!', response.message, 'success').then(() => {
                            window.location.reload();
                        });
                    }).catch((error) => {
                        console.error('Error:', error);
                        Swal.fire('Error!', 'An error occurred while deleting the rows.', 'error');
                    });
                }
            })
        }
    </script>
    <script type="module">
        const element = document.querySelector('#directorate-table');
        const searchInput = document.getElementById('search');
        const exportBtn = document.getElementById('export-btn');
        const deleteSelectedButton = document.getElementById('deleteSelected');
        const canUpdate = @json((bool) ($permissionFlags['can_update'] ?? false));
        const canDelete = @json((bool) ($permissionFlags['can_delete'] ?? false));

        const apiUrl = element.getAttribute('data-api-url');
        const dataTableOptions = {
            apiEndpoint: apiUrl,
            pageSize: 5,
            _state: {
                sortField: 'id',
                sortOrder: 'asc'
            },
            columns: {
                select: {
                    render: (item, data, context) => {
                        if (!canDelete) return '';
                        const checkbox = document.createElement('input');
                        checkbox.className = 'checkbox checkbox-sm';
                        checkbox.type = 'checkbox';
                        checkbox.value = data.id.toString();
                        checkbox.setAttribute('data-datatable-row-check', 'true');
                        return checkbox.outerHTML.trim();
                    },
                },
                code: {
                    title: 'Kode Directorate',
                },
                name: {
                    title: 'Nama Directorate',
                },
                tabulation_label: {
                    title: 'Label Tabulasi',
                    render: (item, data) => data.tabulation_label ?? data.name ?? '-',
                },
                description: {
                    title: 'Deskripsi',
                },
                is_meeting_operational: {
                    title: 'Tipe Unit Meeting',
                    render: (item, data) => {
                        const isOperational = Number(data.is_meeting_operational) === 1;
                        return `<span class="badge badge-${isOperational ? 'info' : 'secondary'}">${isOperational ? 'Unit Operasional' : 'Monitoring Only'}</span>`;
                    }
                },
                status: {
                    title: 'Status',
                    render: (item, data) => {
                        return `<span class="badge badge-${data.status == 1 ? 'success' : 'danger'}">${data.status == 1 ? 'Aktif' : 'Non-Aktif'}</span>`;
                    }
                },
                actions: {
                    title: 'Action',
                    render: (item, data) => {
                        const rowKey = data.uuid ?? data.id;
                        let html = `<div class="flex flex-nowrap justify-center">`;

                        if (canUpdate) {
                            html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="directorate/${rowKey}/edit">
                                <i class="ki-outline ki-notepad-edit"></i>
                            </a>`;
                        }

                        if (canDelete) {
                            html += `<a onclick="deleteData('${rowKey}')" class="delete btn btn-sm btn-icon btn-clear btn-danger">
                                <i class="ki-outline ki-trash"></i>
                            </a>`;
                        }

                        html += `</div>`;
                        return html;
                    },
                }
            },
        };

        let dataTable = new KTDataTable(element, dataTableOptions);

        // Update export URL with filters
        function updateExportUrl() {
            let url = new URL(exportBtn.href);

            if (searchInput.value) {
                url.searchParams.set('search', searchInput.value);
            } else {
                url.searchParams.delete('search');
            }

            exportBtn.href = url.toString();
        }

        // Custom search functionality
        searchInput.addEventListener('input', function() {
            const searchValue = this.value.trim();
            dataTable.goPage(1);
            dataTable.search(searchValue, true);

            // Update export URL with search parameter
            updateExportUrl();
        });

        function updateDeleteButtonVisibility() {
            if (!deleteSelectedButton) return;
            const selectedCheckboxes = document.querySelectorAll('input[data-datatable-row-check="true"]:checked');
            if (selectedCheckboxes.length > 0) {
                deleteSelectedButton.classList.remove('hidden');
            } else {
                deleteSelectedButton.classList.add('hidden');
            }
        }

        // Initial call to set button visibility
        updateDeleteButtonVisibility();

        // Add event listener to the table for checkbox changes
        element.addEventListener('change', function(event) {
            if (event.target.matches('input[data-datatable-row-check="true"]')) {
                updateDeleteButtonVisibility();
            }
        });

        // Add event listener for the "select all" checkbox
        const selectAllCheckbox = element.querySelector('input[data-datatable-check="true"]');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', updateDeleteButtonVisibility);
        }

        window.dataTable = dataTable;
    </script>
@endpush
