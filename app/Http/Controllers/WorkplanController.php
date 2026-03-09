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

        $programSummaryQuery = $this->workflow->scopedProgramsQuery($user);
        $itemSummaryQuery = $this->workflow->scopedItemsQuery($user);
        $programSummaryRow = (clone $programSummaryQuery)
            ->selectRaw('COUNT(*) AS total_programs')
            ->selectRaw(
                "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft_programs",
                [WorkProgram::STATUS_DRAFT]
            )
            ->selectRaw(
                "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS waiting_dir_approval_programs",
                [WorkProgram::STATUS_WAITING_DIR_APPROVAL]
            )
            ->selectRaw(
                "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS active_programs",
                [WorkProgram::STATUS_ACTIVE]
            )
            ->selectRaw(
                "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS returned_programs",
                [WorkProgram::STATUS_RETURNED]
            )
            ->selectRaw(
                "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS done_programs",
                [WorkProgram::STATUS_DONE]
            )
            ->first();

        $itemSummaryRow = (clone $itemSummaryQuery)
            ->selectRaw('COUNT(*) AS total_items')
            ->selectRaw(
                "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS process_on_target",
                [WorkProgramItem::STATUS_PROCESS_ON_TARGET]
            )
            ->selectRaw(
                "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS done_on_target",
                [WorkProgramItem::STATUS_DONE_ON_TARGET]
            )
            ->selectRaw(
                "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS done_over_target",
                [WorkProgramItem::STATUS_DONE_OVER_TARGET]
            )
            ->selectRaw(
                "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS undone",
                [WorkProgramItem::STATUS_UNDONE]
            )
            ->selectRaw(
                "SUM(CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END) AS pending_items",
                [WorkProgramItem::STATUS_DONE_ON_TARGET, WorkProgramItem::STATUS_DONE_OVER_TARGET]
            )
            ->first();

        $doneOnTarget = (int) ($itemSummaryRow->done_on_target ?? 0);
        $doneOverTarget = (int) ($itemSummaryRow->done_over_target ?? 0);
        $totalItems = (int) ($itemSummaryRow->total_items ?? 0);
        $doneItems = $doneOnTarget + $doneOverTarget;
        $pendingApprovals = Approval::query()
            ->where('approvable_type', WorkProgram::class)
            ->where('status', WorkProgramUpdate::STATUS_PENDING)
            ->whereIn('approvable_id', (clone $programSummaryQuery)->select('id'))
            ->count();

        $summary = [
            'total_programs' => (int) ($programSummaryRow->total_programs ?? 0),
            'total_items' => $totalItems,
            'process_on_target' => (int) ($itemSummaryRow->process_on_target ?? 0),
            'done_on_target' => $doneOnTarget,
            'done_over_target' => $doneOverTarget,
            'undone' => (int) ($itemSummaryRow->undone ?? 0),
            'pending_items' => (int) ($itemSummaryRow->pending_items ?? 0),
            'draft_programs' => (int) ($programSummaryRow->draft_programs ?? 0),
            'waiting_dir_approval_programs' => (int) ($programSummaryRow->waiting_dir_approval_programs ?? 0),
            'active_programs' => (int) ($programSummaryRow->active_programs ?? 0),
            'returned_programs' => (int) ($programSummaryRow->returned_programs ?? 0),
            'done_programs' => (int) ($programSummaryRow->done_programs ?? 0),
            'pending_approvals' => $pendingApprovals,
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

        $permissionFlags = [
            'is_deputy_director' => $this->workflow->isDeputyDirector($user),
        ];

        return view('corsec::workplan.index', compact('directorates', 'summary', 'pageInfo', 'permissionFlags'));
    }

    public function datatables(Request $request)
    {
        $this->authorizeRead();

        try {
            $user = Auth::user();
            $query = $this->workflow->scopedProgramsQuery($user)
                ->select([
                    'id',
                    'uuid',
                    'directorate_id',
                    'year',
                    'title',
                    'description',
                    'status',
                    'authorized_status',
                    'created_at',
                ]);

            $baseCountQuery = clone $query;

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
            $hasStructuredFilters = false;
            if (is_array($filters)) {
                foreach ($filters as $filter) {
                    $column = (string) ($filter['column'] ?? '');
                    $value = $filter['value'] ?? null;
                    if ($column === '' || $value === null || $value === '') {
                        continue;
                    }

                    if (in_array($column, ['directorate_id', 'directorate'], true)) {
                        $query->where('directorate_id', (int) $value);
                        $hasStructuredFilters = true;
                    } elseif ($column === 'status') {
                        $query->where('status', (string) $value);
                        $hasStructuredFilters = true;
                    } elseif ($column === 'year') {
                        $query->where('year', (int) $value);
                        $hasStructuredFilters = true;
                    }
                }
            }

            $isFiltered = $search !== ''
                || $request->filled('directorate_id')
                || $request->filled('status')
                || $request->filled('year')
                || $hasStructuredFilters;
            $totalRecords = $baseCountQuery->count();
            $filteredRecords = $isFiltered ? (clone $query)->count() : $totalRecords;

            $sortField = (string) $request->get('sortField', 'created_at');
            $sortOrder = (string) $request->get('sortOrder', 'desc');
            $allowedSort = ['created_at', 'year', 'title', 'status'];
            if (!in_array($sortField, $allowedSort, true)) {
                $sortField = 'created_at';
            }
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'], true)) {
                $sortOrder = 'desc';
            }

            $query->with(['directorate:id,code,name'])
                ->withCount([
                    'items',
                    'items as done_items_count' => function ($itemQuery) {
                        $itemQuery->whereIn('status', [
                            WorkProgramItem::STATUS_DONE_ON_TARGET,
                            WorkProgramItem::STATUS_DONE_OVER_TARGET,
                        ]);
                    },
                ]);

            $query->orderBy($sortField, $sortOrder);

            $page = max((int) $request->get('page', 1), 1);
            $size = max((int) $request->get('size', 10), 1);

            $data = $query->forPage($page, $size)->get()->map(function (WorkProgram $program) {
                $totalItems = (int) ($program->items_count ?? 0);
                $doneItems = (int) ($program->done_items_count ?? 0);

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
        if (!$this->workflow->canSeeProgram($workplan, $user)) {
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

        $pendingApproval = $this->workflow->latestPendingProgramApproval($workplan);
        $approvalFlags = $this->workflow->resolveApprovalPermissionFlags($workplan, $user, $pendingApproval);

        $canEdit = $this->workflow->canEditProgram($workplan, $user);
        $canDelete = $this->workflow->canDeleteProgram($workplan, $user);
        $canSubmit = $this->workflow->canSubmitProgram($workplan, $user);
        $canSubmitUpdate = $this->workflow->canSubmitUpdate($workplan, $user);
        $canCheckerApproval = (bool) ($approvalFlags['can_checker_approval'] ?? false);
        $canApproverApproval = (bool) ($approvalFlags['can_approver_approval'] ?? false);

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
        if (!$this->workflow->canEditProgram($workplan, $user)) {
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
        if (!$this->workflow->canEditProgram($workplan, $user)) {
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
        if (!$this->workflow->canDeleteProgram($workplan, $user)) {
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
        if (!$this->workflow->canEditProgram($workplan, $user)) {
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
        if (!$this->workflow->canSubmitUpdate($workplan, $user)) {
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
        if ($this->workflow->isViewerRole($user)) {
            abort(403, 'Role viewer tidak memiliki akses untuk update work plan.');
        }
        if ($this->workflow->isDeputyDirector($user)) {
            abort(403, 'Posisi Deputy Director hanya dapat melihat dan melakukan approval program kerja.');
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
