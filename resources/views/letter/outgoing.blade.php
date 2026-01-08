@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.outgoing.index') }}
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Outgoing Letters</h3>
        </div>

        <div class="card-body">
            <p class="text-gray-600">
                Ini halaman <strong>Outgoing Letters</strong>.
            </p>
        </div>
    </div>
@endsection
