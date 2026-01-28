<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\OutgoingLetter;
use Modules\Corsec\Models\Sender;
use Modules\Corsec\Services\OutgoingLetterWorkflowService;

class OutgoingLetterController extends Controller
{
    public function __construct(
        private readonly OutgoingLetterWorkflowService $workflow
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->authorizeRead();
        return view('corsec::letter.outgoing.index');
    }

    public function datatables(Request $request)
    {
        $this->authorizeRead();

        $query = OutgoingLetter::query()
            ->with(['requesterDirectorate', 'recipient'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'ilike', "%{$search}%")
                    ->orWhere('registration_no', 'ilike', "%{$search}%")
                    ->orWhere('letter_no', 'ilike', "%{$search}%");
            });
        }

        $totalRecords = OutgoingLetter::count();
        $filteredRecords = (clone $query)->count();

        $sortField = (string) $request->get('sortField', 'created_at');
        $sortOrder = (string) $request->get('sortOrder', 'desc');
        $allowedSort = ['created_at', 'registration_no', 'order_date', 'status'];
        if (!in_array($sortField, $allowedSort, true)) {
            $sortField = 'created_at';
        }
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }
        $query->orderBy($sortField, $sortOrder);

        $page = max((int) $request->get('page', 1), 1);
        $size = max((int) $request->get('size', 10), 1);
        $offset = ($page - 1) * $size;

        $data = $query->skip($offset)->take($size)->get();
        $pageCount = (int) ceil($filteredRecords / $size);

        return response()->json([
            'draw' => $request->get('draw'),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'pageCount' => $pageCount,
            'page' => $page,
            'totalCount' => $totalRecords,
            'data' => $data,
        ]);
    }

    public function create()
    {
        $this->authorizeCreate();
        $senders = Sender::query()->orderBy('name')->get(['id', 'name']);
        $incomingLetters = IncomingLetter::query()->orderByDesc('id')->get(['id', 'registration_no', 'subject']);
        return view('corsec::letter.outgoing.create', compact('senders', 'incomingLetters'));
    }

    public function store(Request $request)
    {
        $this->authorizeCreate();

        $request->validate([
            'order_date' => ['required', 'date'],
            'recipient_id' => ['required', 'string'],
            'recipient_other' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'perihal_type' => ['required', 'string', 'max:50'],
            'perihal_incoming_letter_id' => ['nullable', 'exists:corsec_incoming_letters,id'],
            'perihal_text' => ['nullable', 'string', 'max:255'],
            'need_compliance_review' => ['required', 'boolean'],
            'note' => ['nullable', 'string'],
            'draft_file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $user = Auth::user();
        $recipientId = $request->input('recipient_id');
        $recipientName = null;

        if ($recipientId === 'other') {
            $request->validate([
                'recipient_other' => ['required', 'string', 'max:150'],
            ]);
            $recipientName = $request->recipient_other;
        } else {
            $request->validate([
                'recipient_id' => ['required', Rule::exists('corsec_senders', 'id')],
            ]);
            $recipientName = Sender::query()->whereKey($recipientId)->value('name');
        }

        if ($request->perihal_type === 'tanggapan_surat_masuk' && !$request->perihal_incoming_letter_id) {
            throw ValidationException::withMessages([
                'perihal_incoming_letter_id' => 'Pilih surat masuk untuk tanggapan.',
            ]);
        }
        if (in_array($request->perihal_type, ['rutinitas', 'insidentil'], true) && !$request->perihal_text) {
            throw ValidationException::withMessages([
                'perihal_text' => 'Perihal wajib diisi.',
            ]);
        }

        $letter = DB::transaction(function () use ($request, $user, $recipientId, $recipientName) {
            $letter = OutgoingLetter::create([
                'order_date' => $request->order_date,
                'recipient_id' => $recipientId === 'other' ? null : $recipientId,
                'recipient_other' => $recipientId === 'other' ? $request->recipient_other : null,
                'subject' => $request->subject,
                'summary' => $request->summary,
                'perihal_type' => $request->perihal_type,
                'perihal_incoming_letter_id' => $request->perihal_incoming_letter_id,
                'perihal_text' => $request->perihal_text,
                'note' => $request->note,
                'need_compliance_review' => (bool) $request->need_compliance_review,
                'requester_directorate_id' => $user?->directorate_id,
                'status' => OutgoingLetter::STATUS_DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if (!$letter->registration_no) {
                $letter->update([
                    'registration_no' => 'OUT-' . now()->format('Ymd') . '-' . str_pad((string) $letter->id, 6, '0', STR_PAD_LEFT),
                ]);
            }

            $draft = $request->file('draft_file');
            $path = $draft->store('corsec/outgoing/draft', 'public');
            $attachment = Attachment::create([
                'disk' => 'public',
                'path' => $path,
                'original_name' => $draft->getClientOriginalName(),
                'file_name' => basename($path),
                'mime' => $draft->getClientMimeType(),
                'size' => $draft->getSize(),
                'created_by' => $user->id,
            ]);

            $letter->update(['draft_attachment_id' => $attachment->id]);

            return $letter;
        });

        if ($request->boolean('submit_for_approval', true)) {
            $this->workflow->submitForDirectorateApproval($letter, $user);
        }

        return redirect()->route('letter.outgoing.show', $letter)->with('success', 'Surat keluar tersimpan.');
    }

    public function show(OutgoingLetter $outgoingLetter)
    {
        $this->authorizeRead();

        $approvals = Approval::query()
            ->where('approvable_type', OutgoingLetter::class)
            ->where('approvable_id', $outgoingLetter->id)
            ->with(['actor.directorate', 'actor.position'])
            ->orderByDesc('acted_at')
            ->orderByDesc('created_at')
            ->get();

        $senders = Sender::query()->orderBy('name')->get(['id', 'name']);
        $incomingLetters = IncomingLetter::query()->orderByDesc('id')->get(['id', 'registration_no', 'subject']);

        return view('corsec::letter.outgoing.show', compact('outgoingLetter', 'approvals', 'senders', 'incomingLetters'));
    }

    public function edit(OutgoingLetter $outgoingLetter)
    {
        $this->authorizeUpdate();
        if (!in_array($outgoingLetter->status, [OutgoingLetter::STATUS_DRAFT, OutgoingLetter::STATUS_RETURNED], true)) {
            abort(403, 'Surat keluar tidak dapat diubah pada status ini.');
        }
        $senders = Sender::query()->orderBy('name')->get(['id', 'name']);
        $incomingLetters = IncomingLetter::query()->orderByDesc('id')->get(['id', 'registration_no', 'subject']);
        return view('corsec::letter.outgoing.create', compact('outgoingLetter', 'senders', 'incomingLetters'));
    }

    public function update(Request $request, OutgoingLetter $outgoingLetter)
    {
        $this->authorizeUpdate();
        if (!in_array($outgoingLetter->status, [OutgoingLetter::STATUS_DRAFT, OutgoingLetter::STATUS_RETURNED], true)) {
            abort(403, 'Surat keluar tidak dapat diubah pada status ini.');
        }

        $request->validate([
            'order_date' => ['required', 'date'],
            'recipient_id' => ['required', 'string'],
            'recipient_other' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'perihal_type' => ['required', 'string', 'max:50'],
            'perihal_incoming_letter_id' => ['nullable', 'exists:corsec_incoming_letters,id'],
            'perihal_text' => ['nullable', 'string', 'max:255'],
            'need_compliance_review' => ['required', 'boolean'],
            'note' => ['nullable', 'string'],
            'draft_file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $recipientId = $request->input('recipient_id');
        if ($recipientId === 'other') {
            $request->validate([
                'recipient_other' => ['required', 'string', 'max:150'],
            ]);
        }

        if ($request->perihal_type === 'tanggapan_surat_masuk' && !$request->perihal_incoming_letter_id) {
            throw ValidationException::withMessages([
                'perihal_incoming_letter_id' => 'Pilih surat masuk untuk tanggapan.',
            ]);
        }
        if (in_array($request->perihal_type, ['rutinitas', 'insidentil'], true) && !$request->perihal_text) {
            throw ValidationException::withMessages([
                'perihal_text' => 'Perihal wajib diisi.',
            ]);
        }

        $user = Auth::user();
        DB::transaction(function () use ($request, $outgoingLetter, $user, $recipientId) {
            $outgoingLetter->update([
                'order_date' => $request->order_date,
                'recipient_id' => $recipientId === 'other' ? null : $recipientId,
                'recipient_other' => $recipientId === 'other' ? $request->recipient_other : null,
                'subject' => $request->subject,
                'summary' => $request->summary,
                'perihal_type' => $request->perihal_type,
                'perihal_incoming_letter_id' => $request->perihal_incoming_letter_id,
                'perihal_text' => $request->perihal_text,
                'note' => $request->note,
                'need_compliance_review' => (bool) $request->need_compliance_review,
                'updated_by' => $user->id,
            ]);

            if ($request->hasFile('draft_file')) {
                $draft = $request->file('draft_file');
                $path = $draft->store('corsec/outgoing/draft', 'public');
                $attachment = Attachment::create([
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => $draft->getClientOriginalName(),
                    'file_name' => basename($path),
                    'mime' => $draft->getClientMimeType(),
                    'size' => $draft->getSize(),
                    'created_by' => $user->id,
                ]);
                $outgoingLetter->update(['draft_attachment_id' => $attachment->id]);
            }
        });

        return redirect()->route('letter.outgoing.show', $outgoingLetter)->with('success', 'Surat keluar diupdate.');
    }

    public function submit(OutgoingLetter $outgoingLetter)
    {
        $this->authorizeUpdate();
        $this->workflow->submitForDirectorateApproval($outgoingLetter, Auth::user());
        return back()->with('success', 'Surat keluar diajukan untuk approval.');
    }

    public function approvalAction(Request $request, OutgoingLetter $outgoingLetter)
    {
        $request->validate([
            'action' => ['required', 'in:approve,reject,return'],
            'note' => ['nullable', 'string'],
        ]);

        if ($outgoingLetter->status === OutgoingLetter::STATUS_WAITING_DIR_APPROVAL) {
            $this->workflow->handleDirectorateApproval($outgoingLetter, Auth::user(), $request->string('action'), $request->note);
            return back()->with('success', 'Approval direktorat diproses.');
        }

        if ($outgoingLetter->status === OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL) {
            $this->workflow->handleComplianceApproval($outgoingLetter, Auth::user(), $request->string('action'), $request->note);
            return back()->with('success', 'Approval kepatuhan diproses.');
        }

        return back()->withErrors(['action' => 'Approval tidak sesuai status.']);
    }

    public function complianceReview(Request $request, OutgoingLetter $outgoingLetter)
    {
        $request->validate([
            'compliance_draft' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'note' => ['nullable', 'string'],
        ]);

        $file = $request->file('compliance_draft');
        $path = $file->store('corsec/outgoing/compliance', 'public');
        $attachment = Attachment::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'created_by' => Auth::id(),
        ]);

        $this->workflow->submitComplianceReview($outgoingLetter, Auth::user(), $attachment, $request->note);

        return back()->with('success', 'Review kepatuhan dikirim.');
    }

    public function numbering(Request $request, OutgoingLetter $outgoingLetter)
    {
        $request->validate([
            'letter_no' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->setNumberAndSend($outgoingLetter, Auth::user(), $request->letter_no, $request->note);
        return back()->with('success', 'Nomor surat tersimpan.');
    }

    public function uploadFinal(Request $request, OutgoingLetter $outgoingLetter)
    {
        $request->validate([
            'final_file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $file = $request->file('final_file');
        $path = $file->store('corsec/outgoing/final', 'public');
        $attachment = Attachment::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'created_by' => Auth::id(),
        ]);

        $this->workflow->uploadFinal($outgoingLetter, Auth::user(), $attachment);
        return back()->with('success', 'Final surat diupload.');
    }

    public function verifyAction(Request $request, OutgoingLetter $outgoingLetter)
    {
        $request->validate([
            'action' => ['required', 'in:verify,return,approve,reject'],
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->verifyAction($outgoingLetter, Auth::user(), $request->string('action'), $request->note);
        return back()->with('success', 'Verifikasi diproses.');
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
            abort(403, 'Sorry! You are not allowed to create outgoing letters.');
        }
    }

    private function authorizeUpdate(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.update')) {
            abort(403, 'Sorry! You are not allowed to update outgoing letters.');
        }
    }
}
