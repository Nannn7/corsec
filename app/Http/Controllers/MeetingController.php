<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Corsec\Models\Attachable;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\Comment;
use Modules\Corsec\Models\DecisionUpdate;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\MeetingAgenda;
use Modules\Corsec\Models\MeetingDecision;
use Modules\Corsec\Models\MeetingMaterial;
use Modules\Corsec\Models\MeetingMinutes;
use Modules\Corsec\Models\MeetingParticipant;
use Modules\Corsec\Services\MeetingWorkflowService;
use Modules\Usermanagement\Models\User;

class MeetingController extends Controller
{
    public function __construct(
        private readonly MeetingWorkflowService $workflow
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->authorizeRead();

        $user = Auth::user();
        $query = $this->scopedMeetingsQuery($user)
            ->withCount(['participants', 'agendas'])
            ->latest('meeting_at')
            ->latest('id');
        $meetings = $query->paginate(20);

        $summaryBase = $this->scopedMeetingsQuery($user);
        $summary = [
            'total' => (clone $summaryBase)->count(),
            'waiting_corsec_approval' => (clone $summaryBase)->where('status', Meeting::STATUS_WAITING_CORSEC_APPROVAL)->count(),
            'waiting_direktorat_approval' => (clone $summaryBase)->where('status', Meeting::STATUS_WAITING_DIREKTORAT_APPROVAL)->count(),
            'followup_open' => (clone $summaryBase)->where('status', Meeting::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT)->count(),
            'done_followup' => (clone $summaryBase)->where('status', Meeting::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT)->count(),
        ];

        return view('corsec::meeting.index', [
            'meetings' => $meetings,
            'summary' => $summary,
            'statusLabels' => Meeting::statusLabels(),
            'typeOptions' => Meeting::typeOptions(),
        ]);
    }

    public function create()
    {
        $this->authorizeCreate();

        return redirect()->route('meeting.index')->with(
            'info',
            'Form UI meeting masih bertahap. Gunakan endpoint backend untuk create/update flow rapat.'
        );
    }

    public function store(Request $request)
    {
        $this->authorizeCreate();
        $payload = $this->validateMeetingPayload($request);
        $user = Auth::user();

        $meeting = DB::transaction(function () use ($payload, $user) {
            $meeting = Meeting::create([
                'title' => $payload['title'],
                'meeting_type' => $payload['meeting_type'],
                'meeting_at' => $this->buildMeetingAt($payload['meeting_date'], $payload['meeting_time'] ?? null),
                'location' => $payload['location'] ?? null,
                'description' => $payload['description'] ?? null,
                'status' => Meeting::STATUS_DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->syncParticipants($meeting, (array) ($payload['participants'] ?? []), $user);
            $this->syncAgendas($meeting, (array) ($payload['agendas'] ?? []));

            return $meeting;
        });

        if ((bool) ($payload['submit_for_approval'] ?? false)) {
            $this->workflow->submitPlan($meeting, $user, $payload['submit_note'] ?? null);
        }

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            (bool) ($payload['submit_for_approval'] ?? false)
                ? 'Rencana meeting berhasil dibuat dan dikirim untuk approval.'
                : 'Rencana meeting berhasil dibuat sebagai draft.'
        );
    }

    public function show(Request $request, Meeting $meeting)
    {
        $this->authorizeRead();
        $user = Auth::user();
        if (!$this->canSeeMeeting($meeting, $user)) {
            abort(403, 'Anda tidak memiliki akses melihat meeting ini.');
        }

        $meeting->load([
            'participants.directorate',
            'agendas.ownerDirectorate',
            'materials.attachment',
            'minutes.minutesAttachment',
            'minutes.finalMinutesAttachment',
            'decisions.ownerDirectorate',
            'decisions.updates.updater',
            'decisions.updates.attachables.attachment',
            'comments.createdBy',
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $meeting,
                'status_labels' => Meeting::statusLabels(),
                'type_options' => Meeting::typeOptions(),
            ]);
        }

        return redirect()->route('meeting.index')->with('info', 'Detail meeting tersedia via endpoint JSON.');
    }

    public function edit(Meeting $meeting)
    {
        $this->authorizeUpdate();
        $user = Auth::user();
        if (!$this->canEditMeeting($meeting, $user)) {
            abort(403, 'Meeting tidak dapat diubah pada status saat ini.');
        }

        return redirect()->route('meeting.index')->with(
            'info',
            'Form edit meeting masih bertahap. Gunakan endpoint backend update.'
        );
    }

    public function update(Request $request, Meeting $meeting)
    {
        $this->authorizeUpdate();
        $user = Auth::user();
        if (!$this->canEditMeeting($meeting, $user)) {
            abort(403, 'Meeting tidak dapat diubah pada status saat ini.');
        }

        $payload = $this->validateMeetingPayload($request);

        DB::transaction(function () use ($meeting, $payload, $user) {
            $meeting->update([
                'title' => $payload['title'],
                'meeting_type' => $payload['meeting_type'],
                'meeting_at' => $this->buildMeetingAt($payload['meeting_date'], $payload['meeting_time'] ?? null),
                'location' => $payload['location'] ?? null,
                'description' => $payload['description'] ?? null,
                'updated_by' => $user->id,
            ]);

            $this->syncParticipants($meeting, (array) ($payload['participants'] ?? []), $user);
            $this->syncAgendas($meeting, (array) ($payload['agendas'] ?? []));
        });

        if ((bool) ($payload['submit_for_approval'] ?? false)) {
            $this->workflow->submitPlan($meeting, $user, $payload['submit_note'] ?? null);
        }

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            (bool) ($payload['submit_for_approval'] ?? false)
                ? 'Meeting berhasil diupdate dan dikirim untuk approval.'
                : 'Meeting berhasil diupdate.'
        );
    }

    public function destroy(Meeting $meeting)
    {
        $this->authorizeDelete();
        $user = Auth::user();
        if (!$this->canDeleteMeeting($meeting, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Meeting hanya bisa dihapus oleh pembuat (Draft/Returned) atau administrator.',
            ], 403);
        }

        DB::transaction(function () use ($meeting, $user) {
            $meeting->update(['deleted_by' => $user->id]);
            $meeting->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Meeting berhasil dihapus.',
        ]);
    }

    public function submit(Request $request, Meeting $meeting)
    {
        $this->authorizeUpdate();
        $user = Auth::user();
        if (!$this->canEditMeeting($meeting, $user)) {
            abort(403, 'Meeting tidak dapat disubmit pada status saat ini.');
        }

        $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->submitPlan($meeting, $user, $request->input('note'));

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            'Rencana meeting berhasil dikirim untuk approval.'
        );
    }

    public function corsecApproval(Request $request, Meeting $meeting)
    {
        $this->authorizeAuthorize();
        $request->validate([
            'action' => ['required', 'in:approve,return'],
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->handleCorsecApproval(
            $meeting,
            Auth::user(),
            (string) $request->input('action'),
            $request->input('note')
        );

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            'Approval EO + Kepala Corsec berhasil diproses.'
        );
    }

    public function directorateSubmit(Request $request, Meeting $meeting)
    {
        $this->authorizeUpdate();
        $request->validate([
            'note' => ['nullable', 'string'],
            'material_agenda_id' => ['nullable', 'exists:corsec_meeting_agendas,id'],
            'material_files' => ['nullable', 'array'],
            'material_files.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx'],
            'additional_agendas' => ['nullable', 'array'],
            'additional_agendas.*.title' => ['required_with:additional_agendas', 'string', 'max:255'],
            'additional_agendas.*.description' => ['nullable', 'string'],
            'additional_agendas.*.owner_directorate_id' => ['nullable', 'exists:corsec_directorates,id'],
        ]);

        $user = Auth::user();
        DB::transaction(function () use ($request, $meeting, $user) {
            $this->appendAgendas($meeting, (array) $request->input('additional_agendas', []));
            $this->storeMeetingMaterials(
                $meeting,
                (array) $request->file('material_files', []),
                $user,
                $request->filled('material_agenda_id') ? (int) $request->input('material_agenda_id') : null
            );

            $note = trim((string) $request->input('note'));
            if ($note !== '') {
                Comment::create([
                    'commentable_type' => Meeting::class,
                    'commentable_id' => $meeting->id,
                    'body' => '[PERSIAPAN DIREKTORAT] ' . $note,
                    'created_by' => $user->id,
                ]);
            }
        });

        $this->workflow->submitDirectoratePreparation($meeting, $user, $request->input('note'));

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            'Persiapan rapat direktorat berhasil disubmit untuk approval.'
        );
    }

    public function markPendingDirectorate(Request $request, Meeting $meeting)
    {
        $this->authorizeUpdate();
        $this->workflow->markPendingDirectorate($meeting, Auth::user());

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            'Status meeting berhasil diubah ke pending direktorat.'
        );
    }

    public function directorateApproval(Request $request, Meeting $meeting)
    {
        $this->authorizeAuthorize();
        $request->validate([
            'action' => ['required', 'in:approve,return'],
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->handleDirectorateApproval(
            $meeting,
            Auth::user(),
            (string) $request->input('action'),
            $request->input('note')
        );

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            'Approval EO + DD Direktorat berhasil diproses.'
        );
    }

    public function saveMinutes(Request $request, Meeting $meeting)
    {
        $this->authorizeUpdate();
        $request->validate([
            'minutes_text' => ['required', 'string'],
            'minutes_file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx'],
            'note' => ['nullable', 'string'],
            'submit_for_signature' => ['nullable', 'boolean'],
            'decisions' => ['nullable', 'array'],
            'decisions.*.id' => ['nullable', 'integer'],
            'decisions.*.decision_text' => ['required_with:decisions', 'string'],
            'decisions.*.owner_directorate_id' => ['nullable', 'exists:corsec_directorates,id'],
            'decisions.*.target_date' => ['nullable', 'date'],
        ]);

        $user = Auth::user();
        DB::transaction(function () use ($request, $meeting, $user) {
            $minutes = MeetingMinutes::query()->firstOrNew(['meeting_id' => $meeting->id]);
            $minutes->meeting_id = $meeting->id;
            $minutes->minutes_text = (string) $request->input('minutes_text');
            $minutes->status = MeetingMinutes::STATUS_DRAFT;
            $minutes->save();

            $minutesFile = $request->file('minutes_file');
            if ($minutesFile instanceof UploadedFile) {
                $attachment = $this->storeAttachment($minutesFile, $user, 'corsec/meeting/minutes');
                $minutes->minutes_attachment_id = $attachment->id;
                $minutes->save();
            }

            foreach ((array) $request->input('decisions', []) as $decisionPayload) {
                $decisionText = trim((string) ($decisionPayload['decision_text'] ?? ''));
                if ($decisionText === '') {
                    continue;
                }

                $decisionId = (int) ($decisionPayload['id'] ?? 0);
                if ($decisionId > 0) {
                    $existingDecision = MeetingDecision::query()
                        ->where('meeting_id', $meeting->id)
                        ->where('id', $decisionId)
                        ->first();

                    if ($existingDecision) {
                        $existingDecision->update([
                            'decision_text' => $decisionText,
                            'owner_directorate_id' => $decisionPayload['owner_directorate_id'] ?? null,
                            'target_date' => $decisionPayload['target_date'] ?? null,
                            'updated_by' => $user->id,
                        ]);
                        continue;
                    }
                }

                MeetingDecision::create([
                    'meeting_id' => $meeting->id,
                    'decision_text' => $decisionText,
                    'owner_directorate_id' => $decisionPayload['owner_directorate_id'] ?? null,
                    'target_date' => $decisionPayload['target_date'] ?? null,
                    'status' => MeetingDecision::STATUS_PENDING,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }
        });

        $this->workflow->startMinutes($meeting, $user);

        if ($request->boolean('submit_for_signature', false)) {
            MeetingMinutes::query()
                ->where('meeting_id', $meeting->id)
                ->update([
                    'status' => MeetingMinutes::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                    'submitted_by' => $user->id,
                    'circulated_at' => now(),
                ]);
            $this->workflow->circulateMinutes($meeting, $user);
        }

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            'Notulen rapat berhasil disimpan.'
        );
    }

    public function finalizeMinutes(Request $request, Meeting $meeting)
    {
        $this->authorizeUpdate();
        $request->validate([
            'final_minutes_file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx'],
            'note' => ['nullable', 'string'],
        ]);

        $user = Auth::user();
        DB::transaction(function () use ($request, $meeting, $user) {
            $minutes = MeetingMinutes::query()->firstOrNew(['meeting_id' => $meeting->id]);
            $minutes->meeting_id = $meeting->id;

            $attachment = $this->storeAttachment(
                $request->file('final_minutes_file'),
                $user,
                'corsec/meeting/final_minutes'
            );

            $minutes->final_minutes_attachment_id = $attachment->id;
            $minutes->status = MeetingMinutes::STATUS_APPROVED;
            $minutes->approved_at = now();
            $minutes->approved_by = $user->id;
            $minutes->finalized_at = now();
            $minutes->save();
        });

        $this->workflow->finalizeMinutes($meeting, $user);

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            'Notulen final berhasil diupload.'
        );
    }

    public function submitDecisionUpdate(Request $request, Meeting $meeting, MeetingDecision $decision)
    {
        $this->authorizeUpdate();
        if ((int) $decision->meeting_id !== (int) $meeting->id) {
            abort(404, 'Decision tidak sesuai dengan meeting.');
        }

        $request->validate([
            'update_type' => ['required', Rule::in([DecisionUpdate::TYPE_PROGRESS, DecisionUpdate::TYPE_DONE])],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'happened_at' => ['required', 'date'],
            'is_on_target' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'evidence_files' => ['required', 'array', 'min:1'],
            'evidence_files.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx'],
        ]);

        $user = Auth::user();
        $updateType = (string) $request->input('update_type');
        $progress = $request->filled('progress_percent')
            ? (int) $request->input('progress_percent')
            : ($updateType === DecisionUpdate::TYPE_DONE ? 100 : 0);

        DB::transaction(function () use ($request, $meeting, $decision, $user, $updateType, $progress) {
            $update = DecisionUpdate::create([
                'meeting_decision_id' => $decision->id,
                'progress_percent' => $progress,
                'update_type' => $updateType,
                'status' => $updateType === DecisionUpdate::TYPE_DONE
                    ? DecisionUpdate::STATUS_DONE
                    : DecisionUpdate::STATUS_IN_PROGRESS,
                'note' => $request->input('note'),
                'happened_at' => $request->input('happened_at'),
                'is_on_target' => $request->filled('is_on_target')
                    ? (bool) $request->boolean('is_on_target')
                    : null,
                'reason' => $request->input('reason'),
                'updated_by' => $user->id,
                'authorized_status' => 'authorized',
                'authorized_at' => now(),
                'authorized_by' => $user->id,
            ]);

            foreach ((array) $request->file('evidence_files', []) as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }

                $attachment = $this->storeAttachment($file, $user, 'corsec/meeting/followup');
                Attachable::create([
                    'attachment_id' => $attachment->id,
                    'attachable_type' => DecisionUpdate::class,
                    'attachable_id' => $update->id,
                    'category' => 'followup_evidence',
                    'created_by' => $user->id,
                ]);
            }

            $decision->update([
                'status' => $updateType === DecisionUpdate::TYPE_DONE
                    ? MeetingDecision::STATUS_DONE
                    : MeetingDecision::STATUS_IN_PROGRESS,
                'closed_at' => $updateType === DecisionUpdate::TYPE_DONE ? now() : null,
                'updated_by' => $user->id,
            ]);

            $this->workflow->startFollowup($meeting, $user);
            $this->workflow->syncFollowupStatusFromDecisions($meeting, $user);
        });

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            'Update tindaklanjut hasil rapat berhasil disimpan.'
        );
    }

    public function completeFollowup(Request $request, Meeting $meeting)
    {
        $this->authorizeUpdate();
        $this->workflow->completeFollowup($meeting, Auth::user());

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            'Tindaklanjut hasil rapat dinyatakan selesai.'
        );
    }

    private function validateMeetingPayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'meeting_type' => ['required', Rule::in(array_keys(Meeting::typeOptions()))],
            'meeting_date' => ['required', 'date'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['nullable', 'exists:corsec_directorates,id'],
            'agendas' => ['nullable', 'array'],
            'agendas.*.title' => ['required_with:agendas', 'string', 'max:255'],
            'agendas.*.description' => ['nullable', 'string'],
            'agendas.*.owner_directorate_id' => ['nullable', 'exists:corsec_directorates,id'],
            'submit_for_approval' => ['nullable', 'boolean'],
            'submit_note' => ['nullable', 'string'],
        ]);
    }

    private function buildMeetingAt(string $meetingDate, ?string $meetingTime): string
    {
        return trim($meetingDate . ' ' . ($meetingTime ?: '00:00') . ':00');
    }

    private function syncParticipants(Meeting $meeting, array $participantIds, User $user): void
    {
        $cleanIds = collect($participantIds)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        MeetingParticipant::query()->where('meeting_id', $meeting->id)->delete();
        foreach ($cleanIds as $directorateId) {
            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'directorate_id' => $directorateId,
                'created_by' => $user->id,
            ]);
        }
    }

    private function syncAgendas(Meeting $meeting, array $agendas): void
    {
        MeetingAgenda::query()->where('meeting_id', $meeting->id)->delete();

        $orderNo = 1;
        foreach ($agendas as $agendaPayload) {
            $title = trim((string) ($agendaPayload['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            MeetingAgenda::create([
                'meeting_id' => $meeting->id,
                'order_no' => $orderNo++,
                'title' => $title,
                'description' => $agendaPayload['description'] ?? null,
                'owner_directorate_id' => $agendaPayload['owner_directorate_id'] ?? null,
            ]);
        }
    }

    private function appendAgendas(Meeting $meeting, array $agendas): void
    {
        $nextOrder = (int) MeetingAgenda::query()->where('meeting_id', $meeting->id)->max('order_no');
        $nextOrder = $nextOrder > 0 ? $nextOrder + 1 : 1;

        foreach ($agendas as $agendaPayload) {
            $title = trim((string) ($agendaPayload['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            MeetingAgenda::create([
                'meeting_id' => $meeting->id,
                'order_no' => $nextOrder++,
                'title' => $title,
                'description' => $agendaPayload['description'] ?? null,
                'owner_directorate_id' => $agendaPayload['owner_directorate_id'] ?? null,
            ]);
        }
    }

    private function storeMeetingMaterials(Meeting $meeting, array $files, User $user, ?int $agendaId = null): void
    {
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $attachment = $this->storeAttachment($file, $user, 'corsec/meeting/materials');
            MeetingMaterial::create([
                'meeting_id' => $meeting->id,
                'agenda_id' => $agendaId,
                'attachment_id' => $attachment->id,
                'uploaded_by' => $user->id,
                'uploaded_at' => now(),
            ]);
        }
    }

    private function storeAttachment(UploadedFile $file, User $user, string $folder): Attachment
    {
        $path = $file->store($folder, 'public');

        return Attachment::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'created_by' => $user->id,
        ]);
    }

    private function canViewAllMeetings(User $user): bool
    {
        return $user->hasRole('administrator') || $user->hasRole('checker') || $user->hasRole('approver');
    }

    private function scopedMeetingsQuery(User $user)
    {
        $query = Meeting::query();
        if ($this->canViewAllMeetings($user)) {
            return $query;
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        return $query->where(function ($w) use ($user, $directorateId) {
            $w->where('created_by', $user->id);
            if ($directorateId > 0) {
                $w->orWhereHas('participants', function ($q) use ($directorateId) {
                    $q->where('directorate_id', $directorateId);
                });
            }
        });
    }

    private function canSeeMeeting(Meeting $meeting, User $user): bool
    {
        if ($this->canViewAllMeetings($user)) {
            return true;
        }

        if ((int) $meeting->created_by === (int) $user->id) {
            return true;
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        if ($directorateId <= 0) {
            return false;
        }

        return $meeting->participants()->where('directorate_id', $directorateId)->exists();
    }

    private function canEditMeeting(Meeting $meeting, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if (!in_array((string) $meeting->status, [
            Meeting::STATUS_DRAFT,
            Meeting::STATUS_RETURNED_BY_CORSEC,
            Meeting::STATUS_RETURNED_BY_DIREKTORAT,
        ], true)) {
            return false;
        }

        return (int) $meeting->created_by === (int) $user->id;
    }

    private function canDeleteMeeting(Meeting $meeting, User $user): bool
    {
        return $this->canEditMeeting($meeting, $user);
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
            abort(403, 'Sorry! You are not allowed to create meeting.');
        }
    }

    private function authorizeUpdate(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.update')) {
            abort(403, 'Sorry! You are not allowed to update meeting.');
        }
    }

    private function authorizeDelete(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.delete')) {
            abort(403, 'Sorry! You are not allowed to delete meeting.');
        }
    }

    private function authorizeAuthorize(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.authorize')) {
            abort(403, 'Sorry! You are not allowed to authorize meeting.');
        }
    }

    private function successRedirectResponse(Request $request, string $redirectUrl, string $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect()->to($redirectUrl)->with('success', $message);
    }
}
