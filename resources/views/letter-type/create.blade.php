@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render(isset($letterType) ? 'letter-type.edit' : 'letter-type.create') }}
@endsection

@section('content')
    <div class="grid gap-5 mx-auto w-full lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ki-filled ki-briefcase text-primary"></i>
                    {{ isset($letterType) ? 'Edit Letter Type' : 'Tambah Letter Type' }}
                </h3>
                <a href="{{ route('letter-type.index') }}" class="btn btn-sm btn-info">
                    <i class="ki-filled ki-exit-left"></i> Back
                </a>
            </div>

            <div class="card-body">
                <form method="POST"
                    action="{{ isset($letterType) ? route('letter-type.update', $letterType->id) : route('letter-type.store') }}">
                    @csrf
                    @if (isset($letterType))
                        @method('PUT')
                    @endif

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div class="flex flex-col">
                            <label class="form-label">Kode Letter Type <span class="text-danger">*</span></label>
                            <input class="input @error('code') border-danger bg-danger-light @enderror" type="text"
                                name="code" value="{{ old('code', $letterType->code ?? ($nextCode ?? '')) }}"
                                maxlength="50" required>
                            @error('code')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Nama Letter Type <span class="text-danger">*</span></label>
                            <input class="input @error('name') border-danger bg-danger-light @enderror" type="text"
                                name="name" value="{{ old('name', $letterType->name ?? '') }}" maxlength="150" required>
                            @error('name')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col md:col-span-2">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="textarea @error('description') border-danger bg-danger-light @enderror" name="description"
                                rows="4" placeholder="Keterangan tambahan...">{{ old('description', $letterType->description ?? '') }}</textarea>
                            @error('description')
                                <em class="mt-1 text-sm alert text-danger">{{ $message }}</em>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="form-label">Status</label>
                            @php
                                $statusValue = old('status', isset($letterType) ? (int) $letterType->status : 1);
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
                        <a href="{{ route('letter-type.index') }}" class="btn btn-light">
                            Cancel
                        </a>
                        @can(isset($letterType) ? 'letter-type.update' : 'letter-type.create')
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
