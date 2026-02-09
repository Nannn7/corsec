@extends('layouts.main')
@section('breadcrumbs')
    {{ Breadcrumbs::render('corsec.dashboard') }}
@endsection
@section('content')
    @if (($incomingOpen ?? 0) > 0)
        <div class="card border-l-4 border-amber-400 bg-amber-50">
            <div class="card-body flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-semibold text-amber-700">Incoming Letter belum selesai</div>
                    <div class="text-sm text-gray-700">
                        Ada {{ $incomingOpen }} surat yang belum sampai status verified/rejected/returned.
                    </div>
                </div>
                <a href="{{ route('letter.incoming.index') }}" class="btn btn-sm btn-warning">Lihat</a>
            </div>
        </div>
    @endif
    @if (($outgoingOpen ?? 0) > 0)
        <div class="card border-l-4 border-amber-400 bg-amber-50">
            <div class="card-body flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-semibold text-amber-700">Outgoing Letter belum selesai</div>
                    <div class="text-sm text-gray-700">
                        Ada {{ $outgoingOpen }} surat yang belum final.
                    </div>
                </div>
                <a href="{{ route('letter.outgoing.index') }}" class="btn btn-sm btn-warning">Lihat</a>
            </div>
        </div>
    @endif
    @if (($meetingOpen ?? 0) > 0)
        <div class="card border-l-4 border-amber-400 bg-amber-50">
            <div class="card-body flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-semibold text-amber-700">Meeting belum selesai</div>
                    <div class="text-sm text-gray-700">
                        Ada {{ $meetingOpen }} meeting yang belum final.
                    </div>
                </div>
                <a href="{{ route('meeting.index') }}" class="btn btn-sm btn-warning">Lihat</a>
            </div>
        </div>
    @endif
    @if (($workplanOpen ?? 0) > 0)
        <div class="card border-l-4 border-amber-400 bg-amber-50">
            <div class="card-body flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-semibold text-amber-700">Item Work Plan belum selesai</div>
                    <div class="text-sm text-gray-700">
                        Ada {{ $workplanOpen }} item work plan yang belum selesai.
                    </div>
                </div>
                <a href="{{ route('workplan.index') }}" class="btn btn-sm btn-warning">Lihat</a>
            </div>
        </div>
    @endif

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
