@extends('layouts.main')
@section('breadcrumbs')
    {{ Breadcrumbs::render('corsec.dashboard') }}
@endsection
@section('content')
    @include('dashboard._overview')
@endsection
