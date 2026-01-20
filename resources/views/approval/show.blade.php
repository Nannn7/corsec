@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('approval.show', $approvalRequest) }}
@endsection

@section('content')
    <div class="grid gap-5 mx-auto w-full lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-filled ki-shield-tick text-primary"></i>
                    Detail Approval #{{ $approvalRequest->id }}
                </h3>
                <a href="{{ route('approval.index') }}" class="btn btn-sm btn-info">
                    <i class="ki-filled ki-exit-left"></i> Back
                </a>
            </div>

            <div class="card-body">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="flex flex-col gap-3">
                        <div>
                            <div class="text-sm text-gray-500">Module</div>
                            <div class="font-medium">{{ class_basename($approvalRequest->model) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Aksi</div>
                            <span
                                class="badge {{ $approvalRequest->action_badge }}">{{ ucfirst($approvalRequest->action) }}</span>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Status</div>
                            <span
                                class="badge {{ $approvalRequest->status_badge }}">{{ ucfirst($approvalRequest->status) }}</span>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Deskripsi</div>
                            <div class="font-medium">{{ $approvalRequest->description ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Target ID</div>
                            <div class="font-medium">{{ $approvalRequest->target_id ?? '-' }}</div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <div>
                            <div class="text-sm text-gray-500">Dibuat Oleh</div>
                            <div class="font-medium">{{ $approvalRequest->createdBy?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Tanggal Dibuat</div>
                            <div class="font-medium">
                                {{ $approvalRequest->created_at ? $approvalRequest->created_at->format('Y-m-d H:i:s') : '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Diotorisasi Oleh</div>
                            <div class="font-medium">{{ $approvalRequest->authorizedBy?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Tanggal Otorisasi</div>
                            <div class="font-medium">
                                {{ $approvalRequest->authorized_at ? $approvalRequest->authorized_at->format('Y-m-d H:i:s') : '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Catatan Review</div>
                            <div class="font-medium">{{ $approvalRequest->review_notes ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="my-7 border-t border-gray-200"></div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="flex flex-col">
                        <label class="form-label">Request Lama</label>
                        <pre class="text-xs text-gray-700 p-4 bg-gray-50 rounded border border-gray-200 overflow-auto">{{ json_encode($approvalRequest->request_old ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                    <div class="flex flex-col">
                        <label class="form-label">Request Baru</label>
                        <pre class="text-xs text-gray-700 p-4 bg-gray-50 rounded border border-gray-200 overflow-auto">{{ json_encode($approvalRequest->request_new ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>

                @if ($approvalRequest->status === 'pending')
                    <div class="flex justify-end mt-8 gap-3">
                        <form method="POST" action="{{ route('approval.reject', $approvalRequest) }}"
                            class="flex gap-3 items-center w-full md:w-auto">
                            @csrf
                            <input class="input input-sm w-full md:min-w-[24rem] md:flex-1" type="text"
                                name="review_notes"
                                placeholder="Catatan penolakan (optional)">
                            <button class="btn btn-sm btn-danger" type="submit">Reject</button>
                        </form>
                        <form method="POST" action="{{ route('approval.approve', $approvalRequest) }}">
                            @csrf
                            <button class="btn btn-sm btn-primary" type="submit">Approve</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
