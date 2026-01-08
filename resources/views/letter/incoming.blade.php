@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.incoming.index') }}
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Incoming Letters</h3>
        </div>

        <div class="card-body">
            <p class="text-gray-600">
                Ini halaman <strong>Incoming Letters</strong>.
            </p>
        </div>
    </div>
@endsection
