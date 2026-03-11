<?php

namespace Modules\Corsec\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Corsec\Models\Directorate;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Modules\Corsec\Exports\DirectorateExport;
use Modules\Corsec\Http\Requests\DirectorateRequest;
use Modules\Corsec\Models\ApprovalRequest;
use Modules\Corsec\Services\ApprovalRequestService;
use Modules\Corsec\Services\CorsecPermissionService;

class DirectorateController extends Controller
{
    protected $user;
    private readonly ApprovalRequestService $approvalService;
    private readonly CorsecPermissionService $permissionService;

    public function __construct()
    {
        // Mengatur middleware auth
        $this->middleware('auth');
        $this->approvalService = app(ApprovalRequestService::class);
        $this->permissionService = app(CorsecPermissionService::class);

        // Mengatur user setelah middleware auth dijalankan
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('directorate.read')) {
            abort(403, 'Sorry! You are not allowed to view directorate.');
        }

        Log::info('User accessed directorate index', ['user_id' => $user->id]);
        $permissionFlags = $this->permissionService->masterDataFlags($user, 'directorate');
        return view('corsec::direktorat.index', compact('permissionFlags'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('directorate.create')) {
            abort(403, 'Sorry! You are not allowed to create directorate.');
        }

        Log::info('User accessed directorate create form', ['user_id' => $user->id]);
        $codes = Directorate::query()->pluck('code');
        $numericCodes = $codes
            ->filter(function ($code) {
                return is_string($code) && preg_match('/^\d+$/', $code);
            })
            ->values();
        $maxNumber = $numericCodes
            ->map(function ($code) {
                return (int) $code;
            })
            ->max();
        $padLength = $numericCodes
            ->map(function ($code) {
                return strlen($code);
            })
            ->max() ?? 3;
        $nextCode = $maxNumber !== null ? str_pad((string) ($maxNumber + 1), $padLength, '0', STR_PAD_LEFT) : null;

        return view('corsec::direktorat.create', compact('nextCode'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DirectorateRequest $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('directorate.create')) {
            abort(403, 'Sorry! You are not allowed to create directorate.');
        }

        try {
            $validated = $request->validated();
            $payload = [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $request->boolean('status', true),
                'is_meeting_operational' => $request->boolean('is_meeting_operational', false),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ];

            $this->approvalService->createRequest(
                Directorate::class,
                ApprovalRequest::ACTION_CREATE,
                null,
                $payload,
                null,
                'Pengajuan create directorate'
            );

            Log::info('Directorate create submitted for approval', ['user_id' => $user->id]);

            return redirect()
                ->route('directorate.index')
                ->with('success', 'Directorate submitted for approval.');
        } catch (Exception $e) {
            Log::error('Failed to create directorate: ' . $e->getMessage(), ['user_id' => $user->id]);

            return redirect()
                ->route('directorate.create')
                ->with('error', 'Failed to create directorate: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the specified resource.
     */
    public function show(Directorate $directorate)
    {
        return view('corsec::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Directorate $directorate)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('directorate.update')) {
            abort(403, 'Sorry! You are not allowed to update directorate.');
        }

        Log::info('User accessed directorate edit form', ['directorate_id' => $directorate->id, 'user_id' => $user->id]);
        return view('corsec::direktorat.create', compact('directorate'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DirectorateRequest $request, Directorate $directorate)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('directorate.update')) {
            abort(403, 'Sorry! You are not allowed to update directorate.');
        }

        try {
            $validated = $request->validated();
            $payload = [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $request->boolean('status', $directorate->status),
                'is_meeting_operational' => $request->boolean(
                    'is_meeting_operational',
                    (bool) ($directorate->is_meeting_operational ?? false)
                ),
                'updated_by' => $user->id,
            ];

            $this->approvalService->createRequest(
                Directorate::class,
                ApprovalRequest::ACTION_UPDATE,
                (string) $directorate->id,
                $payload,
                $directorate->only(array_keys($payload)),
                'Pengajuan update directorate'
            );

            Log::info('Directorate update submitted for approval', ['directorate_id' => $directorate->id, 'user_id' => $user->id]);

            return redirect()
                ->route('directorate.index')
                ->with('success', 'Directorate update submitted for approval.');
        } catch (Exception $e) {
            Log::error('Failed to update directorate: ' . $e->getMessage(), ['directorate_id' => $directorate->id, 'user_id' => $user->id]);

            return redirect()
                ->route('directorate.edit', $directorate)
                ->with('error', 'Failed to update directorate: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Directorate $directorate)
    {
        $user = Auth::user();
        if (!$user || !$user->can('directorate.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete directorate.'
            ], 403);
        }

        try {
            DB::transaction(function () use ($directorate, $user) {
                $directorate->update(['deleted_by' => $user->id]);
                $directorate->delete();
            });

            Log::info('Directorate deleted successfully', [
                'directorate_id' => $directorate->id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Directorate deleted successfully.'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete directorate: ' . $e->getMessage(), [
                'directorate_id' => $id,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit delete request. Please try again later.'
            ], 500);
        }
    }

    /**
     * Delete multiple directorate
     */
    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('directorate.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete directorate.'
            ], 403);
        }

        try {
            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No directorate selected for deletion'
                ], 400);
            }

            // Validasi bahwa semua directorate yang dipilih ada di database
            $existingdirectorate = Directorate::whereIn('id', $ids)->pluck('id')->toArray();
            $missingIds = array_diff($ids, $existingdirectorate);

            if (!empty($missingIds)) {
                Log::warning('Some directorate not found for multiple delete', [
                    'requested_ids' => $ids,
                    'missing_ids' => $missingIds,
                    'user_id' => $user->id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Some selected directorate were not found.',
                    'missing_ids' => $missingIds,
                    'existing_ids' => $existingdirectorate
                ], 404);
            }

            DB::transaction(function () use ($ids, $user) {
                Directorate::whereIn('id', $ids)->update(['deleted_by' => $user->id]);
                Directorate::whereIn('id', $ids)->delete();
            });

            Log::info('Multiple directorate deleted successfully', [
                'requested_ids' => $ids,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Multiple directorate deleted successfully.'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete multiple directorate: ' . $e->getMessage(), [
                'requested_ids' => $ids ?? [],
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit delete request. Please try again later.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get data for datatables
     */
    public function dataForDatatables(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('directorate.read')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to view directorate.'
            ], 403);
        }

        try {
            // Base query
            $query = Directorate::query();

            // Apply search filter if provided
            $search = trim((string) $request->get('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            // Get total records count BEFORE pagination
            $totalRecords    = Directorate::count();
            $filteredRecords = (clone $query)->count();

            // Apply sorting if provided
            $sortField = (string) $request->get('sortField', 'id');
            $sortOrder = (string) $request->get('sortOrder', 'desc');

            $allowedSort = [
                'id',
                'code',
                'name',
                'description',
                'status',
                'is_meeting_operational',
                'created_at',
            ];

            if (!in_array($sortField, $allowedSort, true)) {
                $sortField = 'id';
            }
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'], true)) {
                $sortOrder = 'desc';
            }

            $query->orderBy($sortField, $sortOrder);

            // Apply pagination if provided
            $page = max((int) $request->get('page', 1), 1);
            $size = max((int) $request->get('size', 10), 1);
            $offset = ($page - 1) * $size;

            $data = $query->skip($offset)->take($size)->get();

            // Calculate page count
            $pageCount = (int) ceil($filteredRecords / $size);

            Log::info('directorate datatables data retrieved', ['user_id' => $user->id, 'total_records' => $totalRecords]);

            return response()->json([
                'draw'            => $request->get('draw'),
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'pageCount'       => $pageCount,
                'page'            => (int) $page,
                'totalCount'      => $totalRecords,
                'data'            => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get datatables data: ' . $e->getMessage(), ['user_id' => $user->id]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load data'
            ], 500);
        }
    }

    /**
     * Export directorate to Excel
     */
    public function export(Request $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('directorate.export')) {
            abort(403, 'Sorry! You are not allowed to export directorate.');
        }

        try {
            $search = trim((string) $request->get('search', ''));
            Log::info('directorate export initiated', ['user_id' => $user->id, 'search' => $search]);
            return Excel::download(new DirectorateExport($search), 'directorate.xlsx');
        } catch (Exception $e) {
            Log::error('Failed to export directorate: ' . $e->getMessage(), ['user_id' => $user->id]);
            return redirect()
                ->route('directorate.index')
                ->with('error', 'Failed to export directorate.');
        }
    }
}
