@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.outgoing.index') }}
@endsection

@section('content')
    <div class="container-fluid">
        <div class="grid">
            <div class="min-w-full card card-grid" data-datatable="false" data-datatable-page-size="5"
                data-datatable-state-save="true" id="outgoing-letter-table"
                data-api-url="{{ route('letter.outgoing.datatables') }}"
                data-base-url="{{ url('letter/outgoing') }}">
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
                            @if (($canCreate ?? false))
                                <div class="h-[24px] border border-r-gray-200"> </div>
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
                                    <th class="min-w-[220px]" data-datatable-column="subject">Perihal</th>
                                    <th class="min-w-[180px]" data-datatable-column="recipient">Penerima</th>
                                    <th class="min-w-[160px]" data-datatable-column="order_date">Tanggal Order</th>
                                    <th class="min-w-[140px]" data-datatable-column="status">Status</th>
                                    <th class="min-w-[50px] text-center" data-datatable-column="actions">Action</th>
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
    <script type="module">
        const element = document.querySelector('#outgoing-letter-table');
        const searchInput = document.getElementById('search');
        const apiUrl = element.getAttribute('data-api-url');
        const baseUrl = element.getAttribute('data-base-url');
        const isAdmin = @json(auth()->user()?->hasRole('administrator'));

        const dataTableOptions = {
            apiEndpoint: apiUrl,
            pageSize: 5,
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
                registration_no: {
                    title: 'No Registrasi',
                },
                subject: {
                    title: 'Perihal',
                },
                recipient: {
                    title: 'Penerima',
                    render: (item, data) => {
                        return data.recipient?.name || data.recipient_other || '-';
                    },
                },
                order_date: {
                    title: 'Tanggal Order',
                    render: (item, data) => {
                        return data.order_date ? window.formatTanggalWaktuIndonesia(data.order_date) : '-';
                    },
                },
                status: {
                    title: 'Status',
                    render: (item, data) => {
                        const val = (data.status ?? '').toString().toLowerCase();
                        const map = {
                            draft: ['badge-light', 'Draft'],
                            waiting_dir_approval: ['badge-warning', 'Waiting Dir Approval'],
                            compliance_review: ['badge-info', 'Compliance Review'],
                            waiting_compliance_approval: ['badge-warning', 'Waiting Compliance Approval'],
                            numbering: ['badge-primary', 'Numbering'],
                            waiting_verification: ['badge-info', 'Waiting Verification'],
                            final_uploaded: ['badge-info', 'Final Uploaded'],
                            verified: ['badge-success', 'Verified'],
                            returned: ['badge-danger', 'Returned'],
                        };
                        const [cls, text] = map[val] ?? ['badge-light', data.status ?? '-'];
                        return `<span class="badge ${cls}">${text}</span>`;
                    },
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

                        @if (auth()->user()?->hasRole('administrator') || auth()->user()?->can('corsec.update'))
                            if (canEditStatus) {
                                html += `<a class="btn btn-sm btn-icon btn-clear btn-info" href="${baseUrl}/${rowKey}/edit">
                                    <i class="ki-outline ki-notepad-edit"></i>
                                </a>`;
                            }
                        @endif

                        html += `</div>`;
                        return html;
                    },
                }
            },
        };

        let dataTable = new KTDataTable(element, dataTableOptions);
        searchInput.addEventListener('input', function() {
            const searchValue = this.value.trim();
            dataTable.search(searchValue, true);
            dataTable.goPage(1);
        });
    </script>
@endpush
