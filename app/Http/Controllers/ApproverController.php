<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Corsec\Models\ApprovalRequest;
use Modules\Corsec\Services\ApprovalRequestService;

class ApproverController extends Controller
{
    public function __construct(private readonly ApprovalRequestService $approvalService)
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $modules = ApprovalRequest::query()
            ->select('model')
            ->distinct()
            ->orderBy('model')
            ->pluck('model')
            ->mapWithKeys(function ($model) {
                return [$model => class_basename($model)];
            });

        $actions = [
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
        ];

        return view('corsec::approval.index', compact('modules', 'actions'));
    }

    public function datatables(Request $request)
    {
        $user = Auth::user();
        if (!$user || !($user->can('corsec.authorize') || $user->can('corsec.read'))) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat daftar persetujuan.'
            ], 403);
        }

        try {
            $query = ApprovalRequest::query()
                ->with(['createdBy', 'authorizedBy']);

            $search = trim((string) $request->get('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('model', 'ilike', "%{$search}%")
                        ->orWhere('action', 'ilike', "%{$search}%")
                        ->orWhere('status', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhereHas('createdBy', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('authorizedBy', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'ilike', "%{$search}%");
                        });
                });
            }

            $filters = json_decode($request->get('filters', '[]'), true);
            if (is_array($filters)) {
                foreach ($filters as $filter) {
                    $column = $filter['column'] ?? null;
                    $value = $filter['value'] ?? null;
                    if (!$column || $value === null || $value === '') {
                        continue;
                    }

                    if ($column === 'model') {
                        $query->where('model', $value);
                    } elseif ($column === 'action') {
                        $query->where('action', $value);
                    } elseif ($column === 'status') {
                        $query->where('status', $value);
                    }
                }
            }

            $totalRecords    = ApprovalRequest::count();
            $filteredRecords = (clone $query)->count();

            $sortField = (string) $request->get('sortField', 'id');
            $sortOrder = strtolower((string) $request->get('sortOrder', 'desc'));

            $allowedSort = [
                'id',
                'model',
                'action',
                'status',
                'description',
                'created_at',
                'authorized_at',
            ];

            if (!in_array($sortField, $allowedSort, true)) {
                $sortField = 'id';
            }
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'], true)) {
                $sortOrder = 'desc';
            }

            $query->orderBy($sortField, $sortOrder);
            if ($sortField !== 'id') {
                $query->orderBy('id', $sortOrder);
            }

            $page = max((int) $request->get('page', 1), 1);
            $size = max((int) $request->get('size', 10), 1);
            $offset = ($page - 1) * $size;

            $data = $query->skip($offset)->take($size)->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'model' => class_basename($item->model),
                    'action' => $item->action,
                    'action_badge' => $item->action_badge,
                    'description' => $item->description,
                    'status' => $item->status,
                    'status_badge' => $item->status_badge,
                    'created_by_name' => $item->createdBy?->name,
                    'created_at' => $item->created_at,
                    'authorized_by_name' => $item->authorizedBy?->name,
                    'authorized_at' => $item->authorized_at,
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
        } catch (\Throwable $e) {
            Log::error('Approval datatables error: ' . $e->getMessage(), [
                'user_id' => $user?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat daftar persetujuan.'
            ], 500);
        }
    }

    public function approve(ApprovalRequest $approvalRequest)
    {
        $this->approvalService->approve($approvalRequest, Auth::user());

        return back()->with('success', 'Approval berhasil diproses.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest)
    {
        $request->validate([
            'review_notes' => ['nullable', 'string'],
        ]);

        $this->approvalService->reject($approvalRequest, Auth::user(), $request->review_notes);

        return back()->with('success', 'Approval berhasil ditolak.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('corsec::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        $approvalRequest = ApprovalRequest::query()
            ->with(['createdBy', 'authorizedBy'])
            ->findOrFail($id);

        return view('corsec::approval.show', compact('approvalRequest'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('corsec::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
