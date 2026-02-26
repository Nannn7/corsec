<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Corsec\Exports\WorkplanExport;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Attachable;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\Comment;
use Modules\Corsec\Models\Directorate;
use Modules\Corsec\Models\WorkProgram;
use Modules\Corsec\Models\WorkProgramItem;
use Modules\Corsec\Models\WorkProgramUpdate;
use Modules\Corsec\Services\WorkplanWorkflowService;
use Modules\Usermanagement\Models\User;

class WorkplanController extends Controller
{
    public function __construct(
        private readonly WorkplanWorkflowService $workflow
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizeRead();

        $user = Auth::user();
        $user->loadMissing('directorate');
        $directorates = Directorate::query()->orderBy('name')->get(['id', 'name', 'code']);

        $programSummaryQuery = $this->scopedProgramsQuery($user);
        $itemSummaryQuery = $this->scopedItemsQuery($user);
        $programIds = (clone $programSummaryQuery)->pluck('id');
        $doneOnTarget = (clone $itemSummaryQuery)->where('status', WorkProgramItem::STATUS_DONE_ON_TARGET)->count();
        $doneOverTarget = (clone $itemSummaryQuery)->where('status', WorkProgramItem::STATUS_DONE_OVER_TARGET)->count();
        $totalItems = (clone $itemSummaryQuery)->count();
        $doneItems = $doneOnTarget + $doneOverTarget;

        $summary = [
            'total_programs' => (clone $programSummaryQuery)->count(),
            'total_items' => $totalItems,
            'process_on_target' => (clone $itemSummaryQuery)->where('status', WorkProgramItem::STATUS_PROCESS_ON_TARGET)->count(),
            'done_on_target' => $doneOnTarget,
            'done_over_target' => $doneOverTarget,
            'undone' => (clone $itemSummaryQuery)->where('status', WorkProgramItem::STATUS_UNDONE)->count(),
            'pending_items' => (clone $itemSummaryQuery)
                ->whereNotIn('status', [WorkProgramItem::STATUS_DONE_ON_TARGET, WorkProgramItem::STATUS_DONE_OVER_TARGET])
                ->count(),
            'draft_programs' => (clone $programSummaryQuery)->where('status', WorkProgram::STATUS_DRAFT)->count(),
            'waiting_dir_approval_programs' => (clone $programSummaryQuery)->where('status', WorkProgram::STATUS_WAITING_DIR_APPROVAL)->count(),
            'active_programs' => (clone $programSummaryQuery)->where('status', WorkProgram::STATUS_ACTIVE)->count(),
            'returned_programs' => (clone $programSummaryQuery)->where('status', WorkProgram::STATUS_RETURNED)->count(),
            'done_programs' => (clone $programSummaryQuery)->where('status', WorkProgram::STATUS_DONE)->count(),
            'pending_approvals' => $programIds->isEmpty()
                ? 0
                : Approval::query()
                    ->where('approvable_type', WorkProgram::class)
                    ->whereIn('approvable_id', $programIds)
                    ->where('status', WorkProgramUpdate::STATUS_PENDING)
                    ->count(),
            'completion_rate' => $totalItems > 0
                ? (int) round(($doneItems / $totalItems) * 100)
                : 0,
            'on_target_rate' => $doneItems > 0
                ? (int) round(($doneOnTarget / $doneItems) * 100)
                : 0,
        ];

        $pageInfo = [
            'today' => now(),
            'directorate_name' => $user->directorate?->name ?? '-',
            'is_admin' => $user->hasRole('administrator'),
        ];

        return view('corsec::workplan.index', compact('directorates', 'summary', 'pageInfo'));
    }

    public function datatables(Request $request)
    {
        $this->authorizeRead();

        try {
            $user = Auth::user();
            $query = $this->scopedProgramsQuery($user)->with(['directorate', 'createdBy', 'items']);

            $search = trim((string) $request->get('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ilike', '%' . $search . '%')
                        ->orWhere('description', 'ilike', '%' . $search . '%')
                        ->orWhere('status', 'ilike', '%' . $search . '%')
                        ->orWhereHas('directorate', function ($directorateQuery) use ($search) {
                            $directorateQuery->where('name', 'ilike', '%' . $search . '%')
                                ->orWhere('code', 'ilike', '%' . $search . '%');
                        });
                });
            }

            if ($request->filled('directorate_id')) {
                $query->where('directorate_id', (int) $request->input('directorate_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', (string) $request->input('status'));
            }
            if ($request->filled('year')) {
                $query->where('year', (int) $request->input('year'));
            }

            $filtersParam = $request->get('filters', []);
            $filters = is_array($filtersParam)
                ? $filtersParam
                : json_decode((string) $filtersParam, true);
            if (is_array($filters)) {
                foreach ($filters as $filter) {
                    $column = (string) ($filter['column'] ?? '');
                    $value = $filter['value'] ?? null;
                    if ($column === '' || $value === null || $value === '') {
                        continue;
                    }

                    if (in_array($column, ['directorate_id', 'directorate'], true)) {
                        $query->where('directorate_id', (int) $value);
                    } elseif ($column === 'status') {
                        $query->where('status', (string) $value);
                    } elseif ($column === 'year') {
                        $query->where('year', (int) $value);
                    }
                }
            }

            $totalRecords = $this->scopedProgramsQuery($user)->count();
            $filteredRecords = (clone $query)->count();

            $sortField = (string) $request->get('sortField', 'created_at');
            $sortOrder = (string) $request->get('sortOrder', 'desc');
            $allowedSort = ['created_at', 'year', 'title', 'status'];
            if (!in_array($sortField, $allowedSort, true)) {
                $sortField = 'created_at';
            }
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'], true)) {
                $sortOrder = 'desc';
            }

            $query->orderBy($sortField, $sortOrder);

            $page = max((int) $request->get('page', 1), 1);
            $size = max((int) $request->get('size', 10), 1);
            $offset = ($page - 1) * $size;

            $data = $query->skip($offset)->take($size)->get()->map(function (WorkProgram $program) {
                $totalItems = $program->items->count();
                $doneItems = $program->items
                    ->whereIn('status', [WorkProgramItem::STATUS_DONE_ON_TARGET, WorkProgramItem::STATUS_DONE_OVER_TARGET])
                    ->count();

                return [
                    'id' => $program->id,
                    'uuid' => $program->uuid,
                    'program_no' => $this->programNumber($program),
                    'date' => optional($program->created_at)->toDateString(),
                    'year' => $program->year,
                    'title' => $program->title,
                    'directorate' => $program->directorate ? [
                        'id' => $program->directorate->id,
                        'name' => $program->directorate->name,
                        'code' => $program->directorate->code,
                    ] : null,
                    'status' => $program->status,
                    'authorized_status' => $program->authorized_status,
                    'total_items' => $totalItems,
                    'done_items' => $doneItems,
                    'pending_items' => max($totalItems - $doneItems, 0),
                    'created_at' => $program->created_at,
                    'created_by' => $program->createdBy?->name,
                ];
            });

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
            Log::error('Workplan datatables error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data program kerja.',
            ], 500);
        }
    }

    public function create()
    {
        $this->authorizeCreate();

        $directorates = Directorate::query()->orderBy('name')->get(['id', 'name', 'code']);
        return view('corsec::workplan.create', compact('directorates'));
    }

    public function store(Request $request)
    {
        $this->authorizeCreate();

        $request->validate([
            'directorate_id' => ['nullable', 'exists:corsec_directorates,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.target_date' => ['required', 'date'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.note' => ['nullable', 'string'],
            'items.*.file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx'],
            'submit_for_approval' => ['nullable', 'boolean'],
            'submit_note' => ['nullable', 'string'],
        ]);

        foreach ((array) $request->input('items', []) as $index => $itemData) {
            $itemId = (int) ($itemData['id'] ?? 0);
            if ($itemId <= 0 && !$request->hasFile('items.' . $index . '.file')) {
                throw ValidationException::withMessages([
                    'items.' . $index . '.file' => 'Upload wajib untuk item baru.',
                ]);
            }
        }

        $user = Auth::user();
        $directorateId = $this->resolveDirectorateIdForMutation($request, $user);

        $program = DB::transaction(function () use ($request, $user, $directorateId) {
            $program = WorkProgram::create([
                'directorate_id' => $directorateId,
                'year' => (int) $request->input('year'),
                'title' => (string) $request->input('title'),
                'description' => $request->input('description'),
                'status' => WorkProgram::STATUS_DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ((array) $request->input('items', []) as $index => $itemData) {
                $itemTargetDate = $itemData['target_date'] ?? null;
                $item = WorkProgramItem::create([
                    'work_program_id' => $program->id,
                    'title' => (string) ($itemData['title'] ?? ''),
                    'description' => $itemData['description'] ?? null,
                    'initial_target_date' => $itemTargetDate,
                    'target_date' => $itemTargetDate,
                    'status' => $this->resolveInitialItemStatus($itemTargetDate),
                    'created_by' => $user->id,
                ]);

                $file = $request->file('items.' . $index . '.file');
                if ($file) {
                    $this->storeItemAttachment($item, $file, $user, 'initial_plan');
                }

                $note = trim((string) ($itemData['note'] ?? ''));
                if ($note !== '') {
                    Comment::create([
                        'commentable_type' => WorkProgramItem::class,
                        'commentable_id' => $item->id,
                        'body' => '[CATATAN INPUT] ' . $note,
                        'created_by' => $user->id,
                    ]);
                }
            }

            return $program;
        });

        $submitForApproval = $request->boolean('submit_for_approval', true);
        if ($submitForApproval) {
            $this->workflow->submitProgram($program, $user, $request->input('submit_note'));
        }

        return $this->successRedirectResponse(
            $request,
            route('workplan.show', $program),
            $submitForApproval
                ? 'Program kerja berhasil dibuat dan dikirim untuk approval.'
                : 'Program kerja berhasil dibuat sebagai draft.'
        );
    }

    public function show(WorkProgram $workplan)
    {
        $this->authorizeRead();

        $user = Auth::user();
        if (!$this->canSeeProgram($workplan, $user)) {
            abort(403, 'Anda tidak memiliki akses melihat program kerja ini.');
        }

        $workplan->load([
            'directorate',
            'createdBy',
            'updatedBy',
            'authorizedBy',
            'items.attachables.attachment',
            'items.creator',
            'items.comments.createdBy',
            'items.updates.updater',
            'items.updates.authorizedBy',
            'items.updates.attachables.attachment',
            'items.updates.comments.createdBy',
            'comments.createdBy',
        ]);

        $approvals = Approval::query()
            ->where('approvable_type', WorkProgram::class)
            ->where('approvable_id', $workplan->id)
            ->with(['actor.directorate', 'actor.position'])
            ->orderByDesc('acted_at')
            ->orderByDesc('created_at')
            ->get();

        $pendingApproval = Approval::query()
            ->where('approvable_type', WorkProgram::class)
            ->where('approvable_id', $workplan->id)
            ->where('status', WorkProgramUpdate::STATUS_PENDING)
            ->latest('id')
            ->first();

        $checkerApproved = false;
        $requiresCheckerApproval = true;
        if ($pendingApproval) {
            $pendingNote = Str::lower((string) $pendingApproval->note);
            $requiresCheckerApproval = !Str::startsWith($pendingNote, 'menunggu approval dd direktorat');

            if ($requiresCheckerApproval) {
                $checkerApproved = Approval::query()
                    ->where('approvable_type', WorkProgram::class)
                    ->where('approvable_id', $workplan->id)
                    ->where('status', WorkProgramUpdate::STATUS_APPROVED)
                    ->where('created_at', '>=', $pendingApproval->created_at)
                    ->where('note', 'ilike', 'EO Direktorat Approved%')
                    ->exists();
            }
        }

        $canEdit = $this->canEditProgram($workplan, $user);
        $canDelete = $this->canDeleteProgram($workplan, $user);
        $canSubmit = $canEdit && in_array((string) $workplan->status, [WorkProgram::STATUS_DRAFT, WorkProgram::STATUS_RETURNED], true);
        $canSubmitUpdate = $this->canSubmitUpdate($workplan, $user);
        $canCheckerApproval = $pendingApproval && $requiresCheckerApproval && !$checkerApproved && $this->canCheckerApprove($workplan, $user);
        $canApproverApproval = $pendingApproval && ((!$requiresCheckerApproval) || $checkerApproved) && $this->canApproverApprove($workplan, $user);

        $statusSteps = [
            WorkProgram::STATUS_DRAFT => 'Draft',
            WorkProgram::STATUS_WAITING_DIR_APPROVAL => 'Waiting Dir Approval',
            WorkProgram::STATUS_ACTIVE => 'Active',
            WorkProgram::STATUS_DONE => 'Done',
            WorkProgram::STATUS_RETURNED => 'Returned',
        ];

        return view('corsec::workplan.show', compact(
            'workplan',
            'approvals',
            'statusSteps',
            'canEdit',
            'canDelete',
            'canSubmit',
            'canSubmitUpdate',
            'canCheckerApproval',
            'canApproverApproval'
        ));
    }

    public function edit(WorkProgram $workplan)
    {
        $this->authorizeUpdate();

        $user = Auth::user();
        if (!$this->canEditProgram($workplan, $user)) {
            abort(403, 'Program kerja tidak dapat diubah pada status ini.');
        }

        $workplan->load(['items.attachables.attachment', 'items.comments']);
        $directorates = Directorate::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('corsec::workplan.create', compact('workplan', 'directorates'));
    }

    public function update(Request $request, WorkProgram $workplan)
    {
        $this->authorizeUpdate();

        $user = Auth::user();
        if (!$this->canEditProgram($workplan, $user)) {
            abort(403, 'Program kerja tidak dapat diubah pada status ini.');
        }

        $request->validate([
            'directorate_id' => ['nullable', 'exists:corsec_directorates,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.target_date' => ['required', 'date'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.note' => ['nullable', 'string'],
            'items.*.file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx'],
            'submit_for_approval' => ['nullable', 'boolean'],
            'submit_note' => ['nullable', 'string'],
        ]);

        $directorateId = $this->resolveDirectorateIdForMutation($request, $user);

        DB::transaction(function () use ($request, $workplan, $user, $directorateId) {
            $workplan->update([
                'directorate_id' => $directorateId,
                'year' => (int) $request->input('year'),
                'title' => (string) $request->input('title'),
                'description' => $request->input('description'),
                'updated_by' => $user->id,
            ]);

            $existingItems = WorkProgramItem::query()
                ->where('work_program_id', $workplan->id)
                ->get()
                ->keyBy('id');
            $submittedItemIds = [];

            foreach ((array) $request->input('items', []) as $index => $itemData) {
                $itemId = (int) ($itemData['id'] ?? 0);
                $isExisting = $itemId > 0 && $existingItems->has($itemId);
                $itemTargetDate = $itemData['target_date'] ?? null;

                if ($isExisting) {
                    $item = $existingItems->get($itemId);
                    $payload = [
                        'title' => (string) ($itemData['title'] ?? ''),
                        'description' => $itemData['description'] ?? null,
                        'target_date' => $itemTargetDate,
                        'status' => $this->resolveInitialItemStatus($itemTargetDate),
                    ];
                    if (!$item->initial_target_date) {
                        $payload['initial_target_date'] = $itemTargetDate;
                    }
                    $item->update($payload);
                } else {
                    $item = WorkProgramItem::create([
                        'work_program_id' => $workplan->id,
                        'title' => (string) ($itemData['title'] ?? ''),
                        'description' => $itemData['description'] ?? null,
                        'initial_target_date' => $itemTargetDate,
                        'target_date' => $itemTargetDate,
                        'status' => $this->resolveInitialItemStatus($itemTargetDate),
                        'created_by' => $user->id,
                    ]);
                }

                $submittedItemIds[] = (int) $item->id;

                $file = $request->file('items.' . $index . '.file');
                if ($file) {
                    $this->storeItemAttachment($item, $file, $user, 'initial_plan');
                }

                $note = trim((string) ($itemData['note'] ?? ''));
                if ($note !== '') {
                    Comment::create([
                        'commentable_type' => WorkProgramItem::class,
                        'commentable_id' => $item->id,
                        'body' => '[CATATAN INPUT] ' . $note,
                        'created_by' => $user->id,
                    ]);
                }
            }

            $toDelete = $existingItems->keys()->filter(function ($id) use ($submittedItemIds) {
                return !in_array((int) $id, $submittedItemIds, true);
            });

            foreach ($toDelete as $deleteId) {
                $item = $existingItems->get((int) $deleteId);
                if ($item) {
                    $this->deleteProgramItem($item);
                }
            }
        });

        $submitForApproval = $request->boolean('submit_for_approval', false);
        if ($submitForApproval) {
            $this->workflow->submitProgram($workplan, $user, $request->input('submit_note'));
        }

        return $this->successRedirectResponse(
            $request,
            route('workplan.show', $workplan),
            $submitForApproval
                ? 'Program kerja berhasil diupdate dan dikirim untuk approval.'
                : 'Program kerja berhasil diupdate.'
        );
    }

    public function destroy(WorkProgram $workplan)
    {
        $this->authorizeDelete();

        $user = Auth::user();
        if (!$this->canDeleteProgram($workplan, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Program kerja hanya bisa dihapus oleh pembuat (status Draft/Returned) atau Administrator.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($workplan, $user) {
                $workplan->update(['deleted_by' => $user->id]);
                $workplan->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Program kerja berhasil dihapus.',
            ]);
        } catch (Exception $e) {
            Log::error('Workplan delete error: ' . $e->getMessage(), [
                'work_program_id' => $workplan->id,
                'user_id' => $user?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus program kerja.',
            ], 500);
        }
    }

    public function submit(Request $request, WorkProgram $workplan)
    {
        $this->authorizeUpdate();

        $user = Auth::user();
        if (!$this->canEditProgram($workplan, $user)) {
            abort(403, 'Program kerja tidak dapat disubmit pada status ini.');
        }

        $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->submitProgram($workplan, $user, $request->input('note'));

        return $this->successRedirectResponse($request, route('workplan.show', $workplan), 'Program kerja berhasil dikirim untuk approval.');
    }

    public function approvalAction(Request $request, WorkProgram $workplan)
    {
        $this->authorizeAuthorize();

        $request->validate([
            'action' => ['required', 'in:approve,reject,return'],
            'note' => ['nullable', 'string'],
        ]);

        $successMessage = $this->workflow->handleDirectorateApproval(
            $workplan,
            Auth::user(),
            (string) $request->input('action'),
            $request->input('note')
        );

        return $this->successRedirectResponse($request, route('workplan.show', $workplan), $successMessage);
    }

    public function submitProgress(Request $request, WorkProgram $workplan, WorkProgramItem $item)
    {
        $this->authorizeUpdate();

        $user = Auth::user();
        if ((int) $item->work_program_id !== (int) $workplan->id) {
            abort(404, 'Item program kerja tidak sesuai.');
        }
        if (!$this->canSubmitUpdate($workplan, $user)) {
            abort(403, 'Tidak punya akses untuk update progress program kerja ini.');
        }

        $request->validate([
            'action' => [
                'required',
                'in:' . implode(',', [
                    WorkProgramUpdate::ACTION_PROGRESS,
                    WorkProgramUpdate::ACTION_DONE_ON_TARGET,
                    WorkProgramUpdate::ACTION_DONE_OVER_TARGET,
                    WorkProgramUpdate::ACTION_REVISION,
                ]),
            ],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'revised_target_date' => ['nullable', 'date'],
            'note' => ['required', 'string'],
            'evidence_files' => ['required', 'array', 'min:1'],
            'evidence_files.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx'],
        ]);

        $action = (string) $request->input('action');
        if ($action === WorkProgramUpdate::ACTION_REVISION) {
            $request->validate([
                'revised_target_date' => ['required', 'date'],
            ]);
        }

        $progressPercent = $request->filled('progress_percent') ? (int) $request->input('progress_percent') : null;
        if (in_array($action, [WorkProgramUpdate::ACTION_DONE_ON_TARGET, WorkProgramUpdate::ACTION_DONE_OVER_TARGET], true)) {
            $progressPercent = 100;
        }
        if ($action === WorkProgramUpdate::ACTION_PROGRESS && $progressPercent === null) {
            $progressPercent = 0;
        }

        $this->workflow->submitProgressUpdate(
            $item,
            $user,
            $action,
            $progressPercent,
            (string) $request->input('note'),
            $request->input('revised_target_date'),
            (array) $request->file('evidence_files', [])
        );

        return $this->successRedirectResponse(
            $request,
            route('workplan.show', $workplan),
            'Update program kerja berhasil disubmit untuk approval.'
        );
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.export')) {
            abort(403, 'Sorry! You are not allowed to export work plan.');
        }

        return Excel::download(
            new WorkplanExport(
                $user,
                trim((string) $request->get('search', '')),
                trim((string) $request->get('status', '')),
                (int) $request->get('directorate_id', 0),
                (int) $request->get('year', 0)
            ),
            'workplan_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    private function authorizeRead(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.read')) {
            abort(403, 'Sorry! You are not allowed to access this page.');
        }
    }

    private function authorizeCreate(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.create')) {
            abort(403, 'Sorry! You are not allowed to create work plan.');
        }
    }

    private function authorizeUpdate(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.update')) {
            abort(403, 'Sorry! You are not allowed to update work plan.');
        }
    }

    private function authorizeDelete(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.delete')) {
            abort(403, 'Sorry! You are not allowed to delete work plan.');
        }
    }

    private function authorizeAuthorize(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.authorize')) {
            abort(403, 'Sorry! You are not allowed to authorize work plan.');
        }
    }

    private function canViewAllPrograms(User $user): bool
    {
        return $user->hasRole('administrator') || $user->hasRole('checker') || $user->hasRole('approver');
    }

    private function scopedProgramsQuery(User $user)
    {
        $query = WorkProgram::query();
        if ($this->canViewAllPrograms($user)) {
            return $query;
        }

        $directorateId = $user->directorate_id ?? null;
        return $query->where(function ($w) use ($user, $directorateId) {
            $w->where('created_by', $user->id);
            if ($directorateId) {
                $w->orWhere('directorate_id', $directorateId);
            }
        });
    }

    private function scopedItemsQuery(User $user)
    {
        return WorkProgramItem::query()->whereHas('program', function ($query) use ($user) {
            if ($this->canViewAllPrograms($user)) {
                return;
            }

            $directorateId = $user->directorate_id ?? null;
            $query->where(function ($w) use ($user, $directorateId) {
                $w->where('created_by', $user->id);
                if ($directorateId) {
                    $w->orWhere('directorate_id', $directorateId);
                }
            });
        });
    }

    private function canSeeProgram(WorkProgram $program, User $user): bool
    {
        if ($this->canViewAllPrograms($user)) {
            return true;
        }

        return (int) $program->created_by === (int) $user->id ||
            ((int) ($program->directorate_id ?? 0) === (int) ($user->directorate_id ?? 0));
    }

    private function canEditProgram(WorkProgram $program, User $user): bool
    {
        if (!in_array((string) $program->status, [WorkProgram::STATUS_DRAFT, WorkProgram::STATUS_RETURNED], true)) {
            return false;
        }

        if ($user->hasRole('administrator')) {
            return true;
        }

        return (int) $program->created_by === (int) $user->id;
    }

    private function canDeleteProgram(WorkProgram $program, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if (!in_array((string) $program->status, [WorkProgram::STATUS_DRAFT, WorkProgram::STATUS_RETURNED], true)) {
            return false;
        }

        return (int) $program->created_by === (int) $user->id;
    }

    private function canSubmitUpdate(WorkProgram $program, User $user): bool
    {
        if ((string) $program->status !== WorkProgram::STATUS_ACTIVE) {
            return false;
        }

        if ($user->hasRole('administrator')) {
            return true;
        }

        return (int) ($program->directorate_id ?? 0) === (int) ($user->directorate_id ?? 0);
    }

    private function canCheckerApprove(WorkProgram $program, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        return $user->hasRole('checker') &&
            (int) ($program->directorate_id ?? 0) === (int) ($user->directorate_id ?? 0);
    }

    private function canApproverApprove(WorkProgram $program, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        return $user->hasRole('approver') &&
            $this->isDeputyDirector($user) &&
            (int) ($program->directorate_id ?? 0) === (int) ($user->directorate_id ?? 0);
    }

    private function isDeputyDirector(User $user): bool
    {
        $user->loadMissing('position');
        $positionName = Str::lower(trim((string) ($user->position?->name ?? '')));

        return $positionName !== '' && Str::contains($positionName, 'deputy director');
    }

    private function resolveDirectorateIdForMutation(Request $request, User $user): int
    {
        if ($user->hasRole('administrator')) {
            $directorateId = (int) ($request->input('directorate_id') ?: 0);
            if ($directorateId > 0) {
                return $directorateId;
            }
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        if ($directorateId <= 0) {
            abort(422, 'User belum memiliki direktorat.');
        }

        return $directorateId;
    }

    private function resolveInitialItemStatus(?string $targetDate): string
    {
        if ($targetDate && now()->greaterThan(Carbon::parse($targetDate)->endOfDay())) {
            return WorkProgramItem::STATUS_UNDONE;
        }

        return WorkProgramItem::STATUS_PROCESS_ON_TARGET;
    }

    private function storeItemAttachment(WorkProgramItem $item, $file, User $user, string $category): void
    {
        $path = $file->store('corsec/workplan/initial', 'public');
        $attachment = Attachment::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'created_by' => $user->id,
        ]);

        Attachable::create([
            'attachment_id' => $attachment->id,
            'attachable_type' => WorkProgramItem::class,
            'attachable_id' => $item->id,
            'category' => $category,
            'created_by' => $user->id,
        ]);
    }

    private function deleteProgramItem(WorkProgramItem $item): void
    {
        $updateIds = WorkProgramUpdate::query()
            ->where('work_program_item_id', $item->id)
            ->pluck('id')
            ->all();

        foreach ($updateIds as $updateId) {
            $this->deleteMorphAttachments(WorkProgramUpdate::class, (int) $updateId);
            Comment::query()
                ->where('commentable_type', WorkProgramUpdate::class)
                ->where('commentable_id', (int) $updateId)
                ->delete();
        }
        WorkProgramUpdate::query()->where('work_program_item_id', $item->id)->delete();

        $this->deleteMorphAttachments(WorkProgramItem::class, (int) $item->id);
        Comment::query()
            ->where('commentable_type', WorkProgramItem::class)
            ->where('commentable_id', (int) $item->id)
            ->delete();

        $item->delete();
    }

    private function deleteMorphAttachments(string $morphType, int $morphId): void
    {
        $attachables = Attachable::query()
            ->with('attachment')
            ->where('attachable_type', $morphType)
            ->where('attachable_id', $morphId)
            ->get();

        foreach ($attachables as $attachable) {
            $attachment = $attachable->attachment;
            if ($attachment) {
                try {
                    if ($attachment->path) {
                        Storage::disk($attachment->disk ?? 'public')->delete($attachment->path);
                    }
                } catch (Exception $e) {
                    Log::warning('Failed deleting attachment file', [
                        'attachment_id' => $attachment->id,
                        'path' => $attachment->path,
                        'error' => $e->getMessage(),
                    ]);
                }

                $attachment->delete();
            }

            $attachable->delete();
        }
    }

    private function successRedirectResponse(Request $request, string $redirectUrl, string $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect()->to($redirectUrl)->with('success', $message);
    }

    private function programNumber(WorkProgram $program): string
    {
        $date = $program->created_at ? $program->created_at->format('Ymd') : now()->format('Ymd');
        return 'PK-' . $date . '-' . str_pad((string) $program->id, 6, '0', STR_PAD_LEFT);
    }
}
