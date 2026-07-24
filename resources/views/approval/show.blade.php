@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('approval.show', $approvalRequest) }}
@endsection

@section('content')
    <div class="grid gap-5 lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Detail Approval #{{ $approvalRequest->id }}
                </h3>
                <div class="flex gap-2">
                    <a href="{{ route('approval.index') }}" class="btn btn-sm btn-light">
                        <i class="ki-filled ki-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-2 lg:gap-7.5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informasi Umum</h3>
                </div>
                <div class="card-body">
                    <div class="grid gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">ID:</span>
                            <span class="font-medium">{{ $approvalRequest->id }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Module:</span>
                            <span class="font-medium">{{ class_basename($approvalRequest->model) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Aksi:</span>
                            <span class="badge {{ $approvalRequest->action_badge }}">
                                {{ ucfirst($approvalRequest->action) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Target ID:</span>
                            <span class="font-medium">{{ $approvalRequest->target_id ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Status:</span>
                            <span class="badge {{ $approvalRequest->status_badge }}">
                                {{ ucfirst($approvalRequest->status) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Versi:</span>
                            <span class="font-medium">{{ $approvalRequest->version ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Checksum:</span>
                            <span class="font-mono text-xs">{{ $approvalRequest->checksum ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informasi Waktu & User</h3>
                </div>
                <div class="card-body">
                    <div class="grid gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Dibuat Oleh:</span>
                            <span class="font-medium">{{ $approvalRequest->createdBy?->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Tanggal Dibuat:</span>
                            <span class="font-medium">
                                {{ $approvalRequest->created_at ? $approvalRequest->created_at->format('Y-m-d H:i:s') : '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Diotorisasi Oleh:</span>
                            <span class="font-medium">{{ $approvalRequest->authorizedBy?->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Tanggal Otorisasi:</span>
                            <span class="font-medium">
                                {{ $approvalRequest->authorized_at ? $approvalRequest->authorized_at->format('Y-m-d H:i:s') : '-' }}
                            </span>
                        </div>
                        @if ($approvalRequest->reviewer_ip)
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">IP Reviewer:</span>
                                <span class="font-mono text-xs">{{ $approvalRequest->reviewer_ip }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($approvalRequest->description)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Deskripsi</h3>
                </div>
                <div class="card-body">
                    <p class="text-gray-700">{{ $approvalRequest->description }}</p>
                </div>
            </div>
        @endif

        @if ($approvalRequest->review_notes)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Catatan Review</h3>
                </div>
                <div class="card-body">
                    <p class="text-gray-700">{{ $approvalRequest->review_notes }}</p>
                </div>
            </div>
        @endif

        @if ($approvalRequest->request_old || ($approvalRequest->request_new && $approvalRequest->action !== 'delete'))
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        @if ($approvalRequest->action === 'create')
                            Data yang Akan Dibuat
                        @elseif ($approvalRequest->action === 'update')
                            Perbandingan Data
                        @elseif ($approvalRequest->action === 'delete')
                            Data yang Akan Dihapus
                        @endif
                    </h3>
                </div>
                <div class="card-body">
                    @if ($approvalRequest->action === 'create')
                        @if ($approvalRequest->request_new)
                            <div class="overflow-x-auto">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th class="min-w-[200px]">Field</th>
                                            <th class="min-w-[300px]">Nilai Baru</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($approvalRequest->request_new as $key => $value)
                                            @if (!in_array($key, ['password', 'password_confirmation']))
                                                <tr>
                                                    <td class="font-medium">
                                                        {{ ucfirst(str_replace('_', ' ', $key)) }}
                                                    </td>
                                                    <td>
                                                        @if (is_array($value) || is_object($value))
                                                            <pre class="p-2 text-sm bg-green-50 rounded border-l-4 border-green-400">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                        @elseif (is_bool($value))
                                                            <span
                                                                class="badge {{ $value ? 'badge-success' : 'badge-secondary' }}">
                                                                {{ $value ? 'Ya' : 'Tidak' }}
                                                            </span>
                                                        @elseif (is_null($value))
                                                            <span class="italic text-gray-400">Kosong</span>
                                                        @else
                                                            <span
                                                                class="font-medium text-green-600">{{ $value }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @elseif ($approvalRequest->action === 'delete')
                        @if ($approvalRequest->request_old)
                            <div class="overflow-x-auto">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th class="min-w-[200px]">Field</th>
                                            <th class="min-w-[300px]">Nilai yang Akan Dihapus</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($approvalRequest->request_old as $key => $value)
                                            <tr>
                                                <td class="font-medium">{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                                                <td>
                                                    @if (is_array($value) || is_object($value))
                                                        <pre class="p-2 text-sm bg-red-50 rounded border-l-4 border-red-400">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    @elseif (is_bool($value))
                                                        <span
                                                            class="badge {{ $value ? 'badge-success' : 'badge-secondary' }}">
                                                            {{ $value ? 'Ya' : 'Tidak' }}
                                                        </span>
                                                    @elseif (is_null($value))
                                                        <span class="italic text-gray-400">Kosong</span>
                                                    @else
                                                        <span class="font-medium text-red-600">{{ $value }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @else
                        @php
                            $oldData = $approvalRequest->request_old ?? [];
                            $newData = $approvalRequest->request_new ?? [];
                            $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
                        @endphp
                        <div class="overflow-x-auto">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="min-w-[200px]">Field</th>
                                        <th class="min-w-[300px]">Nilai Lama</th>
                                        <th class="min-w-[300px]">Nilai Baru</th>
                                        <th class="min-w-[100px]">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($allKeys as $key)
                                        @php
                                            $oldValue = $oldData[$key] ?? null;
                                            $newValue = $newData[$key] ?? null;
                                            $isChanged = !is_null($newValue) && $oldValue !== $newValue;
                                        @endphp
                                        <tr class="{{ $isChanged ? 'bg-yellow-50' : '' }}">
                                            <td class="font-medium">{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                                            <td>
                                                @if (is_array($oldValue) || is_object($oldValue))
                                                    <pre class="p-2 text-sm bg-gray-50 rounded border-l-4 border-gray-400">{{ json_encode($oldValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                @elseif (is_bool($oldValue))
                                                    <span
                                                        class="badge {{ $oldValue ? 'badge-success' : 'badge-secondary' }}">
                                                        {{ $oldValue ? 'Ya' : 'Tidak' }}
                                                    </span>
                                                @elseif (is_null($oldValue))
                                                    <span class="italic text-gray-400">Kosong</span>
                                                @else
                                                    <span
                                                        class="{{ $isChanged ? 'text-red-600 line-through' : '' }}">{{ $oldValue }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if (is_array($newValue) || is_object($newValue))
                                                    <pre class="p-2 text-sm bg-green-50 rounded border-l-4 border-green-400">{{ json_encode($newValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                @elseif (is_bool($newValue))
                                                    <span
                                                        class="badge {{ $newValue ? 'badge-success' : 'badge-secondary' }}">
                                                        {{ $newValue ? 'Ya' : 'Tidak' }}
                                                    </span>
                                                @elseif (is_null($newValue))
                                                    <span
                                                        class="{{ $isChanged ? 'text-green-600 font-medium' : '' }}">{{ is_array($oldValue) ? json_encode($oldValue) : $oldValue }}</span>
                                                @else
                                                    <span
                                                        class="{{ $isChanged ? 'text-green-600 font-medium' : '' }}">{{ $newValue }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($isChanged)
                                                    <span class="badge badge-warning">Berubah</span>
                                                @else
                                                    <span class="badge badge-secondary">Sama</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if ($approvalRequest->status === 'pending' && auth()->user()->can('corsec.authorize'))
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Tindakan</h3>
                </div>
                <div class="card-body">
                    <div class="grid gap-4">
                        <form id="approval-reject-form" method="POST"
                            action="{{ route('approval.reject', $approvalRequest) }}" class="grid gap-3">
                            @csrf
                            <input class="input input-sm w-full" type="text" name="review_notes"
                                placeholder="Catatan penolakan (opsional)">
                        </form>
                        <div class="flex flex-wrap gap-2 justify-end">
                            <button class="btn btn-sm btn-danger" type="submit" form="approval-reject-form">
                                <i class="ki-filled ki-cross"></i> Tolak
                            </button>
                            <form method="POST" action="{{ route('approval.approve', $approvalRequest) }}">
                                @csrf
                                <button class="btn btn-sm btn-success" type="submit">
                                    <i class="ki-filled ki-check"></i> Setujui
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($approvalRequest->reviewer_agent)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">User Agent Reviewer</h3>
                </div>
                <div class="card-body">
                    <p class="font-mono text-xs text-gray-700 break-all">{{ $approvalRequest->reviewer_agent }}</p>
                </div>
            </div>
        @endif
    </div>
@endsection
