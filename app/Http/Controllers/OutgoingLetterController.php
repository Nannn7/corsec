<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Corsec\Exports\LetterExport;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\LetterType;
use Modules\Corsec\Models\OutgoingLetter;
use Modules\Corsec\Models\Sender;
use Modules\Corsec\Services\CorsecPermissionService;
use Modules\Corsec\Services\OutgoingLetterWorkflowService;

class OutgoingLetterController extends Controller
{
    public function __construct(
        private readonly OutgoingLetterWorkflowService $workflow,
        private readonly CorsecPermissionService $permissionService
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->authorizeRead();
        $user = Auth::user();
        $canCreate = $this->permissionService->canCreateOutgoing($user);
        $permissionFlags = $this->permissionService->outgoingIndexFlags($user);

        return view('corsec::letter.outgoing.index', compact('canCreate', 'permissionFlags'));
    }

    public function datatables(Request $request)
    {
        $this->authorizeRead();
        $user = Auth::user();

        $query = OutgoingLetter::query()
            ->select([
                'id',
                'uuid',
                'registration_no',
                'letter_no',
                'order_date',
                'subject',
                'summary',
                'recipient_id',
                'recipient_other',
                'letter_type_id',
                'perihal_type',
                'requester_directorate_id',
                'status',
                'created_at',
                'created_by',
            ]);

        $baseCountQuery = clone $query;
        $this->scopeOutgoingVisibility($query, $user);
        $this->scopeOutgoingVisibility($baseCountQuery, $user);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'ilike', "%{$search}%")
                    ->orWhere('summary', 'ilike', "%{$search}%")
                    ->orWhere('registration_no', 'ilike', "%{$search}%")
                    ->orWhere('letter_no', 'ilike', "%{$search}%")
                    ->orWhere('perihal_text', 'ilike', "%{$search}%")
                    ->orWhere('recipient_other', 'ilike', "%{$search}%")
                    ->orWhereHas('letterType', function ($letterTypeQuery) use ($search) {
                        $letterTypeQuery->where('name', 'ilike', "%{$search}%")
                            ->orWhere('code', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('recipient', function ($recipientQuery) use ($search) {
                        $recipientQuery->where('name', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('requesterDirectorate', function ($directorateQuery) use ($search) {
                        $directorateQuery->where('name', 'ilike', "%{$search}%")
                            ->orWhere('code', 'ilike', "%{$search}%");
                    });
            });
        }

        $isFiltered = $search !== '' || $request->filled('status');
        $totalRecords = $baseCountQuery->count();
        $filteredRecords = $isFiltered ? (clone $query)->count() : $totalRecords;

        $sortField = (string) $request->get('sortField', 'created_at');
        $sortOrder = (string) $request->get('sortOrder', 'desc');
        $allowedSort = ['created_at', 'registration_no', 'order_date', 'status', 'letter_no', 'subject', 'authorized_at'];
        if (!in_array($sortField, $allowedSort, true)) {
            $sortField = 'created_at';
        }
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }

        $query->with([
            'requesterDirectorate:id,code,name',
            'recipient:id,name,code',
            'letterType:id,name,code',
        ]);

        $query->orderBy($sortField, $sortOrder);

        $page = max((int) $request->get('page', 1), 1);
        $size = max((int) $request->get('size', 10), 1);

        $data = $query->forPage($page, $size)->get();
        $pageCount = (int) ceil($filteredRecords / $size);

        return response()->json([
            'draw' => $request->get('draw'),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'pageCount' => $pageCount,
            'page' => $page,
            'totalCount' => $totalRecords,
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.export')) {
            abort(403, 'Sorry! You are not allowed to export outgoing letters.');
        }

        $search = trim((string) $request->get('search', ''));
        $status = trim((string) $request->get('status', ''));

        return Excel::download(
            new LetterExport('outgoing', $search, $user, $status),
            'outgoing_letters_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function destroy(OutgoingLetter $outgoingLetter)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete outgoing letters.'
            ], 403);
        }

        $isAdmin = $user->hasRole('administrator');
        $deletableStatuses = [OutgoingLetter::STATUS_DRAFT, OutgoingLetter::STATUS_RETURNED];

        if (!$isAdmin) {
            if ((int) $outgoingLetter->created_by !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak punya akses untuk menghapus surat ini.'
                ], 403);
            }

            if (!in_array((string) $outgoingLetter->status, $deletableStatuses, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat keluar hanya bisa dihapus pada status Draft atau Returned.'
                ], 422);
            }
        }

        try {
            DB::transaction(function () use ($outgoingLetter, $user) {
                $outgoingLetter->update(['deleted_by' => $user->id]);
                $outgoingLetter->delete(); // soft delete
            });

            return response()->json([
                'success' => true,
                'message' => 'Surat keluar berhasil dihapus.'
            ]);
        } catch (Exception $e) {
            Log::error('OutgoingLetter delete error: ' . $e->getMessage(), [
                'outgoing_letter_id' => $outgoingLetter->id,
                'user_id' => $user?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus surat keluar.'
            ], 500);
        }
    }

    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete outgoing letters.'
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
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $isAdmin = $user->hasRole('administrator');
            $deletableStatuses = [OutgoingLetter::STATUS_DRAFT, OutgoingLetter::STATUS_RETURNED];

            $query = OutgoingLetter::query()->whereIn('id', $ids);
            if (!$isAdmin) {
                $query->where('created_by', $user->id)
                    ->whereIn('status', $deletableStatuses);
            }

            $allowedIds = $query->pluck('id')->all();
            if (count($allowedIds) !== count($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sebagian data tidak bisa dihapus. Pastikan data milik Anda dengan status Draft/Returned.'
                ], 403);
            }

            DB::transaction(function () use ($allowedIds, $user) {
                OutgoingLetter::whereIn('id', $allowedIds)->update(['deleted_by' => $user->id]);
                OutgoingLetter::whereIn('id', $allowedIds)->delete(); // soft delete
            });

            return response()->json([
                'success' => true,
                'message' => 'Surat keluar terpilih berhasil dihapus.'
            ]);
        } catch (Exception $e) {
            Log::error('OutgoingLetter delete multiple error: ' . $e->getMessage(), [
                'user_id' => $user?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus surat keluar terpilih.'
            ], 500);
        }
    }

    public function create(Request $request)
    {
        $this->authorizeCreate();
        $senders = $this->getCachedSenders();
        $letterTypes = $this->getOutgoingLetterTypes();
        $incomingLetters = $this->getIncomingLettersForResponseLetter();
        $prefillIncomingLetterId = null;
        if ($request->filled('incoming_letter_id')) {
            $candidateId = (int) $request->input('incoming_letter_id');
            if ($candidateId > 0) {
                $prefillAllowed = $incomingLetters->contains('id', $candidateId);
                if ($prefillAllowed) {
                    $prefillIncomingLetterId = $candidateId;
                }
            }
        }
        return view('corsec::letter.outgoing.create', compact('senders', 'letterTypes', 'incomingLetters', 'prefillIncomingLetterId'));
    }

    public function registrationPreview(Request $request)
    {
        $this->authorizeCreate();

        $validated = $request->validate([
            'letter_type_id' => [
                'required',
                Rule::exists('corsec_letter_types', 'id')->where(function ($query) {
                    $query->where('scope', LetterType::SCOPE_OUT);
                }),
            ],
            'order_date' => ['nullable', 'date'],
        ]);

        return response()->json([
            'registration_no' => $this->generateRegistrationNoPreview(
                (int) $validated['letter_type_id'],
                $validated['order_date'] ?? null
            ),
        ]);
    }

    public function incomingPreview(Request $request)
    {
        $this->authorizeCreateOrUpdate();

        $validated = $request->validate([
            'incoming_letter_id' => ['required', 'integer', 'exists:corsec_incoming_letters,id'],
        ]);

        $incomingLetter = $this->ensureIncomingLetterIsResponseLetter(
            (int) $validated['incoming_letter_id'],
            'incoming_letter_id'
        );
        $incomingLetter->load([
            'targetDirectorate:id,code,name',
            'circulationDirectorates:id,code,name',
            'sender:id,name',
        ]);

        $recipientId = $incomingLetter->sender_id;
        $recipientOther = null;
        if (!$recipientId) {
            $recipientOther = trim((string) (
                $incomingLetter->sender_other
                ?: $incomingLetter->getAttribute('sender')
                ?: optional($incomingLetter->getRelation('sender'))->name
            ));
            if ($recipientOther === '') {
                $recipientOther = null;
            }
        }

        return response()->json([
            'id' => $incomingLetter->id,
            'registration_no' => $incomingLetter->registration_no,
            'subject' => $incomingLetter->subject,
            'summary' => $incomingLetter->summary,
            'recipient_id' => $recipientId,
            'recipient_other' => $recipientOther,
            'status' => $incomingLetter->status,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeCreate();
        $this->normalizePerihalText($request);
        $submitForApproval = $request->boolean('submit_for_approval', true);

        $request->validate([
            'order_date' => ['required', 'date'],
            'recipient_id' => ['required', 'string'],
            'recipient_other' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'letter_type_id' => [
                'required',
                Rule::exists('corsec_letter_types', 'id')->where(function ($query) {
                    $query->where('scope', LetterType::SCOPE_OUT);
                }),
            ],
            'summary' => ['required', 'string'],
            'need_compliance_review' => ['nullable', 'boolean'],
            'perihal_type' => ['required', 'string', 'max:50'],
            'perihal_incoming_letter_id' => ['nullable', 'exists:corsec_incoming_letters,id'],
            'perihal_text' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'draft_file' => [$submitForApproval ? 'required' : 'nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $user = Auth::user();
        $recipientId = $request->input('recipient_id');

        if ($recipientId === 'other') {
            $request->validate([
                'recipient_other' => ['required', 'string', 'max:150'],
            ]);
        } else {
            $request->validate([
                'recipient_id' => ['required', Rule::exists('corsec_senders', 'id')],
            ]);
        }

        if ($request->perihal_type === 'tanggapan_surat_masuk' && !$request->perihal_incoming_letter_id) {
            throw ValidationException::withMessages([
                'perihal_incoming_letter_id' => 'Pilih surat masuk untuk tanggapan.',
            ]);
        }
        if ($request->perihal_type === 'tanggapan_surat_masuk' && $request->perihal_incoming_letter_id) {
            $incomingLetterId = (int) $request->perihal_incoming_letter_id;
            $this->ensureIncomingLetterIsResponseLetter($incomingLetterId);
            if ($this->hasActiveResponseOutgoingForIncoming($incomingLetterId)) {
                throw ValidationException::withMessages([
                    'perihal_incoming_letter_id' => 'Surat masuk ini sudah memiliki surat jawaban aktif.',
                ]);
            }
        }
        if (in_array($request->perihal_type, ['rutinitas', 'insidentil'], true) && !$request->perihal_text) {
            throw ValidationException::withMessages([
                'perihal_text' => 'Perihal wajib diisi.',
            ]);
        }

        $letter = DB::transaction(function () use ($request, $user, $recipientId) {
            $letter = OutgoingLetter::create([
                'order_date' => $request->order_date,
                'recipient_id' => $recipientId === 'other' ? null : $recipientId,
                'recipient_other' => $recipientId === 'other' ? $request->recipient_other : null,
                'subject' => $request->subject,
                'letter_type_id' => $request->letter_type_id,
                'summary' => $request->summary,
                'perihal_type' => $request->perihal_type,
                'perihal_incoming_letter_id' => $request->perihal_incoming_letter_id,
                'perihal_text' => $request->perihal_text,
                'note' => $request->note,
                'need_compliance_review' => $request->boolean('need_compliance_review'),
                'requester_directorate_id' => $user?->directorate_id,
                'status' => OutgoingLetter::STATUS_DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if (!$letter->registration_no) {
                $this->acquireOutgoingRegistrationLock(
                    (int) $request->letter_type_id,
                    $this->resolveRegistrationDate($request->order_date)->format('Y')
                );
                $generatedNumber = $this->generateRegistrationNoForPersist(
                    $letter->id,
                    (int) $request->letter_type_id,
                    $request->order_date
                );

                $letter->update([
                    'registration_no' => $generatedNumber,
                    'letter_no' => $generatedNumber,
                ]);
            }

            $draft = $request->file('draft_file');
            if ($draft) {
                $path = $draft->store('corsec/outgoing/draft', 'public');
                $attachment = Attachment::create([
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => $draft->getClientOriginalName(),
                    'file_name' => basename($path),
                    'mime' => $draft->getClientMimeType(),
                    'size' => $draft->getSize(),
                    'created_by' => $user->id,
                ]);

                $letter->update(['draft_attachment_id' => $attachment->id]);
            }

            return $letter;
        });

        if ($submitForApproval) {
            $this->workflow->submit($letter, $user);
        }

        return redirect()->route('letter.outgoing.show', $letter)->with('success', 'Surat keluar tersimpan.');
    }

    public function show(OutgoingLetter $outgoingLetter)
    {
        $this->authorizeRead();
        $user = Auth::user();
        if (!$this->canViewOutgoingLetter($outgoingLetter, $user)) {
            abort(403, 'Anda tidak memiliki akses untuk melihat surat ini.');
        }

        $outgoingLetter->load([
            'recipient',
            'letterType',
            'draftAttachment',
            'complianceAttachment',
            'finalAttachment',
            'cancelRequestedBy:id,name',
            'cancelledBy:id,name',
        ]);

        $approvals = Approval::query()
            ->where('approvable_type', OutgoingLetter::class)
            ->where('approvable_id', $outgoingLetter->id)
            ->with(['actor.directorate', 'actor.position'])
            ->orderByDesc('acted_at')
            ->orderByDesc('created_at')
            ->get();

        $sortedComments = $outgoingLetter->comments()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->get();
        $permissionFlags = $this->permissionService->outgoingDetailFlags($outgoingLetter, $approvals, $user);

        return view('corsec::letter.outgoing.show', compact('outgoingLetter', 'approvals', 'permissionFlags', 'sortedComments'));
    }

    public function edit(OutgoingLetter $outgoingLetter)
    {
        $this->authorizeUpdate();
        if (!in_array($outgoingLetter->status, [OutgoingLetter::STATUS_DRAFT, OutgoingLetter::STATUS_RETURNED], true)) {
            abort(403, 'Surat keluar tidak dapat diubah pada status ini.');
        }
        $senders = $this->getCachedSenders();
        $letterTypes = $this->getOutgoingLetterTypes();
        $incomingLetters = $this->getIncomingLettersForResponseLetter($outgoingLetter->perihal_incoming_letter_id);
        return view('corsec::letter.outgoing.create', compact('outgoingLetter', 'senders', 'letterTypes', 'incomingLetters'));
    }

    public function update(Request $request, OutgoingLetter $outgoingLetter)
    {
        $this->authorizeUpdate();
        $this->normalizePerihalText($request);
        if (!in_array($outgoingLetter->status, [OutgoingLetter::STATUS_DRAFT, OutgoingLetter::STATUS_RETURNED], true)) {
            abort(403, 'Surat keluar tidak dapat diubah pada status ini.');
        }

        $request->validate([
            'order_date' => ['required', 'date'],
            'recipient_id' => ['required', 'string'],
            'recipient_other' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'letter_type_id' => [
                'required',
                Rule::exists('corsec_letter_types', 'id')->where(function ($query) {
                    $query->where('scope', LetterType::SCOPE_OUT);
                }),
            ],
            'summary' => ['required', 'string'],
            'need_compliance_review' => ['nullable', 'boolean'],
            'perihal_type' => ['required', 'string', 'max:50'],
            'perihal_incoming_letter_id' => ['nullable', 'exists:corsec_incoming_letters,id'],
            'perihal_text' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'draft_file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $recipientId = $request->input('recipient_id');
        if ($recipientId === 'other') {
            $request->validate([
                'recipient_other' => ['required', 'string', 'max:150'],
            ]);
        }

        if ($request->perihal_type === 'tanggapan_surat_masuk' && !$request->perihal_incoming_letter_id) {
            throw ValidationException::withMessages([
                'perihal_incoming_letter_id' => 'Pilih surat masuk untuk tanggapan.',
            ]);
        }
        if ($request->perihal_type === 'tanggapan_surat_masuk' && $request->perihal_incoming_letter_id) {
            $incomingLetterId = (int) $request->perihal_incoming_letter_id;
            $this->ensureIncomingLetterIsResponseLetter($incomingLetterId);
            if ($this->hasActiveResponseOutgoingForIncoming($incomingLetterId, $outgoingLetter->id)) {
                throw ValidationException::withMessages([
                    'perihal_incoming_letter_id' => 'Surat masuk ini sudah memiliki surat jawaban aktif.',
                ]);
            }
        }
        if (in_array($request->perihal_type, ['rutinitas', 'insidentil'], true) && !$request->perihal_text) {
            throw ValidationException::withMessages([
                'perihal_text' => 'Perihal wajib diisi.',
            ]);
        }

        $user = Auth::user();
        DB::transaction(function () use ($request, $outgoingLetter, $user, $recipientId) {
            $outgoingLetter->update([
                'order_date' => $request->order_date,
                'recipient_id' => $recipientId === 'other' ? null : $recipientId,
                'recipient_other' => $recipientId === 'other' ? $request->recipient_other : null,
                'subject' => $request->subject,
                'letter_type_id' => $request->letter_type_id,
                'summary' => $request->summary,
                'perihal_type' => $request->perihal_type,
                'perihal_incoming_letter_id' => $request->perihal_incoming_letter_id,
                'perihal_text' => $request->perihal_text,
                'note' => $request->note,
                'need_compliance_review' => $request->boolean('need_compliance_review'),
                'updated_by' => $user->id,
            ]);

            if ($request->hasFile('draft_file')) {
                $draft = $request->file('draft_file');
                $path = $draft->store('corsec/outgoing/draft', 'public');
                $attachment = Attachment::create([
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => $draft->getClientOriginalName(),
                    'file_name' => basename($path),
                    'mime' => $draft->getClientMimeType(),
                    'size' => $draft->getSize(),
                    'created_by' => $user->id,
                ]);
                $outgoingLetter->update(['draft_attachment_id' => $attachment->id]);
            }
        });

        return redirect()->route('letter.outgoing.show', $outgoingLetter)->with('success', 'Surat keluar diupdate.');
    }

    public function submit(OutgoingLetter $outgoingLetter)
    {
        $this->authorizeUpdate();
        $this->workflow->submit($outgoingLetter, Auth::user());
        return back()->with('success', 'Surat keluar diajukan untuk approval direktorat.');
    }

    public function cancelRequest(Request $request, OutgoingLetter $outgoingLetter)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'User tidak ditemukan.');
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $this->workflow->requestCancellation($outgoingLetter, $user, (string) $validated['note']);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Permintaan pembatalan berhasil diajukan ke EO Direktorat.',
            ]);
        }

        return back()->with('success', 'Permintaan pembatalan berhasil diajukan ke EO Direktorat.');
    }

    public function cancelApproval(Request $request, OutgoingLetter $outgoingLetter)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'User tidak ditemukan.');
        }

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject,return'],
            'note' => [
                'nullable',
                'string',
                Rule::requiredIf(function () use ($request) {
                    return in_array((string) $request->input('action'), ['reject', 'return'], true);
                }),
            ],
        ]);

        $this->workflow->cancellationApproval(
            $outgoingLetter,
            $user,
            (string) $validated['action'],
            $validated['note'] ?? null
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Approval pembatalan berhasil diproses.',
            ]);
        }

        return back()->with('success', 'Approval pembatalan berhasil diproses.');
    }

    public function approvalAction(Request $request, OutgoingLetter $outgoingLetter)
    {
        $request->validate([
            'action' => ['required', 'in:approve,reject,return'],
            'note' => [
                'nullable',
                'string',
                Rule::requiredIf(function () use ($request) {
                    return in_array((string) $request->input('action'), ['reject', 'return'], true);
                }),
            ],
        ]);

        if (!in_array((string) $outgoingLetter->status, [
            OutgoingLetter::STATUS_WAITING_DIR_APPROVAL,
            OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL,
        ], true)) {
            abort(403, 'Approval hanya tersedia untuk status waiting approval.');
        }

        $user = Auth::user();
        if (!$user) {
            abort(403, 'User tidak ditemukan.');
        }

        $successMessage = $this->workflow->approvalAction($outgoingLetter, $user, (string) $request->string('action'), $request->note);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
            ]);
        }

        return back()->with('success', $successMessage);
    }

    public function complianceReview(Request $request, OutgoingLetter $outgoingLetter)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'User tidak ditemukan.');
        }

        if ($outgoingLetter->status !== OutgoingLetter::STATUS_COMPLIANCE_REVIEW) {
            abort(403, 'Review kepatuhan hanya untuk status compliance review.');
        }

        $request->validate([
            'compliance_file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'note' => ['nullable', 'string'],
        ]);

        $file = $request->file('compliance_file');
        $path = $file->store('corsec/outgoing/compliance', 'public');
        $attachment = Attachment::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'created_by' => $user->id,
        ]);

        $this->workflow->complianceReview($outgoingLetter, $user, $attachment, $request->note);

        return back()->with('success', 'Review kepatuhan disubmit.');
    }

    public function uploadFinal(Request $request, OutgoingLetter $outgoingLetter)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'User tidak ditemukan.');
        }
        if ($outgoingLetter->status !== OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD) {
            abort(403, 'Upload final surat hanya untuk status waiting final upload.');
        }
        if (!$this->permissionService->isRequesterDirectorateMakerStaff($outgoingLetter, $user)) {
            abort(403, 'Upload final surat hanya untuk staff maker dari direktorat terkait.');
        }

        $request->validate([
            'submit_action' => ['nullable', Rule::in(['draft', 'upload'])],
            'final_upload_date' => ['nullable', 'date'],
            'final_file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $submitAction = (string) $request->input('submit_action', 'upload');
        if ($submitAction === 'draft') {
            $request->validate([
                'final_upload_date' => ['required', 'date'],
            ]);

            $outgoingLetter->update([
                'final_upload_date' => $request->input('final_upload_date'),
                'updated_by' => $user->id,
            ]);

            return back()->with('success', 'Draft final upload disimpan.');
        }

        $request->validate([
            'final_file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $file = $request->file('final_file');
        $path = $file->store('corsec/outgoing/final', 'public');
        $attachment = Attachment::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'created_by' => Auth::id(),
        ]);

        $this->workflow->uploadFinal(
            $outgoingLetter,
            $user,
            $attachment,
            $request->input('final_upload_date')
        );
        return back()->with('success', 'Final surat diupload.');
    }

    public function verifyAction(Request $request, OutgoingLetter $outgoingLetter)
    {
        $request->validate([
            'action' => ['required', 'in:verify,return,approve,reject'],
            'note' => [
                'nullable',
                'string',
                Rule::requiredIf(function () use ($request) {
                    return in_array((string) $request->input('action'), ['reject', 'return'], true);
                }),
            ],
        ]);

        if ($outgoingLetter->status !== OutgoingLetter::STATUS_WAITING_VERIFICATION) {
            abort(403, 'Approval Corporate Secretary hanya untuk status waiting verification.');
        }

        $user = Auth::user();
        if (!$user) {
            abort(403, 'User tidak ditemukan.');
        }

        $this->workflow->verifyAction($outgoingLetter, $user, (string) $request->string('action'), $request->note);
        return back()->with('success', 'Verifikasi diproses.');
    }

    private function getOutgoingLetterTypes()
    {
        return Cache::remember('corsec.letter_types.out.list', 300, function () {
            return LetterType::query()
                ->forScope(LetterType::SCOPE_OUT)
                ->orderBy('name')
                ->get(['id', 'name']);
        });
    }

    private function getCachedSenders()
    {
        return Cache::remember('corsec.senders.list', 300, function () {
            return Sender::query()->orderBy('name')->get(['id', 'name']);
        });
    }

    private function getIncomingLettersForResponseLetter(?int $selectedIncomingLetterId = null)
    {
        return IncomingLetter::query()
            ->where(function ($query) use ($selectedIncomingLetterId) {
                $query->where(function ($eligibleQuery) {
                    $eligibleQuery
                        ->where('followup_action', 'response_letter')
                        ->where('status', IncomingLetter::STATUS_WAITING_RESPONSE_LETTER)
                        ->whereDoesntHave('responseOutgoingLetters', function ($outgoingQuery) {
                            $outgoingQuery->where('status', '!=', OutgoingLetter::STATUS_CANCELLED);
                        });
                });
                if ($selectedIncomingLetterId) {
                    $query->orWhere('id', $selectedIncomingLetterId);
                }
            })
            ->orderByDesc('id')
            ->get(['id', 'external_letter_no', 'registration_no', 'subject']);
    }

    private function ensureIncomingLetterIsResponseLetter(int $incomingLetterId, string $field = 'perihal_incoming_letter_id'): IncomingLetter
    {
        $incomingLetter = IncomingLetter::query()->find($incomingLetterId);
        if (!$incomingLetter) {
            throw ValidationException::withMessages([
                $field => 'Surat masuk tidak ditemukan.',
            ]);
        }

        if ($incomingLetter->followup_action !== 'response_letter') {
            throw ValidationException::withMessages([
                $field => 'Surat masuk hanya bisa dipilih jika tindak lanjutnya Surat Jawaban.',
            ]);
        }

        if ($incomingLetter->status !== IncomingLetter::STATUS_WAITING_RESPONSE_LETTER) {
            throw ValidationException::withMessages([
                $field => 'Surat masuk belum siap diproses melalui Surat Keluar.',
            ]);
        }

        return $incomingLetter;
    }

    private function hasActiveResponseOutgoingForIncoming(int $incomingLetterId, ?int $ignoreOutgoingLetterId = null): bool
    {
        $query = OutgoingLetter::query()
            ->where('perihal_type', 'tanggapan_surat_masuk')
            ->where('perihal_incoming_letter_id', $incomingLetterId)
            ->where('status', '!=', OutgoingLetter::STATUS_CANCELLED);

        if ($ignoreOutgoingLetterId) {
            $query->where('id', '!=', $ignoreOutgoingLetterId);
        }

        return $query->exists();
    }

    private function normalizePerihalText(Request $request): void
    {
        $perihalType = (string) $request->input('perihal_type', '');
        $perihalText = trim((string) $request->input('perihal_text', ''));

        if ($perihalText !== '') {
            $request->merge(['perihal_text' => $perihalText]);
            return;
        }

        if ($perihalType === 'rutinitas') {
            $perihalText = trim((string) $request->input('perihal_text_rutinitas', ''));
        } elseif ($perihalType === 'insidentil') {
            $perihalText = trim((string) $request->input('perihal_text_insidentil', ''));
        } else {
            $perihalText = '';
        }

        $request->merge(['perihal_text' => $perihalText]);
    }

    private function generateRegistrationNoPreview(int $letterTypeId, ?string $orderDate): string
    {
        $nextId = ((int) OutgoingLetter::withTrashed()->max('id')) + 1;

        return $this->generateRegistrationNoForPersist($nextId, $letterTypeId, $orderDate);
    }

    private function generateRegistrationNoForPersist(int $id, int $letterTypeId, ?string $orderDate): string
    {
        $orderDateContext = $this->resolveRegistrationDate($orderDate);
        $template = $this->resolveOutgoingRegistrationTemplate($letterTypeId);

        if (($template['layout'] ?? 'legacy') === 'legacy') {
            $datePart = $orderDateContext->format('Ymd');
            $letterTypeCode = $this->resolveLetterTypeCode($letterTypeId);

            return 'OUT-' . $letterTypeCode . '-' . $datePart . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        }

        $year = $orderDateContext->format('Y');
        $romanMonth = $this->toRomanMonth((int) $orderDateContext->format('n'));
        $sequence = $this->nextRegistrationSequence($letterTypeId, $template, $year);
        $registrationNo = $this->formatRegistrationNoFromTemplate($sequence, $template, $romanMonth, $year);

        while (OutgoingLetter::withTrashed()->where('registration_no', $registrationNo)->exists()) {
            $sequence++;
            $registrationNo = $this->formatRegistrationNoFromTemplate($sequence, $template, $romanMonth, $year);
        }

        return $registrationNo;
    }

    private function resolveLetterTypeCode(int $letterTypeId): string
    {
        $code = LetterType::query()->whereKey($letterTypeId)->value('code');
        $normalized = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));

        return $normalized !== '' ? $normalized : 'GEN';
    }

    private function resolveRegistrationDate(?string $orderDate): Carbon
    {
        if ($orderDate) {
            try {
                return Carbon::parse($orderDate);
            } catch (Exception) {
                // fallback to now()
            }
        }

        return now();
    }

    private function resolveOutgoingRegistrationTemplate(int $letterTypeId): array
    {
        $letterType = LetterType::query()
            ->select(['id', 'code', 'name'])
            ->find($letterTypeId);

        if (!$letterType) {
            return ['layout' => 'legacy'];
        }

        $nameKey = $this->normalizeLetterTypeName((string) $letterType->name);
        $codeKey = trim((string) $letterType->code);

        $templatesByName = [
            'SURAT KUASA' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'KUASA', 'unit' => 'DIRUT', 'pad' => 3],
            'PKS' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'PKS', 'unit' => 'DIRUT', 'pad' => 3],
            'NDA' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'NDA', 'unit' => 'DIRUT', 'pad' => 3],
            'SURAT KELUAR DIRUT' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'SK', 'unit' => 'DIRUT', 'pad' => 4],
            'SK DIT CORSEC' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'SK', 'unit' => 'DIT-CORSEC', 'pad' => 4],
            'SK CORP AFFAIRS' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'SK', 'unit' => 'SUBDIT-CORP.AFFAIRS', 'pad' => 4],
            'MI SUBDIT CORP AFFAIRS' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'MI', 'unit' => 'SUBDIT-CORP.AFFAIRS', 'pad' => 4],
            'MI DIT CORSEC' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'MI', 'unit' => 'DIT-CORSEC', 'pad' => 4],
            'MAK SUBDIT CORSEC' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'MAK', 'unit' => 'SUBDIT-CORP.AFFAIRS', 'pad' => 4],
            'KEPUTUSAN DIREKSI' => ['layout' => 'sequence_prefix_month_year', 'prefix' => 'KEP-DIR', 'pad' => 3],
            'MAK DIRUT' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'MAK', 'unit' => 'DIRUT', 'pad' => 4],
        ];

        $templatesByCode = [
            '006' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'KUASA', 'unit' => 'DIRUT', 'pad' => 3],
            '007' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'PKS', 'unit' => 'DIRUT', 'pad' => 3],
            '008' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'NDA', 'unit' => 'DIRUT', 'pad' => 3],
            '001' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'SK', 'unit' => 'DIRUT', 'pad' => 4],
            '002' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'SK', 'unit' => 'DIT-CORSEC', 'pad' => 4],
            '003' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'SK', 'unit' => 'SUBDIT-CORP.AFFAIRS', 'pad' => 4],
            '004' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'MI', 'unit' => 'SUBDIT-CORP.AFFAIRS', 'pad' => 4],
            '005' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'MI', 'unit' => 'DIT-CORSEC', 'pad' => 4],
            '011' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'MAK', 'unit' => 'SUBDIT-CORP.AFFAIRS', 'pad' => 4],
            '009' => ['layout' => 'sequence_prefix_month_year', 'prefix' => 'KEP-DIR', 'pad' => 3],
            '010' => ['layout' => 'prefix_sequence_unit_month_year', 'prefix' => 'MAK', 'unit' => 'DIRUT', 'pad' => 4],
        ];

        if (isset($templatesByName[$nameKey])) {
            return $templatesByName[$nameKey];
        }

        return $templatesByCode[$codeKey] ?? ['layout' => 'legacy'];
    }

    private function normalizeLetterTypeName(string $name): string
    {
        $normalized = Str::upper(trim($name));
        $normalized = preg_replace('/[^A-Z0-9]+/', ' ', $normalized);

        return trim(preg_replace('/\s+/', ' ', (string) $normalized));
    }

    private function toRomanMonth(int $month): string
    {
        $romans = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        return $romans[$month] ?? 'I';
    }

    private function nextRegistrationSequence(int $letterTypeId, array $template, string $year): int
    {
        $numbers = OutgoingLetter::withTrashed()
            ->where('letter_type_id', $letterTypeId)
            ->whereNotNull('registration_no')
            ->where('registration_no', 'like', "%/{$year}")
            ->pluck('registration_no');

        $maxSequence = 0;
        foreach ($numbers as $registrationNo) {
            $sequence = $this->extractSequenceFromRegistrationNo((string) $registrationNo, $template, $year);
            if ($sequence > $maxSequence) {
                $maxSequence = $sequence;
            }
        }

        return $maxSequence + 1;
    }

    private function extractSequenceFromRegistrationNo(string $registrationNo, array $template, string $year): int
    {
        $layout = (string) ($template['layout'] ?? '');
        $prefix = preg_quote((string) ($template['prefix'] ?? ''), '/');

        if ($layout === 'prefix_sequence_unit_month_year') {
            $unit = preg_quote((string) ($template['unit'] ?? ''), '/');
            $pattern = '/^' . $prefix . '\/(\d+)\/' . $unit . '\/[IVXLCDM]+\/' . preg_quote($year, '/') . '$/';
        } elseif ($layout === 'sequence_prefix_month_year') {
            $pattern = '/^(\d+)\/' . $prefix . '\/[IVXLCDM]+\/' . preg_quote($year, '/') . '$/';
        } else {
            return 0;
        }

        if (!preg_match($pattern, $registrationNo, $matches)) {
            return 0;
        }

        return (int) ($matches[1] ?? 0);
    }

    private function formatRegistrationNoFromTemplate(
        int $sequence,
        array $template,
        string $romanMonth,
        string $year
    ): string {
        $layout = (string) ($template['layout'] ?? '');
        $pad = max(1, (int) ($template['pad'] ?? 1));
        $sequencePart = str_pad((string) $sequence, $pad, '0', STR_PAD_LEFT);
        $prefix = (string) ($template['prefix'] ?? '');

        if ($layout === 'prefix_sequence_unit_month_year') {
            $unit = (string) ($template['unit'] ?? '');
            return "{$prefix}/{$sequencePart}/{$unit}/{$romanMonth}/{$year}";
        }

        if ($layout === 'sequence_prefix_month_year') {
            return "{$sequencePart}/{$prefix}/{$romanMonth}/{$year}";
        }

        return $sequencePart;
    }

    public function directorNote(Request $request, OutgoingLetter $outgoingLetter)
    {
        $user = Auth::user();
        if (!$this->permissionService->canAddDirectorNote($user)) {
            abort(403, 'Anda tidak memiliki akses untuk menambahkan catatan.');
        }

        $validated = $request->validate([
            'note' => ['required', 'string'],
        ]);

        \Modules\Corsec\Models\Comment::create([
            'commentable_type' => OutgoingLetter::class,
            'commentable_id' => $outgoingLetter->id,
            'body' => '[KOMENTAR VIEWER] ' . $validated['note'],
            'created_by' => $user?->id,
        ]);

        return back()->with('success', 'Komentar viewer tersimpan.');
    }

    private function acquireOutgoingRegistrationLock(int $letterTypeId, string $year): void
    {
        $lockKey = abs(crc32(sprintf('corsec.outgoing.registration.%d.%s', $letterTypeId, $year)));
        DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
    }

    private function authorizeRead(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.read')) {
            abort(403, 'Sorry! You are not allowed to access this page.');
        }
    }

    private function scopeOutgoingVisibility($query, $user): void
    {
        if (!$user || $this->permissionService->canViewAllCorsec($user)) {
            return;
        }

        $directorateId = (int) ($user->directorate_id ?? $user->directorateid ?? 0);
        $query->where(function ($builder) use ($user, $directorateId) {
            $builder->where('created_by', $user->id);
            if ($directorateId > 0) {
                $builder->orWhere('requester_directorate_id', $directorateId);
            }
        });
    }

    private function canViewOutgoingLetter(OutgoingLetter $outgoingLetter, $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->permissionService->canViewAllCorsec($user)) {
            return true;
        }

        $directorateId = (int) ($user->directorate_id ?? $user->directorateid ?? 0);

        return (int) $outgoingLetter->created_by === (int) $user->id
            || ($directorateId > 0 && (int) $outgoingLetter->requester_directorate_id === $directorateId);
    }

    private function authorizeCreate(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.create')) {
            abort(403, 'Sorry! You are not allowed to create outgoing letters.');
        }
        if ($this->permissionService->isCorpSecretaryDirectorate($user)) {
            abort(403, 'Direktorat Corporate Secretary tidak diperbolehkan membuat surat keluar.');
        }
    }

    private function authorizeUpdate(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.update')) {
            abort(403, 'Sorry! You are not allowed to update outgoing letters.');
        }
        if ($this->permissionService->isViewerRole($user)) {
            abort(403, 'Role viewer tidak memiliki akses untuk update surat keluar.');
        }
    }

    private function authorizeCreateOrUpdate(): void
    {
        $user = Auth::user();
        if (!$user || (!$user->can('corsec.create') && !$user->can('corsec.update'))) {
            abort(403, 'Sorry! You are not allowed to access this action.');
        }
        if ($this->permissionService->isViewerRole($user)) {
            abort(403, 'Role viewer tidak memiliki akses untuk aksi ini.');
        }
    }

}
