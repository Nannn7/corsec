<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Corsec\Exports\IncomingLetterExport;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\IncomingLetterRoute;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\Attachable;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Comment;
use Modules\Corsec\Services\IncomingLetterWorkflowService;
use Modules\Basicdata\Models\Branch;
use Modules\Corsec\Models\Directorate;

class IncomingLetterController extends Controller
{
    public function __construct(
        private readonly IncomingLetterWorkflowService $workflow
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $q = IncomingLetter::query()
            ->with(['targetDirectorate'])
            ->latest();

        // filter
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('directorateid')) {
            $q->where('target_directorateid', $request->integer('directorateid'));
        }
        if ($request->filled('keyword')) {
            $kw = $request->string('keyword')->toString();
            $q->where(function ($w) use ($kw) {
                $w->where('subject', 'ilike', "%{$kw}%")
                    ->orWhere('sender', 'ilike', "%{$kw}%")
                    ->orWhere('external_letter_no', 'ilike', "%{$kw}%");
            });
        }

        // scope akses: selain admin/corsec, direktorat cuma liat yg ditargetkan ke directoratedia
        $user = Auth::user();
        if (!$user->hasRole('administrator')) {
            // kalau user bukan corsec directorate (asumsi corsec = directoratetertentu -> nanti bisa refine)
            // simple rule: user boleh lihat kalau:
            // - dia creator
            // - atau target directorate= directorateuser
            $q->where(function ($w) use ($user) {
                $w->where('created_by', $user->id)
                    ->orWhere('target_directorateid', $user->directorateid);
            });
        }

        $letters = $q->paginate(15)->withQueryString();
        $directorates = Directorate::query()->orderBy('name')->get(['id', 'name']);

        return view('corsec::letter.incoming.index', compact('letters', 'directorates'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $directorates = Directorate::query()->orderBy('name')->get(['id', 'name']);
        return view('corsec::letter.incoming.create', compact('directorates'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'external_letter_no' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'sender' => ['nullable', 'string', 'max:255'],
            'received_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'target_directorate_id' => ['nullable', 'exists:corsec_directorates,id'],
            'target_date' => ['nullable', 'date'],
            'files.*' => ['nullable', 'file', 'max:10240'], // 10MB
        ]);

        $user = auth()->user();
        $submitForApproval = $request->boolean('submit_for_approval', true);

        $letter = DB::transaction(function () use ($request, $user) {
            $letter = IncomingLetter::create([
                'external_letter_no' => $request->external_letter_no,
                'subject' => $request->subject,
                'sender' => $request->sender,
                'received_date' => $request->received_date,
                'priority' => $request->priority,
                'description' => $request->description,
                'target_directorate_id' => $request->target_directorate_id,
                'target_date' => $request->target_date,
                'status' => IncomingLetter::STATUS_DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // upload attachments
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('corsec/incoming', 'public');

                    $att = Attachment::create([
                        'disk' => 'public',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'file_name' => basename($path),
                        'mime' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'created_by' => $user->id,
                    ]);

                    Attachable::create([
                        'attachment_id' => $att->id,
                        'attachable_type' => IncomingLetter::class,
                        'attachable_id' => $letter->id,
                        'category' => 'incoming',
                        'created_by' => $user->id,
                    ]);
                }
            }

            return $letter;
        });

        if ($submitForApproval) {
            $this->workflow->submitToEoCorpAffair($letter, $user);
        }

        return redirect()
            ->route('letter.incoming.show', $letter)
            ->with('success', $submitForApproval
                ? 'Surat masuk berhasil dibuat dan diajukan untuk approval.'
                : 'Surat masuk berhasil dibuat.');
    }

    /**
     * Show the specified resource.
     */
    public function show(IncomingLetter $incomingLetter)
    {
        $incomingLetter->load([
            'targetBranch',
            'routes.fromBranch',
            'routes.toBranch',
            'routes.fromUser',
            'routes.toUser',
            'attachables.attachment',
            'comments.createdBy',
        ]);

        $approvals = Approval::query()
            ->where('approvable_type', IncomingLetter::class)
            ->where('approvable_id', $incomingLetter->id)
            ->latest()
            ->get();

        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);

        return view('corsec::letter.incoming.show', compact('incomingLetter', 'approvals', 'branches'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(IncomingLetter $incomingLetter)
    {
        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);
        return view('corsec::letter.incoming.edit', compact('incomingLetter', 'branches'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, IncomingLetter $incomingLetter)
    {
        $request->validate([
            'external_letter_no' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'sender' => ['nullable', 'string', 'max:255'],
            'received_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'target_directorate_id' => ['nullable', 'exists:corsec_directorates,id'],
            'target_date' => ['nullable', 'date'],
            'files.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $user = auth()->user();

        return DB::transaction(function () use ($request, $incomingLetter, $user) {
            $incomingLetter->update([
                'external_letter_no' => $request->external_letter_no,
                'subject' => $request->subject,
                'sender' => $request->sender,
                'received_date' => $request->received_date,
                'priority' => $request->priority,
                'description' => $request->description,
                'target_directorate_id' => $request->target_directorate_id,
                'target_date' => $request->target_date,
                'updated_by' => $user->id,
            ]);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('corsec/incoming', 'public');
                    $att = Attachment::create([
                        'disk' => 'public',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'file_name' => basename($path),
                        'mime' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'created_by' => $user->id,
                    ]);

                    Attachable::create([
                        'attachment_id' => $att->id,
                        'attachable_type' => IncomingLetter::class,
                        'attachable_id' => $incomingLetter->id,
                        'category' => 'incoming',
                        'created_by' => $user->id,
                    ]);
                }
            }

            return redirect()
                ->route('letter.incoming.show', $incomingLetter)
                ->with('success', 'Surat masuk berhasil diupdate.');
        });
    }

    public function datatables(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.read')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to view incoming letters.'
            ], 403);
        }

        try {
            $query = IncomingLetter::query()
                ->with(['targetDirectorate'])
                ->latest();

            // search (sesuai template: param "search")
            $search = trim((string) $request->get('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'ilike', "%{$search}%")
                        ->orWhere('sender', 'ilike', "%{$search}%")
                        ->orWhere('external_letter_no', 'ilike', "%{$search}%");
                });
            }

            // optional filter kalau nanti mau dipakai dari UI
            if ($request->filled('status')) {
                $query->where('status', $request->string('status')->toString());
            }
            if ($request->filled('directorate_id')) {
                $query->where('target_directorate_id', (int) $request->directorate_id);
            }

            // scope akses (copy dari index lo, biar konsisten)
            if (!$user->hasRole('administrator')) {
                $query->where(function ($w) use ($user) {
                    $w->where('created_by', $user->id)
                        ->orWhere('target_directorate_id', $user->directorate_id);
                });
            }

            // total counts
            $totalRecords = IncomingLetter::count();
            $filteredRecords = (clone $query)->count();

            // sorting (KTDataTable biasanya kirim sortField/sortOrder)
            $sortField = (string) $request->get('sortField', 'created_at');
            $sortOrder = (string) $request->get('sortOrder', 'desc');

            $allowedSort = [
                'external_letter_no',
                'subject',
                'sender',
                'status',
                'received_date',
                'target_date',
                'created_at',
            ];

            if (!in_array($sortField, $allowedSort, true)) {
                $sortField = 'created_at';
            }
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'], true)) {
                $sortOrder = 'desc';
            }

            $query->orderBy($sortField, $sortOrder);

            // paging (KTDataTable: page & size)
            $page = max((int) $request->get('page', 1), 1);
            $size = max((int) $request->get('size', 10), 1);
            $offset = ($page - 1) * $size;

            $data = $query->skip($offset)->take($size)->get();

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
            Log::error('IncomingLetter datatables error: ' . $e->getMessage(), [
                'user_id' => $user?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data surat masuk.'
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.export')) {
            abort(403, 'Sorry! You are not allowed to export incoming letters.');
        }

        $search = trim((string) $request->get('search', ''));

        return Excel::download(
            new IncomingLetterExport($search, $user),
            'incoming_letters_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function destroy(IncomingLetter $incomingLetter)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete incoming letters.'
            ], 403);
        }

        try {
            $letter = IncomingLetter::find($id);
            if (!$letter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat masuk tidak ditemukan.'
                ], 404);
            }

            // optional: scope akses delete
            if (!$user->hasRole('administrator')) {
                // minimal: hanya creator yg bisa delete
                if ((int)$letter->created_by !== (int)$user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak punya akses untuk menghapus surat ini.'
                    ], 403);
                }
            }

            DB::transaction(function () use ($letter, $user) {
                $letter->update(['deleted_by' => $user->id]);
                $letter->delete(); // soft delete
            });

            return response()->json([
                'success' => true,
                'message' => 'Surat masuk berhasil dihapus.'
            ]);
        } catch (Exception $e) {
            Log::error('IncomingLetter delete error: ' . $e->getMessage(), [
                'incoming_letter_id' => $id,
                'user_id' => $user?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus surat masuk.'
            ], 500);
        }
    }

    public function deleteMultiple(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry! You are not allowed to delete incoming letters.'
            ], 403);
        }

        $ids = $request->input('ids', []);
        if (!is_array($ids) || count($ids) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih minimal satu baris untuk dihapus.'
            ], 400);
        }

        try {
            DB::transaction(function () use ($ids, $user) {
                IncomingLetter::whereIn('id', $ids)->update(['deleted_by' => $user->id]);
                IncomingLetter::whereIn('id', $ids)->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Surat masuk terpilih berhasil dihapus.'
            ]);
        } catch (Exception $e) {
            Log::error('IncomingLetter delete multiple error: ' . $e->getMessage(), [
                'user_id' => $user?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus surat masuk terpilih.'
            ], 500);
        }
    }

    // Staff Corsec submit untuk approval EO Corp Affair
    public function submit(IncomingLetter $incomingLetter)
    {
        $this->workflow->submitToEoCorpAffair($incomingLetter, auth()->user());

        return back()->with('success', 'Surat masuk berhasil disubmit untuk approval.');
    }

    // Staff Corsec set sirkulasi/directorate target
    public function circulate(Request $request, IncomingLetter $incomingLetter)
    {
        $request->validate([
            'to_branch_id' => ['required', 'exists:branches,id'],
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->circulateToDirectorate(
            incomingLetter: $incomingLetter,
            actor: auth()->user(),
            toBranchId: (int)$request->to_branch_id,
            note: $request->note
        );

        return back()->with('success', 'Surat masuk berhasil disirkulasi ke direktorat.');
    }

    // Action approve/return untuk EO Corp Affair / EO+DD Direktorat
    public function approvalAction(Request $request, IncomingLetter $incomingLetter)
    {
        $request->validate([
            'action' => ['required', 'in:approve,return,reject'],
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->handleApprovalAction(
            incomingLetter: $incomingLetter,
            actor: auth()->user(),
            action: $request->string('action')->toString(),
            note: $request->note
        );

        return back()->with('success', 'Action approval berhasil diproses.');
    }

    // Staff Direktorat update tindak lanjut + upload bukti
    public function directorateUpdate(Request $request, IncomingLetter $incomingLetter)
    {
        $request->validate([
            'target_date' => ['nullable', 'date'],
            'followup_note' => ['nullable', 'string'],
            'evidence_files.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $this->workflow->directorateUpdate(
            incomingLetter: $incomingLetter,
            actor: auth()->user(),
            targetDate: $request->target_date,
            note: $request->followup_note,
            evidenceFiles: $request->file('evidence_files', [])
        );

        return back()->with('success', 'Update tindak lanjut berhasil disimpan.');
    }

    // EO Corp Affair verifikasi selesai
    public function verifyAction(Request $request, IncomingLetter $incomingLetter)
    {
        $request->validate([
            'action' => ['required', 'in:verify,return'],
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->verifyAction(
            incomingLetter: $incomingLetter,
            actor: auth()->user(),
            action: $request->string('action')->toString(),
            note: $request->note
        );

        return back()->with('success', 'Verifikasi berhasil diproses.');
    }

    // Direksi catatan
    public function directorNote(Request $request, IncomingLetter $incomingLetter)
    {
        $request->validate([
            'note' => ['required', 'string'],
        ]);

        Comment::create([
            'commentable_type' => IncomingLetter::class,
            'commentable_id' => $incomingLetter->id,
            'body' => '[CATATAN DIREKSI] ' . $request->note,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Catatan direksi tersimpan.');
    }
}
