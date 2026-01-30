<?php

namespace Modules\Corsec\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Corsec\Exports\LetterTypeExport;
use Modules\Corsec\Http\Requests\LetterTypeRequest;
use Modules\Corsec\Models\ApprovalRequest;
use Modules\Corsec\Models\LetterType;
use Modules\Corsec\Services\ApprovalRequestService;

class LetterTypeController extends Controller
{
    protected $user;
    private readonly ApprovalRequestService $approvalService;

    public function __construct()
    {
        $this->middleware('auth');
        $this->approvalService = app(ApprovalRequestService::class);

        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }

    public function index()
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('letter-type.read')) {
            abort(403, 'Sorry! You are not allowed to view letter type.');
        }

        Log::info('User accessed letter type index', ['user_id' => $user->id]);
        return view('corsec::letter-type.index');
    }

    public function create()
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('letter-type.create')) {
            abort(403, 'Sorry! You are not allowed to create letter type.');
        }

        Log::info('User accessed letter type create form', ['user_id' => $user->id]);
        $codes = LetterType::query()->pluck('code');
        $numericCodes = $codes
            ->filter(function ($code) {
                return is_string($code) && preg_match('/^\\d+$/', $code);
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

        return view('corsec::letter-type.create', compact('nextCode'));
    }

    public function store(LetterTypeRequest $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('letter-type.create')) {
            abort(403, 'Sorry! You are not allowed to create letter type.');
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
                LetterType::class,
                ApprovalRequest::ACTION_CREATE,
                null,
                $payload,
                null,
                'Pengajuan create letter type'
            );

            Log::info('Letter type create submitted for approval', ['user_id' => $user->id]);

            return redirect()
                ->route('letter-type.index')
                ->with('success', 'Letter type submitted for approval.');
        } catch (Exception $e) {
            Log::error('Failed to create letter type: ' . $e->getMessage(), ['user_id' => $user->id]);

            return redirect()
                ->route('letter-type.create')
                ->with('error', 'Failed to create letter type: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show($id)
    {
        return view('corsec::show');
    }

    public function edit($id)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('letter-type.update')) {
            abort(403, 'Sorry! You are not allowed to update letter type.');
        }

        $letterType = LetterType::find($id);
        if (!$letterType) {
            Log::warning('Letter type not found for edit', ['letter_type_id' => $id, 'user_id' => $user->id]);
            return redirect()
                ->route('letter-type.index')
                ->with('error', 'Letter type not found.');
        }

        Log::info('User accessed letter type edit form', ['letter_type_id' => $id, 'user_id' => $user->id]);
        return view('corsec::letter-type.create', compact('letterType'));
    }

    public function update(LetterTypeRequest $request, $id)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('letter-type.update')) {
            abort(403, 'Sorry! You are not allowed to update letter type.');
        }

        $letterType = LetterType::find($id);
        if (!$letterType) {
            Log::warning('Letter type not found for update', ['letter_type_id' => $id, 'user_id' => $user->id]);
            return redirect()
                ->route('letter-type.index')
                ->with('error', 'Letter type not found.');
        }

        try {
            $validated = $request->validated();
            $payload = [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $request->boolean('status', $letterType->status),
                'updated_by' => $user->id,
            ];

            $this->approvalService->createRequest(
                LetterType::class,
                ApprovalRequest::ACTION_UPDATE,
                (string) $letterType->id,
                $payload,
                $letterType->only(array_keys($payload)),
                'Pengajuan update letter type'
            );

            Log::info('Letter type update submitted for approval', ['letter_type_id' => $letterType->id, 'user_id' => $user->id]);

            return redirect()
                ->route('letter-type.index')
                ->with('success', 'Letter type update submitted for approval.');
        } catch (Exception $e) {
            Log::error('Failed to update letter type: ' . $e->getMessage(), ['letter_type_id' => $id, 'user_id' => $user->id]);

            return redirect()
                ->route('letter-type.edit', $id)
                ->with('error', 'Failed to update letter type: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || !$user->can('letter-type.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete letter type.'
            ], 403);
        }

        try {
            $letterType = LetterType::find($id);
            if (!$letterType) {
                Log::warning('Letter type not found for delete', ['letter_type_id' => $id, 'user_id' => $user->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Letter type not found.'
                ], 404);
            }

            DB::transaction(function () use ($letterType, $user) {
                $letterType->update(['deleted_by' => $user->id]);
                $letterType->delete();
            });

            Log::info('Letter type deleted successfully', [
                'letter_type_id' => $id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Letter type deleted successfully.'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete letter type: ' . $e->getMessage(), [
                'letter_type_id' => $id,
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
        if (!$user || !$user->can('letter-type.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete letter type.'
            ], 403);
        }

        try {
            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No letter type selected for deletion'
                ], 400);
            }

            $existingLetterType = LetterType::whereIn('id', $ids)->pluck('id')->toArray();
            $missingIds = array_diff($ids, $existingLetterType);

            if (!empty($missingIds)) {
                Log::warning('Some letter type not found for multiple delete', [
                    'requested_ids' => $ids,
                    'missing_ids' => $missingIds,
                    'user_id' => $user->id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Some selected letter type were not found.',
                    'missing_ids' => $missingIds,
                    'existing_ids' => $existingLetterType
                ], 404);
            }

            DB::transaction(function () use ($ids, $user) {
                LetterType::whereIn('id', $ids)->update(['deleted_by' => $user->id]);
                LetterType::whereIn('id', $ids)->delete();
            });

            Log::info('Multiple letter type deleted successfully', [
                'requested_ids' => $ids,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Multiple letter type deleted successfully.'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete multiple letter type: ' . $e->getMessage(), [
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
        if (!$user || !$user->can('letter-type.read')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to view letter type.'
            ], 403);
        }

        try {
            $query = LetterType::query();

            $search = trim((string) $request->get('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            $totalRecords    = LetterType::count();
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

            Log::info('letter type datatables data retrieved', ['user_id' => $user->id, 'total_records' => $totalRecords]);

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
            Log::error('Failed to get letter type datatables data: ' . $e->getMessage(), ['user_id' => $user->id]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load data'
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('letter-type.export')) {
            abort(403, 'Sorry! You are not allowed to export letter type.');
        }

        try {
            $search = trim((string) $request->get('search', ''));
            Log::info('letter type export initiated', ['user_id' => $user->id, 'search' => $search]);
            return Excel::download(new LetterTypeExport($search), 'letter_type.xlsx');
        } catch (Exception $e) {
            Log::error('Failed to export letter type: ' . $e->getMessage(), ['user_id' => $user->id]);
            return redirect()
                ->route('letter-type.index')
                ->with('error', 'Failed to export letter type.');
        }
    }
}
