<?php

namespace Modules\Corsec\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Corsec\Exports\LetterTypeExport;
use Modules\Corsec\Http\Requests\LetterTypeRequest;
use Modules\Corsec\Models\ApprovalRequest;
use Modules\Corsec\Models\LetterType;
use Modules\Corsec\Services\ApprovalRequestService;
use Modules\Corsec\Services\CorsecPermissionService;

class LetterTypeController extends Controller
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

    public function index(Request $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('letter-type.read')) {
            abort(403, 'Anda tidak memiliki akses untuk melihat tipe surat.');
        }

        $scope = $this->resolveScope($request);
        $routePrefix = $this->resolveRoutePrefix($request, $scope);
        $scopeLabel = $this->scopeLabel($scope);
        $breadcrumb = $this->resolveIndexBreadcrumb($routePrefix, $scope);

        Log::info('User accessed letter type index', [
            'user_id' => $user->id,
            'scope' => $scope,
        ]);

        $permissionFlags = $this->permissionService->masterDataFlags($user, 'letter-type');
        return view('corsec::letter-type.index', compact('scope', 'scopeLabel', 'routePrefix', 'breadcrumb', 'permissionFlags'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('letter-type.create')) {
            abort(403, 'Anda tidak memiliki akses untuk menambah tipe surat.');
        }

        $scope = $this->resolveScope($request);
        $routePrefix = $this->resolveRoutePrefix($request, $scope);
        $scopeLabel = $this->scopeLabel($scope);
        $breadcrumb = $this->resolveFormBreadcrumb($routePrefix, false);

        Log::info('User accessed letter type create form', [
            'user_id' => $user->id,
            'scope' => $scope,
        ]);

        $nextCode = $this->resolveNextNumericCode(LetterType::query()->forScope($scope));

        return view('corsec::letter-type.create', compact('nextCode', 'scope', 'scopeLabel', 'routePrefix', 'breadcrumb'));
    }

    public function store(LetterTypeRequest $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('letter-type.create')) {
            abort(403, 'Anda tidak memiliki akses untuk menambah tipe surat.');
        }

        $scope = $this->resolveScope($request);
        $routePrefix = $this->resolveRoutePrefix($request, $scope);

        try {
            $validated = $request->validated();
            $payload = [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'scope' => $scope,
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
                'Pengajuan create letter type ' . $this->scopeLabel($scope)
            );

            Log::info('Letter type create submitted for approval', [
                'user_id' => $user->id,
                'scope' => $scope,
            ]);

            return redirect()
                ->route($routePrefix . '.index')
                ->with('success', 'Pengajuan tipe surat berhasil dikirim untuk persetujuan.');
        } catch (Exception $e) {
            Log::error('Failed to create letter type: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'scope' => $scope,
            ]);

            return redirect()
                ->route($routePrefix . '.create')
                ->with('error', 'Gagal mengajukan tipe surat: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show(Request $request, LetterType $letterType)
    {
        $scope = $this->resolveScope($request);
        $this->ensureLetterTypeScope($letterType, $scope);

        return view('corsec::show');
    }

    public function edit(Request $request, LetterType $letterType)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('letter-type.update')) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah tipe surat.');
        }

        $scope = $this->resolveScope($request);
        $routePrefix = $this->resolveRoutePrefix($request, $scope);
        $scopeLabel = $this->scopeLabel($scope);
        $breadcrumb = $this->resolveFormBreadcrumb($routePrefix, true);

        $this->ensureLetterTypeScope($letterType, $scope);

        Log::info('User accessed letter type edit form', [
            'letter_type_id' => $letterType->id,
            'user_id' => $user->id,
            'scope' => $scope,
        ]);

        return view('corsec::letter-type.create', compact('letterType', 'scope', 'scopeLabel', 'routePrefix', 'breadcrumb'));
    }

    public function update(LetterTypeRequest $request, LetterType $letterType)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('letter-type.update')) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah tipe surat.');
        }

        $scope = $this->resolveScope($request);
        $routePrefix = $this->resolveRoutePrefix($request, $scope);

        $this->ensureLetterTypeScope($letterType, $scope);

        try {
            $validated = $request->validated();
            $payload = [
                'code' => $validated['code'],
                'name' => $validated['name'],
                'scope' => $scope,
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
                'Pengajuan update letter type ' . $this->scopeLabel($scope)
            );

            Log::info('Letter type update submitted for approval', [
                'letter_type_id' => $letterType->id,
                'user_id' => $user->id,
                'scope' => $scope,
            ]);

            return redirect()
                ->route($routePrefix . '.index')
                ->with('success', 'Pengajuan perubahan tipe surat berhasil dikirim untuk persetujuan.');
        } catch (Exception $e) {
            Log::error('Failed to update letter type: ' . $e->getMessage(), [
                'letter_type_id' => $letterType->id,
                'user_id' => $user->id,
                'scope' => $scope,
            ]);

            return redirect()
                ->route($routePrefix . '.edit', $letterType)
                ->with('error', 'Gagal mengajukan perubahan tipe surat: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(Request $request, LetterType $letterType)
    {
        $user = Auth::user();
        if (!$user || !$user->can('letter-type.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus tipe surat.'
            ], 403);
        }

        $scope = $this->resolveScope($request);
        $this->ensureLetterTypeScope($letterType, $scope);

        try {
            DB::transaction(function () use ($letterType, $user) {
                $letterType->update(['deleted_by' => $user->id]);
                $letterType->delete();
            });

            $this->forgetLetterTypeCaches($scope);

            Log::info('Letter type deleted successfully', [
                'letter_type_id' => $letterType->id,
                'user_id' => $user->id,
                'scope' => $scope,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tipe surat berhasil dihapus.'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete letter type: ' . $e->getMessage(), [
                'letter_type_id' => $letterType->id,
                'user_id' => $user->id,
                'scope' => $scope,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus tipe surat. Silakan coba lagi.'
            ], 500);
        }
    }

    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('letter-type.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus tipe surat.'
            ], 403);
        }

        $scope = $this->resolveScope($request);

        try {
            $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pilih minimal satu tipe surat untuk dihapus.'
                ], 400);
            }

            $existingLetterType = LetterType::query()
                ->forScope($scope)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->toArray();

            $missingIds = array_diff($ids, $existingLetterType);

            if (!empty($missingIds)) {
                Log::warning('Some letter type not found for multiple delete', [
                    'requested_ids' => $ids,
                    'missing_ids' => $missingIds,
                    'user_id' => $user->id,
                    'scope' => $scope,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Sebagian tipe surat yang dipilih tidak ditemukan.',
                    'missing_ids' => $missingIds,
                    'existing_ids' => $existingLetterType
                ], 404);
            }

            DB::transaction(function () use ($ids, $scope, $user) {
                LetterType::query()
                    ->forScope($scope)
                    ->whereIn('id', $ids)
                    ->update(['deleted_by' => $user->id]);

                LetterType::query()
                    ->forScope($scope)
                    ->whereIn('id', $ids)
                    ->delete();
            });

            $this->forgetLetterTypeCaches($scope);

            Log::info('Multiple letter type deleted successfully', [
                'requested_ids' => $ids,
                'user_id' => $user->id,
                'scope' => $scope,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tipe surat terpilih berhasil dihapus.'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete multiple letter type: ' . $e->getMessage(), [
                'requested_ids' => $ids ?? [],
                'user_id' => $user->id,
                'scope' => $scope,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus tipe surat terpilih. Silakan coba lagi.',
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
                'message' => 'Anda tidak memiliki akses untuk melihat tipe surat.'
            ], 403);
        }

        $scope = $this->resolveScope($request);

        try {
            $query = LetterType::query()->forScope($scope);

            $search = trim((string) $request->get('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            $totalRecords = LetterType::query()->forScope($scope)->count();
            $filteredRecords = (clone $query)->count();

            $sortField = (string) $request->get('sortField', 'id');
            $sortOrder = strtolower((string) $request->get('sortOrder', 'desc'));

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
            if ($sortField !== 'id') {
                $query->orderBy('id', $sortOrder);
            }

            $page = max((int) $request->get('page', 1), 1);
            $size = max((int) $request->get('size', 10), 1);
            $offset = ($page - 1) * $size;

            $data = $query->skip($offset)->take($size)->get();
            $pageCount = (int) ceil($filteredRecords / $size);

            Log::info('letter type datatables data retrieved', [
                'user_id' => $user->id,
                'scope' => $scope,
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
            Log::error('Failed to get letter type datatables data: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'scope' => $scope,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data tipe surat.'
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if (is_null($user) || !$user->can('letter-type.export')) {
            abort(403, 'Anda tidak memiliki akses untuk export tipe surat.');
        }

        $scope = $this->resolveScope($request);
        $routePrefix = $this->resolveRoutePrefix($request, $scope);

        try {
            $search = trim((string) $request->get('search', ''));
            Log::info('letter type export initiated', [
                'user_id' => $user->id,
                'search' => $search,
                'scope' => $scope,
            ]);

            $filename = sprintf('letter_type_%s.xlsx', $scope);

            return Excel::download(new LetterTypeExport($search, $scope), $filename);
        } catch (Exception $e) {
            Log::error('Failed to export letter type: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'scope' => $scope,
            ]);

            return redirect()
                ->route($routePrefix . '.index')
                ->with('error', 'Gagal export tipe surat.');
        }
    }

    private function resolveScope(Request $request): string
    {
        $scope = (string) ($request->route('scope') ?? '');

        if (!in_array($scope, [LetterType::SCOPE_IN, LetterType::SCOPE_OUT], true)) {
            $routeName = (string) ($request->route()?->getName() ?? '');
            if (str_starts_with($routeName, 'letter-type.out.')) {
                $scope = LetterType::SCOPE_OUT;
            } else {
                $scope = LetterType::SCOPE_IN;
            }
        }

        return $scope;
    }

    private function resolveRoutePrefix(Request $request, string $scope): string
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        if (str_starts_with($routeName, 'letter-type.out.')) {
            return 'letter-type.out';
        }

        if (str_starts_with($routeName, 'letter-type.in.')) {
            return 'letter-type.in';
        }

        if (str_starts_with($routeName, 'letter-type.')) {
            return 'letter-type';
        }

        return $scope === LetterType::SCOPE_OUT ? 'letter-type.out' : 'letter-type.in';
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

    private function resolveIndexBreadcrumb(string $routePrefix, string $scope): string
    {
        if ($scope === LetterType::SCOPE_OUT) {
            return 'corsec.letter-type.out';
        }

        return $routePrefix === 'letter-type.in' ? 'corsec.letter-type.in' : 'corsec.letter-type';
    }

    private function resolveFormBreadcrumb(string $routePrefix, bool $isEdit): string
    {
        if ($routePrefix === 'letter-type.out') {
            return $isEdit ? 'letter-type.out.edit' : 'letter-type.out.create';
        }

        if ($routePrefix === 'letter-type.in') {
            return $isEdit ? 'letter-type.in.edit' : 'letter-type.in.create';
        }

        return $isEdit ? 'letter-type.edit' : 'letter-type.create';
    }

    private function scopeLabel(string $scope): string
    {
        return $scope === LetterType::SCOPE_OUT ? 'Out' : 'In';
    }

    private function ensureLetterTypeScope(LetterType $letterType, string $scope): void
    {
        $letterTypeScope = (string) ($letterType->scope ?: LetterType::SCOPE_IN);
        if ($letterTypeScope !== $scope) {
            abort(404, 'Tipe surat tidak ditemukan.');
        }
    }

    private function forgetLetterTypeCaches(?string $scope = null): void
    {
        Cache::forget('corsec.letter_types.list');
        Cache::forget('corsec.letter_types.in.list');
        Cache::forget('corsec.letter_types.out.list');

        if ($scope && in_array($scope, [LetterType::SCOPE_IN, LetterType::SCOPE_OUT], true)) {
            Cache::forget("corsec.letter_types.{$scope}.list");
        }
    }
}
