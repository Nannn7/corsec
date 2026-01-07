@extends('layouts.main')
@section('breadcrumbs')
    {{ Breadcrumbs::render('dashboard') }}
@endsection
@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1>Welcome to the {{ env('APP_NAME') }} Dashboard</h1>
            </div>
        </div>
    </div>
@endsection
