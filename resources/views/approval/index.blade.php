@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('approval.index') }}
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Approval Requests</h3>
        </div>

        <div class="card-body">
            @if ($requests->isEmpty())
                <p class="text-gray-600">Belum ada approval request.</p>
            @else
                <div class="scrollable-x-auto">
                    <table class="table text-sm font-medium text-gray-700 align-middle table-auto table-border">
                        <thead>
                            <tr>
                                <th class="min-w-[180px]">Model</th>
                                <th class="min-w-[120px]">Action</th>
                                <th class="min-w-[240px]">Deskripsi</th>
                                <th class="min-w-[120px]">Status</th>
                                <th class="min-w-[260px]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $request)
                                <tr>
                                    <td>{{ class_basename($request->model) }}</td>
                                    <td>
                                        <span
                                            class="badge {{ $request->action_badge }}">{{ ucfirst($request->action) }}</span>
                                    </td>
                                    <td>{{ $request->description ?? '-' }}</td>
                                    <td>
                                        <span
                                            class="badge {{ $request->status_badge }}">{{ ucfirst($request->status) }}</span>
                                    </td>
                                    <td>
                                        @if ($request->status === 'pending')
                                            <div class="flex flex-col gap-2">
                                                <form method="POST" action="{{ route('approval.approve', $request) }}">
                                                    @csrf
                                                    <button class="btn btn-sm btn-primary w-full"
                                                        type="submit">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('approval.reject', $request) }}">
                                                    @csrf
                                                    <input class="input input-sm" type="text" name="review_notes"
                                                        placeholder="Catatan penolakan (optional)">
                                                    <button class="btn btn-sm btn-danger w-full mt-2"
                                                        type="submit">Reject</button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-gray-500">-</span>
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
@endsection
