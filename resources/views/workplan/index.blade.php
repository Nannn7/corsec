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
                                <th class="min-w-[220px]" data-datatable-column="circulation">Sirkulasi</th>
                                <th class="min-w-[260px]" data-datatable-column="comments">Komentar</th>
                                <th class="min-w-[220px]" data-datatable-column="attachments">Attachment</th>
                                <th class="min-w-[120px]" data-datatable-column="year">Tahun</th>
                                <th class="min-w-[260px]" data-datatable-column="title">Program Kerja</th>
                                <th class="min-w-[120px]" data-datatable-column="total_items">Total Item</th>
                                <th class="min-w-[120px]" data-datatable-column="done_items">Done</th>
                                <th class="min-w-[140px]" data-datatable-column="pending_items">Pending</th>
                                <th class="min-w-[140px]" data-datatable-column="status">Status</th>
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
        function workplanDeleteErrorMessage(error, fallback = 'Gagal menghapus program kerja.') {
            if (typeof window.corsecAjaxMessage === 'function') {
                return window.corsecAjaxMessage(error, fallback);
            }

            if (error?.responseJSON?.message) {
                return error.responseJSON.message;
            }

            if (error?.responseJSON?.error) {
                return error.responseJSON.error;
            }

            if (typeof error?.responseText === 'string' && error.responseText.trim() !== '') {
                try {
                    const payload = JSON.parse(error.responseText);

                    if (payload?.message) {
                        return payload.message;
                    }
                } catch (e) {
                    return fallback;
                }
            }

            return fallback;
        }

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
                    if (response?.success === false) {
                        Swal.fire('Error!', response.message ?? 'Gagal menghapus program kerja.', 'error');
                        return;
                    }

                    Swal.fire('Terhapus!', response.message, 'success').then(() => {
                        window.location.reload();
                    });
                }).catch((error) => {
                    Swal.fire('Error!', workplanDeleteErrorMessage(error), 'error');
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
        const isDeputyDirector = @json($permissionFlags['is_deputy_director'] ?? false);
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
                    <i class="ki-outline ki-eye"></i>${escapeHtml(attachment.name || 'Attachment')}
                </a>`;
            }).join('')}</div>`;
        };

        const renderComments = (data) => {
            const comments = Array.isArray(data.comments) ? data.comments : [];
            const commentList = comments.length > 0
                ? `<div class="mb-2 space-y-1">${comments.map((comment) => `<div class="rounded border border-gray-200 bg-gray-50 p-2 text-xs">
                    <div>${escapeHtml(comment.body || '-')}</div>
                    <div class="mt-1 text-[11px] text-gray-500">${escapeHtml(comment.created_by || '')}</div>
                </div>`).join('')}</div>`
                : '<div class="mb-2 text-xs text-gray-500">Belum ada komentar.</div>';

            if (!canComment || !data.comment_url) return commentList;

            return `${commentList}<div class="flex flex-col gap-1">
                <textarea class="textarea textarea-sm min-h-16" data-table-comment-input placeholder="Tulis komentar..."></textarea>
                <button type="button" class="btn btn-xs btn-primary self-start" data-table-comment-submit data-comment-url="${data.comment_url}">Simpan</button>
            </div>`;
        };

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
                        const canEditStatus = editableStatuses.includes(status);
                        const rowKey = data.uuid ?? data.id;
                        let html = `<div class="flex flex-nowrap justify-center">`;

                        @can('corsec.read')
                            html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${rowKey}">
                                <i class="ki-outline ki-eye"></i>
                            </a>`;
                        @endcan

                        @if (auth()->user()?->can('corsec.update') && !(auth()->user()?->hasRole('viewer') && !auth()->user()?->hasRole(['administrator', 'maker', 'checker', 'approver'])))
                            if (canEditStatus && !isDeputyDirector) {
                                html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${rowKey}/edit">
                                    <i class="ki-outline ki-notepad-edit"></i>
                                </a>`;
                            }
                        @endif

                        @can('corsec.delete')
                            html += `<a onclick="deleteWorkplan('${rowKey}')" class="btn btn-sm btn-icon btn-clear btn-danger">
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
                    body: JSON.stringify({ note })
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
