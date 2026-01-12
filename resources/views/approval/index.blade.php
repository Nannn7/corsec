@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('approval.index') }}
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Approval</h3>
        </div>

        <div class="card-body">
            <p class="text-gray-600">
                Ini halaman utama module <strong>Approval</strong>.
            </p>
        </div>
    </div>
@endsection
