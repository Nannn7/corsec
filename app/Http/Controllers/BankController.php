<?php

namespace Modules\Corsec\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Corsec\Exports\BankExport;
use Modules\Corsec\Http\Requests\BankRequest;
use Modules\Corsec\Models\ApprovalRequest;
use Modules\Corsec\Models\Bank;
use Modules\Corsec\Services\ApprovalRequestService;

class BankController extends Controller
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
        if (is_null($user) || !$user->can('bank.read')) {
            abort(403, 'Sorry! You are not allowed to view bank.');
        }

        Log::info('User accessed bank index', ['user_id' => $user->id]);
        return view('corsec::bank.index');
    }

    public function create()
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('bank.create')) {
            abort(403, 'Sorry! You are not allowed to create bank.');
        }

        Log::info('User accessed bank create form', ['user_id' => $user->id]);

        $codes = Bank::query()->pluck('code');
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

        return view('corsec::bank.create', compact('nextCode'));
    }

    public function store(BankRequest $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('bank.create')) {
            abort(403, 'Sorry! You are not allowed to create bank.');
        }

        try {
            $validated = $request->validated();
            $payload = [
                'code' => $validated['code'],
                'swift_code' => $validated['swift_code'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $request->boolean('status', true),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ];

            $this->approvalService->createRequest(
                Bank::class,
                ApprovalRequest::ACTION_CREATE,
                null,
                $payload,
                null,
                'Pengajuan create bank'
            );

            Log::info('Bank create submitted for approval', ['user_id' => $user->id]);

            return redirect()
                ->route('bank.index')
                ->with('success', 'Bank submitted for approval.');
        } catch (Exception $e) {
            Log::error('Failed to create bank: ' . $e->getMessage(), ['user_id' => $user->id]);

            return redirect()
                ->route('bank.create')
                ->with('error', 'Failed to create bank: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show(Bank $bank)
    {
        return view('corsec::show');
    }

    public function edit(Bank $bank)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('bank.update')) {
            abort(403, 'Sorry! You are not allowed to update bank.');
        }

        Log::info('User accessed bank edit form', ['bank_id' => $bank->id, 'user_id' => $user->id]);
        return view('corsec::bank.create', compact('bank'));
    }

    public function update(BankRequest $request, Bank $bank)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('bank.update')) {
            abort(403, 'Sorry! You are not allowed to update bank.');
        }

        try {
            $validated = $request->validated();
            $payload = [
                'code' => $validated['code'],
                'swift_code' => $validated['swift_code'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $request->boolean('status', $bank->status),
                'updated_by' => $user->id,
            ];

            $this->approvalService->createRequest(
                Bank::class,
                ApprovalRequest::ACTION_UPDATE,
                (string) $bank->id,
                $payload,
                $bank->only(array_keys($payload)),
                'Pengajuan update bank'
            );

            Log::info('Bank update submitted for approval', ['bank_id' => $bank->id, 'user_id' => $user->id]);

            return redirect()
                ->route('bank.index')
                ->with('success', 'Bank update submitted for approval.');
        } catch (Exception $e) {
            Log::error('Failed to update bank: ' . $e->getMessage(), ['bank_id' => $bank->id, 'user_id' => $user->id]);
            return redirect()
                ->route('bank.edit', $bank)
                ->with('error', 'Failed to update bank: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(Bank $bank)
    {
        $user = Auth::user();
        if (!$user || !$user->can('bank.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete bank.'
            ], 403);
        }

        try {
            DB::transaction(function () use ($bank, $user) {
                $bank->update(['deleted_by' => $user->id]);
                $bank->delete();
            });

            Log::info('Bank deleted successfully', ['bank_id' => $bank->id, 'user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Bank deleted successfully.'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete bank: ' . $e->getMessage(), [
                'bank_id' => $id,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bank.'
            ], 500);
        }
    }

    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('bank.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete bank.'
            ], 403);
        }

        $ids = $request->input('ids', []);
        if (!is_array($ids) || count($ids) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No bank selected for deletion'
            ], 400);
        }

        try {
            $existingBanks = Bank::whereIn('id', $ids)->pluck('id')->toArray();
            $missingIds = array_diff($ids, $existingBanks);

            if (!empty($missingIds)) {
                Log::warning('Some bank not found for multiple delete', [
                    'missing_ids' => $missingIds,
                    'user_id' => $user->id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Some selected bank were not found.',
                    'missing_ids' => $missingIds
                ], 404);
            }

            DB::transaction(function () use ($ids, $user) {
                Bank::whereIn('id', $ids)->update(['deleted_by' => $user->id]);
                Bank::whereIn('id', $ids)->delete();
            });

            Log::info('Multiple bank deleted successfully', [
                'user_id' => $user->id,
                'count' => count($ids)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Multiple bank deleted successfully.'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete multiple bank: ' . $e->getMessage(), [
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
        if (!$user || !$user->can('bank.read')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to view bank.'
            ], 403);
        }

        try {
            $query = Bank::query();

            $search = trim((string) $request->get('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('swift_code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            $totalRecords    = Bank::count();
            $filteredRecords = (clone $query)->count();

            $sortField = (string) $request->get('sortField', 'id');
            $sortOrder = (string) $request->get('sortOrder', 'desc');

            $allowedSort = [
                'id',
                'code',
                'swift_code',
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

            Log::info('bank datatables data retrieved', ['user_id' => $user->id, 'total_records' => $totalRecords]);

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
            Log::error('Failed to get bank datatables data: ' . $e->getMessage(), ['user_id' => $user->id]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load data'
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('bank.export')) {
            abort(403, 'Sorry! You are not allowed to export bank.');
        }

        try {
            $search = trim((string) $request->get('search', ''));
            Log::info('bank export initiated', ['user_id' => $user->id, 'search' => $search]);
            return Excel::download(new BankExport($search), 'bank.xlsx');
        } catch (Exception $e) {
            Log::error('Failed to export bank: ' . $e->getMessage(), ['user_id' => $user->id]);
            return redirect()
                ->route('bank.index')
                ->with('error', 'Failed to export bank.');
        }
    }
}
