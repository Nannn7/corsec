<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Corsec\Exports\LetterExport;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\OutgoingLetter;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\Attachable;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Comment;
use Modules\Corsec\Services\IncomingLetterWorkflowService;
use Modules\Corsec\Services\CorsecPermissionService;
use Modules\Corsec\Models\Directorate;
use Modules\Corsec\Models\Sender;
use Modules\Corsec\Models\LetterType;
use Modules\Basicdata\Models\Branch;
use Modules\Corsec\Notifications\CorsecFlowNotification;
use Modules\Corsec\Support\UploadRule;
use Modules\Usermanagement\Models\Position;
use Modules\Usermanagement\Models\User;

class IncomingLetterController extends Controller
{
    public function __construct(
        private readonly IncomingLetterWorkflowService $workflow,
        private readonly CorsecPermissionService $permissionService
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        $directorates = $this->getCachedDirectorates();
        $senders = $this->getCachedSenders();
        $letterTypes = $this->getCachedLetterTypes();
        $permissionFlags = $this->permissionService->incomingIndexFlags($user);

        return view('corsec::letter.incoming.index', compact('directorates', 'senders', 'letterTypes', 'permissionFlags'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $directorates = $this->getCachedDirectorates();
        $senders = $this->getCachedSenders();
        $letterTypes = $this->getCachedLetterTypes();
        $branches = $this->getCachedBranches();
        $customerSenderId = $this->getCustomerSenderId($senders);
        return view('corsec::letter.incoming.create', compact('directorates', 'senders', 'letterTypes', 'branches', 'customerSenderId'));
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
            'customer_branch_id' => ['nullable', 'exists:branches,id'],
            'letter_type_id' => ['required', 'string'],
            'letter_type_other' => ['nullable', 'string', 'max:150'],
            'received_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'target_directorate_id' => ['required', 'exists:corsec_directorates,id'],
            'target_date' => ['nullable', 'date', 'after_or_equal:today'],
            'register_due_date' => ['nullable', 'date'],
            'circulation_directorate_ids' => ['required', 'array'],
            'circulation_directorate_ids.*' => ['required', 'exists:corsec_directorates,id'],
            'files' => ['required', 'array'],
            'files.*' => ['file', UploadRule::maxRule(), 'mimes:pdf,jpg,jpeg,png,xls,xlsx'],
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
        $customerName = Str::lower((string) config('corsec.customer_sender_name', 'Nasabah/Debitur'));
        $isCustomerSender = $senderName && Str::lower((string) $senderName) === $customerName;
        if ($isCustomerSender) {
            $request->validate([
                'customer_branch_id' => ['required', 'exists:branches,id'],
            ]);
        }

        $letterTypeId = $request->input('letter_type_id');
        if ($letterTypeId === 'other') {
            $request->validate([
                'letter_type_other' => ['required', 'string', 'max:150'],
            ]);
        } else {
            $request->validate([
                'letter_type_id' => [
                    'required',
                    Rule::exists('corsec_letter_types', 'id')->where(function ($query) {
                        $query->where(function ($inner) {
                            $inner->where('scope', LetterType::SCOPE_IN)->orWhereNull('scope');
                        });
                    }),
                ],
            ]);
        }

        $isInvitationLetter = $this->isInvitationLetterPayload($request, $letterTypeId);
        if ($isInvitationLetter) {
            $request->validate([
                'register_due_date' => ['required', 'date'],
            ]);
        }

        if (!in_array((int) $request->target_directorate_id, array_map('intval', $circulationDirectorateIds), true)) {
            throw ValidationException::withMessages([
                'target_directorate_id' => 'Leader tindak lanjut harus termasuk di daftar sirkulasi.',
            ]);
        }

        $letter = DB::transaction(function () use ($request, $user, $circulationDirectorateIds, $senderId, $senderName, $isCustomerSender, $letterTypeId, $isInvitationLetter) {
            $letter = IncomingLetter::create([
                'external_letter_no' => $request->external_letter_no,
                'letter_date' => $request->letter_date,
                'subject' => $request->subject,
                'summary' => $request->summary,
                'sender' => $senderName,
                'sender_id' => $senderId === 'other' ? null : $senderId,
                'sender_other' => $senderId === 'other' ? $request->sender_other : null,
                'customer_branch_id' => $isCustomerSender ? $request->customer_branch_id : null,
                'letter_type_id' => $letterTypeId === 'other' ? null : $letterTypeId,
                'letter_type_other' => $letterTypeId === 'other' ? $request->letter_type_other : null,
                'received_date' => $request->received_date ?? now()->toDateString(),
                'priority' => $request->priority,
                'description' => $request->description,
                'target_directorate_id' => $request->target_directorate_id,
                'target_date' => $request->target_date,
                'register_due_date' => $isInvitationLetter ? $request->register_due_date : null,
                'status' => IncomingLetter::STATUS_DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if (!$letter->registration_no) {
                $letter->update([
                    'registration_no' => $this->generateIncomingRegistrationNo(),
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

        if ($submitForApproval && !empty($circulationDirectorateIds)) {
            $this->notifyIncomingDirectorates($circulationDirectorateIds, $letter, $user);
        }

        return redirect()
            ->route('letter.incoming.show', $letter)
            ->with('success', $submitForApproval
                ? 'Surat masuk berhasil dibuat dan disirkulasikan.'
                : 'Surat masuk berhasil dibuat.');
    }

    private function generateIncomingRegistrationNo(): string
    {
        $month = now()->format('m');
        $year = now()->format('Y');
        $nextSequence = $this->nextIncomingRegistrationSequence($month, $year);

        return $this->formatIncomingRegistrationNo($nextSequence, $month, $year);
    }

    private function nextIncomingRegistrationSequence(string $month, string $year): int
    {
        $registrationNos = IncomingLetter::withTrashed()
            ->whereNotNull('registration_no')
            ->where('registration_no', 'like', "%/{$month}/{$year}")
            ->pluck('registration_no');

        $maxSequence = 0;
        foreach ($registrationNos as $registrationNo) {
            if (!is_string($registrationNo)) {
                continue;
            }

            if (preg_match('/^(\d{4})\/(\d{2})\/(\d{4})$/', $registrationNo, $matches) !== 1) {
                continue;
            }

            if ($matches[2] !== $month || $matches[3] !== $year) {
                continue;
            }

            $sequence = (int) $matches[1];
            if ($sequence > $maxSequence) {
                $maxSequence = $sequence;
            }
        }

        return $maxSequence + 1;
    }

    private function formatIncomingRegistrationNo(int $sequence, string $month, string $year): string
    {
        return str_pad((string) $sequence, 4, '0', STR_PAD_LEFT) . '/' . $month . '/' . $year;
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
            'customerBranch',
            'circulationDirectorates',
            'lastRoutedFromDirectorate',
            'lastRoutedToDirectorate',
            'lastRoutedFromUser',
            'lastRoutedToUser',
            'attachables.attachment',
            'corpSecretaryValidatedBy',
        ]);
        $responseOutgoingLetter = $incomingLetter
            ->responseOutgoingLetters()
            ->where('status', '!=', OutgoingLetter::STATUS_CANCELLED)
            ->with(['finalAttachment'])
            ->latest('id')
            ->first();

        $user = Auth::user();
        if ($user && !$this->permissionService->canViewAllCorsec($user)) {
            $directorateId = $user->directorate_id ?? $user->directorateid;
            $isCreator = (int) $incomingLetter->created_by === (int) $user->id;
            $isTargetDirectorate = $directorateId && (int) $incomingLetter->target_directorate_id === (int) $directorateId;
            $isCirculationDirectorate = $directorateId &&
                $incomingLetter->circulationDirectorates?->contains('id', (int) $directorateId);
            $isEoCorpAffairActor = $this->permissionService->isEoCorpAffairActor($user);
            $canEoCorpAffairSee = $isEoCorpAffairActor;

            if (!$isCreator && !$isTargetDirectorate && !$isCirculationDirectorate && !$canEoCorpAffairSee) {
                abort(403, 'Anda tidak memiliki akses untuk melihat surat ini.');
            }
        }

        $approvals = Approval::query()
            ->where('approvable_type', IncomingLetter::class)
            ->where('approvable_id', $incomingLetter->id)
            ->with(['actor.directorate', 'actor.position'])
            ->orderByDesc('acted_at')
            ->orderByDesc('created_at')
            ->get();

        $directorates = $this->getCachedDirectorates();
        $sortedComments = $incomingLetter->comments()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->get();
        $permissionFlags = $this->permissionService->incomingDetailFlags(
            $incomingLetter,
            $approvals,
            $user,
            $responseOutgoingLetter
        );

        return view('corsec::letter.incoming.show', compact('incomingLetter', 'approvals', 'directorates', 'responseOutgoingLetter', 'permissionFlags', 'sortedComments'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(IncomingLetter $incomingLetter)
    {
        $this->authorizeNonViewerUpdate();

        $directorates = $this->getCachedDirectorates();
        $senders = $this->getCachedSenders();
        $letterTypes = $this->getCachedLetterTypes();
        $branches = $this->getCachedBranches();
        $customerSenderId = $this->getCustomerSenderId($senders);
        return view('corsec::letter.incoming.create', compact('incomingLetter', 'directorates', 'senders', 'letterTypes', 'branches', 'customerSenderId'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, IncomingLetter $incomingLetter)
    {
        $this->authorizeNonViewerUpdate();

        $request->validate([
            'external_letter_no' => ['required', 'string', 'max:255'],
            'letter_date' => ['required', 'date'],
            'subject' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'sender_id' => ['required', 'string'],
            'sender_other' => ['nullable', 'string', 'max:150'],
            'customer_branch_id' => ['nullable', 'exists:branches,id'],
            'letter_type_id' => ['required', 'string'],
            'letter_type_other' => ['nullable', 'string', 'max:150'],
            'received_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'target_directorate_id' => ['required', 'exists:corsec_directorates,id'],
            'target_date' => ['nullable', 'date', 'after_or_equal:today'],
            'register_due_date' => ['nullable', 'date'],
            'circulation_directorate_ids' => ['required', 'array'],
            'circulation_directorate_ids.*' => ['required', 'exists:corsec_directorates,id'],
            'files.*' => ['nullable', 'file', UploadRule::maxRule(), 'mimes:pdf,jpg,jpeg,png,xls,xlsx'],
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
        $customerName = Str::lower((string) config('corsec.customer_sender_name', 'Nasabah/Debitur'));
        $isCustomerSender = $senderName && Str::lower((string) $senderName) === $customerName;
        if ($isCustomerSender) {
            $request->validate([
                'customer_branch_id' => ['required', 'exists:branches,id'],
            ]);
        }

        $letterTypeId = $request->input('letter_type_id');
        if ($letterTypeId === 'other') {
            $request->validate([
                'letter_type_other' => ['required', 'string', 'max:150'],
            ]);
        } else {
            $request->validate([
                'letter_type_id' => [
                    'required',
                    Rule::exists('corsec_letter_types', 'id')->where(function ($query) {
                        $query->where(function ($inner) {
                            $inner->where('scope', LetterType::SCOPE_IN)->orWhereNull('scope');
                        });
                    }),
                ],
            ]);
        }

        $isInvitationLetter = $this->isInvitationLetterPayload($request, $letterTypeId, $incomingLetter);
        if ($isInvitationLetter) {
            $request->validate([
                'register_due_date' => ['required', 'date'],
            ]);
        }

        if (!in_array((int) $request->target_directorate_id, array_map('intval', $circulationDirectorateIds), true)) {
            throw ValidationException::withMessages([
                'target_directorate_id' => 'Leader tindak lanjut harus termasuk di daftar sirkulasi.',
            ]);
        }

        DB::transaction(function () use ($request, $incomingLetter, $user, $circulationDirectorateIds, $senderName, $senderId, $isCustomerSender, $letterTypeId, $isInvitationLetter) {
            $incomingLetter->update([
                'external_letter_no' => $request->external_letter_no,
                'letter_date' => $request->letter_date,
                'subject' => $request->subject,
                'summary' => $request->summary,
                'sender' => $senderName,
                'sender_id' => $senderId === 'other' ? null : $senderId,
                'sender_other' => $senderId === 'other' ? $request->sender_other : null,
                'customer_branch_id' => $isCustomerSender ? $request->customer_branch_id : null,
                'letter_type_id' => $letterTypeId === 'other' ? null : $letterTypeId,
                'letter_type_other' => $letterTypeId === 'other' ? $request->letter_type_other : null,
                'received_date' => $request->received_date,
                'priority' => $request->priority,
                'description' => $request->description,
                'target_directorate_id' => $request->target_directorate_id,
                'target_date' => $request->target_date,
                'register_due_date' => $isInvitationLetter ? $request->register_due_date : null,
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

    private function getCachedDirectorates()
    {
        return Directorate::query()->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function getCachedSenders()
    {
        return Sender::query()->orderBy('name')->get(['id', 'name']);
    }

    private function getCachedLetterTypes()
    {
        return LetterType::query()
            ->forScope(LetterType::SCOPE_IN)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function getCachedBranches()
    {
        return Branch::query()->orderBy('name')->get(['id', 'code', 'name']);
    }

    private function getCustomerSenderId($senders): ?int
    {
        $targetName = Str::lower((string) config('corsec.customer_sender_name', 'Nasabah/Debitur'));
        if (!$senders) {
            return null;
        }

        $sender = $senders->first(function ($item) use ($targetName) {
            return Str::lower((string) ($item?->name ?? '')) === $targetName;
        });

        return $sender?->id ? (int) $sender->id : null;
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
                ->select([
                    'id',
                    'uuid',
                    'registration_no',
                    'external_letter_no',
                    'letter_date',
                    'subject',
                    'summary',
                    'sender_id',
                    'sender_other',
                    'letter_type_id',
                    'letter_type_other',
                    'target_directorate_id',
                    'status',
                    'corp_secretary_validation_requested_at',
                    'corp_secretary_validated_at',
                    'received_date',
                    'created_at',
                    'created_by',
                ]);

            // scope akses (copy dari index lo, biar konsisten)
            if (!$this->permissionService->canViewAllCorsec($user)) {
                $directorateId = $user->directorate_id ?? $user->directorateid;
                $isEoCorpAffairActor = $this->permissionService->isEoCorpAffairActor($user);
                $query->where(function ($w) use ($user, $directorateId, $isEoCorpAffairActor) {
                    $w->where('created_by', $user->id)
                        ->orWhere('target_directorate_id', $user->directorate_id ?? $user->directorateid);
                    if (!empty($directorateId)) {
                        $w->orWhereHas('circulationDirectorates', function ($circulationQuery) use ($directorateId) {
                            $circulationQuery->where('directorate_id', $directorateId);
                        });
                    }
                    if ($isEoCorpAffairActor) {
                        $w->orWhereNotNull('id');
                    }
                });
            }

            $baseCountQuery = clone $query;

            // optional filter kalau nanti mau dipakai dari UI
            if ($request->filled('status')) {
                $query->where('status', $request->string('status')->toString());
            }
            if ($request->filled('directorate_id')) {
                $query->where('target_directorate_id', (int) $request->directorate_id);
            }

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

            // total counts
            $totalRecords = $baseCountQuery->count();
            $isFiltered = $search !== '' || $request->filled('status') || $request->filled('directorate_id');
            $filteredRecords = $isFiltered ? (clone $query)->count() : $totalRecords;

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

            $query->with([
                'targetDirectorate:id,code,name',
                'sender:id,code,name',
                'letterType:id,code,name',
                'circulationDirectorates:id,code,name',
            ]);

            $query->orderBy($sortField, $sortOrder);

            // paging (KTDataTable: page & size)
            $page = max((int) $request->get('page', 1), 1);
            $size = max((int) $request->get('size', 10), 1);

            $data = $query->forPage($page, $size)->get();

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
            new LetterExport('incoming', $search, $user),
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

        $isAdmin = $user->hasRole('administrator');
        $deletableStatuses = [IncomingLetter::STATUS_DRAFT, IncomingLetter::STATUS_RETURNED];

        try {
            if (!$isAdmin) {
                if ((int) $incomingLetter->created_by !== (int) $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak punya akses untuk menghapus surat ini.'
                    ], 403);
                }

                if (!in_array((string) $incomingLetter->status, $deletableStatuses, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Surat masuk hanya bisa dihapus pada status Draft atau Returned.'
                    ], 422);
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
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $isAdmin = $user->hasRole('administrator');
            $deletableStatuses = [IncomingLetter::STATUS_DRAFT, IncomingLetter::STATUS_RETURNED];

            $query = IncomingLetter::query()->whereIn('id', $ids);
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
                IncomingLetter::whereIn('id', $allowedIds)->update(['deleted_by' => $user->id]);
                IncomingLetter::whereIn('id', $allowedIds)->delete(); // soft delete
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
        $user = auth()->user();
        $this->workflow->submitToEoCorpAffair($incomingLetter, $user);
        $directorateIds = $incomingLetter->circulationDirectorates()->pluck('directorate_id');
        $this->notifyIncomingDirectorates($directorateIds, $incomingLetter, $user);

        return back()->with('success', 'Surat masuk berhasil disirkulasikan.');
    }

    // Staff Corsec set sirkulasi/directorate target
    public function circulate(Request $request, IncomingLetter $incomingLetter)
    {
        $this->authorizeNonViewerUpdate();

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

        $successMessage = $this->workflow->handleApprovalAction(
            incomingLetter: $incomingLetter,
            actor: auth()->user(),
            action: $request->string('action')->toString(),
            note: $request->note
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
            ]);
        }

        return back()->with('success', $successMessage);
    }

    // Staff Direktorat update tindak lanjut + upload bukti
    public function directorateUpdate(Request $request, IncomingLetter $incomingLetter)
    {
        $this->authorizeNonViewerUpdate();

        $submitForApproval = $request->boolean('submit_for_approval', true);
        $followupActionInput = $request->string('followup_action')->toString();

        $rules = [
            'target_date' => ['nullable', 'date'],
            'followup_action' => ['required', 'string', 'max:100'],
            'followup_note' => ['nullable', 'string'],
            'followup_meeting_participants' => ['nullable', 'array'],
            'followup_meeting_participants.*' => ['nullable', 'exists:corsec_directorates,id'],
            'followup_meeting_time' => ['nullable', 'date_format:H:i'],
            'followup_meeting_date' => ['nullable', 'date'],
            'followup_meeting_location' => ['nullable', 'string'],
            'followup_response_target_date' => ['nullable', 'date'],
            'followup_social_material' => ['nullable', 'file', UploadRule::maxRule(), 'mimes:pdf,jpg,jpeg,png'],
            'followup_social_note' => ['nullable', 'string'],
            'followup_social_participants' => ['nullable', 'array'],
            'followup_social_participants.*' => ['nullable', 'exists:corsec_directorates,id'],
            'followup_social_date' => ['nullable', 'date'],
            'followup_social_location' => ['nullable', 'string'],
            'followup_social_directorate' => ['nullable', 'array'],
            'followup_social_directorate.*' => ['nullable', 'exists:corsec_directorates,id'],
            'followup_invitation_participants' => ['nullable', 'array'],
            'followup_invitation_participants.*.nik' => ['nullable', 'string', 'max:50'],
            'followup_invitation_participants.*.name' => ['nullable', 'string', 'max:150'],
            'followup_invitation_participants.*.directorate' => ['nullable', 'string', 'max:150'],
            'followup_invitation_participants.*.position' => ['nullable', 'string', 'max:150'],
            'followup_invitation_participants.*.registration_status' => ['nullable', 'in:sudah,belum'],
            'followup_invitation_participants.*.pic_name' => ['nullable', 'string', 'max:150'],
            'followup_invitation_participants.*.pic_contact' => ['nullable', 'string', 'max:100'],
            'followup_invitation_participants.*.registration_deadline' => ['nullable', 'date'],
            'followup_invitation_participants.*.note' => ['nullable', 'string'],
            'followup_review_regulation_number' => ['nullable', 'string', 'max:150'],
            'followup_review_regulation_title' => ['nullable', 'string', 'max:255'],
            'followup_review_upload_date' => ['nullable', 'date'],
            'followup_review_note' => ['nullable', 'string'],
            'submit_for_approval' => ['nullable', 'boolean'],
        ];

        if ($submitForApproval && $followupActionInput !== 'response_letter') {
            $rules['evidence_files'] = ['required', 'array', 'min:1'];
            $rules['evidence_files.*'] = ['file', UploadRule::maxRule()];
        } else {
            $rules['evidence_files.*'] = ['nullable', 'file', UploadRule::maxRule()];
        }

        $request->validate($rules);

        $followupAction = $request->string('followup_action')->toString();
        $followupDetail = match ($followupAction) {
            'meeting' => [
                'participants' => $request->followup_meeting_participants,
                'date' => $request->followup_meeting_date,
                'time' => $request->followup_meeting_time,
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
                'material' => $request->file('followup_social_material')?->getClientOriginalName()
                    ?? ($incomingLetter->followup_detail['material'] ?? null),
                'note' => $request->followup_social_note,
            ],
            'invitation' => [
                'participants' => $this->normalizeInvitationParticipants($request),
            ],
            'review' => [
                'regulation_number' => $request->followup_review_regulation_number,
                'regulation_title' => $request->followup_review_regulation_title,
                'upload_date' => $request->followup_review_upload_date,
                'note' => $request->followup_review_note,
            ],
            default => [],
        };

        if ($followupAction === 'meeting' && (
            !$request->followup_meeting_participants ||
            count((array) $request->followup_meeting_participants) === 0 ||
            !$request->followup_meeting_date ||
            !$request->followup_meeting_time
        )) {
            return back()->withErrors(['followup_action' => 'Detail meeting wajib diisi.'])->withInput();
        }
        if ($followupAction === 'response_letter' && !$request->followup_response_target_date) {
            return back()->withErrors(['followup_action' => 'Target tanggal surat jawaban wajib diisi.'])->withInput();
        }
        if ($followupAction === 'socialization' && (
            !$request->followup_social_participants ||
            count((array) $request->followup_social_participants) === 0 ||
            !$request->followup_social_date
        )) {
            return back()->withErrors(['followup_action' => 'Detail sosialisasi wajib diisi.'])->withInput();
        }
        if ($followupAction === 'invitation' && count($followupDetail['participants'] ?? []) === 0) {
            return back()->withErrors(['followup_action' => 'Detail peserta undangan wajib diisi.'])->withInput();
        }
        if ($followupAction === 'review' && (!$request->followup_review_regulation_number || !$request->followup_review_regulation_title || !$request->followup_review_upload_date)) {
            return back()->withErrors(['followup_action' => 'Detail review/new ketentuan wajib diisi.'])->withInput();
        }

        $submitResult = $this->workflow->directorateUpdate(
            incomingLetter: $incomingLetter,
            actor: auth()->user(),
            targetDate: $request->target_date,
            followupAction: $followupAction,
            followupDetail: $followupDetail,
            followupNote: $request->followup_note,
            evidenceFiles: $request->file('evidence_files', []),
            socialMaterialFile: $request->file('followup_social_material'),
            submitForApproval: $submitForApproval
        );

        return back()->with('success', (string) ($submitResult['success_message'] ?? 'Update tindak lanjut berhasil disimpan.'));
    }

    public function lookupUserByNik(Request $request)
    {
        $this->authorizeNonViewerUpdate();

        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:50'],
        ]);

        $nik = trim($validated['nik']);
        if ($nik === '') {
            return response()->json([
                'success' => false,
                'message' => 'NIK wajib diisi.',
            ], 422);
        }

        $user = User::query()
            ->with(['directorate', 'position', 'roles'])
            ->where('nik', $nik)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Data user tidak ditemukan.',
            ], 404);
        }

        $position = $user->position;
        if (!$position) {
            $positionIds = $user->roles
                ->pluck('position_id')
                ->filter()
                ->unique()
                ->values();

            if ($positionIds->isNotEmpty()) {
                $position = Position::query()
                    ->whereIn('id', $positionIds)
                    ->orderByDesc('level')
                    ->first();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'nik' => $user->nik,
                'name' => $user->name,
                'directorate_id' => $user->directorate?->id,
                'directorate_name' => $user->directorate?->name,
                'position' => $position?->name,
            ],
        ]);
    }

    // EO Corporate Secretary validasi selesai
    public function verifyAction(Request $request, IncomingLetter $incomingLetter)
    {
        $request->validate([
            'action' => ['required', 'in:validate,verify'],
            'note' => ['required', 'string'],
        ]);

        $this->workflow->verifyAction(
            incomingLetter: $incomingLetter,
            actor: auth()->user(),
            action: $request->string('action')->toString(),
            note: $request->note
        );

        return back()->with('success', 'Validasi Corporate Secretary berhasil diproses.');
    }

    // Direksi catatan
    public function directorNote(Request $request, IncomingLetter $incomingLetter)
    {
        if (!$this->permissionService->canAddDirectorNote(Auth::user())) {
            abort(403, 'Anda tidak memiliki akses untuk menambahkan catatan.');
        }

        $request->validate([
            'note' => ['required', 'string'],
        ]);

        Comment::create([
            'commentable_type' => IncomingLetter::class,
            'commentable_id' => $incomingLetter->id,
            'body' => '[KOMENTAR VIEWER] ' . $request->note,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Komentar viewer tersimpan.');
    }

    public function addMonitoringDirectorates(Request $request, IncomingLetter $incomingLetter)
    {
        $this->authorizeNonViewerUpdate();

        $user = Auth::user();
        $directorateId = $user?->directorate_id ?? $user?->directorateid;
        $isAdmin = $user?->hasRole('administrator');
        $isTargetDirectorate = $user && (int) $incomingLetter->target_directorate_id === (int) $directorateId;
        $isExecutiveOfficer = $this->permissionService->isExecutiveOfficer($user);
        $isSekretariatDireksi = $this->permissionService->isSekretariatDireksi($user);
        $isEoCorpSecretaryChecker =
            $user && $user->hasRole('checker') && $this->permissionService->isCorpSecretaryDirectorate($user) && $isExecutiveOfficer;

        if (!$user || (!$isAdmin && !$isTargetDirectorate && !$isEoCorpSecretaryChecker && !$isSekretariatDireksi)) {
            abort(403, 'Anda tidak memiliki akses untuk menambahkan monitoring.');
        }

        $request->validate([
            'monitoring_directorate_id' => ['required', 'exists:corsec_directorates,id'],
            'monitoring_note' => ['nullable', 'string'],
        ]);

        $newIds = array_values(array_filter([(int) $request->input('monitoring_directorate_id')]));
        if (count($newIds) === 0) {
            return back()->withErrors(['monitoring_directorate_id' => 'Pilih minimal satu direktorat.'])->withInput();
        }

        $incomingLetter->circulationDirectorates()->syncWithoutDetaching($newIds);

        $note = $request->string('monitoring_note')->toString();
        if ($note !== '') {
            Comment::create([
                'commentable_type' => IncomingLetter::class,
                'commentable_id' => $incomingLetter->id,
                'body' => '[MONITORING] ' . $note,
                'created_by' => $user->id,
            ]);
        }

        $this->notifyIncomingDirectorates($newIds, $incomingLetter, $user);

        return back()->with('success', 'Direktorat monitoring berhasil ditambahkan.');
    }

    private function notifyIncomingDirectorates(iterable $directorateIds, IncomingLetter $incomingLetter, User $actor): void
    {
        $ids = collect($directorateIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $targetUserIds = User::query()
            ->whereIn('directorate_id', $ids)
            ->pluck('id');

        CorsecFlowNotification::insertForUsers($targetUserIds, 'incoming_letter_dir_circulation', [
            'title' => 'Surat masuk baru',
            'message' => 'Surat masuk perlu tindak lanjut direktorat.',
            'incoming_letter_id' => $incomingLetter->id,
            'registration_no' => $incomingLetter->registration_no,
            'subject' => $incomingLetter->subject,
            'sender' => $incomingLetter->sender,
            'status' => $incomingLetter->status,
            'target_directorate_id' => $incomingLetter->target_directorate_id,
            'created_by' => [
                'id' => $actor->id,
                'name' => $actor->name,
            ],
        ]);
    }

    private function isInvitationLetterPayload(Request $request, mixed $letterTypeId, ?IncomingLetter $incomingLetter = null): bool
    {
        $candidates = [
            (string) $request->input('subject', ''),
            (string) $request->input('letter_type_other', ''),
        ];

        if ($letterTypeId !== 'other' && $letterTypeId) {
            $candidates[] = (string) LetterType::query()->whereKey($letterTypeId)->value('name');
        }

        if ($incomingLetter) {
            $incomingLetter->loadMissing('letterType');
            $candidates[] = (string) ($incomingLetter->subject ?? '');
            $candidates[] = (string) ($incomingLetter->letterType?->name ?? '');
            $candidates[] = (string) ($incomingLetter->letter_type_other ?? '');
        }

        foreach ($candidates as $candidate) {
            $normalized = Str::lower(trim($candidate));
            if ($normalized !== '' && Str::contains($normalized, 'undangan')) {
                return true;
            }
        }

        return false;
    }

    private function normalizeInvitationParticipants(Request $request): array
    {
        $rows = collect((array) $request->input('followup_invitation_participants', []))
            ->map(function ($row) {
                $row = is_array($row) ? $row : [];

                return [
                    'nik' => trim((string) ($row['nik'] ?? '')),
                    'name' => trim((string) ($row['name'] ?? '')),
                    'directorate' => trim((string) ($row['directorate'] ?? '')),
                    'position' => trim((string) ($row['position'] ?? '')),
                    'registration_status' => trim((string) ($row['registration_status'] ?? 'belum')),
                    'pic_name' => trim((string) ($row['pic_name'] ?? '')),
                    'pic_contact' => trim((string) ($row['pic_contact'] ?? '')),
                    'registration_deadline' => trim((string) ($row['registration_deadline'] ?? '')),
                    'note' => trim((string) ($row['note'] ?? '')),
                ];
            })
            ->filter(function (array $row) {
                return collect($row)
                    ->except(['registration_status'])
                    ->contains(fn($value) => $value !== '');
            })
            ->values();

        foreach ($rows as $index => $row) {
            if ($row['name'] === '') {
                throw ValidationException::withMessages([
                    "followup_invitation_participants.{$index}.name" => 'Nama peserta undangan wajib diisi.',
                ]);
            }

            if ($row['registration_status'] === 'sudah') {
                if ($row['pic_name'] === '') {
                    throw ValidationException::withMessages([
                        "followup_invitation_participants.{$index}.pic_name" => 'Nama PIC wajib diisi jika peserta sudah terdaftar.',
                    ]);
                }

                if ($row['pic_contact'] === '') {
                    throw ValidationException::withMessages([
                        "followup_invitation_participants.{$index}.pic_contact" => 'Nomor contact PIC wajib diisi jika peserta sudah terdaftar.',
                    ]);
                }

                if ($row['registration_deadline'] === '') {
                    throw ValidationException::withMessages([
                        "followup_invitation_participants.{$index}.registration_deadline" => 'Tanggal deadline pendaftaran wajib diisi jika peserta sudah terdaftar.',
                    ]);
                }
            }
        }

        return $rows->all();
    }

    private function authorizeNonViewerUpdate(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.update')) {
            abort(403, 'Sorry! You are not allowed to update incoming letters.');
        }
        if ($this->permissionService->isViewerRole($user)) {
            abort(403, 'Role viewer tidak memiliki akses untuk aksi update ini.');
        }
    }
}
