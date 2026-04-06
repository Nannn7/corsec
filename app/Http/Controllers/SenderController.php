<?php

namespace Modules\Corsec\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Corsec\Exports\SenderExport;
use Modules\Corsec\Http\Requests\SenderRequest;
use Modules\Corsec\Models\ApprovalRequest;
use Modules\Corsec\Models\Sender;
use Modules\Corsec\Services\ApprovalRequestService;
use Modules\Corsec\Services\CorsecPermissionService;

class SenderController extends Controller
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
        if (is_null($user) || !$user->can('sender.read')) {
            abort(403, 'Sorry! You are not allowed to view sender.');
        }

        Log::info('User accessed sender index', ['user_id' => $user->id]);
        $permissionFlags = $this->permissionService->masterDataFlags($user, 'sender');
        return view('corsec::sender.index', compact('permissionFlags'));
    }

    public function create()
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('sender.create')) {
            abort(403, 'Sorry! You are not allowed to create sender.');
        }

        Log::info('User accessed sender create form', ['user_id' => $user->id]);
        $nextCode = $this->resolveNextNumericCode(Sender::query());

        return view('corsec::sender.create', compact('nextCode'));
    }

    public function store(SenderRequest $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('sender.create')) {
            abort(403, 'Sorry! You are not allowed to create sender.');
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
                Sender::class,
                ApprovalRequest::ACTION_CREATE,
                null,
                $payload,
                null,
                'Pengajuan create sender'
            );

            Log::info('Sender create submitted for approval', ['user_id' => $user->id]);

            return redirect()
                ->route('sender.index')
                ->with('success', 'Sender submitted for approval.');
        } catch (Exception $e) {
            Log::error('Failed to create sender: ' . $e->getMessage(), ['user_id' => $user->id]);

            return redirect()
                ->route('sender.create')
                ->with('error', 'Failed to create sender: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show(Sender $sender)
    {
        return view('corsec::show');
    }

    public function edit(Sender $sender)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('sender.update')) {
            abort(403, 'Sorry! You are not allowed to update sender.');
        }

        Log::info('User accessed sender edit form', ['sender_id' => $sender->id, 'user_id' => $user->id]);
        return view('corsec::sender.create', compact('sender'));
    }

    public function update(SenderRequest $request, Sender $sender)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('sender.update')) {
            abort(403, 'Sorry! You are not allowed to update sender.');
        }

        try {
            $validated = $request->validated();
            $payload = [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $request->boolean('status', $sender->status),
                'updated_by' => $user->id,
            ];

            $this->approvalService->createRequest(
                Sender::class,
                ApprovalRequest::ACTION_UPDATE,
                (string) $sender->id,
                $payload,
                $sender->only(array_keys($payload)),
                'Pengajuan update sender'
            );

            Log::info('Sender update submitted for approval', ['sender_id' => $sender->id, 'user_id' => $user->id]);

            return redirect()
                ->route('sender.index')
                ->with('success', 'Sender update submitted for approval.');
        } catch (Exception $e) {
            Log::error('Failed to update sender: ' . $e->getMessage(), ['sender_id' => $sender->id, 'user_id' => $user->id]);

            return redirect()
                ->route('sender.edit', $sender)
                ->with('error', 'Failed to update sender: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(Sender $sender)
    {
        $user = Auth::user();
        if (!$user || !$user->can('sender.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete sender.'
            ], 403);
        }

        try {
            DB::transaction(function () use ($sender, $user) {
                $sender->update(['deleted_by' => $user->id]);
                $sender->delete();
            });

            Log::info('Sender deleted successfully', [
                'sender_id' => $sender->id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sender deleted successfully.'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete sender: ' . $e->getMessage(), [
                'sender_id' => $id,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit delete request. Please try again later.'
            ], 500);
        }
    }

    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('sender.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete sender.'
            ], 403);
        }

        try {
            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No sender selected for deletion'
                ], 400);
            }

            $existingSender = Sender::whereIn('id', $ids)->pluck('id')->toArray();
            $missingIds = array_diff($ids, $existingSender);

            if (!empty($missingIds)) {
                Log::warning('Some sender not found for multiple delete', [
                    'requested_ids' => $ids,
                    'missing_ids' => $missingIds,
                    'user_id' => $user->id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Some selected sender were not found.',
                    'missing_ids' => $missingIds,
                    'existing_ids' => $existingSender
                ], 404);
            }

            DB::transaction(function () use ($ids, $user) {
                Sender::whereIn('id', $ids)->update(['deleted_by' => $user->id]);
                Sender::whereIn('id', $ids)->delete();
            });

            Log::info('Multiple sender deleted successfully', [
                'requested_ids' => $ids,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Multiple sender deleted successfully.'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete multiple sender: ' . $e->getMessage(), [
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

    public function dataForDatatables(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('sender.read')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to view sender.'
            ], 403);
        }

        try {
            $query = Sender::query();

            $search = trim((string) $request->get('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            $totalRecords    = Sender::count();
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

            Log::info('sender datatables data retrieved', ['user_id' => $user->id, 'total_records' => $totalRecords]);

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
            Log::error('Failed to get sender datatables data: ' . $e->getMessage(), ['user_id' => $user->id]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load data'
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('sender.export')) {
            abort(403, 'Sorry! You are not allowed to export sender.');
        }

        try {
            $search = trim((string) $request->get('search', ''));
            Log::info('sender export initiated', ['user_id' => $user->id, 'search' => $search]);
            return Excel::download(new SenderExport($search), 'sender.xlsx');
        } catch (Exception $e) {
            Log::error('Failed to export sender: ' . $e->getMessage(), ['user_id' => $user->id]);
            return redirect()
                ->route('sender.index')
                ->with('error', 'Failed to export sender.');
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
