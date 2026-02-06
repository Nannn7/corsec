@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render(isset($bank) ? 'bank.edit' : 'bank.create') }}
@endsection

@section('content')
    <div class="grid gap-5 mx-auto w-full lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-filled ki-briefcase text-primary"></i>
                    {{ isset($bank) ? 'Edit Bank' : 'Tambah Bank' }}
                </h3>
                <a href="{{ route('bank.index') }}" class="btn btn-sm btn-info">
                    <i class="ki-filled ki-exit-left"></i> Back
                </a>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ isset($bank) ? route('bank.update', $bank) : route('bank.store') }}">
                    @csrf
                    @if (isset($bank))
                        @method('PUT')
                    @endif

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="flex flex-col">
                            <label class="form-label">Kode Bank <span class="text-danger">*</span></label>
                            <input class="input @error('code') border-danger bg-danger-light @enderror" type="text"
                                name="code" value="{{ old('code', $bank->code ?? ($nextCode ?? '')) }}" maxlength="50" required>
                            @error('code')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Swift Code</label>
                            <input class="input @error('swift_code') border-danger bg-danger-light @enderror" type="text"
                                name="swift_code" value="{{ old('swift_code', $bank->swift_code ?? '') }}" maxlength="50">
                            @error('swift_code')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Nama Bank <span class="text-danger">*</span></label>
                            <input class="input @error('name') border-danger bg-danger-light @enderror" type="text"
                                name="name" value="{{ old('name', $bank->name ?? '') }}" maxlength="150" required>
                            @error('name')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="textarea @error('description') border-danger bg-danger-light @enderror" name="description"
                                rows="4" placeholder="Keterangan tambahan...">{{ old('description', $bank->description ?? '') }}</textarea>
                            @error('description')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Status</label>
                            @php
                                $statusValue = old('status', isset($bank) ? (int) $bank->status : 1);
                            @endphp
                            <select class="select @error('status') border-danger bg-danger-light @enderror" name="status">
                                <option value="1" {{ (string) $statusValue === '1' ? 'selected' : '' }}>Aktif</option>
                                <option value="0" {{ (string) $statusValue === '0' ? 'selected' : '' }}>Non-Aktif</option>
                            </select>
                            @error('status')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end mt-7 gap-2">
                        <a href="{{ route('bank.index') }}" class="btn btn-light">
                            Cancel
                        </a>
                        @can(isset($bank) ? 'bank.update' : 'bank.create')
                            <button type="submit" class="btn btn-primary">
                                <i class="ki-filled ki-check"></i> Save
                            </button>
                        @endcan
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
