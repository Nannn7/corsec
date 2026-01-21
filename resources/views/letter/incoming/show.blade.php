@extends('layouts.main')

@section('breadcrumbs')
    {{ Breadcrumbs::render('letter.incoming.show', $incomingLetter) }}
@endsection

@section('content')
    <div class="grid gap-5 lg:gap-7.5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detail Surat Masuk #{{ $incomingLetter->id }}</h3>
                <div class="flex gap-2">
                    <a href="{{ route('letter.incoming.index') }}" class="btn btn-sm btn-light">
                        <i class="ki-filled ki-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-2 lg:gap-7.5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informasi Surat</h3>
                </div>
                <div class="card-body">
                    <div class="grid gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">No Registrasi:</span>
                            <span class="font-medium">{{ $incomingLetter->registration_no ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Nomor Surat:</span>
                            <span class="font-medium">{{ $incomingLetter->external_letter_no ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Tanggal Surat:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->letter_date ? $incomingLetter->letter_date->format('Y-m-d') : '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Tanggal Terima:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->received_date ? $incomingLetter->received_date->format('Y-m-d') : '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Perihal:</span>
                            <span class="font-medium">{{ $incomingLetter->subject ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Ringkasan:</span>
                            <span class="font-medium">{{ $incomingLetter->summary ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informasi Pengirim & Status</h3>
                </div>
                <div class="card-body">
                    <div class="grid gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Pengirim:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->sender?->name ?? ($incomingLetter->sender_other ?? ($incomingLetter->getAttribute('sender') ?? '-')) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Action Surat:</span>
                            <span class="font-medium">{{ $incomingLetter->letterType?->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Sirkulasi:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->circulationDirectorates?->pluck('name')->implode(', ') ?: '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Leader:</span>
                            <span class="font-medium">{{ $incomingLetter->targetDirectorate?->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Target Date:</span>
                            <span class="font-medium">
                                {{ $incomingLetter->target_date ? $incomingLetter->target_date->format('Y-m-d') : '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Status:</span>
                            <span class="badge badge-light">{{ $incomingLetter->status ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($incomingLetter->description)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Deskripsi</h3>
                </div>
                <div class="card-body">
                    <p class="text-gray-700">{{ $incomingLetter->description }}</p>
                </div>
            </div>
        @endif

        @if ($incomingLetter->followup_action)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Rencana Tindak Lanjut</h3>
                </div>
                <div class="card-body">
                    <div class="grid gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Jenis Tindak Lanjut:</span>
                            <span class="font-medium">
                                @php
                                    $followupLabels = [
                                        'meeting' => 'Meeting Koordinasi',
                                        'response_letter' => 'Surat Jawaban',
                                        'socialization' => 'Sosialisasi',
                                        'invitation' => 'Peserta Undangan',
                                        'review' => 'Review / New Ketentuan',
                                    ];
                                @endphp
                                {{ $followupLabels[$incomingLetter->followup_action] ?? $incomingLetter->followup_action }}
                            </span>
                        </div>
                        @php
                            $followupDetail = $incomingLetter->followup_detail ?? [];
                        @endphp
                        @if ($incomingLetter->followup_action === 'meeting')
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Peserta:</span>
                                <span class="font-medium">{{ $followupDetail['participants'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Tanggal:</span>
                                <span class="font-medium">{{ $followupDetail['date'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Tempat:</span>
                                <span class="font-medium">{{ $followupDetail['location'] ?? '-' }}</span>
                            </div>
                        @elseif ($incomingLetter->followup_action === 'response_letter')
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Target Jawaban:</span>
                                <span class="font-medium">{{ $followupDetail['target_date'] ?? '-' }}</span>
                            </div>
                        @elseif ($incomingLetter->followup_action === 'socialization')
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Peserta:</span>
                                <span class="font-medium">{{ $followupDetail['participants'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Tanggal:</span>
                                <span class="font-medium">{{ $followupDetail['date'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Tempat:</span>
                                <span class="font-medium">{{ $followupDetail['location'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Koordinasi Direktorat:</span>
                                <span class="font-medium">{{ $followupDetail['coordinated_directorate'] ?? '-' }}</span>
                            </div>
                        @elseif ($incomingLetter->followup_action === 'invitation')
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Nama Jabatan:</span>
                                <span class="font-medium">{{ $followupDetail['positions'] ?? '-' }}</span>
                            </div>
                        @elseif ($incomingLetter->followup_action === 'review')
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Target Update SisDur:</span>
                                <span class="font-medium">{{ $followupDetail['target_date'] ?? '-' }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Catatan:</span>
                            <span class="font-medium">{{ $incomingLetter->followup_note ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @can('corsec.update')
            @if (in_array($incomingLetter->status, ['dispatched', 'returned', 'in_progress'], true))
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Input Tindak Lanjut Direktorat</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('letter.incoming.directorate.update', $incomingLetter) }}"
                            enctype="multipart/form-data" class="grid gap-4">
                            @csrf
                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="flex flex-col lg:col-span-2">
                                    <label class="form-label">Tindak Lanjut <span class="text-danger">*</span></label>
                                    <select class="select" name="followup_action" id="followup_action" required>
                                        <option value="">- Pilih Tindak Lanjut -</option>
                                        <option value="meeting" {{ old('followup_action', $incomingLetter->followup_action) === 'meeting' ? 'selected' : '' }}>Meeting Koordinasi</option>
                                        <option value="response_letter" {{ old('followup_action', $incomingLetter->followup_action) === 'response_letter' ? 'selected' : '' }}>Surat Jawaban</option>
                                        <option value="socialization" {{ old('followup_action', $incomingLetter->followup_action) === 'socialization' ? 'selected' : '' }}>Sosialisasi</option>
                                        <option value="invitation" {{ old('followup_action', $incomingLetter->followup_action) === 'invitation' ? 'selected' : '' }}>Peserta Undangan</option>
                                        <option value="review" {{ old('followup_action', $incomingLetter->followup_action) === 'review' ? 'selected' : '' }}>Review / New Ketentuan</option>
                                    </select>
                                </div>

                                <div class="flex flex-col followup-field hidden" data-followup="meeting">
                                    <label class="form-label">Peserta Meeting <span class="text-danger">*</span></label>
                                    <input class="input" type="text" name="followup_meeting_participants"
                                        value="{{ old('followup_meeting_participants', $incomingLetter->followup_detail['participants'] ?? '') }}"
                                        placeholder="Nama peserta...">
                                </div>
                                <div class="flex flex-col followup-field hidden" data-followup="meeting">
                                    <label class="form-label">Tanggal Meeting <span class="text-danger">*</span></label>
                                    <input class="input" type="date" name="followup_meeting_date"
                                        value="{{ old('followup_meeting_date', $incomingLetter->followup_detail['date'] ?? '') }}">
                                </div>
                                <div class="flex flex-col followup-field hidden" data-followup="meeting">
                                    <label class="form-label">Tempat Meeting</label>
                                    <input class="input" type="text" name="followup_meeting_location"
                                        value="{{ old('followup_meeting_location', $incomingLetter->followup_detail['location'] ?? '') }}"
                                        placeholder="Lokasi...">
                                </div>

                                <div class="flex flex-col followup-field hidden" data-followup="response_letter">
                                    <label class="form-label">Target Jawaban <span class="text-danger">*</span></label>
                                    <input class="input" type="date" name="followup_response_target_date"
                                        value="{{ old('followup_response_target_date', $incomingLetter->followup_detail['target_date'] ?? '') }}">
                                </div>

                                <div class="flex flex-col followup-field hidden" data-followup="socialization">
                                    <label class="form-label">Peserta Sosialisasi <span class="text-danger">*</span></label>
                                    <input class="input" type="text" name="followup_social_participants"
                                        value="{{ old('followup_social_participants', $incomingLetter->followup_detail['participants'] ?? '') }}"
                                        placeholder="Nama peserta...">
                                </div>
                                <div class="flex flex-col followup-field hidden" data-followup="socialization">
                                    <label class="form-label">Tanggal Sosialisasi <span class="text-danger">*</span></label>
                                    <input class="input" type="date" name="followup_social_date"
                                        value="{{ old('followup_social_date', $incomingLetter->followup_detail['date'] ?? '') }}">
                                </div>
                                <div class="flex flex-col followup-field hidden" data-followup="socialization">
                                    <label class="form-label">Tempat Sosialisasi</label>
                                    <input class="input" type="text" name="followup_social_location"
                                        value="{{ old('followup_social_location', $incomingLetter->followup_detail['location'] ?? '') }}"
                                        placeholder="Lokasi...">
                                </div>
                                <div class="flex flex-col followup-field hidden" data-followup="socialization">
                                    <label class="form-label">Koordinasi Direktorat</label>
                                    <input class="input" type="text" name="followup_social_directorate"
                                        value="{{ old('followup_social_directorate', $incomingLetter->followup_detail['coordinated_directorate'] ?? '') }}"
                                        placeholder="Direktorat terkait...">
                                </div>

                                <div class="flex flex-col followup-field hidden" data-followup="invitation">
                                    <label class="form-label">Nama Jabatan <span class="text-danger">*</span></label>
                                    <input class="input" type="text" name="followup_invitation_positions"
                                        value="{{ old('followup_invitation_positions', $incomingLetter->followup_detail['positions'] ?? '') }}"
                                        placeholder="Jabatan peserta...">
                                </div>

                                <div class="flex flex-col followup-field hidden" data-followup="review">
                                    <label class="form-label">Target Update SisDur <span class="text-danger">*</span></label>
                                    <input class="input" type="date" name="followup_review_target_date"
                                        value="{{ old('followup_review_target_date', $incomingLetter->followup_detail['target_date'] ?? '') }}">
                                </div>

                                <div class="flex flex-col">
                                    <label class="form-label">Target Date (SLA)</label>
                                    <input class="input" type="date" name="target_date"
                                        value="{{ old('target_date', $incomingLetter->target_date?->format('Y-m-d')) }}">
                                </div>
                            </div>

                            <div class="flex flex-col">
                                <label class="form-label">Catatan</label>
                                <textarea class="textarea w-full" name="followup_note" rows="3"
                                    placeholder="Tambahkan catatan...">{{ old('followup_note', $incomingLetter->followup_note) }}</textarea>
                            </div>

                            <div class="flex flex-col">
                                <label class="form-label">Upload Hasil (PDF/JPG/PNG)</label>
                                <input class="file-input" type="file" name="evidence_files[]" multiple
                                    accept=".pdf,.jpg,.jpeg,.png">
                            </div>

                            <div class="flex justify-end gap-2">
                                <button class="btn btn-light" type="submit" name="submit_for_approval" value="0">
                                    Simpan Draft
                                </button>
                                <button class="btn btn-primary" type="submit" name="submit_for_approval" value="1">
                                    Submit Approval
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan

        @if ($approvals->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Riwayat Approval</h3>
                </div>
                <div class="card-body">
                    <div class="overflow-x-auto">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="min-w-[160px]">Status</th>
                                    <th class="min-w-[220px]">Catatan</th>
                                    <th class="min-w-[160px]">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($approvals as $approval)
                                    <tr>
                                        <td>{{ $approval->status ?? '-' }}</td>
                                        <td>{{ $approval->note ?? '-' }}</td>
                                        <td>{{ $approval->acted_at ? $approval->acted_at->format('Y-m-d H:i:s') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @can('corsec.authorize')
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Approval</h3>
                </div>
                <div class="card-body">
                    @if (in_array($incomingLetter->status, ['on_approval', 'waiting_dir_approval'], true))
                        <form method="POST" action="{{ route('letter.incoming.approval.action', $incomingLetter) }}"
                            class="grid gap-4">
                            @csrf
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan..."></textarea>
                            </div>
                            <div class="flex flex-wrap gap-2 justify-end">
                                <button class="btn btn-sm btn-danger" type="submit" name="action" value="reject">
                                    <i class="ki-filled ki-cross"></i> Reject
                                </button>
                                <button class="btn btn-sm btn-success" type="submit" name="action" value="approve">
                                    <i class="ki-filled ki-check"></i> Approve
                                </button>
                            </div>
                        </form>
                    @elseif ($incomingLetter->status === 'waiting_verification')
                        <form method="POST" action="{{ route('letter.incoming.verify.action', $incomingLetter) }}"
                            class="grid gap-4">
                            @csrf
                            <div class="flex flex-col">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea class="textarea w-full" name="note" rows="3" placeholder="Tambahkan catatan..."></textarea>
                            </div>
                            <div class="flex flex-wrap gap-2 justify-end">
                                <button class="btn btn-sm btn-danger" type="submit" name="action" value="reject">
                                    <i class="ki-filled ki-cross"></i> Reject
                                </button>
                                <button class="btn btn-sm btn-success" type="submit" name="action" value="approve">
                                    <i class="ki-filled ki-check"></i> Approve
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="text-gray-500 text-sm">Belum ada aksi approval untuk status ini.</div>
                    @endif
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const followupSelect = document.getElementById('followup_action');
            const followupFields = document.querySelectorAll('.followup-field');

            function toggleFollowupFields() {
                const selected = followupSelect ? followupSelect.value : '';
                followupFields.forEach((field) => {
                    if (field.dataset.followup === selected) {
                        field.classList.remove('hidden');
                    } else {
                        field.classList.add('hidden');
                    }
                });
            }

            if (followupSelect) {
                toggleFollowupFields();
                followupSelect.addEventListener('change', toggleFollowupFields);
            }
        });
    </script>
@endpush
