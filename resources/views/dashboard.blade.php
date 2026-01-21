@extends('layouts.main')
@section('breadcrumbs')
    {{ Breadcrumbs::render('corsec.dashboard') }}
@endsection
@section('content')
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Incoming Letter</h3>
            </div>
            <div class="card-body">
                <div class="text-3xl font-semibold text-gray-800">{{ $incomingOpen ?? 0 }}</div>
                <div class="text-gray-500 text-sm">Belum final</div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Outgoing Letter</h3>
            </div>
            <div class="card-body">
                <div class="text-3xl font-semibold text-gray-800">{{ $outgoingOpen ?? 0 }}</div>
                <div class="text-gray-500 text-sm">Belum final</div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Meeting</h3>
            </div>
            <div class="card-body">
                <div class="text-3xl font-semibold text-gray-800">{{ $meetingOpen ?? 0 }}</div>
                <div class="text-gray-500 text-sm">Belum final</div>
            </div>
        </div>
    </div>
@endsection
