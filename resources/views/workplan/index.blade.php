@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('workplan.index') }}
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Work Plan</h3>
        </div>

        <div class="card-body">
            <p class="text-gray-600">
                Ini halaman utama module <strong>Work Plan</strong>.
            </p>
        </div>
    </div>
@endsection
