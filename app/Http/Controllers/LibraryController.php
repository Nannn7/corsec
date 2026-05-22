<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Corsec\Http\Requests\LibraryItemRequest;
use Modules\Corsec\Models\LibraryItem;
use Modules\Corsec\Services\CorsecPermissionService;
use Modules\Usermanagement\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryController extends Controller
{
    private readonly CorsecPermissionService $permissionService;

    public function __construct()
    {
        $this->middleware('auth');
        $this->permissionService = app(CorsecPermissionService::class);
    }

    public function index(Request $request): View
    {
        return $this->renderIndex($request, null, 'library.index', 'Library', 'Daftar pustaka dan dokumen referensi Corsec.');
    }

    public function guidelineIndex(Request $request): View
    {
        return $this->renderIndex(
            $request,
            LibraryItem::CATEGORY_APP_GUIDELINE,
            'library.guideline.index',
            'Apps Guideline',
            'Dokumen internal guideline aplikasi dan prosedur.'
        );
    }

    public function create(Request $request): View
    {
        $user = Auth::user();
        $this->ensureCanManageLibrary($user);

        $selectedCategory = $this->resolveCategoryCode($request->query('category'))
            ?? LibraryItem::CATEGORY_APP_GUIDELINE;

        return view('corsec::library.create', [
            'categoryOptions' => LibraryItem::categoryOptions(),
            'selectedCategory' => $selectedCategory,
            'returnTo' => $this->resolveReturnRouteName($request->query('return_to')),
        ]);
    }

    public function store(LibraryItemRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $this->ensureCanManageLibrary($user);

        $validated = $request->validated();
        $file = $request->file('file');
        $path = $file->store('corsec/library', 'public');
        $originalName = (string) $file->getClientOriginalName();

        LibraryItem::create([
            'category_code' => $validated['category_code'],
            'title' => $this->resolveTitle($originalName),
            'file_disk' => 'public',
            'file_path' => $path,
            'original_name' => $originalName,
            'file_name' => basename($path),
            'file_mime' => $file->getClientMimeType(),
            'file_extension' => Str::lower((string) $file->getClientOriginalExtension()),
            'file_size' => $file->getSize(),
            'status' => true,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return $this->redirectToListing(
            $this->resolveReturnRouteName($validated['return_to'] ?? null),
            $validated['category_code'],
            'Dokumen daftar pustaka berhasil diunggah.'
        );
    }

    public function edit(Request $request, LibraryItem $libraryItem): View
    {
        $user = Auth::user();
        $this->ensureCanManageLibrary($user);

        return view('corsec::library.create', [
            'libraryItem' => $libraryItem,
            'categoryOptions' => LibraryItem::categoryOptions(),
            'selectedCategory' => old('category_code', $libraryItem->category_code),
            'returnTo' => $this->resolveReturnRouteName($request->query('return_to')),
        ]);
    }

    public function update(LibraryItemRequest $request, LibraryItem $libraryItem): RedirectResponse
    {
        $user = Auth::user();
        $this->ensureCanManageLibrary($user);

        $validated = $request->validated();
        $payload = [
            'category_code' => $validated['category_code'],
            'updated_by' => $user?->id,
        ];

        $oldDisk = null;
        $oldPath = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('corsec/library', 'public');
            $originalName = (string) $file->getClientOriginalName();

            $payload = array_merge($payload, [
                'title' => $this->resolveTitle($originalName),
                'file_disk' => 'public',
                'file_path' => $path,
                'original_name' => $originalName,
                'file_name' => basename($path),
                'file_mime' => $file->getClientMimeType(),
                'file_extension' => Str::lower((string) $file->getClientOriginalExtension()),
                'file_size' => $file->getSize(),
            ]);

            $oldDisk = (string) ($libraryItem->file_disk ?: 'public');
            $oldPath = (string) ($libraryItem->file_path ?? '');
        }

        $libraryItem->update($payload);

        if ($oldPath !== '') {
            $this->deleteStoredFile($oldDisk, $oldPath);
        }

        return $this->redirectToListing(
            $this->resolveReturnRouteName($validated['return_to'] ?? null),
            $validated['category_code'],
            'Dokumen daftar pustaka berhasil diperbarui.'
        );
    }

    public function destroy(Request $request, LibraryItem $libraryItem): RedirectResponse
    {
        $user = Auth::user();
        $this->ensureCanManageLibrary($user);

        $disk = (string) ($libraryItem->file_disk ?: 'public');
        $path = (string) ($libraryItem->file_path ?? '');

        $libraryItem->update([
            'updated_by' => $user?->id,
            'deleted_by' => $user?->id,
        ]);
        $libraryItem->delete();

        if ($path !== '') {
            $this->deleteStoredFile($disk, $path);
        }

        return $this->redirectToListing(
            $this->resolveReturnRouteName($request->input('return_to')),
            (string) $libraryItem->category_code,
            'Dokumen daftar pustaka berhasil dihapus.'
        );
    }

    public function download(LibraryItem $libraryItem): StreamedResponse
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.read')) {
            abort(403, 'Anda tidak memiliki akses untuk mengunduh dokumen pustaka.');
        }

        $disk = (string) ($libraryItem->file_disk ?: 'public');
        $path = (string) ($libraryItem->file_path ?? '');

        if ($path === '' || !Storage::disk($disk)->exists($path)) {
            abort(404, 'File daftar pustaka tidak ditemukan.');
        }

        return Storage::disk($disk)->download($path, $libraryItem->downloadFileName());
    }

    private function renderIndex(
        Request $request,
        ?string $forcedCategory,
        string $routeName,
        string $title,
        string $description
    ): View {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.read')) {
            abort(403, 'Anda tidak memiliki akses untuk melihat daftar pustaka.');
        }

        $activeCategory = $forcedCategory ?? $this->resolveCategoryCode($request->query('category'));
        $search = trim((string) $request->query('search', ''));
        $operator = $this->caseInsensitiveOperator();

        $query = LibraryItem::query()
            ->where('status', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($activeCategory !== null) {
            $query->where('category_code', $activeCategory);
        }

        if ($search !== '') {
            $keyword = '%' . $search . '%';
            $query->where(function ($builder) use ($keyword, $operator) {
                $builder->where('title', $operator, $keyword)
                    ->orWhere('original_name', $operator, $keyword);
            });
        }

        $items = $query->paginate(10)->withQueryString();

        $categoryCounts = LibraryItem::query()
            ->where('status', true)
            ->selectRaw('category_code, COUNT(*) as aggregate')
            ->groupBy('category_code')
            ->pluck('aggregate', 'category_code');

        return view('corsec::library.index', [
            'items' => $items,
            'categoryOptions' => LibraryItem::categoryOptions(),
            'categoryCounts' => $categoryCounts,
            'activeCategory' => $activeCategory,
            'canManageLibrary' => $this->canManageLibrary($user),
            'currentRouteName' => $routeName,
            'pageTitle' => $title,
            'pageDescription' => $description,
            'search' => $search,
            'breadcrumbName' => $routeName,
        ]);
    }

    private function canManageLibrary(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasRole('administrator')) {
            return (bool) ($user->can('corsec.create') ?? true);
        }

        return (bool) ($user->can('corsec.create') ?? false)
            && $this->permissionService->isCorpSecretaryDirectorate($user);
    }

    private function ensureCanManageLibrary(?User $user): void
    {
        if (!$this->canManageLibrary($user)) {
            abort(403, 'Hanya user Corporate Secretary dengan akses corsec.create yang dapat mengelola daftar pustaka.');
        }
    }

    private function resolveCategoryCode(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return array_key_exists($value, LibraryItem::categoryOptions()) ? $value : null;
    }

    private function resolveReturnRouteName(?string $routeName): string
    {
        $allowedRoutes = ['library.index', 'library.guideline.index'];

        return in_array($routeName, $allowedRoutes, true) ? $routeName : 'library.index';
    }

    private function redirectToListing(string $routeName, string $categoryCode, string $message): RedirectResponse
    {
        if ($routeName === 'library.guideline.index') {
            return redirect()->route('library.guideline.index')->with('success', $message);
        }

        return redirect()->route('library.index', ['category' => $categoryCode])->with('success', $message);
    }

    private function resolveTitle(string $originalName): string
    {
        $title = trim((string) pathinfo($originalName, PATHINFO_FILENAME));

        return $title !== '' ? $title : 'Library Document';
    }

    private function caseInsensitiveOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    private function deleteStoredFile(string $disk, string $path): void
    {
        try {
            if ($path !== '' && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed deleting library file', [
                'disk' => $disk,
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
