<?php

namespace Modules\Corsec\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Corsec\Exports\MeetingTypeExport;
use Modules\Corsec\Http\Requests\MeetingTypeRequest;
use Modules\Corsec\Models\ApprovalRequest;
use Modules\Corsec\Models\MeetingType;
use Modules\Corsec\Services\ApprovalRequestService;
use Modules\Corsec\Services\CorsecPermissionService;

class MeetingTypeController extends Controller
{
    protected $user;
    private readonly ApprovalRequestService $approvalService;
    private readonly CorsecPermissionService $permissionService;

    public function __construct()
    {
        $this->middleware('auth');
        $this->approvalService = app(ApprovalRequestService::class);
        $this->permissionService = app(CorsecPermissionService::class);

        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }

    public function index()
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('meeting-type.read')) {
            abort(403, 'Sorry! You are not allowed to view meeting type.');
        }

        Log::info('User accessed meeting type index', ['user_id' => $user->id]);
        $permissionFlags = $this->permissionService->masterDataFlags($user, 'meeting-type');

        return view('corsec::meeting-type.index', compact('permissionFlags'));
    }

    public function create()
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('meeting-type.create')) {
            abort(403, 'Sorry! You are not allowed to create meeting type.');
        }

        Log::info('User accessed meeting type create form', ['user_id' => $user->id]);
        $nextCode = $this->resolveNextNumericCode(MeetingType::query());

        return view('corsec::meeting-type.create', compact('nextCode'));
    }

    public function store(MeetingTypeRequest $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('meeting-type.create')) {
            abort(403, 'Sorry! You are not allowed to create meeting type.');
        }

        try {
            $validated = $request->validated();
            $payload = [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $request->boolean('status', true),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ];

            $this->approvalService->createRequest(
                MeetingType::class,
                ApprovalRequest::ACTION_CREATE,
                null,
                $payload,
                null,
                'Pengajuan create meeting type'
            );

            Log::info('Meeting type create submitted for approval', ['user_id' => $user->id]);

            return redirect()
                ->route('meeting-type.index')
                ->with('success', 'Meeting type submitted for approval.');
        } catch (Exception $e) {
            Log::error('Failed to create meeting type: ' . $e->getMessage(), ['user_id' => $user->id]);

            return redirect()
                ->route('meeting-type.create')
                ->with('error', 'Failed to create meeting type: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show(MeetingType $meetingType)
    {
        return view('corsec::show');
    }

    public function edit(MeetingType $meetingType)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('meeting-type.update')) {
            abort(403, 'Sorry! You are not allowed to update meeting type.');
        }

        Log::info('User accessed meeting type edit form', [
            'meeting_type_id' => $meetingType->id,
            'user_id' => $user->id,
        ]);

        return view('corsec::meeting-type.create', compact('meetingType'));
    }

    public function update(MeetingTypeRequest $request, MeetingType $meetingType)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('meeting-type.update')) {
            abort(403, 'Sorry! You are not allowed to update meeting type.');
        }

        try {
            $validated = $request->validated();
            $payload = [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $request->boolean('status', $meetingType->status),
                'updated_by' => $user->id,
            ];

            $this->approvalService->createRequest(
                MeetingType::class,
                ApprovalRequest::ACTION_UPDATE,
                (string) $meetingType->id,
                $payload,
                $meetingType->only(array_keys($payload)),
                'Pengajuan update meeting type'
            );

            Log::info('Meeting type update submitted for approval', [
                'meeting_type_id' => $meetingType->id,
                'user_id' => $user->id,
            ]);

            return redirect()
                ->route('meeting-type.index')
                ->with('success', 'Meeting type update submitted for approval.');
        } catch (Exception $e) {
            Log::error('Failed to update meeting type: ' . $e->getMessage(), [
                'meeting_type_id' => $meetingType->id,
                'user_id' => $user->id,
            ]);

            return redirect()
                ->route('meeting-type.edit', $meetingType)
                ->with('error', 'Failed to update meeting type: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(MeetingType $meetingType)
    {
        $user = Auth::user();
        if (!$user || !$user->can('meeting-type.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete meeting type.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($meetingType, $user) {
                $meetingType->update(['deleted_by' => $user->id]);
                $meetingType->delete();
            });

            Log::info('Meeting type deleted successfully', [
                'meeting_type_id' => $meetingType->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Meeting type deleted successfully.',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete meeting type: ' . $e->getMessage(), [
                'meeting_type_id' => $meetingType->id,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit delete request. Please try again later.',
            ], 500);
        }
    }

    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('meeting-type.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete meeting type.',
            ], 403);
        }

        try {
            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No meeting type selected for deletion',
                ], 400);
            }

            $existingMeetingType = MeetingType::whereIn('id', $ids)->pluck('id')->toArray();
            $missingIds = array_diff($ids, $existingMeetingType);

            if (!empty($missingIds)) {
                Log::warning('Some meeting type not found for multiple delete', [
                    'requested_ids' => $ids,
                    'missing_ids' => $missingIds,
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Some selected meeting type were not found.',
                    'missing_ids' => $missingIds,
                    'existing_ids' => $existingMeetingType,
                ], 404);
            }

            DB::transaction(function () use ($ids, $user) {
                MeetingType::whereIn('id', $ids)->update(['deleted_by' => $user->id]);
                MeetingType::whereIn('id', $ids)->delete();
            });

            Log::info('Multiple meeting type deleted successfully', [
                'requested_ids' => $ids,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Multiple meeting type deleted successfully.',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete multiple meeting type: ' . $e->getMessage(), [
                'requested_ids' => $ids ?? [],
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit delete request. Please try again later.',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    public function dataForDatatables(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('meeting-type.read')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to view meeting type.',
            ], 403);
        }

        try {
            $query = MeetingType::query();

            $search = trim((string) $request->get('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            $totalRecords = MeetingType::count();
            $filteredRecords = (clone $query)->count();

            $sortField = (string) $request->get('sortField', 'id');
            $sortOrder = (string) $request->get('sortOrder', 'desc');

            $allowedSort = [
                'id',
                'code',
                'name',
                'description',
                'status',
                'created_at',
            ];

            if (!in_array($sortField, $allowedSort, true)) {
                $sortField = 'id';
            }
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'], true)) {
                $sortOrder = 'desc';
            }

            $query->orderBy($sortField, $sortOrder);

            $page = max((int) $request->get('page', 1), 1);
            $size = max((int) $request->get('size', 10), 1);
            $offset = ($page - 1) * $size;

            $data = $query->skip($offset)->take($size)->get();
            $pageCount = (int) ceil($filteredRecords / $size);

            Log::info('meeting type datatables data retrieved', [
                'user_id' => $user->id,
                'total_records' => $totalRecords,
            ]);

            return response()->json([
                'draw' => $request->get('draw'),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'pageCount' => $pageCount,
                'page' => (int) $page,
                'totalCount' => $totalRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get meeting type datatables data: ' . $e->getMessage(), ['user_id' => $user->id]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load data',
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('meeting-type.export')) {
            abort(403, 'Sorry! You are not allowed to export meeting type.');
        }

        try {
            $search = trim((string) $request->get('search', ''));
            Log::info('meeting type export initiated', ['user_id' => $user->id, 'search' => $search]);

            return Excel::download(new MeetingTypeExport($search), 'meeting-type.xlsx');
        } catch (Exception $e) {
            Log::error('Failed to export meeting type: ' . $e->getMessage(), ['user_id' => $user->id]);

            return redirect()
                ->route('meeting-type.index')
                ->with('error', 'Failed to export meeting type.');
        }
    }

    private function resolveNextNumericCode($query): ?string
    {
        $summary = (clone $query)
            ->whereNotNull('code')
            ->whereRaw("code ~ '^[0-9]+$'")
            ->selectRaw('MAX(code::bigint) AS max_number')
            ->selectRaw('MAX(char_length(code)) AS pad_length')
            ->first();

        $maxNumber = $summary?->max_number;
        if ($maxNumber === null) {
            return null;
        }

        $padLength = max((int) ($summary->pad_length ?? 3), 1);

        return str_pad((string) ((int) $maxNumber + 1), $padLength, '0', STR_PAD_LEFT);
    }
}
