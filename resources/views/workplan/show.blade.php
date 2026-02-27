@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('workplan.show', $workplan) }}
@endsection

@section('content')
    @php
        $programStatusBadge = function ($status) {
            return match ((string) $status) {
                'draft' => 'badge-light',
                'waiting_dir_approval' => 'badge-warning',
                'active' => 'badge-info',
                'done' => 'badge-success',
                'returned' => 'badge-danger',
                default => 'badge-light',
            };
        };

        $programStatusLabel = function ($status) {
            return match ((string) $status) {
                'draft' => 'Draft',
                'waiting_dir_approval' => 'Waiting Dir Approval',
                'active' => 'Active',
                'done' => 'Done',
                'returned' => 'Returned',
                default => $status ?: '-',
            };
        };

        $itemStatusBadge = function ($status) {
            return match ((string) $status) {
                'process_on_target' => 'badge-info',
                'done_on_target' => 'badge-success',
                'done_over_target' => 'badge-warning',
                'undone' => 'badge-danger',
                default => 'badge-light',
            };
        };

        $itemStatusLabel = function ($status) {
            return match ((string) $status) {
                'process_on_target' => 'PK - proses on target',
                'done_on_target' => 'PK - done on target',
                'done_over_target' => 'PK - done over target',
                'undone' => 'PK - undone',
                default => $status ?: '-',
            };
        };

        $updateActionLabel = function ($action) {
            return match ((string) $action) {
                'progress' => 'Progress',
                'done_on_target' => 'Done On Target',
                'done_over_target' => 'Done Over Target',
                'revision' => 'Revision',
                default => $action ?: '-',
            };
        };

        $programNo = 'PK-' . ($workplan->created_at ? $workplan->created_at->format('Ymd') : now()->format('Ymd')) . '-' . str_pad((string) $workplan->id, 6, '0', STR_PAD_LEFT);

        $allUpdates = $workplan->items
            ->flatMap(function ($item) {
                return $item->updates->map(function ($update) use ($item) {
                    return [
                        'item' => $item,
                        'update' => $update,
                    ];
                });
            })
            ->sortByDesc(function ($row) {
                return $row['update']->created_at;
            })
            ->values();
    @endphp

    <div class="grid gap-5 lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detail Program Kerja #{{ $workplan->id }}</h3>
                <div class="flex gap-2">
                    <a href="{{ route('workplan.index') }}" class="btn btn-sm btn-light">
                        <i class="ki-filled ki-arrow-left"></i> Kembali
                    </a>
                    @if ($canEdit)
                        <a href="{{ route('workplan.edit', $workplan) }}" class="btn btn-sm btn-info">
                            Edit
                        </a>
                    @endif
                    @if ($canDelete)
                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteWorkplanDetail('{{ $workplan->uuid }}')">
                            Hapus
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informasi Program</h3>
            </div>
            <div class="card-body">
                <div class="grid gap-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">No Program:</span>
                        <span class="font-medium">{{ $programNo }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Tanggal Input:</span>
                        <span class="font-medium">
                            {{ $workplan->created_at ? $workplan->created_at->format('Y-m-d H:i') : '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Direktorat:</span>
                        <span class="font-medium">{{ $workplan->directorate?->name ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Tahun:</span>
                        <span class="font-medium">{{ $workplan->year }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Judul Program:</span>
                        <span class="font-medium">{{ $workplan->title }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Status:</span>
                        <span class="badge {{ $programStatusBadge($workplan->status) }}">
                            {{ $programStatusLabel($workplan->status) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Authorized:</span>
                        <span class="font-medium">{{ $workplan->authorized_status ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Authorized At:</span>
                        <span class="font-medium">
                            {{ $workplan->authorized_at ? $workplan->authorized_at->format('Y-m-d H:i') : '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Authorized By:</span>
                        <span class="font-medium">{{ $workplan->authorizedBy?->name ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Rencana Tindak Lanjut</h3>
            </div>
            <div class="card-body">
                <div class="flex flex-wrap gap-2">
                    @foreach ($statusSteps as $key => $label)
                        <span class="badge {{ $workplan->status === $key ? 'badge-success' : 'badge-light' }}">{{ $label }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($canSubmit)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Submit ke Otorisator</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('workplan.submit', $workplan) }}" class="grid gap-4">
                        @csrf
                        <div class="flex flex-col">
                            <label class="form-label">Catatan (opsional)</label>
                            <textarea class="textarea w-full" name="note" rows="3" placeholder="Catatan untuk approver..."></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary">Submit Approval</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Tabulasi Program Kerja</h3>
            </div>
            <div class="card-body">
                <div class="overflow-x-auto">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th class="min-w-[40px]">No</th>
                                <th class="min-w-[260px]">Program Kerja</th>
                                <th class="min-w-[160px]">Target</th>
                                <th class="min-w-[190px]">Status</th>
                                <th class="min-w-[150px]">Selesai</th>
                                <th class="min-w-[240px]">Upload</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($workplan->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <div class="font-medium">{{ $item->title }}</div>
                                        @if ($item->description)
                                            <div class="text-xs text-gray-500">{{ $item->description }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $targetDate = $item->target_date;
                                            $initialTargetDate = $item->initial_target_date;
                                            $hasRevisedTarget = $targetDate && $initialTargetDate && !$initialTargetDate->isSameDay($targetDate);
                                        @endphp
                                        <div class="font-medium">{{ $targetDate ? $targetDate->format('Y-m-d') : '-' }}</div>
                                        @if ($hasRevisedTarget)
                                            <div class="text-xs text-gray-500">Target awal: {{ $initialTargetDate->format('Y-m-d') }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $itemStatusBadge($item->status) }}">
                                            {{ $itemStatusLabel($item->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $item->completed_at ? $item->completed_at->format('Y-m-d H:i') : '-' }}</td>
                                    <td>
                                        @php
                                            $files = $item->attachables;
                                        @endphp
                                        @if ($files->count() > 0)
                                            <div class="flex flex-col gap-1">
                                                @foreach ($files as $attachable)
                                                    @if ($attachable->attachment)
                                                        <a class="text-primary hover:underline text-xs"
                                                            href="{{ Storage::disk($attachable->attachment->disk ?? 'public')->url($attachable->attachment->path) }}"
                                                            target="_blank" rel="noopener">
                                                            {{ $attachable->attachment->original_name ?? $attachable->attachment->file_name }}
                                                        </a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="table-empty-row" data-empty-row="true">
                                    <td colspan="6" class="table-empty-cell">
                                        <div class="table-empty-state" role="status" aria-live="polite">
                                            <i class="ki-filled ki-file-deleted" aria-hidden="true"></i>
                                            <div class="table-empty-title">Belum ada item program kerja</div>
                                            <div class="table-empty-description">Item akan muncul setelah program kerja ditambahkan.</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($canSubmitUpdate)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Update Program Kerja</h3>
                </div>
                <div class="card-body grid gap-5">
                    @foreach ($workplan->items as $item)
                        @if (!in_array((string) $item->status, ['done_on_target', 'done_over_target'], true))
                            <form method="POST" action="{{ route('workplan.items.progress', [$workplan, $item]) }}"
                                enctype="multipart/form-data" class="p-4 border rounded-xl border-gray-200 grid gap-4 workplan-update-form">
                                @csrf
                                <div class="font-medium text-gray-800">{{ $item->title }}</div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex flex-col">
                                        <label class="form-label">Update Program Kerja <span class="text-danger">*</span></label>
                                        <select class="select js-update-action" name="action" required>
                                            <option value="progress">Proses</option>
                                            <option value="done_on_target">Done - On Target</option>
                                            <option value="done_over_target">Done - Over Target</option>
                                            <option value="revision">Revisi</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col js-progress-percent-wrap">
                                        <label class="form-label">Progress (%)</label>
                                        <input class="input" type="number" min="0" max="100"
                                            name="progress_percent" value="0">
                                    </div>
                                    <div class="flex flex-col md:col-span-2 js-revision-target-wrap hidden">
                                        <label class="form-label">Target Revisi <span class="text-danger">*</span></label>
                                        <input class="input" type="date" name="revised_target_date">
                                    </div>
                                    <div class="flex flex-col md:col-span-2">
                                        <label class="form-label">Catatan <span class="text-danger">*</span></label>
                                        <textarea class="textarea w-full" name="note" rows="3"
                                            placeholder="Isi progress / kendala / alasan over target / alasan revisi" required></textarea>
                                    </div>
                                    <div class="flex flex-col md:col-span-2">
                                        <label class="form-label">Upload Bukti <span class="text-danger">*</span></label>
                                        <input class="file-input" type="file" name="evidence_files[]"
                                            accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx" multiple required>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn btn-primary">Submit Update</button>
                                </div>
                            </form>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if ($allUpdates->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Riwayat Update Program Kerja</h3>
                </div>
                <div class="card-body">
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="min-w-[220px]">Item Program</th>
                                    <th class="min-w-[150px]">Aksi</th>
                                    <th class="min-w-[120px]">Progress</th>
                                    <th class="min-w-[260px]">Catatan</th>
                                    <th class="min-w-[150px]">Target Revisi</th>
                                    <th class="min-w-[120px]">Approval</th>
                                    <th class="min-w-[180px]">Oleh</th>
                                    <th class="min-w-[160px]">Waktu</th>
                                    <th class="min-w-[220px]">Bukti</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($allUpdates as $row)
                                    @php
                                        $item = $row['item'];
                                        $update = $row['update'];
                                    @endphp
                                    <tr>
                                        <td>{{ $item->title }}</td>
                                        <td>{{ $updateActionLabel($update->action) }}</td>
                                        <td>{{ $update->progress_percent ?? 0 }}%</td>
                                        <td>{{ $update->note ?? '-' }}</td>
                                        <td>{{ $update->revised_target_date ? $update->revised_target_date->format('Y-m-d') : '-' }}</td>
                                        <td>{{ $update->status ?? '-' }}</td>
                                        <td>{{ $update->updater?->name ?? '-' }}</td>
                                        <td>{{ $update->created_at ? $update->created_at->format('Y-m-d H:i') : '-' }}</td>
                                        <td>
                                            @if ($update->attachables->count() > 0)
                                                <div class="flex flex-col gap-1">
                                                    @foreach ($update->attachables as $attachable)
                                                        @if ($attachable->attachment)
                                                            <a class="text-primary hover:underline text-xs"
                                                                href="{{ Storage::disk($attachable->attachment->disk ?? 'public')->url($attachable->attachment->path) }}"
                                                                target="_blank" rel="noopener">
                                                                {{ $attachable->attachment->original_name ?? $attachable->attachment->file_name }}
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if ($approvals->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Riwayat Approval</h3>
                </div>
                <div class="card-body">
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="min-w-[120px]">Status</th>
                                    <th class="min-w-[220px]">Oleh</th>
                                    <th class="min-w-[280px]">Catatan</th>
                                    <th class="min-w-[180px]">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($approvals as $approval)
                                    <tr>
                                        <td>{{ $approval->status ?? '-' }}</td>
                                        <td>
                                            {{ $approval->actor?->name ?? '-' }}
                                            @if ($approval->actor?->directorate?->name)
                                                <span class="text-xs text-gray-500">({{ $approval->actor->directorate->name }})</span>
                                            @endif
                                        </td>
                                        <td>{{ $approval->note ?? '-' }}</td>
                                        <td>{{ $approval->acted_at ? $approval->acted_at->format('Y-m-d H:i:s') : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @can('corsec.authorize')
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Approval</h3>
                </div>
                <div class="card-body">
                    @if ($canCheckerApproval || $canApproverApproval)
                        <form method="POST" action="{{ route('workplan.approval.action', $workplan) }}" class="grid gap-4">
                            @csrf
                            <div class="text-sm text-gray-500">
                                {{ $canCheckerApproval ? 'Approval EO Direktorat' : 'Approval DD Direktorat' }}
                            </div>
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan..."></textarea>
                            </div>
                            <div class="flex flex-wrap gap-2 justify-end">
                                <button class="btn btn-sm btn-danger" type="submit" name="action" value="reject">
                                    Reject/Return
                                </button>
                                <button class="btn btn-sm btn-success" type="submit" name="action" value="approve">
                                    Approve
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="text-sm text-gray-500">Belum ada aksi approval untuk status ini.</div>
                    @endif
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script type="text/javascript">
        function deleteWorkplanDetail(rowKey) {
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

                $.ajax(`{{ url('workplan') }}/${rowKey}`, {
                    type: 'DELETE'
                }).then((response) => {
                    Swal.fire('Terhapus!', response.message, 'success').then(() => {
                        window.location.href = '{{ route('workplan.index') }}';
                    });
                }).catch(() => {
                    Swal.fire('Error!', 'Gagal menghapus program kerja.', 'error');
                });
            });
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.workplan-update-form').forEach((form) => {
                const actionSelect = form.querySelector('.js-update-action');
                const revisionWrap = form.querySelector('.js-revision-target-wrap');
                const revisionInput = form.querySelector('input[name="revised_target_date"]');
                const progressWrap = form.querySelector('.js-progress-percent-wrap');
                const progressInput = form.querySelector('input[name="progress_percent"]');

                const refreshUpdateFields = () => {
                    const action = actionSelect ? actionSelect.value : 'progress';
                    const isRevision = action === 'revision';
                    const isDone = action === 'done_on_target' || action === 'done_over_target';

                    if (revisionWrap) {
                        revisionWrap.classList.toggle('hidden', !isRevision);
                    }
                    if (revisionInput) {
                        revisionInput.required = isRevision;
                        if (!isRevision) {
                            revisionInput.value = '';
                        }
                    }

                    if (progressInput) {
                        if (isDone) {
                            progressInput.value = 100;
                            progressInput.readOnly = true;
                        } else {
                            progressInput.readOnly = false;
                        }
                    }
                    if (progressWrap) {
                        progressWrap.classList.remove('hidden');
                    }
                };

                actionSelect?.addEventListener('change', refreshUpdateFields);
                refreshUpdateFields();
            });
        });
    </script>
@endpush
