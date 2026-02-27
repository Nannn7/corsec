@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.outgoing.index') }}
@endsection

@section('content')
    <div class="container-fluid">
        <div class="grid">
            <div class="min-w-full card card-grid" data-datatable="false" data-datatable-page-size="10"
                data-datatable-state-save="true" id="outgoing-letter-table"
                data-api-url="{{ route('letter.outgoing.datatables') }}" data-base-url="{{ url('letter/outgoing') }}">
                <div class="flex-wrap py-5 card-header">
                    <h3 class="card-title">
                        Outgoing Letters
                    </h3>
                    <div class="flex flex-wrap gap-2 lg:gap-5">
                        <div class="flex">
                            <label class="input input-sm"> <i class="ki-filled ki-magnifier"> </i>
                                <input placeholder="Search outgoing letters" id="search" type="text" value="">
                            </label>
                        </div>
                        <div class="flex flex-wrap gap-2.5 lg:gap-5">
                            @if (($canCreate ?? false) || auth()->user()?->can('corsec.export') || auth()->user()?->can('corsec.delete'))
                                <div class="h-[24px] border border-r-gray-200"> </div>
                            @endif

                            @can('corsec.export')
                                <a id="export-btn" class="btn btn-sm btn-light" href="{{ route('letter.outgoing.export') }}">
                                    Export to Excel
                                </a>
                            @endcan

                            @can('corsec.delete')
                                <button class="hidden btn btn-sm btn-danger" id="deleteSelected"
                                    onclick="deleteSelectedRows()">Delete Selected</button>
                            @endcan

                            @if ($canCreate ?? false)
                                <a class="btn btn-sm btn-primary" href="{{ route('letter.outgoing.create') }}">
                                    Tambah Surat
                                </a>
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
                                    <th class="min-w-[180px]" data-datatable-column="registration_no">No Registrasi</th>
                                    <th class="min-w-[180px]" data-datatable-column="letter_no">No Surat</th>
                                    <th class="min-w-[160px]" data-datatable-column="order_date">Tanggal Order</th>
                                    <th class="min-w-[220px]" data-datatable-column="subject">Perihal</th>
                                    <th class="min-w-[220px]" data-datatable-column="summary">Ringkasan</th>
                                    <th class="min-w-[180px]" data-datatable-column="recipient">Penerima</th>
                                    <th class="min-w-[180px]" data-datatable-column="letter_type">Jenis Surat</th>
                                    <th class="min-w-[170px]" data-datatable-column="perihal_type">Jenis Perihal</th>
                                    <th class="min-w-[170px]" data-datatable-column="requester_directorate">Direktorat</th>
                                    <th class="min-w-[140px]" data-datatable-column="status">Status</th>
                                    <th class="min-w-[180px]" data-datatable-column="created_at">Dibuat</th>
                                    <th class="min-w-[70px] text-center" data-datatable-column="actions">Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    <div
                        class="flex-col gap-3 justify-center font-medium text-gray-600 card-footer md:justify-between md:flex-row text-2sm">
                        <div class="flex gap-2 items-center">
                            Show
                            <select class="w-16 select select-sm" data-datatable-size="true" name="perpage"> </select> per
                            page
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
    </div>
@endsection

@push('scripts')
    <script type="text/javascript">
        function deleteData(rowKey) {
            const element = document.querySelector('#outgoing-letter-table');
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

                    $.ajax(`${baseUrl}/${rowKey}`, {
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

                    $.ajax('{{ route('letter.outgoing.delete_multiple') }}', {
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

        function cancelRequestData(rowKey) {
            const element = document.querySelector('#outgoing-letter-table');
            const baseUrl = element.getAttribute('data-base-url');

            Swal.fire({
                title: 'Ajukan Pembatalan Surat?',
                width: 'min(920px, 95vw)',
                padding: '1.5rem 1.75rem 1.25rem',
                html: `
                    <div class="text-left">
                        <label for="cancel_reason" class="mb-2 block text-sm text-gray-700">Alasan pembatalan <span class="text-danger">*</span></label>
                        <textarea id="cancel_reason" class="swal2-textarea !mt-0 !w-full !max-w-full" rows="4" style="width: 90%; min-height: 110px; max-width: 100%; resize: none;" placeholder="Contoh: Data surat perlu diganti total..."></textarea>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ajukan Approval EO',
                cancelButtonText: 'Batal',
                focusConfirm: false,
                didOpen: () => {
                    const reasonEl = document.getElementById('cancel_reason');
                    if (reasonEl) {
                        reasonEl.focus();
                    }
                },
                preConfirm: () => {
                    const reasonEl = document.getElementById('cancel_reason');
                    const reason = reasonEl ? reasonEl.value.trim() : '';
                    if (!reason) {
                        Swal.showValidationMessage('Alasan pembatalan wajib diisi.');
                        return false;
                    }
                    return reason;
                }
            }).then((result) => {
                if (!result.isConfirmed) return;

                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                $.ajax(`${baseUrl}/${rowKey}/cancel-request`, {
                    type: 'POST',
                    data: {
                        note: result.value
                    }
                }).then((response) => {
                    Swal.fire('Berhasil', response.message || 'Permintaan pembatalan berhasil diajukan.',
                            'success')
                        .then(() => window.location.reload());
                }).catch((error) => {
                    const message = error?.responseJSON?.message ||
                        'Terjadi kesalahan saat mengajukan pembatalan.';
                    Swal.fire('Error!', message, 'error');
                });
            });
        }
    </script>

    <script type="module">
        const element = document.querySelector('#outgoing-letter-table');
        const searchInput = document.getElementById('search');
        const exportBtn = document.getElementById('export-btn');
        const deleteSelectedButton = document.getElementById('deleteSelected');
        const apiUrl = element.getAttribute('data-api-url');
        const baseUrl = element.getAttribute('data-base-url');
        const isAdmin = @json(auth()->user()?->hasRole('administrator'));
        const hasOperationalRole = @json((bool) (auth()->user()?->hasRole('administrator') || auth()->user()?->hasRole('maker') || auth()->user()?->hasRole('checker') || auth()->user()?->hasRole('approver')));
        const isViewerRole = @json((bool) auth()->user()?->hasRole('viewer')) && !hasOperationalRole;
        const hasMakerRole = @json(auth()->user()?->hasRole('maker'));
        const isStaffPosition = @json(
            \Illuminate\Support\Str::contains(
                \Illuminate\Support\Str::lower((string) (auth()->user()?->position?->name ?? '')),
                'staff'));
        const currentUserId = @json((int) (auth()->id() ?? 0));
        const currentUserDirectorateId = @json((int) (auth()->user()?->directorate_id ?? 0));
        const canCreateOrUpdate = @json((bool) (auth()->user()?->can('corsec.create') || auth()->user()?->can('corsec.update'))) && !isViewerRole;

        const statusBadge = (status) => {
            const val = (status ?? '').toString().toLowerCase();
            let normalized = 'draft';
            if (val === 'waiting_dir_approval') normalized = 'waiting_dir_approval';
            if (val === 'compliance_review') normalized = 'compliance_review';
            if (val === 'waiting_compliance_approval') normalized = 'waiting_compliance_approval';
            if (val === 'waiting_verification') normalized = 'waiting_verification';
            if (val === 'waiting_final_upload' || val === 'final_uploaded') normalized = 'waiting_final_upload';
            if (val === 'waiting_cancel_approval') normalized = 'waiting_cancel_approval';
            if (val === 'verified') normalized = 'done';
            if (val === 'returned') normalized = 'revisi';
            if (val === 'cancelled') normalized = 'cancelled';

            const map = {
                draft: ['badge-light', 'Draft'],
                waiting_dir_approval: ['badge-warning', 'Approval EO dan DD Direktorat'],
                compliance_review: ['badge-info', 'Review Kepatuhan'],
                waiting_compliance_approval: ['badge-warning', 'Approval EO dan DD Kepatuhan'],
                waiting_verification: ['badge-warning', 'Verifikasi EO Corp Affair'],
                waiting_final_upload: ['badge-primary', 'Final Upload'],
                waiting_cancel_approval: ['badge-warning', 'Approval Pembatalan EO Direktorat'],
                done: ['badge-success', 'Done'],
                revisi: ['badge-danger', 'Revisi'],
                cancelled: ['badge-secondary', 'Cancelled'],
            };
            const [cls, text] = map[normalized] ?? ['badge-light', status ?? '-'];
            return `<span class="badge ${cls}">${text}</span>`;
        };

        const authorizedBadge = (status) => {
            const val = (status ?? '').toString().toLowerCase();
            const map = {
                pending: ['badge-warning', 'Pending'],
                authorized: ['badge-success', 'Authorized'],
                returned: ['badge-danger', 'Returned'],
            };
            const [cls, text] = map[val] ?? ['badge-light', status ?? '-'];
            return `<span class="badge ${cls}">${text}</span>`;
        };

        const perihalTypeLabel = (type) => {
            const val = (type ?? '').toString().toLowerCase();
            const map = {
                tanggapan_surat_masuk: 'Tanggapan Surat Masuk',
                rutinitas: 'Rutinitas',
                insidentil: 'Insidentil',
            };
            return map[val] ?? (type ?? '-');
        };

        const dataTableOptions = {
            apiEndpoint: apiUrl,
            pageSize: 10,
            columns: {
                select: {
                    render: (item, data) => {
                        @can('corsec.delete')
                            const status = (data.status ?? '').toString().toLowerCase();
                            const deletableStatuses = ['draft', 'returned'];
                            if (!isAdmin && !deletableStatuses.includes(status)) return '';

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
                registration_no: {
                    title: 'No Registrasi',
                    render: (item, data) => data.registration_no ?? '-',
                },
                letter_no: {
                    title: 'No Surat',
                    render: (item, data) => data.letter_no ?? '-',
                },
                order_date: {
                    title: 'Tanggal Order',
                    render: (item, data) => {
                        return data.order_date ? window.formatTanggalIndonesia(data.order_date) : '-';
                    },
                },
                subject: {
                    title: 'Perihal',
                    render: (item, data) => data.subject ?? '-',
                },
                summary: {
                    title: 'Ringkasan',
                    render: (item, data) => data.summary ?? '-',
                },
                recipient: {
                    title: 'Penerima',
                    render: (item, data) => {
                        return data.recipient?.name || data.recipient_other || '-';
                    },
                },
                letter_type: {
                    title: 'Jenis Surat',
                    render: (item, data) => {
                        return data.letter_type?.name || data.letterType?.name || '-';
                    },
                },
                perihal_type: {
                    title: 'Jenis Perihal',
                    render: (item, data) => perihalTypeLabel(data.perihal_type),
                },
                requester_directorate: {
                    title: 'Direktorat',
                    render: (item, data) => {
                        return data.requester_directorate?.name || data.requesterDirectorate?.name || '-';
                    },
                },
                status: {
                    title: 'Status',
                    render: (item, data) => statusBadge(data.status),
                },
                created_at: {
                    title: 'Dibuat',
                    render: (item, data) => data.created_at ? window.formatTanggalWaktuIndonesia(data.created_at) : '-',
                },
                actions: {
                    title: 'Action',
                    render: (item, data) => {
                        const status = (data.status ?? '').toString().toLowerCase();
                        const editableStatuses = ['draft', 'returned'];
                        const deletableStatuses = ['draft', 'returned'];
                        const cancellableStatuses = [
                            'draft',
                            'returned',
                            'waiting_dir_approval',
                            'compliance_review',
                            'waiting_compliance_approval',
                            'waiting_verification',
                            'waiting_final_upload'
                        ];
                        const canEditStatus = editableStatuses.includes(status);
                        const canDeleteStatus = isAdmin || deletableStatuses.includes(status);
                        const isRequesterMakerStaff = isAdmin || (
                            hasMakerRole &&
                            isStaffPosition &&
                            Number(data.created_by ?? 0) === Number(currentUserId) &&
                            Number(data.requester_directorate_id ?? 0) === Number(currentUserDirectorateId)
                        );
                        const canCancelRequest = canCreateOrUpdate && isRequesterMakerStaff && cancellableStatuses
                            .includes(status);
                        const rowKey = data.uuid ?? data.id;
                        let html = `<div class="flex flex-nowrap justify-center">`;

                        @can('corsec.read')
                            html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${rowKey}">
                                <i class="ki-outline ki-eye"></i>
                            </a>`;
                        @endcan

                        @if (auth()->user()?->hasRole('administrator') || (auth()->user()?->can('corsec.update') && !(auth()->user()?->hasRole('viewer') && !auth()->user()?->hasRole(['administrator', 'maker', 'checker', 'approver']))))
                            if (canEditStatus) {
                                html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${rowKey}/edit">
                                    <i class="ki-outline ki-notepad-edit"></i>
                                </a>`;
                            }
                        @endif

                        @can('corsec.delete')
                            if (canDeleteStatus) {
                                html += `<a onclick="deleteData('${rowKey}')" class="btn btn-sm btn-icon btn-clear btn-danger">
                                    <i class="ki-outline ki-trash"></i>
                                </a>`;
                            }
                        @endcan

                        if (canCancelRequest) {
                            html += `<a onclick="cancelRequestData('${rowKey}')" class="btn btn-sm btn-icon btn-clear btn-warning" title="Ajukan Pembatalan">
                                <i class="ki-outline ki-cross-circle"></i>
                            </a>`;
                        }

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
            const searchValue = searchInput.value.trim();

            if (searchValue) url.searchParams.set('search', searchValue);
            else url.searchParams.delete('search');

            exportBtn.href = url.toString();
        }

        searchInput.addEventListener('input', function() {
            const searchValue = this.value.trim();
            dataTable.search(searchValue, true);
            dataTable.goPage(1);
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

        updateExportUrl();
    </script>
@endpush
