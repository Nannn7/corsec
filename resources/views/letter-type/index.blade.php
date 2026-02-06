@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('corsec.letter-type') }}
@endsection

@section('content')
    <div class="grid">
        <div class="min-w-full card card-grid" data-datatable="false" data-datatable-page-size="10"
            data-datatable-state-save="false" id="letter-type-table" data-api-url="{{ route('letter-type.datatables') }}">
            <div class="flex-wrap py-5 card-header">
                <h3 class="card-title">
                    Daftar Letter Type
                </h3>
                <div class="flex flex-wrap gap-2 lg:gap-5">
                    <div class="flex">
                        <label class="input input-sm"> <i class="ki-filled ki-magnifier"> </i>
                            <input placeholder="Search Letter Type" id="search" type="text" value="">
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2.5">
                        <div class="h-[24px] border border-r-gray-200"></div>
                        @can('letter-type.export')
                            <a id="export-btn" class="btn btn-sm btn-light" href="{{ route('letter-type.export') }}">
                                Export to
                                Excel </a>
                        @endcan
                        @can('letter-type.create')
                            <a class="btn btn-sm btn-primary" href="{{ route('letter-type.create') }}"> Tambah Letter Type
                            </a>
                        @endcan
                        @can('letter-type.delete')
                            <button class="hidden btn btn-sm btn-danger" id="deleteSelected"
                                onclick="deleteSelectedRows()">Delete Selected</button>
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
                                <th class="w-14">
                                    <input class="checkbox checkbox-sm" data-datatable-check="true" type="checkbox" />
                                </th>
                                <th class="min-w-[100px]" data-datatable-column="code">
                                    <span class="sort"> <span class="sort-label"> Kode Letter Type </span>
                                        <span class="sort-icon"> </span> </span>
                                </th>
                                <th class="min-w-[250px]" data-datatable-column="name">
                                    <span class="sort"> <span class="sort-label"> Nama Letter Type </span>
                                        <span class="sort-icon"> </span> </span>
                                </th>
                                <th class="min-w-[250px]" data-datatable-column="description">
                                    <span class="sort"> <span class="sort-label"> Deskripsi </span>
                                        <span class="sort-icon"> </span> </span>
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

                    $.ajax(`letter-type/${rowKey}`, {
                        type: 'DELETE'
                    }).then((response) => {
                        swal.fire('Deleted!', response.message, 'success').then(() => {
                            window.location.reload();
                        });
                    }).catch((error) => {
                        console.error('Error:', error);
                        Swal.fire('Error!', 'An error occurred while deleting the letter type.', 'error');
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

                    $.ajax('{{ route('letter-type.deleteMultiple') }}', {
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
        const element = document.querySelector('#letter-type-table');
        const searchInput = document.getElementById('search');
        const exportBtn = document.getElementById('export-btn');
        const deleteSelectedButton = document.getElementById('deleteSelected');

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
                        const checkbox = document.createElement('input');
                        checkbox.className = 'checkbox checkbox-sm';
                        checkbox.type = 'checkbox';
                        checkbox.value = data.id.toString();
                        checkbox.setAttribute('data-datatable-row-check', 'true');
                        return checkbox.outerHTML.trim();
                    },
                },
                code: {
                    title: 'Kode Letter Type',
                },
                name: {
                    title: 'Nama Letter Type',
                },
                description: {
                    title: 'Deskripsi',
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

                        @can('letter-type.update')
                            html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="letter-type/${rowKey}/edit">
                                <i class="ki-outline ki-notepad-edit"></i>
                            </a>`;
                        @endcan

                        @can('letter-type.delete')
                            html += `<a onclick="deleteData('${rowKey}')" class="delete btn btn-sm btn-icon btn-clear btn-danger">
                                <i class="ki-outline ki-trash"></i>
                            </a>`;
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
            let url = new URL(exportBtn.href);

            if (searchInput.value) {
                url.searchParams.set('search', searchInput.value);
            } else {
                url.searchParams.delete('search');
            }

            exportBtn.href = url.toString();
        }

        searchInput.addEventListener('input', function() {
            const searchValue = this.value.trim();
            dataTable.goPage(1);
            dataTable.search(searchValue, true);
            updateExportUrl();
        });

        function updateDeleteButtonVisibility() {
            const selectedCheckboxes = document.querySelectorAll('input[data-datatable-row-check="true"]:checked');
            if (selectedCheckboxes.length > 0) {
                deleteSelectedButton.classList.remove('hidden');
            } else {
                deleteSelectedButton.classList.add('hidden');
            }
        }

        updateDeleteButtonVisibility();

        element.addEventListener('change', function(event) {
            if (event.target.matches('input[data-datatable-row-check="true"]')) {
                updateDeleteButtonVisibility();
            }
        });

        const selectAllCheckbox = element.querySelector('input[data-datatable-check="true"]');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', updateDeleteButtonVisibility);
        }

        window.dataTable = dataTable;
    </script>
@endpush
