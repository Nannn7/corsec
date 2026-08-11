@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.outgoing.index') }}
@endsection

@section('content')
    @php($permissionFlags = $permissionFlags ?? [])
    <div class="container-fluid">
        <div class="grid">
            <div class="min-w-full card card-grid" data-datatable="false" data-datatable-page-size="10"
                data-datatable-state-save="false" id="outgoing-letter-table"
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
                            @if (($canCreate ?? false) || ($permissionFlags['can_export'] ?? false) || ($permissionFlags['can_delete'] ?? false))
                                <div class="h-[24px] border border-r-gray-200"> </div>
                            @endif

                            @if ($permissionFlags['can_export'] ?? false)
                                <a id="export-btn" class="btn btn-sm btn-light"
                                    href="{{ route('letter.outgoing.export') }}">
                                    Export to Excel
                                </a>
                            @endif

                            @if ($permissionFlags['can_delete'] ?? false)
                                <button class="hidden btn btn-sm btn-danger" id="deleteSelected"
                                    onclick="deleteSelectedRows()">Delete Selected</button>
                            @endif

                            @if ($canCreate ?? false)
                                <a class="btn btn-sm btn-primary" href="{{ route('letter.outgoing.create') }}">
                                    Tambah Surat
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="hidden flex-wrap items-center gap-2.5 px-5 py-3 border-b border-gray-200 bg-warning-light text-warning"
                    id="active-filter-banner">
                    <i class="ki-filled ki-filter-tick text-base"></i>
                    <span class="text-2sm font-medium">
                        Sedang menampilkan surat dengan filter <strong>Butuh Tindak Lanjut</strong> aktif.
                    </span>
                    <button type="button" class="btn btn-sm btn-light ms-auto" id="active-filter-clear">
                        Lihat Semua
                    </button>
                </div>

                <div class="flex flex-wrap items-end gap-3.5 px-5 py-4 border-b border-gray-200"
                    id="outgoing-letter-filters">
                    <div class="flex flex-col gap-1">
                        <label class="form-label text-2sm">Status</label>
                        <select class="select select-sm w-48" id="filter-status">
                            <option value="">- Semua -</option>
                            <option value="needs_followup">Butuh Tindak Lanjut</option>
                            <option value="draft">Draft</option>
                            <option value="waiting_dir_approval">Approval Direktorat</option>
                            <option value="compliance_review">Review Kepatuhan</option>
                            <option value="waiting_compliance_approval">Approval EO dan DD Kepatuhan</option>
                            <option value="waiting_final_upload">Final Upload</option>
                            <option value="waiting_cancel_approval">Approval Pembatalan EO Direktorat</option>
                            <option value="verified">Done</option>
                            <option value="returned">Revisi</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="form-label text-2sm">Penerima</label>
                        <select class="select select-sm w-48" id="filter-recipient">
                            <option value="">- Semua -</option>
                            @foreach ($recipients as $recipient)
                                <option value="{{ $recipient->id }}">{{ $recipient->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="form-label text-2sm">Jenis Surat</label>
                        <select class="select select-sm w-44" id="filter-letter-type">
                            <option value="">- Semua -</option>
                            @foreach ($letterTypes as $letterType)
                                <option value="{{ $letterType->id }}">{{ $letterType->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="form-label text-2sm">Jenis Perihal</label>
                        <select class="select select-sm w-44" id="filter-perihal-type">
                            <option value="">- Semua -</option>
                            <option value="tanggapan_surat_masuk">Tanggapan Surat Masuk</option>
                            <option value="rutinitas">Rutinitas</option>
                            <option value="insidentil">Insidentil</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="form-label text-2sm">Tanggal Order</label>
                        <div class="flex items-center gap-1.5">
                            <input type="date" class="input input-sm w-36" id="filter-order-date-from">
                            <span class="text-2sm text-gray-500">s/d</span>
                            <input type="date" class="input input-sm w-36" id="filter-order-date-to">
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light" id="filter-reset">Reset Filter</button>
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
                                    <th class="min-w-[180px]" data-datatable-column="registration_no">
                                        <span class="sort">
                                            <span class="sort-label">No Registrasi</span>
                                            <span class="sort-icon"></span>
                                        </span>
                                    </th>
                                    <th class="min-w-[180px]" data-datatable-column="letter_no">
                                        <span class="sort">
                                            <span class="sort-label">No Surat</span>
                                            <span class="sort-icon"></span>
                                        </span>
                                    </th>
                                    <th class="min-w-[160px]" data-datatable-column="order_date">
                                        <span class="sort">
                                            <span class="sort-label">Tanggal Order</span>
                                            <span class="sort-icon"></span>
                                        </span>
                                    </th>
                                    <th class="min-w-[220px]" data-datatable-column="subject">
                                        <span class="sort">
                                            <span class="sort-label">Perihal</span>
                                            <span class="sort-icon"></span>
                                        </span>
                                    </th>
                                    <th class="min-w-[220px]" data-datatable-column="summary">Ringkasan</th>
                                    <th class="min-w-[180px]" data-datatable-column="recipient">Penerima</th>
                                    <th class="min-w-[180px]" data-datatable-column="letter_type">Jenis Surat</th>
<<<<<<< HEAD
                                    <th class="min-w-[120px]" data-datatable-column="perihal_type">Jenis Perihal</th>
                                    <th class="min-w-[170px]" data-datatable-column="requester_directorate">Direktorat
                                    </th>
                                    <th class="min-w-[220px]" data-datatable-column="circulation">Sirkulasi</th>
                                    <th class="min-w-[260px]" data-datatable-column="comments">Komentar</th>
                                    <th class="min-w-[220px]" data-datatable-column="attachments">Attachment</th>
                                    <th class="min-w-[140px]" data-datatable-column="status">
                                        <span class="sort">
                                            <span class="sort-label">Status</span>
                                            <span class="sort-icon"></span>
                                        </span>
                                    </th>
=======
                                    <th class="min-w-[170px]" data-datatable-column="perihal_type">Jenis Perihal</th>
                                    <th class="min-w-[170px]" data-datatable-column="requester_directorate">Direktorat</th>
                                    <th class="min-w-[220px]" data-datatable-column="circulation">Sirkulasi</th>
                                    <th class="min-w-[260px]" data-datatable-column="comments">Komentar</th>
                                    <th class="min-w-[220px]" data-datatable-column="attachments">Attachment</th>
                                    <th class="min-w-[140px]" data-datatable-column="status">Status</th>
                                    <th class="min-w-[180px]" data-datatable-column="created_at">Dibuat</th>
>>>>>>> 41a6d587a986009fad13830696d5399143b77ee3
                                    <th class="min-w-[70px] text-center" data-datatable-column="actions">Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    <div
                        class="flex-col gap-3 justify-center font-medium text-gray-600 card-footer md:justify-between md:flex-row text-2sm">
                        <div class="flex gap-2 items-center">
                            Show
                            <select class="w-16 select select-sm" data-datatable-size="true" name="perpage"> </select>
                            per
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

@push('styles')
    <style>
        .cancel-request-swal-popup {
            width: min(680px, calc(100vw - 1.5rem));
            padding: 1rem;
        }

        .cancel-request-swal-popup .swal2-html-container {
            margin: 0.5rem 0 0;
            text-align: left;
        }

        .cancel-request-swal-textarea {
            width: 100% !important;
            min-height: 110px;
            max-height: 42vh;
            max-width: 100% !important;
            resize: vertical;
            margin: 0 !important;
            box-sizing: border-box;
        }

        @media (max-width: 640px) {
            .cancel-request-swal-popup {
                width: calc(100vw - 1rem);
                padding: 0.875rem;
            }

            .cancel-request-swal-popup .swal2-actions {
                display: flex;
                flex-direction: column-reverse;
                gap: 0.5rem;
                width: 100%;
                margin-top: 0.875rem;
            }

            .cancel-request-swal-popup .swal2-styled {
                width: 100%;
                margin: 0 !important;
            }
        }
    </style>
@endpush

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
                            Swal.fire('Error!', window.corsecAjaxMessage(error,
                                'Gagal menghapus surat keluar.'), 'error');
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
                        Swal.fire('Error!', window.corsecAjaxMessage(error,
                            'Gagal menghapus surat keluar terpilih.'), 'error');
                    });
                }
            })
        }

        function cancelRequestData(rowKey) {
            const element = document.querySelector('#outgoing-letter-table');
            const baseUrl = element.getAttribute('data-base-url');

            Swal.fire({
                title: 'Ajukan Pembatalan Surat?',
                customClass: {
                    popup: 'cancel-request-swal-popup'
                },
                html: `
                    <div class="text-left">
                        <label for="cancel_reason" class="mb-2 block text-sm text-gray-700">Alasan pembatalan <span class="text-danger">*</span></label>
                        <textarea id="cancel_reason" class="swal2-textarea cancel-request-swal-textarea" rows="4" placeholder="Contoh: Data surat perlu diganti total..."></textarea>
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
                    const message = window.corsecAjaxMessage(error,
                        'Gagal mengajukan pembatalan surat.');
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

        // --- Filter panel (Status, Penerima, Jenis Surat, Jenis Perihal, rentang Tanggal Order) ---
        const filterElements = {
            status: document.getElementById('filter-status'),
            recipient_id: document.getElementById('filter-recipient'),
            letter_type_id: document.getElementById('filter-letter-type'),
            perihal_type: document.getElementById('filter-perihal-type'),
            order_date_from: document.getElementById('filter-order-date-from'),
            order_date_to: document.getElementById('filter-order-date-to'),
        };
        const filterResetButton = document.getElementById('filter-reset');
        const activeFilterBanner = document.getElementById('active-filter-banner');
        const activeFilterClearButton = document.getElementById('active-filter-clear');

        function updateActiveFilterBanner() {
            if (!activeFilterBanner) return;
            const isNeedsFollowup = filterElements.status?.value === 'needs_followup';
            activeFilterBanner.classList.toggle('hidden', !isNeedsFollowup);
            activeFilterBanner.classList.toggle('flex', isNeedsFollowup);
        }

        function getActiveFilters() {
            const filters = {};
            Object.entries(filterElements).forEach(([key, el]) => {
                if (el && el.value) filters[key] = el.value;
            });
            return filters;
        }

        function applyFiltersFromUrl() {
            const params = new URLSearchParams(window.location.search);
            Object.entries(filterElements).forEach(([key, el]) => {
                if (el && params.has(key)) el.value = params.get(key);
            });
            if (params.has('search')) searchInput.value = params.get('search');
            updateActiveFilterBanner();
        }

        function updateUrlFromFilters() {
            const url = new URL(window.location.href);
            url.search = '';
            Object.entries(getActiveFilters()).forEach(([key, value]) => url.searchParams.set(key, value));
            if (searchInput.value) url.searchParams.set('search', searchInput.value);
            window.history.replaceState({}, '', url.toString());
        }

        applyFiltersFromUrl();
        const isAdmin = @json((bool) ($permissionFlags['is_admin'] ?? false));
        const hasOperationalRole = @json((bool) ($permissionFlags['has_operational_role'] ?? false));
        const isViewerRole = @json((bool) ($permissionFlags['is_viewer_role'] ?? false));
        const hasMakerRole = @json((bool) ($permissionFlags['has_maker_role'] ?? false));
        const isStaffPosition = @json((bool) ($permissionFlags['is_staff_position'] ?? false));
        const currentUserId = @json((int) ($permissionFlags['current_user_id'] ?? 0));
        const currentUserDirectorateId = @json((int) ($permissionFlags['current_user_directorate_id'] ?? 0));
        const canCreateOrUpdate = @json((bool) ($permissionFlags['can_create_or_update'] ?? false));
        const canRead = @json((bool) ($permissionFlags['can_read'] ?? false));
        const canDelete = @json((bool) ($permissionFlags['can_delete'] ?? false));
        const canEditAction = @json((bool) ($permissionFlags['can_edit_action'] ?? false));
        const canComment = @json((bool) ($permissionFlags['can_comment'] ?? false));

        const escapeHtml = (value) => {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        };

        const renderBulletList = (items) => {
            const list = Array.isArray(items) ? items.filter(Boolean) : [];
            if (list.length === 0) return '-';
            return `<ul class="list-disc ps-4 space-y-1">${list.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
        };

        const renderAttachments = (attachments) => {
            const list = Array.isArray(attachments) ? attachments : [];
            if (list.length === 0) return '-';
            return `<div class="flex flex-col gap-1">${list.map((attachment) => {
                if (!attachment?.view_url) return '';
                return `<a class="btn btn-xs btn-light justify-start" target="_blank" href="${attachment.view_url}">
<<<<<<< HEAD
                    <i class="ki-outline ki-eye"></i>${escapeHtml(attachment.name || 'Attachment')}
                </a>`;
=======
<<<<<<< HEAD
                            <i class="ki-outline ki-eye"></i>${escapeHtml(attachment.name || 'Attachment')}
                        </a>`;
=======
                    <i class="ki-outline ki-eye"></i>${escapeHtml(attachment.name || 'Attachment')}
                </a>`;
>>>>>>> 41a6d587a986009fad13830696d5399143b77ee3
>>>>>>> 4773762663b025baff535a4ccf0a0ba07c294817
            }).join('')}</div>`;
        };

        const renderComments = (data) => {
            const comments = Array.isArray(data.comments) ? data.comments : [];
<<<<<<< HEAD
=======
<<<<<<< HEAD
            const commentList = comments.length > 0 ?
                `<div class="mb-2 space-y-1">${comments.map((comment) => `<div class="rounded border border-gray-200 bg-gray-50 p-2 text-xs">
                            <div>${escapeHtml(comment.body || '-')}</div>
                            <div class="mt-1 text-[11px] text-gray-500">${escapeHtml(comment.created_by || '')}</div>
                        </div>`).join('')}</div>` :
                '<div class="mb-2 text-xs text-gray-500">Belum ada komentar.</div>';
=======
>>>>>>> 4773762663b025baff535a4ccf0a0ba07c294817
            const commentList = comments.length > 0
                ? `<div class="mb-2 space-y-1">${comments.map((comment) => `<div class="rounded border border-gray-200 bg-gray-50 p-2 text-xs">
                    <div>${escapeHtml(comment.body || '-')}</div>
                    <div class="mt-1 text-[11px] text-gray-500">${escapeHtml(comment.created_by || '')}</div>
                </div>`).join('')}</div>`
                : '<div class="mb-2 text-xs text-gray-500">Belum ada komentar.</div>';
<<<<<<< HEAD
=======
>>>>>>> 41a6d587a986009fad13830696d5399143b77ee3
>>>>>>> 4773762663b025baff535a4ccf0a0ba07c294817

            if (!canComment || !data.comment_url) return commentList;

            return `${commentList}<div class="flex flex-col gap-1">
                <textarea class="textarea textarea-sm min-h-16" data-table-comment-input placeholder="Tulis komentar..."></textarea>
                <button type="button" class="btn btn-xs btn-primary self-start" data-table-comment-submit data-comment-url="${data.comment_url}">Simpan</button>
            </div>`;
        };

        const statusBadge = (status) => {
            const val = (status ?? '').toString().toLowerCase();
            let normalized = 'draft';
            if (val === 'waiting_dir_approval') normalized = 'waiting_dir_approval';
            if (val === 'compliance_review') normalized = 'compliance_review';
            if (val === 'waiting_compliance_approval') normalized = 'waiting_compliance_approval';
            if (val === 'waiting_final_upload' || val === 'final_uploaded' || val === 'waiting_verification')
                normalized = 'waiting_final_upload';
            if (val === 'waiting_cancel_approval') normalized = 'waiting_cancel_approval';
            if (val === 'waiting_response_letter') normalized = 'waiting_response_letter';
            if (val === 'verified') normalized = 'done';
            if (val === 'returned') normalized = 'revisi';
            if (val === 'cancelled') normalized = 'cancelled';

            const map = {
                draft: ['badge-light', 'Draft'],
                waiting_dir_approval: ['badge-warning', 'Approval Direktorat'],
                compliance_review: ['badge-info', 'Review Kepatuhan'],
                waiting_compliance_approval: ['badge-warning', 'Approval EO dan DD Kepatuhan'],
                waiting_final_upload: ['badge-primary', 'Final Upload'],
                waiting_cancel_approval: ['badge-warning', 'Approval Pembatalan EO Direktorat'],
                waiting_response_letter: ['badge-info', 'Waiting Response Letter'],
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
            stateSave: false,
            mapRequest: (params) => {
                Object.entries(getActiveFilters()).forEach(([key, value]) => params.set(key, value));
                return params;
            },
            columns: {
                select: {
                    render: (item, data) => {
                        if (data.is_pending_response_letter) return '';
                        if (canDelete) {
                            const status = (data.status ?? '').toString().toLowerCase();
                            const deletableStatuses = ['draft', 'returned'];
                            if (!isAdmin && !deletableStatuses.includes(status)) return '';

                            const checkbox = document.createElement('input');
                            checkbox.className = 'checkbox checkbox-sm';
                            checkbox.type = 'checkbox';
                            checkbox.value = data.id.toString();
                            checkbox.setAttribute('data-datatable-row-check', 'true');
                            return checkbox.outerHTML.trim();
                        }
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
                circulation: {
                    title: 'Sirkulasi',
                    render: (item, data) => renderBulletList(data.circulation_items),
                },
                comments: {
                    title: 'Komentar',
                    render: (item, data) => renderComments(data),
                },
                attachments: {
                    title: 'Attachment',
                    render: (item, data) => renderAttachments(data.attachments),
                },
                status: {
                    title: 'Status',
                    render: (item, data) => statusBadge(data.status),
                },
                actions: {
                    title: 'Action',
                    render: (item, data) => {
                        if (data.is_pending_response_letter && data.create_response_url) {
                            return `<div class="flex flex-nowrap justify-center">
                                <a class="btn btn-sm btn-icon btn-clear btn-primary" href="${data.create_response_url}" title="Buat Surat Jawaban">
                                    <i class="ki-outline ki-plus"></i>
                                </a>
                            </div>`;
                        }

                        const status = (data.status ?? '').toString().toLowerCase();
                        const editableStatuses = ['draft', 'returned'];
                        const deletableStatuses = ['draft', 'returned'];
                        const cancellableStatuses = [
                            'draft',
                            'returned',
                            'waiting_dir_approval',
                            'compliance_review',
                            'waiting_compliance_approval',
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

                        if (canRead) {
                            html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${rowKey}">
                                <i class="ki-outline ki-eye"></i>
                            </a>`;
                        }

                        if (canEditAction) {
                            if (canEditStatus) {
                                html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${rowKey}/edit">
                                    <i class="ki-outline ki-notepad-edit"></i>
                                </a>`;
                            }
                        }

                        if (canDelete) {
                            if (canDeleteStatus) {
                                html += `<a onclick="deleteData('${rowKey}')" class="btn btn-sm btn-icon btn-clear btn-danger">
                                    <i class="ki-outline ki-trash"></i>
                                </a>`;
                            }
                        }

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

        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-table-comment-submit]');
            if (!button) return;

            const wrapper = button.closest('td') || button.parentElement;
            const input = wrapper?.querySelector('[data-table-comment-input]');
            const note = input?.value?.trim() ?? '';
            const url = button.getAttribute('data-comment-url');
            if (!note || !url) {
                Swal.fire('Peringatan', 'Komentar wajib diisi.', 'warning');
                return;
            }

            button.disabled = true;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
<<<<<<< HEAD
                    body: JSON.stringify({ note })
=======
<<<<<<< HEAD
                    body: JSON.stringify({
                        note
                    })
=======
                    body: JSON.stringify({ note })
>>>>>>> 41a6d587a986009fad13830696d5399143b77ee3
>>>>>>> 4773762663b025baff535a4ccf0a0ba07c294817
                });
                if (!response.ok) throw response;
                if (typeof dataTable.reload === 'function') dataTable.reload();
                else window.location.reload();
            } catch (error) {
                Swal.fire('Error!', window.corsecAjaxMessage(error, 'Gagal menyimpan komentar.'), 'error');
            } finally {
                button.disabled = false;
            }
        });

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
            updateUrlFromFilters();
            updateExportUrl();
        });

        Object.values(filterElements).forEach((el) => {
            if (!el) return;
            el.addEventListener('change', function() {
                dataTable.goPage(1);
                dataTable.reload();
                updateUrlFromFilters();
                updateExportUrl();
                updateActiveFilterBanner();
            });
        });

        filterResetButton?.addEventListener('click', function() {
            Object.values(filterElements).forEach((el) => {
                if (el) el.value = '';
            });
            dataTable.goPage(1);
            dataTable.reload();
            updateUrlFromFilters();
            updateExportUrl();
            updateActiveFilterBanner();
        });

        activeFilterClearButton?.addEventListener('click', function() {
            filterResetButton?.click();
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
