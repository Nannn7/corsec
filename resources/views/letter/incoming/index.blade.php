@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.incoming.index') }}
@endsection

@section('content')
    @php($permissionFlags = $permissionFlags ?? [])
    <div class="grid">
        <div class="min-w-full card card-grid" data-datatable="false" data-datatable-page-size="10"
            data-datatable-state-save="false" id="incoming-letter-table"
            data-api-url="{{ route('letter.incoming.datatables') }}" data-base-url="{{ url('letter/incoming') }}">
            <div class="flex-wrap py-5 card-header">
                <h3 class="card-title">
                    Incoming Letters
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

                        @if ($permissionFlags['can_export'] ?? false)
                            <a id="export-btn" class="btn btn-sm btn-light" href="{{ route('letter.incoming.export') }}">
                                Export to Excel
                            </a>
                        @endif

                        @if ($permissionFlags['can_create'] ?? false)
                            <a class="btn btn-sm btn-primary" href="{{ route('letter.incoming.create') }}">
                                Tambah Surat
                            </a>
                        @endif

                        @if ($permissionFlags['can_delete'] ?? false)
                            <button class="hidden btn btn-sm btn-danger" id="deleteSelected"
                                onclick="deleteSelectedRows()">Delete Selected</button>
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

            <div class="flex flex-wrap items-end gap-3.5 px-5 py-4 border-b border-gray-200" id="incoming-letter-filters">
                <div class="flex flex-col gap-1">
                    <label class="form-label text-2sm">Status</label>
                    <select class="select select-sm w-40" id="filter-status">
                        <option value="">- Semua -</option>
                        <option value="needs_followup">Butuh Tindak Lanjut</option>
                        <option value="draft">Draft</option>
                        <option value="on_approval">On Approval</option>
                        <option value="dispatched">Dispatched</option>
                        <option value="in_progress">In Progress</option>
                        <option value="waiting_dir_approval">Waiting Dir Approval</option>
                        <option value="waiting_response_letter">Waiting Response Letter</option>
                        <option value="waiting_verification">Waiting Validation</option>
                        <option value="verified">Verified</option>
                        <option value="returned">Returned</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="form-label text-2sm">Pengirim</label>
                    <select class="select select-sm w-48" id="filter-sender">
                        <option value="">- Semua -</option>
                        @foreach ($senders as $sender)
                            <option value="{{ $sender->id }}">{{ $sender->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="form-label text-2sm">Tanggal Surat</label>
                    <div class="flex items-center gap-1.5">
                        <input type="date" class="input input-sm w-36" id="filter-letter-date-from">
                        <span class="text-2sm text-gray-500">s/d</span>
                        <input type="date" class="input input-sm w-36" id="filter-letter-date-to">
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="form-label text-2sm">Tanggal Diterima</label>
                    <div class="flex items-center gap-1.5">
                        <input type="date" class="input input-sm w-36" id="filter-received-date-from">
                        <span class="text-2sm text-gray-500">s/d</span>
                        <input type="date" class="input input-sm w-36" id="filter-received-date-to">
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

                                <th class="min-w-[160px]" data-datatable-column="registration_no">
                                    <span class="sort">
                                        <span class="sort-label">No Registrasi</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[200px]" data-datatable-column="sender_id">
                                    <span class="sort">
                                        <span class="sort-label">Pengirim</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[160px]" data-datatable-column="external_letter_no">
                                    <span class="sort">
                                        <span class="sort-label">No Surat</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[160px]" data-datatable-column="letter_date">
                                    <span class="sort">
                                        <span class="sort-label">Tanggal Surat</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[250px]" data-datatable-column="subject">
                                    <span class="sort">
                                        <span class="sort-label">Perihal</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[150px]" data-datatable-column="letter_type_id">
                                    <span class="sort">
                                        <span class="sort-label">Jenis Surat</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[220px]" data-datatable-column="circulationDirectorates">
                                    <span class="sort">
                                        <span class="sort-label">Sirkulasi</span>
                                        <span class="sort-icon"></span>
                                    </span>
                                </th>

                                <th class="min-w-[260px]" data-datatable-column="comments">Komentar</th>

                                <th class="min-w-[150px]" data-datatable-column="attachments">Attachment</th>

                                <th class="min-w-[200px]" data-datatable-column="targetDirectorate">
                                    <span class="sort">
                                        <span class="sort-label">Leader</span>
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
        function deleteData(rowKey) {
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
                                'Gagal menghapus surat masuk.'), 'error');
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
                        Swal.fire('Error!', window.corsecAjaxMessage(error,
                            'Gagal menghapus surat masuk terpilih.'), 'error');
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
        const isAdmin = @json((bool) ($permissionFlags['is_admin'] ?? false));
        const canRead = @json((bool) ($permissionFlags['can_read'] ?? false));
        const canDelete = @json((bool) ($permissionFlags['can_delete'] ?? false));
        const canEditAction = @json((bool) ($permissionFlags['can_edit_action'] ?? false));
        const canComment = @json((bool) ($permissionFlags['can_comment'] ?? false));
        const isCorpSecretary = @json((bool) ($permissionFlags['is_corp_secretary'] ?? false));

        // --- Filter panel (Status, Pengirim, rentang Tanggal Surat & Tanggal Diterima) ---
        const filterElements = {
            status: document.getElementById('filter-status'),
            sender_id: document.getElementById('filter-sender'),
            letter_date_from: document.getElementById('filter-letter-date-from'),
            letter_date_to: document.getElementById('filter-letter-date-to'),
            received_date_from: document.getElementById('filter-received-date-from'),
            received_date_to: document.getElementById('filter-received-date-to'),
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
                            <i class="ki-outline ki-eye"></i>${escapeHtml(attachment.name || 'Attachment')}
                        </a>`;
            }).join('')}</div>`;
        };

        const renderComments = (data) => {
            const comments = Array.isArray(data.comments) ? data.comments : [];
            const commentList = comments.length > 0 ?
                `<div class="mb-2 space-y-1">${comments.map((comment) => `<div class="rounded border border-gray-200 bg-gray-50 p-2 text-xs">
                            <div>${escapeHtml(comment.body || '-')}</div>
                            <div class="mt-1 text-[11px] text-gray-500">${escapeHtml(comment.created_by || '')}</div>
                        </div>`).join('')}</div>` :
                '<div class="mb-2 text-xs text-gray-500">Belum ada komentar.</div>';

            if (!canComment || !data.comment_url) return commentList;

            return `${commentList}<div class="flex flex-col gap-1">
                <textarea class="textarea textarea-sm min-h-16" data-table-comment-input placeholder="Tulis komentar..."></textarea>
                <button type="button" class="btn btn-xs btn-primary self-start" data-table-comment-submit data-comment-url="${data.comment_url}">Simpan</button>
            </div>`;
        };

        const isValidationOverdue = (data) => {
            if (data?.corp_secretary_validated_at) {
                return false;
            }

            if (!data?.corp_secretary_validation_requested_at) {
                return false;
            }

            const requestedAt = new Date(data.corp_secretary_validation_requested_at);
            if (Number.isNaN(requestedAt.getTime())) {
                return false;
            }

            const deadline = new Date(requestedAt);
            deadline.setDate(deadline.getDate() + 1);
            deadline.setHours(23, 59, 59, 999);

            return new Date() > deadline;
        };

        const statusBadge = (status, data = null) => {
            const val = (status ?? '').toString().toLowerCase();

            // sesuai status constants yang kita pake
            const map = {
                draft: ['badge-light', 'Draft'],
                on_approval: ['badge-warning', 'On Approval'],
                dispatched: ['badge-info', 'Dispatched'],
                in_progress: ['badge-warning', 'In Progress'],
                waiting_dir_approval: ['badge-warning', 'Waiting Dir Approval'],
                waiting_response_letter: ['badge-info', 'Waiting Response Letter'],
                waiting_verification: ['badge-info', 'Waiting Validation'],
                verified: ['badge-success', 'Verified'],
                returned: ['badge-danger', 'Returned'],
                rejected: ['badge-danger', 'Rejected'],
            };

            const [cls, text] = map[val] ?? ['badge-light', status ?? '-'];
            const overdueBadge = isValidationOverdue(data) ?
                ' <span class="badge badge-danger">Overdue Validasi</span>' : '';
            return `<span class="badge ${cls}">${text}</span>${overdueBadge}`;
        };

        const dataTableOptions = {
            apiEndpoint: apiUrl,
            pageSize: 10,
            mapRequest: (params) => {
                Object.entries(getActiveFilters()).forEach(([key, value]) => params.set(key, value));
                return params;
            },
            columns: {
                select: {
                    render: (item, data) => {
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

                sender_id: {
                    title: 'Pengirim',
                    render: (item, data) => {
                        if (data.sender && data.sender.name) return `${data.sender.name}`;
                        if (data.sender_other) return data.sender_other;
                        return data.sender ?? '-';
                    },
                },

                external_letter_no: {
                    title: 'No Surat',
                    render: (item, data) => data.external_letter_no ?? '-',
                },

                letter_date: {
                    title: 'Tanggal Surat',
                    render: (item, data) => {
                        return data.letter_date ? window.formatTanggalIndonesia(data.letter_date) : '-';
                    },
                },

                subject: {
                    title: 'Perihal'
                },

                letter_type_id: {
                    title: 'Jenis Surat',
                    render: (item, data) => {
                        if (data.letter_type_other) return data.letter_type_other;
                        return data.letter_type ? `${data.letter_type.name}` : '-';
                    },
                },

                circulationDirectorates: {
                    title: 'Sirkulasi',
                    render: (item, data) => {
                        const list = data.circulation_directorates ?? data.circulationDirectorates ?? [];
                        if (!Array.isArray(list) || list.length === 0) return '-';
                        return renderBulletList(list.map((row) => row.name));
                    },
                },

                comments: {
                    title: 'Komentar',
                    render: (item, data) => renderComments(data),
                },

                attachments: {
                    title: 'Attachment',
                    render: (item, data) => renderAttachments(data.attachments),
                },

                targetDirectorate: {
                    title: 'Leader',
                    render: (item, data) => {
                        return data.target_directorate ?
                            `${data.target_directorate.name}` :
                            (data.targetDirectorate ? `${data.targetDirectorate.name}` : '-');
                    },
                },

                status: {
                    title: 'Status',
                    render: (item, data) => statusBadge(data.status, data),
                },

                received_date: {
                    title: 'Diterima',
                    render: (item, data) => {
                        // received_date itu date, bukan datetime
                        return data.received_date ? window.formatTanggalIndonesia(data.received_date) : '-';
                    },
                },

                actions: {
                    title: 'Action',
                    render: (item, data) => {
                        const status = (data.status ?? '').toString().toLowerCase();
                        const deletableStatuses = ['draft', 'returned'];
                        const canDeleteStatus = isAdmin || deletableStatuses.includes(status);
                        const canEditStatus = isCorpSecretary ? status !== 'verified' : ['draft', 'returned'].includes(status);
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

                            if (canDeleteStatus) {
                                html += `<a onclick="deleteData('${rowKey}')" class="btn btn-sm btn-icon btn-clear btn-danger">
                                    <i class="ki-outline ki-trash"></i>
                                </a>`;
                            }
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
                    body: JSON.stringify({
                        note
                    })
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
            let url = new URL(exportBtn.href);

            if (searchInput.value) url.searchParams.set('search', searchInput.value);
            else url.searchParams.delete('search');

            exportBtn.href = url.toString();
        }

        searchInput.addEventListener('input', function() {
            const searchValue = this.value.trim();
            dataTable.goPage(1);
            dataTable.search(searchValue, true);
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
        window.dataTable = dataTable;
    </script>
@endpush
