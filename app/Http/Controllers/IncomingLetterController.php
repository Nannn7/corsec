<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Corsec\Exports\IncomingLetterExport;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\IncomingLetterRoute;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\Attachable;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Comment;
use Modules\Corsec\Services\IncomingLetterWorkflowService;
use Modules\Corsec\Models\Directorate;
use Modules\Corsec\Models\Sender;
use Modules\Corsec\Models\LetterType;
use Modules\Corsec\Notifications\IncomingLetterDirectorateNotification;
use Modules\Usermanagement\Models\User;

class IncomingLetterController extends Controller
{
    public function __construct(
        private readonly IncomingLetterWorkflowService $workflow
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $q = IncomingLetter::query()
            ->with(['targetDirectorate', 'sender', 'letterType'])
            ->latest();

        // filter
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('directorateid')) {
            $q->where('target_directorateid', $request->integer('directorateid'));
        }
        if ($request->filled('keyword')) {
            $kw = $request->string('keyword')->toString();
            $q->where(function ($w) use ($kw) {
                $w->where('subject', 'ilike', "%{$kw}%")
                    ->orWhere('external_letter_no', 'ilike', "%{$kw}%")
                    ->orWhereHas('sender', function ($senderQuery) use ($kw) {
                        $senderQuery->where('name', 'ilike', "%{$kw}%")
                            ->orWhere('code', 'ilike', "%{$kw}%");
                    })
                    ->orWhereHas('letterType', function ($letterTypeQuery) use ($kw) {
                        $letterTypeQuery->where('name', 'ilike', "%{$kw}%")
                            ->orWhere('code', 'ilike', "%{$kw}%");
                    });
            });
        }

        // scope akses: selain admin/corsec, direktorat cuma liat yg ditargetkan ke directoratedia
        $user = Auth::user();
        if (!$user->hasRole('administrator')) {
            // kalau user bukan corsec directorate (asumsi corsec = directoratetertentu -> nanti bisa refine)
            // simple rule: user boleh lihat kalau:
            // - dia creator
            // - atau target directorate= directorateuser
            $q->where(function ($w) use ($user) {
                $w->where('created_by', $user->id)
                    ->orWhere('target_directorateid', $user->directorateid);
            });
        }

        $directorates = Directorate::query()->orderBy('name')->get(['id', 'name']);
        $senders = Sender::query()->orderBy('name')->get(['id', 'name']);
        $letterTypes = LetterType::query()->orderBy('name')->get(['id', 'name']);

        return view('corsec::letter.incoming.index', compact('directorates', 'senders', 'letterTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $directorates = Directorate::query()->orderBy('name')->get(['id', 'name']);
        $senders = Sender::query()->orderBy('name')->get(['id', 'name']);
        $letterTypes = LetterType::query()->orderBy('name')->get(['id', 'name']);
        return view('corsec::letter.incoming.create', compact('directorates', 'senders', 'letterTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'external_letter_no' => ['required', 'string', 'max:255'],
            'letter_date' => ['required', 'date'],
            'subject' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'sender_id' => ['required', 'string'],
            'sender_other' => ['nullable', 'string', 'max:150'],
            'letter_type_id' => ['required', 'exists:corsec_letter_types,id'],
            'received_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'target_directorate_id' => ['required', 'exists:corsec_directorates,id'],
            'target_date' => ['nullable', 'date'],
            'circulation_directorate_ids' => ['required', 'array'],
            'circulation_directorate_ids.*' => ['required', 'exists:corsec_directorates,id'],
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:10240'], // 10MB
        ]);

        $user = auth()->user();
        $submitForApproval = $request->boolean('submit_for_approval', true);
        $circulationDirectorateIds = array_values(array_filter(
            (array) $request->input('circulation_directorate_ids', [])
        ));

        $senderId = $request->input('sender_id');
        $senderName = null;
        if ($senderId === 'other') {
            $request->validate([
                'sender_other' => ['required', 'string', 'max:150'],
            ]);
            $senderName = $request->sender_other;
        } else {
            $request->validate([
                'sender_id' => ['required', Rule::exists('corsec_senders', 'id')],
            ]);
            $senderName = Sender::query()->whereKey($senderId)->value('name');
        }

        if (!in_array((int) $request->target_directorate_id, array_map('intval', $circulationDirectorateIds), true)) {
            throw ValidationException::withMessages([
                'target_directorate_id' => 'Leader tindak lanjut harus termasuk di daftar sirkulasi.',
            ]);
        }

        $letter = DB::transaction(function () use ($request, $user, $circulationDirectorateIds, $senderId, $senderName) {
            $letter = IncomingLetter::create([
                'external_letter_no' => $request->external_letter_no,
                'letter_date' => $request->letter_date,
                'subject' => $request->subject,
                'summary' => $request->summary,
                'sender' => $senderName,
                'sender_id' => $senderId === 'other' ? null : $senderId,
                'sender_other' => $senderId === 'other' ? $request->sender_other : null,
                'letter_type_id' => $request->letter_type_id,
                'received_date' => $request->received_date ?? now()->toDateString(),
                'priority' => $request->priority,
                'description' => $request->description,
                'target_directorate_id' => $request->target_directorate_id,
                'target_date' => $request->target_date,
                'status' => IncomingLetter::STATUS_DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if (!$letter->registration_no) {
                $letter->update([
                    'registration_no' => 'REG-' . now()->format('Ymd') . '-' . str_pad((string) $letter->id, 6, '0', STR_PAD_LEFT),
                ]);
            }

            if (!empty($circulationDirectorateIds)) {
                $letter->circulationDirectorates()->sync($circulationDirectorateIds);
            }

            // upload attachments
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('corsec/incoming', 'public');

                    $att = Attachment::create([
                        'disk' => 'public',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'file_name' => basename($path),
                        'mime' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'created_by' => $user->id,
                    ]);

                    Attachable::create([
                        'attachment_id' => $att->id,
                        'attachable_type' => IncomingLetter::class,
                        'attachable_id' => $letter->id,
                        'category' => 'incoming',
                        'created_by' => $user->id,
                    ]);
                }
            }

            return $letter;
        });

        if ($submitForApproval) {
            $this->workflow->submitToEoCorpAffair($letter, $user);
        }

        if (!empty($circulationDirectorateIds)) {
            $users = User::query()
                ->whereIn('directorate_id', $circulationDirectorateIds)
                ->get();

            if ($users->isNotEmpty()) {
                Notification::send($users, new IncomingLetterDirectorateNotification($letter, $user));
            }
        }

        return redirect()
            ->route('letter.incoming.show', $letter)
            ->with('success', $submitForApproval
                ? 'Surat masuk berhasil dibuat dan diajukan untuk approval.'
                : 'Surat masuk berhasil dibuat.');
    }

    /**
     * Show the specified resource.
     */
    public function show(IncomingLetter $incomingLetter)
    {
        $incomingLetter->load([
            'targetDirectorate',
            'sender',
            'letterType',
            'circulationDirectorates',
            'routes.fromDirectorate',
            'routes.toDirectorate',
            'routes.fromUser',
            'routes.toUser',
            'attachables.attachment',
            'comments.createdBy',
        ]);

        $approvals = Approval::query()
            ->where('approvable_type', IncomingLetter::class)
            ->where('approvable_id', $incomingLetter->id)
            ->with(['actor.directorate'])
            ->latest()
            ->get();

        $directorates = Directorate::query()->orderBy('name')->get(['id', 'name']);

        return view('corsec::letter.incoming.show', compact('incomingLetter', 'approvals', 'directorates'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(IncomingLetter $incomingLetter)
    {
        $directorates = Directorate::query()->orderBy('name')->get(['id', 'name']);
        $senders = Sender::query()->orderBy('name')->get(['id', 'name']);
        $letterTypes = LetterType::query()->orderBy('name')->get(['id', 'name']);
        return view('corsec::letter.incoming.create', compact('incomingLetter', 'directorates', 'senders', 'letterTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, IncomingLetter $incomingLetter)
    {
        $request->validate([
            'external_letter_no' => ['required', 'string', 'max:255'],
            'letter_date' => ['required', 'date'],
            'subject' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'sender_id' => ['required', 'string'],
            'sender_other' => ['nullable', 'string', 'max:150'],
            'letter_type_id' => ['required', 'exists:corsec_letter_types,id'],
            'received_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'target_directorate_id' => ['required', 'exists:corsec_directorates,id'],
            'target_date' => ['nullable', 'date'],
            'circulation_directorate_ids' => ['required', 'array'],
            'circulation_directorate_ids.*' => ['required', 'exists:corsec_directorates,id'],
            'files.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $user = auth()->user();
        $circulationDirectorateIds = array_values(array_filter(
            (array) $request->input('circulation_directorate_ids', [])
        ));

        $senderId = $request->input('sender_id');
        $senderName = null;
        if ($senderId === 'other') {
            $request->validate([
                'sender_other' => ['required', 'string', 'max:150'],
            ]);
            $senderName = $request->sender_other;
        } else {
            $request->validate([
                'sender_id' => ['required', Rule::exists('corsec_senders', 'id')],
            ]);
            $senderName = Sender::query()->whereKey($senderId)->value('name');
        }

        if (!in_array((int) $request->target_directorate_id, array_map('intval', $circulationDirectorateIds), true)) {
            throw ValidationException::withMessages([
                'target_directorate_id' => 'Leader tindak lanjut harus termasuk di daftar sirkulasi.',
            ]);
        }

        DB::transaction(function () use ($request, $incomingLetter, $user, $circulationDirectorateIds, $senderName, $senderId) {
            $incomingLetter->update([
                'external_letter_no' => $request->external_letter_no,
                'letter_date' => $request->letter_date,
                'subject' => $request->subject,
                'summary' => $request->summary,
                'sender' => $senderName,
                'sender_id' => $senderId === 'other' ? null : $senderId,
                'sender_other' => $senderId === 'other' ? $request->sender_other : null,
                'letter_type_id' => $request->letter_type_id,
                'received_date' => $request->received_date,
                'priority' => $request->priority,
                'description' => $request->description,
                'target_directorate_id' => $request->target_directorate_id,
                'target_date' => $request->target_date,
                'updated_by' => $user->id,
            ]);

            $incomingLetter->circulationDirectorates()->sync($circulationDirectorateIds);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('corsec/incoming', 'public');
                    $att = Attachment::create([
                        'disk' => 'public',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'file_name' => basename($path),
                        'mime' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'created_by' => $user->id,
                    ]);

                    Attachable::create([
                        'attachment_id' => $att->id,
                        'attachable_type' => IncomingLetter::class,
                        'attachable_id' => $incomingLetter->id,
                        'category' => 'incoming',
                        'created_by' => $user->id,
                    ]);
                }
            }
        });

        return redirect()
            ->route('letter.incoming.index')
            ->with('success', 'Surat masuk berhasil diupdate.');
    }

    public function datatables(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.read')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to view incoming letters.'
            ], 403);
        }

        try {
            $query = IncomingLetter::query()
                ->with(['targetDirectorate', 'sender', 'letterType', 'circulationDirectorates'])
                ->latest();

            // search (sesuai template: param "search")
            $search = trim((string) $request->get('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('registration_no', 'ilike', "%{$search}%")
                        ->orWhere('subject', 'ilike', "%{$search}%")
                        ->orWhere('summary', 'ilike', "%{$search}%")
                        ->orWhere('external_letter_no', 'ilike', "%{$search}%")
                        ->orWhereHas('sender', function ($senderQuery) use ($search) {
                            $senderQuery->where('name', 'ilike', "%{$search}%")
                                ->orWhere('code', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('letterType', function ($letterTypeQuery) use ($search) {
                            $letterTypeQuery->where('name', 'ilike', "%{$search}%")
                                ->orWhere('code', 'ilike', "%{$search}%");
                        });
                });
            }

            // optional filter kalau nanti mau dipakai dari UI
            if ($request->filled('status')) {
                $query->where('status', $request->string('status')->toString());
            }
            if ($request->filled('directorate_id')) {
                $query->where('target_directorate_id', (int) $request->directorate_id);
            }

            // scope akses (copy dari index lo, biar konsisten)
            if (!$user->hasRole('administrator')) {
                $query->where(function ($w) use ($user) {
                    $w->where('created_by', $user->id)
                        ->orWhere('target_directorate_id', $user->directorate_id);
                });
            }

            // total counts
            $totalRecords = IncomingLetter::count();
            $filteredRecords = (clone $query)->count();

            // sorting (KTDataTable biasanya kirim sortField/sortOrder)
            $sortField = (string) $request->get('sortField', 'created_at');
            $sortOrder = (string) $request->get('sortOrder', 'desc');

            $allowedSort = [
                'external_letter_no',
                'registration_no',
                'letter_date',
                'subject',
                'summary',
                'sender_id',
                'letter_type_id',
                'status',
                'received_date',
                'target_date',
                'created_at',
            ];

            if (!in_array($sortField, $allowedSort, true)) {
                $sortField = 'created_at';
            }
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'], true)) {
                $sortOrder = 'desc';
            }

            $query->orderBy($sortField, $sortOrder);

            // paging (KTDataTable: page & size)
            $page = max((int) $request->get('page', 1), 1);
            $size = max((int) $request->get('size', 10), 1);
            $offset = ($page - 1) * $size;

            $data = $query->skip($offset)->take($size)->get();

            $pageCount = (int) ceil($filteredRecords / $size);

            return response()->json([
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'pageCount' => $pageCount,
                'page' => $page,
                'totalCount' => $totalRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('IncomingLetter datatables error: ' . $e->getMessage(), [
                'user_id' => $user?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data surat masuk.'
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.export')) {
            abort(403, 'Sorry! You are not allowed to export incoming letters.');
        }

        $search = trim((string) $request->get('search', ''));

        return Excel::download(
            new IncomingLetterExport($search, $user),
            'incoming_letters_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function destroy(IncomingLetter $incomingLetter)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete incoming letters.'
            ], 403);
        }

        try {
            // optional: scope akses delete
            if (!$user->hasRole('administrator')) {
                // minimal: hanya creator yg bisa delete
                if ((int)$incomingLetter->created_by !== (int)$user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak punya akses untuk menghapus surat ini.'
                    ], 403);
                }
            }

            DB::transaction(function () use ($incomingLetter, $user) {
                $incomingLetter->update(['deleted_by' => $user->id]);
                $incomingLetter->delete(); // soft delete
            });

            return response()->json([
                'success' => true,
                'message' => 'Surat masuk berhasil dihapus.'
            ]);
        } catch (Exception $e) {
            Log::error('IncomingLetter delete error: ' . $e->getMessage(), [
                'incoming_letter_id' => $incomingLetter->id,
                'user_id' => $user?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus surat masuk.'
            ], 500);
        }
    }

    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete incoming letters.'
            ], 403);
        }

        $ids = $request->input('ids', []);
        if (!is_array($ids) || count($ids) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih minimal satu baris untuk dihapus.'
            ], 400);
        }

        try {
            DB::transaction(function () use ($ids, $user) {
                IncomingLetter::whereIn('id', $ids)->update(['deleted_by' => $user->id]);
                IncomingLetter::whereIn('id', $ids)->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Surat masuk terpilih berhasil dihapus.'
            ]);
        } catch (Exception $e) {
            Log::error('IncomingLetter delete multiple error: ' . $e->getMessage(), [
                'user_id' => $user?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus surat masuk terpilih.'
            ], 500);
        }
    }

    // Staff Corsec submit untuk approval EO Corp Affair
    public function submit(IncomingLetter $incomingLetter)
    {
        $this->workflow->submitToEoCorpAffair($incomingLetter, auth()->user());

        return back()->with('success', 'Surat masuk berhasil disubmit untuk approval.');
    }

    // Staff Corsec set sirkulasi/directorate target
    public function circulate(Request $request, IncomingLetter $incomingLetter)
    {
        $request->validate([
            'to_directorate_id' => ['required', 'exists:directorates,id'],
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->circulateToDirectorate(
            incomingLetter: $incomingLetter,
            actor: auth()->user(),
            toDirectorateId: (int)$request->to_directorate_id,
            note: $request->note
        );

        return back()->with('success', 'Surat masuk berhasil disirkulasi ke direktorat.');
    }

    // Action approve/return untuk EO Corp Affair / EO+DD Direktorat
    public function approvalAction(Request $request, IncomingLetter $incomingLetter)
    {
        $request->validate([
            'action' => ['required', 'in:approve,reject,return'],
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->handleApprovalAction(
            incomingLetter: $incomingLetter,
            actor: auth()->user(),
            action: $request->string('action')->toString(),
            note: $request->note
        );

        return back()->with('success', 'Action approval berhasil diproses.');
    }

    // Staff Direktorat update tindak lanjut + upload bukti
    public function directorateUpdate(Request $request, IncomingLetter $incomingLetter)
    {
        $submitForApproval = $request->boolean('submit_for_approval', true);

        $rules = [
            'target_date' => ['nullable', 'date'],
            'followup_action' => ['required', 'string', 'max:100'],
            'followup_note' => ['nullable', 'string'],
            'followup_meeting_participants' => ['nullable', 'string'],
            'followup_meeting_date' => ['nullable', 'date'],
            'followup_meeting_location' => ['nullable', 'string'],
            'followup_response_target_date' => ['nullable', 'date'],
            'followup_social_participants' => ['nullable', 'string'],
            'followup_social_date' => ['nullable', 'date'],
            'followup_social_location' => ['nullable', 'string'],
            'followup_social_directorate' => ['nullable', 'string'],
            'followup_invitation_positions' => ['nullable', 'string'],
            'followup_review_target_date' => ['nullable', 'date'],
            'submit_for_approval' => ['nullable', 'boolean'],
        ];

        if ($submitForApproval) {
            $rules['evidence_files'] = ['required', 'array', 'min:1'];
            $rules['evidence_files.*'] = ['file', 'max:10240'];
        } else {
            $rules['evidence_files.*'] = ['nullable', 'file', 'max:10240'];
        }

        $request->validate($rules);

        $followupAction = $request->string('followup_action')->toString();
        $followupDetail = match ($followupAction) {
            'meeting' => [
                'participants' => $request->followup_meeting_participants,
                'date' => $request->followup_meeting_date,
                'location' => $request->followup_meeting_location,
            ],
            'response_letter' => [
                'target_date' => $request->followup_response_target_date,
            ],
            'socialization' => [
                'participants' => $request->followup_social_participants,
                'date' => $request->followup_social_date,
                'location' => $request->followup_social_location,
                'coordinated_directorate' => $request->followup_social_directorate,
            ],
            'invitation' => [
                'positions' => $request->followup_invitation_positions,
            ],
            'review' => [
                'target_date' => $request->followup_review_target_date,
            ],
            default => [],
        };

        if ($followupAction === 'meeting' && (!$request->followup_meeting_participants || !$request->followup_meeting_date)) {
            return back()->withErrors(['followup_action' => 'Detail meeting wajib diisi.'])->withInput();
        }
        if ($followupAction === 'response_letter' && !$request->followup_response_target_date) {
            return back()->withErrors(['followup_action' => 'Target tanggal surat jawaban wajib diisi.'])->withInput();
        }
        if ($followupAction === 'socialization' && (!$request->followup_social_participants || !$request->followup_social_date)) {
            return back()->withErrors(['followup_action' => 'Detail sosialisasi wajib diisi.'])->withInput();
        }
        if ($followupAction === 'invitation' && !$request->followup_invitation_positions) {
            return back()->withErrors(['followup_action' => 'Detail peserta undangan wajib diisi.'])->withInput();
        }
        if ($followupAction === 'review' && !$request->followup_review_target_date) {
            return back()->withErrors(['followup_action' => 'Target update sisdur wajib diisi.'])->withInput();
        }

        $this->workflow->directorateUpdate(
            incomingLetter: $incomingLetter,
            actor: auth()->user(),
            targetDate: $request->target_date,
            followupAction: $followupAction,
            followupDetail: $followupDetail,
            followupNote: $request->followup_note,
            evidenceFiles: $request->file('evidence_files', []),
            submitForApproval: $submitForApproval
        );

        return back()->with('success', 'Update tindak lanjut berhasil disimpan.');
    }

    // EO Corp Affair verifikasi selesai
    public function verifyAction(Request $request, IncomingLetter $incomingLetter)
    {
        $request->validate([
            'action' => ['required', 'in:verify,return,approve,reject'],
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->verifyAction(
            incomingLetter: $incomingLetter,
            actor: auth()->user(),
            action: $request->string('action')->toString(),
            note: $request->note
        );

        return back()->with('success', 'Verifikasi berhasil diproses.');
    }

    // Direksi catatan
    public function directorNote(Request $request, IncomingLetter $incomingLetter)
    {
        $request->validate([
            'note' => ['required', 'string'],
        ]);

        Comment::create([
            'commentable_type' => IncomingLetter::class,
            'commentable_id' => $incomingLetter->id,
            'body' => '[CATATAN DIREKSI] ' . $request->note,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Catatan direksi tersimpan.');
    }
}
