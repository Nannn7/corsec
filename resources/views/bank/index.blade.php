@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('corsec.bank') }}
@endsection

@section('content')
    <div class="grid">
        <div class="min-w-full card card-grid" data-datatable="false" data-datatable-page-size="10"
            data-datatable-state-save="false" id="bank-table" data-api-url="{{ route('bank.datatables') }}">
            <div class="flex-wrap py-5 card-header">
                <h3 class="card-title">
                    Daftar Bank
                </h3>
                <div class="flex flex-wrap gap-2 lg:gap-5">
                    <div class="flex">
                        <label class="input input-sm"> <i class="ki-filled ki-magnifier"> </i>
                            <input placeholder="Search Bank" id="search" type="text" value="">
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2.5">
                        <div class="h-[24px] border border-r-gray-200"></div>
                        @can('bank.export')
                            <a id="export-btn" class="btn btn-sm btn-light" href="{{ route('bank.export') }}"> Export to
                                Excel </a>
                        @endcan
                        @can('bank.create')
                            <a class="btn btn-sm btn-primary" href="{{ route('bank.create') }}"> Tambah Bank </a>
                        @endcan
                        @can('bank.delete')
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
                                    <span class="sort"> <span class="sort-label"> Kode Bank </span>
                                        <span class="sort-icon"> </span> </span>
                                </th>
                                <th class="min-w-[140px]" data-datatable-column="swift_code">
                                    <span class="sort"> <span class="sort-label"> Swift Code </span>
                                        <span class="sort-icon"> </span> </span>
                                </th>
                                <th class="min-w-[250px]" data-datatable-column="name">
                                    <span class="sort"> <span class="sort-label"> Nama Bank </span>
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
        function deleteData(data) {
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

                    $.ajax(`bank/${data}`, {
                        type: 'DELETE'
                    }).then((response) => {
                        swal.fire('Deleted!', response.message, 'success').then(() => {
                            window.location.reload();
                        });
                    }).catch((error) => {
                        console.error('Error:', error);
                        Swal.fire('Error!', 'An error occurred while deleting the bank.', 'error');
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
                title: 'Apakah Anda yakin?',
                text: `Anda akan menghapus ${selectedCheckboxes.length} baris terpilih. Aksi ini tidak dapat dibatalkan!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, hapus semua!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const ids = Array.from(selectedCheckboxes).map(cb => cb.value);

                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });

                    $.ajax('{{ route('bank.deleteMultiple') }}', {
                        type: 'POST',
                        data: {
                            ids
                        }
                    }).then((response) => {
                        Swal.fire('Terhapus!', response.message, 'success').then(() => {
                            window.location.reload();
                        });
                    }).catch((error) => {
                        console.error('Error:', error);
                        Swal.fire('Error!', 'Terjadi kesalahan saat menghapus baris.', 'error');
                    });
                }
            })
        }
    </script>

    <script type="module">
        const element = document.querySelector('#bank-table');
        const searchInput = document.getElementById('search');
        const exportBtn = document.getElementById('export-btn');
        const deleteSelectedButton = document.getElementById('deleteSelected');

        const apiUrl = element.getAttribute('data-api-url');

        const statusBadge = (status) => {
            return status
                ? `<span class="badge badge-success">Aktif</span>`
                : `<span class="badge badge-danger">Non-Aktif</span>`;
        };

        const dataTableOptions = {
            apiEndpoint: apiUrl,
            pageSize: 10,
            columns: {
                select: {
                    render: (item, data) => {
                        @can('bank.delete')
                            const checkbox = document.createElement('input');
                            checkbox.className = 'checkbox checkbox-sm';
                            checkbox.type = 'checkbox';
                            checkbox.value = data.id.toString();
                            checkbox.setAttribute('data-datatable-row-check', 'true');
                            return checkbox.outerHTML.trim();
                        @endcan
                        return '';
                    },
                },

                code: {
                    title: 'Kode Bank',
                    render: (item, data) => data.code ?? '-',
                },

                swift_code: {
                    title: 'Swift Code',
                    render: (item, data) => data.swift_code ?? '-',
                },

                name: {
                    title: 'Nama Bank',
                    render: (item, data) => data.name ?? '-',
                },

                description: {
                    title: 'Deskripsi',
                    render: (item, data) => data.description ?? '-',
                },

                status: {
                    title: 'Status',
                    render: (item, data) => statusBadge(data.status),
                },

                actions: {
                    title: 'Action',
                    render: (item, data) => {
                        let html = `<div class="flex flex-nowrap justify-center">`;

                        @if (auth()->user()?->hasRole('administrator') || auth()->user()?->can('bank.update'))
                            html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="bank/${data.id}/edit">
                                <i class="ki-outline ki-notepad-edit"></i>
                            </a>`;
                        @endif

                        @can('bank.delete')
                            html += `<a onclick="deleteData('${data.id}')" class="btn btn-sm btn-icon btn-clear btn-danger">
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

            if (searchInput.value) url.searchParams.set('search', searchInput.value);
            else url.searchParams.delete('search');

            exportBtn.href = url.toString();
        }

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchValue = this.value.trim();
                dataTable.goPage(1);
                dataTable.search(searchValue, true);
                updateExportUrl();
            });
        }

        function updateDeleteButtonVisibility() {
            if (!deleteSelectedButton) return;
            const selectedCheckboxes = document.querySelectorAll('input[data-datatable-row-check="true"]:checked');
            if (selectedCheckboxes.length > 0) deleteSelectedButton.classList.remove('hidden');
            else deleteSelectedButton.classList.add('hidden');
        }

        element.addEventListener('change', function(e) {
            if (e.target.matches('input[data-datatable-row-check="true"]')) {
                updateDeleteButtonVisibility();
            }
        });
        updateDeleteButtonVisibility();
    </script>
@endpush
