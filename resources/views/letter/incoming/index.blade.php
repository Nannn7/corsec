@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.incoming.index') }}
@endsection

@section('content')
    <div class="grid">
        <div class="min-w-full card card-grid" data-datatable="false" data-datatable-page-size="10"
            data-datatable-state-save="false" id="incoming-letter-table"
            data-api-url="{{ route('letter.incoming.datatables') }}" data-base-url="{{ url('letter/incoming') }}">
            <div class="flex-wrap py-5 card-header">
                <h3 class="card-title">
                    Surat Masuk
                </h3>
                <div class="flex flex-wrap gap-2 lg:gap-5">
                    <div class="flex">
                        <label class="input input-sm">
                            <i class="ki-filled ki-magnifier"></i>
                            <input placeholder="Cari surat..." id="search" type="text" value="">
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2.5">
                        <div class="h-[24px] border border-r-gray-200"></div>

                        @can('corsec.export')
                            <a id="export-btn" class="btn btn-sm btn-light" href="{{ route('letter.incoming.export') }}">
                                Export to Excel
                            </a>
                        @endcan

                        @can('corsec.create')
                            <a class="btn btn-sm btn-primary" href="{{ route('letter.incoming.create') }}">
                                Tambah Surat
                            </a>
                        @endcan

                        @can('corsec.delete')
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

                                <th class="min-w-[160px]" data-datatable-column="external_letter_no">
                                    <span class="sort">
                                        <span class="sort-label">No Surat</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[250px]" data-datatable-column="subject">
                                    <span class="sort">
                                        <span class="sort-label">Perihal</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[200px]" data-datatable-column="sender_id">
                                    <span class="sort">
                                        <span class="sort-label">Pengirim</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[200px]" data-datatable-column="letter_type_id">
                                    <span class="sort">
                                        <span class="sort-label">Jenis Surat</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[200px]" data-datatable-column="targetDirectorate">
                                    <span class="sort">
                                        <span class="sort-label">Tujuan</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[120px]" data-datatable-column="status">
                                    <span class="sort">
                                        <span class="sort-label">Status</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[160px]" data-datatable-column="received_date">
                                    <span class="sort">
                                        <span class="sort-label">Diterima</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[120px]" data-datatable-column="created_at">
                                    <span class="sort">
                                        <span class="sort-label">Dibuat</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

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
        function deleteData(id) {
            const element = document.querySelector('#incoming-letter-table');
            const baseUrl = element.getAttribute('data-base-url');

            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });

                    $.ajax(`${baseUrl}/${id}`, {
                            type: 'DELETE'
                        })
                        .then((response) => {
                            Swal.fire('Terhapus!', response.message, 'success').then(() => {
                                window.location.reload();
                            });
                        })
                        .catch((error) => {
                            console.error('Error:', error);
                            Swal.fire('Error!', 'Terjadi kesalahan saat menghapus surat.', 'error');
                        });
                }
            })
        }

        function deleteSelectedRows() {
            const selectedCheckboxes = document.querySelectorAll('input[data-datatable-row-check="true"]:checked');
            if (selectedCheckboxes.length === 0) {
                Swal.fire('Peringatan', 'Pilih minimal satu baris untuk dihapus.', 'warning');
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

                    $.ajax('{{ route('letter.incoming.delete_multiple') }}', {
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
        const element = document.querySelector('#incoming-letter-table');
        const searchInput = document.getElementById('search');
        const exportBtn = document.getElementById('export-btn');
        const deleteSelectedButton = document.getElementById('deleteSelected');

        const apiUrl = element.getAttribute('data-api-url');
        const baseUrl = element.getAttribute('data-base-url');

        const statusBadge = (status) => {
            const val = (status ?? '').toString().toLowerCase();

            // sesuai status constants yang kita pake
            const map = {
                draft: ['badge-light', 'Draft'],
                on_approval: ['badge-warning', 'On Approval'],
                dispatched: ['badge-info', 'Dispatched'],
                in_progress: ['badge-warning', 'In Progress'],
                waiting_dir_approval: ['badge-warning', 'Waiting Dir Approval'],
                waiting_verification: ['badge-info', 'Waiting Verification'],
                verified: ['badge-success', 'Verified'],
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
                select: {
                    render: (item, data) => {
                        const checkbox = document.createElement('input');
                        checkbox.className = 'checkbox checkbox-sm';
                        checkbox.type = 'checkbox';
                        checkbox.value = data.id.toString();
                        checkbox.setAttribute('data-datatable-row-check', 'true');
                        return checkbox.outerHTML.trim();
                    },
                },

                external_letter_no: {
                    title: 'No Surat',
                    render: (item, data) => data.external_letter_no ?? '-',
                },

                subject: {
                    title: 'Perihal'
                },

                sender_id: {
                    title: 'Pengirim',
                    render: (item, data) => {
                        return data.sender ? `${data.sender.name}` : '-';
                    },
                },

                letter_type_id: {
                    title: 'Jenis Surat',
                    render: (item, data) => {
                        return data.letter_type ? `${data.letter_type.name}` : '-';
                    },
                },

                targetDirectorate: {
                    title: 'Tujuan',
                    render: (item, data) => {
                        // controller: ->with(['targetDirectorate'])
                        return data.target_directorate ?
                            `${data.target_directorate.name}` :
                            (data.targetDirectorate ? `${data.targetDirectorate.name}` : '-');
                    },
                },

                status: {
                    title: 'Status',
                    render: (item, data) => statusBadge(data.status),
                },

                received_date: {
                    title: 'Diterima',
                    render: (item, data) => {
                        // received_date itu date, bukan datetime
                        return data.received_date ? window.formatTanggalIndonesia(data.received_date) : '-';
                    },
                },

                created_at: {
                    title: 'Dibuat',
                    render: (item, data) => data.created_at ? window.formatTanggalWaktuIndonesia(data.created_at) : '-',
                },

                actions: {
                    title: 'Action',
                    render: (item, data) => {
                        let html = `<div class="flex flex-nowrap justify-center">`;

                        @can('corsec.read')
                            html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${data.id}">
                                <i class="ki-outline ki-eye"></i>
                            </a>`;
                        @endcan

                        @can('corsec.update')
                            html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${data.id}/edit">
                                <i class="ki-outline ki-notepad-edit"></i>
                            </a>`;
                        @endcan

                        @can('corsec.delete')
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

        searchInput.addEventListener('input', function() {
            const searchValue = this.value.trim();
            dataTable.goPage(1);
            dataTable.search(searchValue, true);
            updateExportUrl();
        });

        function updateDeleteButtonVisibility() {
            if (!deleteSelectedButton) return;

            const selectedCheckboxes = document.querySelectorAll('input[data-datatable-row-check="true"]:checked');
            if (selectedCheckboxes.length > 0) deleteSelectedButton.classList.remove('hidden');
            else deleteSelectedButton.classList.add('hidden');
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
