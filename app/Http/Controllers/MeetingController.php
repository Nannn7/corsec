<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Corsec\Exports\DirectorateMinutesTemplateExport;
use Modules\Corsec\Exports\MeetingExport;
use Modules\Corsec\Models\Attachable;
use Modules\Corsec\Models\Attachment;
use Modules\Corsec\Models\Comment;
use Modules\Corsec\Models\DecisionUpdate;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\MeetingAgenda;
use Modules\Corsec\Models\MeetingDecision;
use Modules\Corsec\Models\MeetingDecisionOccurrence;
use Modules\Corsec\Models\MeetingMaterial;
use Modules\Corsec\Models\MeetingMinutes;
use Modules\Corsec\Models\MeetingParticipant;
use Modules\Corsec\Models\Directorate;
use Modules\Corsec\Models\Approval;
use Modules\Corsec\Services\CorsecPermissionService;
use Modules\Corsec\Services\MeetingWorkflowService;
use Modules\Corsec\Support\UploadRule;
use Modules\Usermanagement\Models\User;

class MeetingController extends Controller
{
    public function __construct(
        private readonly MeetingWorkflowService $workflow,
        private readonly CorsecPermissionService $permissionService
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->authorizeRead();

        $user = Auth::user();
        $summary = Cache::remember($this->meetingIndexSummaryCacheKey($user), now()->addSeconds(30), function () use ($user) {
            $summaryBase = $this->scopedMeetingsQuery($user);
            $summaryRow = (clone $summaryBase)
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw(
                    "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS waiting_corsec_approval",
                    [Meeting::STATUS_WAITING_CORSEC_APPROVAL]
                )
                ->selectRaw(
                    "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS waiting_direktorat_approval",
                    [Meeting::STATUS_WAITING_DIREKTORAT_APPROVAL]
                )
                ->selectRaw(
                    "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS followup_open",
                    [Meeting::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT]
                )
                ->selectRaw(
                    "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS done_followup",
                    [Meeting::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT]
                )
                ->selectRaw(
                    "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS not_conducted",
                    [Meeting::STATUS_CLOSED_NOT_CONDUCTED]
                )
                ->first();

            return [
                'total' => (int) ($summaryRow->total ?? 0),
                'waiting_corsec_approval' => (int) ($summaryRow->waiting_corsec_approval ?? 0),
                'waiting_direktorat_approval' => (int) ($summaryRow->waiting_direktorat_approval ?? 0),
                'followup_open' => (int) ($summaryRow->followup_open ?? 0),
                'done_followup' => (int) ($summaryRow->done_followup ?? 0),
                'not_conducted' => (int) ($summaryRow->not_conducted ?? 0),
            ];
        });
        $permissionFlags = $this->permissionService->meetingIndexFlags($user);

        return view('corsec::meeting.index', [
            'summary' => $summary,
            'statusLabels' => Meeting::statusLabels(),
            'typeOptions' => Meeting::typeOptions(),
            'permissionFlags' => $permissionFlags,
        ]);
    }

    public function tabulation(Request $request)
    {
        $this->authorizeRead();
        return redirect()->route('report.index', array_merge($request->query(), [
            'module' => 'meeting',
        ]));
    }

    public function datatables(Request $request)
    {
        $this->authorizeRead();

        try {
            $user = Auth::user();
            $query = $this->scopedMeetingsQuery($user)
                ->select([
                    'id',
                    'uuid',
                    'meeting_at',
                    'meeting_type',
                    'title',
                    'status',
                    'created_by',
                    'created_at',
                ]);

            $search = trim((string) $request->get('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ilike', '%' . $search . '%')
                        ->orWhere('meeting_type', 'ilike', '%' . $search . '%')
                        ->orWhere('status', 'ilike', '%' . $search . '%');

                    try {
                        $meetingDate = \Illuminate\Support\Carbon::parse($search)->toDateString();
                        $q->orWhereDate('meeting_at', $meetingDate);
                    } catch (\Throwable) {
                        // ignore invalid date search
                    }
                });
            }

            $isFiltered = $search !== '';
            $totalRecords = $this->scopedMeetingsQuery($user)->count();
            $filteredRecords = $isFiltered ? (clone $query)->count() : $totalRecords;

            $sortField = (string) $request->get('sortField', 'meeting_at');
            $sortOrder = strtolower((string) $request->get('sortOrder', 'desc'));

            $allowedSort = [
                'meeting_at',
                'meeting_type',
                'title',
                'status',
                'participants_count',
                'agendas_count',
                'created_at',
            ];
            if (!in_array($sortField, $allowedSort, true)) {
                $sortField = 'meeting_at';
            }
            if (!in_array($sortOrder, ['asc', 'desc'], true)) {
                $sortOrder = 'desc';
            }

            $query->withCount(['participants', 'agendas']);
            $query->orderBy($sortField, $sortOrder)->orderBy('id', 'desc');

            $page = max((int) $request->get('page', 1), 1);
            $size = max((int) $request->get('size', 10), 1);
            $offset = ($page - 1) * $size;

            $statusLabels = Meeting::statusLabels();
            $typeOptions = Meeting::typeOptions();
            $data = $query->forPage($page, $size)->get()->values()->map(
                function (Meeting $meeting, int $index) use ($offset, $statusLabels, $typeOptions) {
                    return [
                        'id' => (int) $meeting->id,
                        'uuid' => (string) $meeting->uuid,
                        'row_number' => $offset + $index + 1,
                        'meeting_at' => optional($meeting->meeting_at)->toDateTimeString(),
                        'meeting_type' => $meeting->meeting_type,
                        'meeting_type_label' => $typeOptions[$meeting->meeting_type] ?? ($meeting->meeting_type ?: '-'),
                        'title' => (string) ($meeting->title ?? '-'),
                        'status' => $meeting->status,
                        'status_label' => $statusLabels[$meeting->status] ?? ($meeting->status ?: '-'),
                        'participants_count' => (int) ($meeting->participants_count ?? 0),
                        'agendas_count' => (int) ($meeting->agendas_count ?? 0),
                        'created_by' => (int) ($meeting->created_by ?? 0),
                    ];
                }
            );

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
            Log::error('Meeting datatables error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data meeting.',
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.export')) {
            abort(403, 'Anda tidak memiliki akses untuk export meeting.');
        }

        $search = trim((string) $request->get('search', ''));

        return Excel::download(
            new MeetingExport($user, $search),
            'meetings_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function downloadDirectorateMinutesTemplate(Meeting $meeting)
    {
        $this->authorizeRead();
        $user = Auth::user();
        if (!$this->canSeeMeeting($meeting, $user)) {
            abort(403, 'Anda tidak memiliki akses melihat meeting ini.');
        }
        if (!$meeting->isDirektoratType()) {
            abort(404, 'Template notulen hanya tersedia untuk rapat direktorat.');
        }

        $meeting->loadMissing([
            'createdBy',
            'participants.directorate',
            'agendas.ownerDirectorate',
            'agendas.picUser',
            'agendas.sourceDecision.meeting',
            'agendas.minutesDecision',
            'minutes.submitter',
            'minutes.approver',
        ]);

        $export = new DirectorateMinutesTemplateExport($meeting);
        $filePath = $export->storeTemporaryFile();
        $fileName = 'notulen_direktorat_' . Str::slug((string) ($meeting->title ?: 'meeting')) . '_' . now()->format('Ymd_His') . '.xlsx';

        return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
    }

    public function create()
    {
        $this->authorizeCreate();
        $options = $this->meetingFormOptions();

        return view('corsec::meeting.create', [
            'typeOptions' => Meeting::typeOptionsFromMasterData(true),
            'direktoratTypeCodes' => Meeting::direktoratTypeCodes(),
            'mandatoryDirectorateIds' => $this->resolveMandatoryDirectorateIds(),
            ...$options,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeCreate();
        $payload = $this->validateMeetingPayload($request);
        $user = Auth::user();

        $meetingDates = collect((array) ($payload['meeting_dates'] ?? []))
            ->filter()
            ->map(fn($date) => (string) $date)
            ->unique()
            ->values();

        if ($meetingDates->isEmpty()) {
            $meetingDates = collect([(string) $payload['meeting_date']]);
        }

        $isDirektoratMeeting = Meeting::isDirektoratTypeCode((string) ($payload['meeting_type'] ?? ''));
        if (!$isDirektoratMeeting) {
            $meetingDates = $meetingDates->take(1)->values();
        }

        $meetings = DB::transaction(function () use ($payload, $user, $meetingDates) {
            $createdMeetings = collect();

            foreach ($meetingDates as $meetingDate) {
                $meeting = Meeting::create([
                    'title' => $payload['title'],
                    'meeting_type' => $payload['meeting_type'],
                    'meeting_at' => $this->buildMeetingAt((string) $meetingDate, $payload['meeting_time'] ?? null),
                    'location' => $payload['location'] ?? null,
                    'description' => $payload['description'] ?? null,
                    'status' => Meeting::STATUS_DRAFT,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $this->syncParticipants(
                    $meeting,
                    (array) ($payload['participants'] ?? []),
                    (array) ($payload['participant_users'] ?? []),
                    $user,
                    (string) ($payload['meeting_type'] ?? ''),
                    (bool) ($payload['submit_for_approval'] ?? false)
                );
                $this->syncAgendas(
                    $meeting,
                    Meeting::isDirektoratTypeCode((string) ($payload['meeting_type'] ?? ''))
                        ? []
                        : (array) ($payload['agendas'] ?? [])
                );
                $this->syncOptionalMancommEscalationAgendas(
                    $meeting,
                    (string) ($payload['meeting_type'] ?? ''),
                    (array) ($payload['escalation_decision_ids'] ?? [])
                );
                $this->syncInheritedDirectorateAgendas($meeting);

                $createdMeetings->push($meeting);
            }

            return $createdMeetings;
        });

        if ((bool) ($payload['submit_for_approval'] ?? false)) {
            foreach ($meetings as $meeting) {
                $this->workflow->submitPlan($meeting, $user, $payload['submit_note'] ?? null);
            }
        }

        $primaryMeeting = $meetings->first();
        $createdCount = (int) $meetings->count();
        $isBatchCreate = $createdCount > 1;

        $redirectUrl = $isBatchCreate
            ? route('meeting.index')
            : route('meeting.show', $primaryMeeting);

        $successMessage = $isBatchCreate
            ? (
                (bool) ($payload['submit_for_approval'] ?? false)
                ? "Rencana meeting berhasil dibuat sebanyak {$createdCount} jadwal dan dikirim untuk approval."
                : "Rencana meeting berhasil dibuat sebanyak {$createdCount} jadwal sebagai draft."
            )
            : (
                (bool) ($payload['submit_for_approval'] ?? false)
                ? 'Rencana meeting berhasil dibuat dan dikirim untuk approval.'
                : 'Rencana meeting berhasil dibuat sebagai draft.'
            );

        return $this->successRedirectResponse(
            $request,
            $redirectUrl,
            $successMessage
        );
    }

    public function show(Request $request, Meeting $meeting)
    {
        $this->authorizeRead();
        $user = Auth::user();
        if (!$this->canSeeMeeting($meeting, $user)) {
            abort(403, 'Anda tidak memiliki akses melihat meeting ini.');
        }

        $this->syncInheritedDirectorateAgendas($meeting);

        $meetingRelations = [
            'createdBy',
            'updatedBy',
            'authorizedBy',
            'directorateRespondedBy',
            'participants.directorate',
            'participants.participantUser',
            'agendas.ownerDirectorate',
            'agendas.picUser',
            'agendas.attachables.attachment',
            'agendas.sourceDecision.meeting',
            'agendas.sourceDecision.ownerDirectorate',
            'agendas.sourceDecision.picUser',
            'materials.attachment',
            'materials.uploader',
            'minutes.minutesAttachment',
            'minutes.finalMinutesAttachment',
            'decisions.ownerDirectorate',
            'decisions.picUser',
            'decisions.occurrences.meeting',
            'decisions.updates.updater',
            'decisions.updates.attachables.attachment',
        ];
        $meeting->load($meetingRelations);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $meeting,
                'status_labels' => Meeting::statusLabels(),
                'type_options' => Meeting::typeOptions(),
                'response_labels' => Meeting::responseLabels(),
            ]);
        }

        $options = $this->meetingFormOptions();
        $permissionFlags = $this->resolveMeetingPermissionFlags($meeting, $user);

        // load approvals for the meeting to be available in the view
        $approvals = Approval::query()
            ->where('approvable_type', Meeting::class)
            ->where('approvable_id', $meeting->id)
            ->with(['actor.directorate', 'actor.position'])
            ->orderByDesc('acted_at')
            ->orderByDesc('created_at')
            ->get();

        $crossMeetingOpenDecisions = $this->crossMeetingOpenDecisions($meeting);
        $linkableDecisions = $crossMeetingOpenDecisions
            ->sortByDesc(function (MeetingDecision $decision) {
                return sprintf(
                    '%010d-%010d',
                    $decision->last_discussed_at?->timestamp ?? 0,
                    (int) $decision->id
                );
            })
            ->values();
        $decisionUpdates = $meeting->decisions
            ->flatMap(function (MeetingDecision $decision) {
                return $decision->updates->map(function (DecisionUpdate $update) use ($decision) {
                    return [
                        'decision' => $decision,
                        'update' => $update,
                    ];
                });
            })
            ->sortByDesc(function (array $row) {
                return $row['update']->created_at;
            })
            ->values();
        $decisionProgressById = $meeting->decisions
            ->mapWithKeys(function (MeetingDecision $decision) {
                $latestUpdate = $decision->updates->sortByDesc('id')->first();
                $progressValue = (int) (
                    $latestUpdate?->progress_percent
                    ?? $decision->latest_progress_percent
                    ?? ((string) $decision->status === MeetingDecision::STATUS_DONE ? 100 : 0)
                );

                return [(int) $decision->id => $progressValue];
            })
            ->all();
        $sortedComments = $meeting->comments()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->get();
        $viewState = $this->buildMeetingShowViewState(
            $meeting,
            $permissionFlags
        );
        $materialAgendaOptions = $this->materialAgendaOptionsForUser($meeting, $user);

        return view('corsec::meeting.show', [
            'meeting' => $meeting,
            'statusLabels' => Meeting::statusLabels(),
            'typeOptions' => Meeting::typeOptions(),
            'responseLabels' => Meeting::responseLabels(),
            'approvals' => $approvals,
            'crossMeetingOpenDecisions' => $crossMeetingOpenDecisions,
            'linkableDecisions' => $linkableDecisions,
            'decisionUpdates' => $decisionUpdates,
            'decisionProgressById' => $decisionProgressById,
            'sortedComments' => $sortedComments,
            'materialAgendaOptions' => $materialAgendaOptions,
            ...$viewState,
            ...$options,
        ]);
    }

    public function presentation(Meeting $meeting)
    {
        $this->authorizeRead();
        $user = Auth::user();

        if (!$this->canSeeMeeting($meeting, $user)) {
            abort(403, 'Anda tidak memiliki akses melihat meeting ini.');
        }

        $meeting->load([
            'minutes',
            'agendas.ownerDirectorate',
            'agendas.picUser',
            'agendas.sourceDecision.meeting',
            'agendas.materials.attachment',
            'materials.attachment',
        ]);

        if (!$this->canOpenPresentationMode($meeting)) {
            abort(403, 'Mode presentasi hanya tersedia setelah bahan rapat terkirim dan sebelum notulen diinput.');
        }

        $slides = $this->buildMeetingPresentationSlides($meeting);

        if (empty($slides)) {
            return redirect()->route('meeting.show', $meeting)->with('warning', 'Belum ada bahan rapat yang bisa ditampilkan pada mode presentasi.');
        }

        return view('corsec::meeting.presentation', [
            'meeting' => $meeting,
            'slides' => $slides,
            'agendaGroups' => $this->buildMeetingPresentationAgendaGroups($slides),
            'typeOptions' => Meeting::typeOptions(),
        ]);
    }

    public function materialFile(Meeting $meeting, MeetingMaterial $material)
    {
        $this->authorizeRead();
        $user = Auth::user();

        if (!$this->canSeeMeeting($meeting, $user)) {
            abort(403, 'Anda tidak memiliki akses melihat meeting ini.');
        }

        if ((int) $material->meeting_id !== (int) $meeting->id) {
            abort(404, 'Bahan rapat tidak ditemukan.');
        }

        $material->loadMissing('attachment');
        $attachment = $material->attachment;

        if (!$attachment) {
            abort(404, 'File bahan rapat tidak ditemukan.');
        }

        $disk = (string) ($attachment->disk ?: 'public');
        $path = trim((string) ($attachment->path ?? ''));

        if ($path === '' || !Storage::disk($disk)->exists($path)) {
            abort(404, 'File bahan rapat tidak ditemukan.');
        }

        $downloadName = (string) ($attachment->original_name ?: $attachment->file_name ?: basename($path));
        $headers = [];

        if (filled($attachment->mime)) {
            $headers['Content-Type'] = (string) $attachment->mime;
        }

        return Storage::disk($disk)->response($path, $downloadName, $headers);
    }

    public function edit(Meeting $meeting)
    {
        $this->authorizeUpdate();
        $user = Auth::user();
        if (!$this->canEditMeeting($meeting, $user)) {
            abort(403, 'Meeting tidak dapat diubah pada status saat ini.');
        }

        $meeting->loadMissing([
            'participants.directorate',
            'participants.participantUser',
            'agendas.ownerDirectorate',
            'agendas.picUser',
        ]);

        $options = $this->meetingFormOptions($meeting);

        return view('corsec::meeting.create', [
            'meeting' => $meeting,
            'typeOptions' => Meeting::typeOptionsFromMasterData(true),
            'direktoratTypeCodes' => Meeting::direktoratTypeCodes(),
            'mandatoryDirectorateIds' => $this->resolveMandatoryDirectorateIds(),
            ...$options,
        ]);
    }

    public function update(Request $request, Meeting $meeting)
    {
        $this->authorizeUpdate();
        $user = Auth::user();
        if (!$this->canEditMeeting($meeting, $user)) {
            abort(403, 'Meeting tidak dapat diubah pada status saat ini.');
        }

        $payload = $this->validateMeetingPayload($request, (string) $meeting->meeting_type);

        DB::transaction(function () use ($meeting, $payload, $user) {
            $meeting->update([
                'title' => $payload['title'],
                'meeting_type' => $payload['meeting_type'],
                'meeting_at' => $this->buildMeetingAt($payload['meeting_date'], $payload['meeting_time'] ?? null),
                'location' => $payload['location'] ?? null,
                'description' => $payload['description'] ?? null,
                'updated_by' => $user->id,
            ]);

            $this->syncParticipants(
                $meeting,
                (array) ($payload['participants'] ?? []),
                (array) ($payload['participant_users'] ?? []),
                $user,
                (string) ($payload['meeting_type'] ?? ''),
                (bool) ($payload['submit_for_approval'] ?? false)
            );
            $this->syncAgendas(
                $meeting,
                Meeting::isDirektoratTypeCode((string) ($payload['meeting_type'] ?? ''))
                    ? []
                    : (array) ($payload['agendas'] ?? [])
            );
            $this->syncOptionalMancommEscalationAgendas(
                $meeting,
                (string) ($payload['meeting_type'] ?? ''),
                (array) ($payload['escalation_decision_ids'] ?? [])
            );
            $this->syncInheritedDirectorateAgendas($meeting);
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
            'Corporate Secretary berhasil diproses.'
        );
    }

    public function directorateResponse(Request $request, Meeting $meeting)
    {
        $this->authorizeUpdate();
        $request->validate([
            'action' => ['required', Rule::in([
                Meeting::RESPONSE_ON_SCHEDULE,
                Meeting::RESPONSE_CANCEL,
                Meeting::RESPONSE_RESCHEDULE,
            ])],
            'note' => ['nullable', 'string'],
        ]);

        $action = (string) $request->input('action');
        $this->workflow->respondDirectorateSchedule($meeting, Auth::user(), $action, $request->input('note'));

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            $action === Meeting::RESPONSE_CANCEL
                ? 'Tanggapan jadwal disimpan: rapat dibatalkan oleh direktorat.'
                : ($action === Meeting::RESPONSE_RESCHEDULE
                    ? 'Tanggapan jadwal disimpan: direktorat meminta reschedule.'
                    : 'Tanggapan jadwal disimpan: rapat on schedule.')
        );
    }

    public function directorateSubmit(Request $request, Meeting $meeting)
    {
        $this->authorizeUpdate();
        $request->validate([
            'note' => ['nullable', 'string'],
            'material_agenda_id' => ['nullable', 'exists:corsec_meeting_agendas,id'],
            'material_files' => ['nullable', 'array'],
            'material_files.*' => ['file', UploadRule::maxRule(), 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx,ppt,pptx'],
            'additional_participants' => ['nullable', 'array'],
            'additional_participants.*' => ['nullable', 'exists:corsec_directorates,id'],
            'additional_agendas' => ['nullable', 'array'],
            'additional_agendas.*.title' => ['required_with:additional_agendas', 'string', 'max:255'],
            'additional_agendas.*.description' => ['nullable', 'string'],
            'additional_agendas.*.owner_directorate_id' => ['nullable', 'exists:corsec_directorates,id'],
            'additional_agendas.*.pic_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $user = Auth::user();
        if (!$this->canSubmitMeetingPreparation($meeting, $user)) {
            abort(403, 'Submit persiapan hanya untuk user pada direktorat peserta/PIC rapat.');
        }
        if ($meeting->isDirektoratType() && (string) $meeting->directorate_response_status !== Meeting::RESPONSE_ON_SCHEDULE) {
            abort(422, 'PIC direktorat wajib memberikan tanggapan on schedule sebelum submit persiapan.');
        }

        $this->syncInheritedDirectorateAgendas($meeting);

        $uploadedMaterialFiles = collect((array) $request->file('material_files', []))
            ->filter(fn($file) => $file instanceof UploadedFile)
            ->values();
        $materialAgendaId = $request->filled('material_agenda_id') ? (int) $request->input('material_agenda_id') : null;
        if ($uploadedMaterialFiles->isNotEmpty() && !$materialAgendaId) {
            throw ValidationException::withMessages([
                'material_agenda_id' => 'Agenda bahan wajib dipilih saat upload bahan rapat.',
            ]);
        }
        if ($materialAgendaId && !MeetingAgenda::query()
            ->where('meeting_id', $meeting->id)
            ->where('id', $materialAgendaId)
            ->exists()) {
            throw ValidationException::withMessages([
                'material_agenda_id' => 'Agenda material harus berasal dari meeting yang sama.',
            ]);
        }
        if ($uploadedMaterialFiles->isNotEmpty() && $materialAgendaId) {
            $materialAgenda = MeetingAgenda::query()
                ->with('picUser')
                ->where('meeting_id', $meeting->id)
                ->find($materialAgendaId);

            if (!$materialAgenda) {
                throw ValidationException::withMessages([
                    'material_agenda_id' => 'Agenda material harus berasal dari meeting yang sama.',
                ]);
            }

            if (!$this->canUploadMeetingAgendaMaterial($materialAgenda, $user)) {
                throw ValidationException::withMessages([
                    'material_agenda_id' => 'Bahan agenda hanya boleh diupload oleh PIC user agenda yang ditunjuk.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $meeting, $user, $uploadedMaterialFiles, $materialAgendaId) {
            $this->appendParticipants(
                $meeting,
                (array) $request->input('additional_participants', []),
                $user,
                (string) ($meeting->meeting_type ?? '')
            );
            $this->appendAgendas($meeting, (array) $request->input('additional_agendas', []));
            $this->storeMeetingMaterials(
                $meeting,
                $uploadedMaterialFiles->all(),
                $user,
                $materialAgendaId
            );

            $note = trim((string) $request->input('note'));
            if ($note !== '') {
                $commentLabel = $meeting->isDirektoratType() ? '[PERSIAPAN DIREKTORAT]' : '[KOORDINASI UNIT RAPAT]';
                Comment::create([
                    'commentable_type' => Meeting::class,
                    'commentable_id' => $meeting->id,
                    'body' => $commentLabel . ' ' . $note,
                    'created_by' => $user->id,
                ]);
            }
        });

        $submitResult = $this->workflow->submitDirectoratePreparation($meeting, $user, $request->input('note'));

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            (string) ($submitResult['message'] ?? $submitResult['success_message'] ?? 'Koordinasi rapat berhasil disubmit.'),
            (string) ($submitResult['message_type'] ?? 'success')
        );
    }

    public function markPendingDirectorate(Request $request, Meeting $meeting)
    {
        $this->authorizeUpdate();
        $user = Auth::user();
        if (!$this->canSubmitMeetingPreparation($meeting, $user)) {
            abort(403, 'Status pending koordinasi hanya untuk user pada direktorat peserta rapat.');
        }

        $this->workflow->markPendingDirectorate($meeting, $user);

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

        $successMessage = $this->workflow->handleDirectorateApproval(
            $meeting,
            Auth::user(),
            (string) $request->input('action'),
            $request->input('note')
        );

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            $successMessage
        );
    }

    public function saveMinutes(Request $request, Meeting $meeting)
    {
        $this->authorizeUpdate();
        $user = Auth::user();
        $this->authorizeMeetingMinutesAction($meeting, $user, 'can_save_minutes');

        if ($meeting->isDirektoratType()) {
            return $this->saveDirectorateMinutes($request, $meeting);
        }

        $request->validate([
            'minutes_text' => ['nullable', 'string'],
            'minutes_file' => ['nullable', 'file', UploadRule::maxRule(), 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx,ppt,pptx'],
            'note' => ['nullable', 'string'],
            'submit_for_signature' => ['nullable', 'boolean'],
            'minutes_agendas' => ['nullable', 'array'],
            'minutes_agendas.*.agenda_id' => ['nullable', 'integer', 'exists:corsec_meeting_agendas,id'],
            'minutes_agendas.*.source_decision_id' => ['nullable', 'integer', 'exists:corsec_meeting_decisions,id'],
            'minutes_agendas.*.title' => ['required_with:minutes_agendas', 'string', 'max:255'],
            'minutes_agendas.*.description' => ['nullable', 'string'],
            'minutes_agendas.*.minutes_discussion' => ['nullable', 'string'],
            'minutes_agendas.*.owner_directorate_id' => ['nullable', 'exists:corsec_directorates,id'],
            'minutes_agendas.*.pic_user_id' => ['nullable', 'exists:users,id'],
            'minutes_agendas.*.photo_files' => ['nullable', 'array'],
            'minutes_agendas.*.photo_files.*' => ['file', UploadRule::maxRule(), 'mimes:jpg,jpeg,png'],
            'minutes_agendas.*.decision_id' => ['nullable', 'integer', 'exists:corsec_meeting_decisions,id'],
            'minutes_agendas.*.existing_decision_id' => ['nullable', 'integer', 'exists:corsec_meeting_decisions,id'],
            'minutes_agendas.*.followup_enabled' => ['nullable', 'boolean'],
            'minutes_agendas.*.decision_text' => ['nullable', 'string'],
            'minutes_agendas.*.target_date' => ['nullable', 'date'],
            'minutes_agendas.*.status' => ['nullable', Rule::in($this->allDecisionStatuses())],
            'decisions' => ['nullable', 'array'],
            'decisions.*.id' => ['nullable', 'integer'],
            'decisions.*.existing_decision_id' => ['nullable', 'integer', 'exists:corsec_meeting_decisions,id'],
            'decisions.*.decision_text' => ['nullable', 'string'],
            'decisions.*.owner_directorate_id' => ['nullable', 'exists:corsec_directorates,id'],
            'decisions.*.pic_user_id' => ['nullable', 'exists:users,id'],
            'decisions.*.target_date' => ['nullable', 'date'],
            'decisions.*.status' => ['nullable', Rule::in($this->allDecisionStatuses())],
        ], [
            'minutes_file.file' => 'Lampiran notulen harus berupa file yang valid.',
            'minutes_file.mimes' => 'Format lampiran notulen tidak didukung.',
            'minutes_agendas.*.title.required_with' => 'Materi pembahasan wajib diisi.',
            'minutes_agendas.*.title.max' => 'Materi pembahasan maksimal 255 karakter.',
            'minutes_agendas.*.target_date.date' => 'Target tindaklanjut harus berupa tanggal yang valid.',
            'minutes_agendas.*.status.in' => 'Status tindaklanjut tidak valid.',
            'decisions.*.target_date.date' => 'Target penyelesaian harus berupa tanggal yang valid.',
            'decisions.*.status.in' => 'Status issue tidak valid.',
        ]);

        $shouldSyncMinutesAgendas = $request->has('minutes_agendas_present');
        $minutesAgendaRows = $shouldSyncMinutesAgendas
            ? collect((array) $request->input('minutes_agendas', []))->values()->all()
            : [];
        if ($shouldSyncMinutesAgendas) {
            $this->validateMinutesAgendaRows($meeting, $minutesAgendaRows, false);
        }

        DB::transaction(function () use ($request, $meeting, $user, $minutesAgendaRows, $shouldSyncMinutesAgendas) {
            $minutes = MeetingMinutes::query()->firstOrNew(['meeting_id' => $meeting->id]);
            $minutes->meeting_id = $meeting->id;
            $minutes->minutes_text = trim((string) $request->input('minutes_text', ''));
            $minutes->status = MeetingMinutes::STATUS_DRAFT;
            $minutes->save();

            $minutesFile = $request->file('minutes_file');
            if ($minutesFile instanceof UploadedFile) {
                $attachment = $this->storeAttachment($minutesFile, $user, 'corsec/meeting/minutes');
                $minutes->minutes_attachment_id = $attachment->id;
                $minutes->save();
            }

            $retainedAgendaIds = [];
            $touchedRootDecisionIds = [];
            if ($shouldSyncMinutesAgendas) {
                foreach ($minutesAgendaRows as $index => $rowPayload) {
                    $agendaTitle = trim((string) ($rowPayload['title'] ?? ''));
                    if ($agendaTitle === '') {
                        continue;
                    }

                    $agenda = $this->upsertMinutesAgenda(
                        $meeting,
                        $rowPayload,
                        $user,
                        $index + 1
                    );
                    $retainedAgendaIds[] = (int) $agenda->id;

                    $this->storeAgendaPointPhotos(
                        $agenda,
                        $this->resolveAgendaPointPhotoFiles($request, $index),
                        $user
                    );

                    $agendaDecision = $this->upsertMinutesAgendaDecision(
                        $meeting,
                        $agenda,
                        $rowPayload,
                        $user,
                        MeetingDecision::STATUS_PENDING
                    );
                    if ($agendaDecision) {
                        $touchedRootDecisionIds[] = $this->resolveDecisionFamilyRootId($agendaDecision) ?? (int) $agendaDecision->id;
                    }
                }

                $this->cleanupMinutesAgendas($meeting, $retainedAgendaIds);
            }

            foreach ((array) $request->input('decisions', []) as $decisionIndex => $decisionPayload) {
                $decisionText = trim((string) ($decisionPayload['decision_text'] ?? ''));
                if ($decisionText === '') {
                    continue;
                }
                $picUserId = isset($decisionPayload['pic_user_id']) && $decisionPayload['pic_user_id'] !== ''
                    ? (int) $decisionPayload['pic_user_id']
                    : null;
                $ownerDirectorateId = $this->resolveOwnerDirectorateId(
                    isset($decisionPayload['owner_directorate_id']) && $decisionPayload['owner_directorate_id'] !== ''
                        ? (int) $decisionPayload['owner_directorate_id']
                        : null,
                    $picUserId
                );
                $targetDate = $decisionPayload['target_date'] ?? null;
                if (!$ownerDirectorateId && !$picUserId) {
                    throw ValidationException::withMessages([
                        "decisions.{$decisionIndex}.pic_user_id" => 'PIC user atau direktorat wajib diisi untuk tindaklanjut ini.',
                    ]);
                }
                if (!$targetDate) {
                    throw ValidationException::withMessages([
                        "decisions.{$decisionIndex}.target_date" => 'Target wajib diisi untuk tindaklanjut ini.',
                    ]);
                }

                $linkedDecisionId = isset($decisionPayload['existing_decision_id']) && $decisionPayload['existing_decision_id'] !== ''
                    ? (int) $decisionPayload['existing_decision_id']
                    : null;
                $linkedDecision = $linkedDecisionId
                    ? MeetingDecision::query()->find($linkedDecisionId)
                    : null;
                $linkedRootDecisionId = $this->resolveDecisionFamilyRootId($linkedDecision);
                $decisionStatus = $this->normalizeDecisionStatus((string) ($decisionPayload['status'] ?? ''));
                $decisionId = (int) ($decisionPayload['id'] ?? 0);
                $existingDecision = null;
                if ($decisionId > 0) {
                    $existingDecision = MeetingDecision::query()
                        ->where('meeting_id', $meeting->id)
                        ->where('id', $decisionId)
                        ->first();
                }
                if (!$existingDecision && $linkedRootDecisionId) {
                    $existingDecision = MeetingDecision::query()
                        ->where('meeting_id', $meeting->id)
                        ->where(function ($query) use ($linkedRootDecisionId) {
                            $query->where('id', $linkedRootDecisionId)
                                ->orWhere('root_decision_id', $linkedRootDecisionId);
                        })
                        ->first();
                }

                $decisionAttributes = [
                    'decision_text' => $decisionText,
                    'owner_directorate_id' => $ownerDirectorateId,
                    'pic_user_id' => $picUserId,
                    'target_date' => $targetDate,
                    'status' => $decisionStatus,
                    'closed_at' => in_array($decisionStatus, $this->resolvedDecisionStatuses(), true) ? now() : null,
                    'updated_by' => $user->id,
                ];
                if ($linkedDecision && $linkedRootDecisionId) {
                    $decisionAttributes['root_decision_id'] = $linkedRootDecisionId;
                    $decisionAttributes['source_decision_id'] = $linkedDecision->id;
                    $decisionAttributes['issue_key'] = $linkedDecision->issue_key ?: $this->buildIssueKey($linkedRootDecisionId);
                }

                if ($existingDecision) {
                    if (!$existingDecision->decision_key) {
                        $decisionAttributes['decision_key'] = $this->buildDecisionKey((int) $existingDecision->id);
                    }
                    if (!$existingDecision->root_decision_id && !$linkedRootDecisionId) {
                        $decisionAttributes['root_decision_id'] = (int) $existingDecision->id;
                    }
                    if (!$existingDecision->issue_key && !$linkedRootDecisionId) {
                        $decisionAttributes['issue_key'] = $this->buildIssueKey((int) $existingDecision->id);
                    }

                    $existingDecision->update($decisionAttributes);
                    $decision = $existingDecision->fresh();
                } else {
                    $decision = MeetingDecision::create([
                        'meeting_id' => $meeting->id,
                        'decision_text' => $decisionText,
                        'decision_key' => null,
                        'issue_key' => $linkedDecision?->issue_key,
                        'root_decision_id' => $linkedRootDecisionId,
                        'source_decision_id' => $linkedDecision?->id,
                        'owner_directorate_id' => $ownerDirectorateId,
                        'pic_user_id' => $picUserId,
                        'target_date' => $targetDate,
                        'status' => $decisionStatus,
                        'closed_at' => in_array($decisionStatus, $this->resolvedDecisionStatuses(), true) ? now() : null,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }

                $rootDecisionId = $this->resolveDecisionFamilyRootId($decision);
                $keyPayload = [];
                if (!$decision->decision_key) {
                    $keyPayload['decision_key'] = $this->buildDecisionKey((int) $decision->id);
                }
                if (!$rootDecisionId) {
                    $rootDecisionId = (int) $decision->id;
                    $keyPayload['root_decision_id'] = $rootDecisionId;
                }
                if (!$decision->issue_key) {
                    $keyPayload['issue_key'] = $this->buildIssueKey($rootDecisionId);
                }
                if (!empty($keyPayload)) {
                    $decision->update($keyPayload);
                    $decision = $decision->fresh();
                }

                $this->recordDecisionOccurrence($decision, $linkedDecision, $user);
                $touchedRootDecisionIds[] = $this->resolveDecisionFamilyRootId($decision) ?? (int) $decision->id;
            }

            collect($touchedRootDecisionIds)
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->each(function (int $rootDecisionId) {
                    $this->syncDecisionFamilySummaryByRootDecisionId($rootDecisionId);
                });

            $this->syncMeetingDecisionFamilySummaries($meeting);
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
        $user = Auth::user();
        $this->authorizeMeetingMinutesAction($meeting, $user, 'can_finalize_minutes');

        $request->validate([
            'final_minutes_file' => [
                $meeting->isDirektoratType() ? 'nullable' : 'required',
                'file',
                UploadRule::maxRule(),
                'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx,ppt,pptx',
            ],
            'note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $meeting, $user) {
            $minutes = MeetingMinutes::query()->firstOrNew(['meeting_id' => $meeting->id]);
            $minutes->meeting_id = $meeting->id;

            $finalMinutesFile = $request->file('final_minutes_file');
            if ($finalMinutesFile instanceof UploadedFile) {
                $attachment = $this->storeAttachment(
                    $finalMinutesFile,
                    $user,
                    'corsec/meeting/final_minutes'
                );

                $minutes->final_minutes_attachment_id = $attachment->id;
            }
            $minutes->status = MeetingMinutes::STATUS_APPROVED;
            $minutes->approved_at = now();
            $minutes->approved_by = $user->id;
            $minutes->finalized_at = now();
            $minutes->save();
        });

        $this->workflow->finalizeMinutes($meeting, $user);
        $successMessage = $request->file('final_minutes_file') instanceof UploadedFile
            ? 'Notulen final berhasil diupload.'
            : 'Notulen final berhasil difinalisasi.';

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            $successMessage
        );
    }

    private function resolveMeetingPermissionFlags(Meeting $meeting, ?User $user): array
    {
        $approvals = Approval::query()
            ->where('approvable_type', Meeting::class)
            ->where('approvable_id', $meeting->id)
            ->with(['actor.directorate', 'actor.position'])
            ->orderByDesc('acted_at')
            ->orderByDesc('created_at')
            ->get();

        return $this->permissionService->meetingDetailFlags($meeting, $approvals, $user);
    }

    private function authorizeMeetingMinutesAction(Meeting $meeting, User $user, string $abilityKey): void
    {
        $permissionFlags = $this->resolveMeetingPermissionFlags($meeting, $user);
        if ((bool) ($permissionFlags[$abilityKey] ?? false)) {
            return;
        }

        $message = match ($abilityKey) {
            'can_finalize_minutes' => $meeting->isDirektoratType()
                ? 'Finalisasi notulen rapat direktorat hanya untuk user assigned atau administrator pada status saat ini.'
                : 'Anda tidak memiliki akses finalisasi notulen meeting pada status saat ini.',
            default => $meeting->isDirektoratType()
                ? 'Input notulen rapat direktorat hanya untuk user assigned atau administrator pada status saat ini.'
                : 'Anda tidak memiliki akses input notulen meeting pada status saat ini.',
        };

        abort(403, $message);
    }

    private function saveDirectorateMinutes(Request $request, Meeting $meeting)
    {
        $request->validate([
            'minutes_text' => ['nullable', 'string'],
            'minutes_file' => ['nullable', 'file', UploadRule::maxRule(), 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx,ppt,pptx'],
            'note' => ['nullable', 'string'],
            'submit_for_signature' => ['nullable', 'boolean'],
            'minutes_agendas' => ['required', 'array', 'min:1'],
            'minutes_agendas.*.agenda_id' => ['nullable', 'integer', 'exists:corsec_meeting_agendas,id'],
            'minutes_agendas.*.source_decision_id' => ['nullable', 'integer', 'exists:corsec_meeting_decisions,id'],
            'minutes_agendas.*.title' => ['required', 'string', 'max:255'],
            'minutes_agendas.*.description' => ['nullable', 'string'],
            'minutes_agendas.*.owner_directorate_id' => ['nullable', 'exists:corsec_directorates,id'],
            'minutes_agendas.*.pic_user_id' => ['nullable', 'exists:users,id'],
            'minutes_agendas.*.decision_id' => ['nullable', 'integer', 'exists:corsec_meeting_decisions,id'],
            'minutes_agendas.*.existing_decision_id' => ['nullable', 'integer', 'exists:corsec_meeting_decisions,id'],
            'minutes_agendas.*.followup_enabled' => ['nullable', 'boolean'],
            'minutes_agendas.*.decision_text' => ['nullable', 'string'],
            'minutes_agendas.*.target_date' => ['nullable', 'date'],
            'minutes_agendas.*.status' => ['nullable', Rule::in($this->allDecisionStatuses())],
        ], [
            'minutes_file.file' => 'Lampiran notulen harus berupa file yang valid.',
            'minutes_file.mimes' => 'Format lampiran notulen tidak didukung.',
            'minutes_agendas.required' => 'Minimal satu materi pembahasan wajib diisi.',
            'minutes_agendas.min' => 'Minimal satu materi pembahasan wajib diisi.',
            'minutes_agendas.*.title.required' => 'Materi pembahasan wajib diisi.',
            'minutes_agendas.*.title.max' => 'Materi pembahasan maksimal 255 karakter.',
            'minutes_agendas.*.target_date.date' => 'Target tindaklanjut harus berupa tanggal yang valid.',
            'minutes_agendas.*.status.in' => 'Status tindaklanjut tidak valid.',
        ]);

        $user = Auth::user();
        $minutesAgendaRows = collect((array) $request->input('minutes_agendas', []))
            ->values()
            ->all();
        $this->validateMinutesAgendaRows($meeting, $minutesAgendaRows, true);

        DB::transaction(function () use ($request, $meeting, $user, $minutesAgendaRows) {
            $minutes = MeetingMinutes::query()->firstOrNew(['meeting_id' => $meeting->id]);
            $minutes->meeting_id = $meeting->id;
            $minutes->minutes_text = trim((string) $request->input('minutes_text', ''));
            $minutes->status = MeetingMinutes::STATUS_DRAFT;
            $minutes->save();

            $minutesFile = $request->file('minutes_file');
            if ($minutesFile instanceof UploadedFile) {
                $attachment = $this->storeAttachment($minutesFile, $user, 'corsec/meeting/minutes');
                $minutes->minutes_attachment_id = $attachment->id;
                $minutes->save();
            }

            $retainedAgendaIds = [];
            $touchedRootDecisionIds = [];
            foreach ($minutesAgendaRows as $index => $rowPayload) {
                $agenda = $this->upsertMinutesAgenda(
                    $meeting,
                    $rowPayload,
                    $user,
                    $index + 1
                );
                $retainedAgendaIds[] = (int) $agenda->id;

                $decision = $this->upsertMinutesAgendaDecision(
                    $meeting,
                    $agenda,
                    $rowPayload,
                    $user,
                    MeetingDecision::STATUS_IN_PROGRESS
                );
                if ($decision) {
                    $touchedRootDecisionIds[] = $this->resolveDecisionFamilyRootId($decision) ?? (int) $decision->id;
                }
            }

            $this->cleanupMinutesAgendas($meeting, $retainedAgendaIds);

            collect($touchedRootDecisionIds)
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->each(function (int $rootDecisionId) {
                    $this->syncDecisionFamilySummaryByRootDecisionId($rootDecisionId);
                });

            $this->syncMeetingDecisionFamilySummaries($meeting);
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
            'Notulen rapat direktorat berhasil disimpan.'
        );
    }

    private function validateMinutesAgendaRows(Meeting $meeting, array $rows, bool $requirePicForEveryRow = true): void
    {
        $agendaIds = MeetingAgenda::query()
            ->where('meeting_id', $meeting->id)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
        $decisionIds = MeetingDecision::query()
            ->where('meeting_id', $meeting->id)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        $errors = [];
        foreach ($rows as $index => $rowPayload) {
            $rowNumber = $index + 1;
            $agendaId = (int) ($rowPayload['agenda_id'] ?? 0);
            if ($agendaId > 0 && !in_array($agendaId, $agendaIds, true)) {
                $errors["minutes_agendas.$index.agenda_id"] = "Baris materi #{$rowNumber} tidak berasal dari meeting yang sama.";
            }

            $decisionId = (int) ($rowPayload['decision_id'] ?? 0);
            if ($decisionId > 0 && !in_array($decisionId, $decisionIds, true)) {
                $errors["minutes_agendas.$index.decision_id"] = "Tindaklanjut baris materi #{$rowNumber} tidak berasal dari meeting yang sama.";
            }

            $picUserId = isset($rowPayload['pic_user_id']) && $rowPayload['pic_user_id'] !== ''
                ? (int) $rowPayload['pic_user_id']
                : null;
            $ownerDirectorateId = $this->resolveOwnerDirectorateId(
                isset($rowPayload['owner_directorate_id']) && $rowPayload['owner_directorate_id'] !== ''
                    ? (int) $rowPayload['owner_directorate_id']
                    : null,
                $picUserId
            );
            $hasExistingDecision = $decisionId > 0;
            $hasFollowupPayload = $hasExistingDecision || $this->minutesAgendaHasFollowupPayload($rowPayload);
            if (($requirePicForEveryRow || $hasFollowupPayload) && !$ownerDirectorateId && !$picUserId) {
                $errors["minutes_agendas.$index.pic_user_id"] = "PIC wajib diisi untuk materi pembahasan baris #{$rowNumber}.";
            }

            if (!$hasFollowupPayload) {
                continue;
            }

            if (trim((string) ($rowPayload['decision_text'] ?? '')) === '') {
                $errors["minutes_agendas.$index.decision_text"] = "Tindaklanjut wajib diisi untuk materi pembahasan baris #{$rowNumber}.";
            }

            if (blank($rowPayload['target_date'] ?? null)) {
                $errors["minutes_agendas.$index.target_date"] = "Target tindaklanjut wajib diisi untuk materi pembahasan baris #{$rowNumber}.";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function minutesAgendaFollowupEnabled(array $rowPayload): bool
    {
        return filter_var($rowPayload['followup_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function minutesAgendaHasFollowupPayload(array $rowPayload): bool
    {
        return $this->minutesAgendaFollowupEnabled($rowPayload)
            || filled($rowPayload['existing_decision_id'] ?? null)
            || trim((string) ($rowPayload['decision_text'] ?? '')) !== ''
            || filled($rowPayload['target_date'] ?? null);
    }

    private function upsertMinutesAgenda(Meeting $meeting, array $rowPayload, User $user, int $orderNo): MeetingAgenda
    {
        $agendaId = isset($rowPayload['agenda_id']) && $rowPayload['agenda_id'] !== ''
            ? (int) $rowPayload['agenda_id']
            : null;
        $agenda = $agendaId
            ? MeetingAgenda::query()
            ->where('meeting_id', $meeting->id)
            ->find($agendaId)
            : null;

        $picUserId = isset($rowPayload['pic_user_id']) && $rowPayload['pic_user_id'] !== ''
            ? (int) $rowPayload['pic_user_id']
            : null;
        $ownerDirectorateId = $this->resolveOwnerDirectorateId(
            isset($rowPayload['owner_directorate_id']) && $rowPayload['owner_directorate_id'] !== ''
                ? (int) $rowPayload['owner_directorate_id']
                : null,
            $picUserId
        );

        $payload = [
            'meeting_id' => $meeting->id,
            'order_no' => $orderNo,
            'title' => trim((string) ($rowPayload['title'] ?? '')),
            'description' => trim((string) ($rowPayload['description'] ?? '')) ?: null,
            'minutes_discussion' => trim((string) ($rowPayload['minutes_discussion'] ?? '')) ?: null,
            'owner_directorate_id' => $ownerDirectorateId,
            'pic_user_id' => $picUserId,
            'source_decision_id' => isset($rowPayload['source_decision_id']) && $rowPayload['source_decision_id'] !== ''
                ? (int) $rowPayload['source_decision_id']
                : null,
        ];

        if ($agenda) {
            $agenda->update($payload);
            return $agenda->fresh();
        }

        return MeetingAgenda::query()->create($payload);
    }

    private function upsertMinutesAgendaDecision(
        Meeting $meeting,
        MeetingAgenda $agenda,
        array $rowPayload,
        User $user,
        string $defaultStatus = MeetingDecision::STATUS_IN_PROGRESS
    ): ?MeetingDecision {
        $decisionId = isset($rowPayload['decision_id']) && $rowPayload['decision_id'] !== ''
            ? (int) $rowPayload['decision_id']
            : null;
        $existingDecision = $decisionId
            ? MeetingDecision::query()
            ->where('meeting_id', $meeting->id)
            ->find($decisionId)
            : null;
        if (!$existingDecision) {
            $existingDecision = MeetingDecision::query()
                ->where('meeting_id', $meeting->id)
                ->where('agenda_id', $agenda->id)
                ->first();
        }

        $followupEnabled = $existingDecision instanceof MeetingDecision || $this->minutesAgendaHasFollowupPayload($rowPayload);
        if (!$followupEnabled) {
            return null;
        }

        $picUserId = isset($rowPayload['pic_user_id']) && $rowPayload['pic_user_id'] !== ''
            ? (int) $rowPayload['pic_user_id']
            : null;
        $ownerDirectorateId = $this->resolveOwnerDirectorateId(
            isset($rowPayload['owner_directorate_id']) && $rowPayload['owner_directorate_id'] !== ''
                ? (int) $rowPayload['owner_directorate_id']
                : null,
            $picUserId
        );

        $linkedDecisionId = isset($rowPayload['existing_decision_id']) && $rowPayload['existing_decision_id'] !== ''
            ? (int) $rowPayload['existing_decision_id']
            : ((int) ($agenda->source_decision_id ?? 0) ?: null);
        $linkedDecision = $linkedDecisionId
            ? MeetingDecision::query()->find($linkedDecisionId)
            : null;
        $linkedRootDecisionId = $this->resolveDecisionFamilyRootId($linkedDecision);
        $decisionStatus = $this->normalizeMinutesAgendaDecisionStatus((string) ($rowPayload['status'] ?? ''), $defaultStatus);
        $decisionText = trim((string) ($rowPayload['decision_text'] ?? ''));

        $decisionAttributes = [
            'agenda_id' => $agenda->id,
            'decision_text' => $decisionText,
            'owner_directorate_id' => $ownerDirectorateId,
            'pic_user_id' => $picUserId,
            'target_date' => $rowPayload['target_date'] ?? null,
            'status' => $decisionStatus,
            'closed_at' => in_array($decisionStatus, $this->resolvedDecisionStatuses(), true) ? now() : null,
            'updated_by' => $user->id,
        ];
        if ($linkedDecision && $linkedRootDecisionId) {
            $decisionAttributes['root_decision_id'] = $linkedRootDecisionId;
            $decisionAttributes['source_decision_id'] = $linkedDecision->id;
            $decisionAttributes['issue_key'] = $linkedDecision->issue_key ?: $this->buildIssueKey($linkedRootDecisionId);
        }

        if ($existingDecision) {
            if (!$existingDecision->decision_key) {
                $decisionAttributes['decision_key'] = $this->buildDecisionKey((int) $existingDecision->id);
            }
            if (!$existingDecision->root_decision_id && !$linkedRootDecisionId) {
                $decisionAttributes['root_decision_id'] = (int) $existingDecision->id;
            }
            if (!$existingDecision->issue_key && !$linkedRootDecisionId) {
                $decisionAttributes['issue_key'] = $this->buildIssueKey((int) $existingDecision->id);
            }

            $existingDecision->update($decisionAttributes);
            $decision = $existingDecision->fresh();
        } else {
            $decision = MeetingDecision::query()->create([
                'meeting_id' => $meeting->id,
                'agenda_id' => $agenda->id,
                'decision_text' => $decisionText,
                'decision_key' => null,
                'issue_key' => $linkedDecision?->issue_key,
                'root_decision_id' => $linkedRootDecisionId,
                'source_decision_id' => $linkedDecision?->id,
                'owner_directorate_id' => $ownerDirectorateId,
                'pic_user_id' => $picUserId,
                'target_date' => $rowPayload['target_date'] ?? null,
                'status' => $decisionStatus,
                'closed_at' => in_array($decisionStatus, $this->resolvedDecisionStatuses(), true) ? now() : null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        $rootDecisionId = $this->resolveDecisionFamilyRootId($decision);
        $keyPayload = [];
        if (!$decision->decision_key) {
            $keyPayload['decision_key'] = $this->buildDecisionKey((int) $decision->id);
        }
        if (!$rootDecisionId) {
            $rootDecisionId = (int) $decision->id;
            $keyPayload['root_decision_id'] = $rootDecisionId;
        }
        if (!$decision->issue_key) {
            $keyPayload['issue_key'] = $this->buildIssueKey($rootDecisionId);
        }
        if (!empty($keyPayload)) {
            $decision->update($keyPayload);
            $decision = $decision->fresh();
        }

        $this->recordDecisionOccurrence($decision, $linkedDecision, $user);

        return $decision;
    }

    private function resolveAgendaPointPhotoFiles(Request $request, int $index): array
    {
        $photoFiles = data_get($request->file('minutes_agendas', []), $index . '.photo_files', []);
        if ($photoFiles instanceof UploadedFile) {
            return [$photoFiles];
        }

        return array_values(array_filter((array) $photoFiles, fn($file) => $file instanceof UploadedFile));
    }

    private function storeAgendaPointPhotos(MeetingAgenda $agenda, array $photoFiles, User $user): void
    {
        foreach ($photoFiles as $photoFile) {
            if (!$photoFile instanceof UploadedFile) {
                continue;
            }

            $attachment = $this->storeAttachment($photoFile, $user, 'corsec/meeting/minutes-points');
            Attachable::create([
                'attachment_id' => $attachment->id,
                'attachable_type' => MeetingAgenda::class,
                'attachable_id' => $agenda->id,
                'category' => 'minutes_point_photo',
                'created_by' => $user->id,
            ]);
        }
    }

    private function cleanupMinutesAgendas(Meeting $meeting, array $retainedAgendaIds): void
    {
        $removableAgendaIds = MeetingAgenda::query()
            ->where('meeting_id', $meeting->id)
            ->whereNull('source_decision_id')
            ->when(!empty($retainedAgendaIds), function ($query) use ($retainedAgendaIds) {
                $query->whereNotIn('id', $retainedAgendaIds);
            })
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        foreach ($removableAgendaIds as $agendaId) {
            $hasDecision = MeetingDecision::query()
                ->where('meeting_id', $meeting->id)
                ->where('agenda_id', $agendaId)
                ->exists();
            $hasMaterials = MeetingMaterial::query()
                ->where('meeting_id', $meeting->id)
                ->where('agenda_id', $agendaId)
                ->exists();

            if ($hasDecision || $hasMaterials) {
                continue;
            }

            $this->deleteMorphAttachments(MeetingAgenda::class, $agendaId);
            MeetingAgenda::query()
                ->where('meeting_id', $meeting->id)
                ->where('id', $agendaId)
                ->delete();
        }
    }

    private function normalizeMinutesAgendaDecisionStatus(
        string $status,
        string $defaultStatus = MeetingDecision::STATUS_IN_PROGRESS
    ): string {
        $status = trim($status);
        if ($status === '') {
            return $defaultStatus;
        }

        return $this->normalizeDecisionStatus($status);
    }

    public function submitDecisionUpdate(Request $request, Meeting $meeting, MeetingDecision $decision)
    {
        if ((int) $decision->meeting_id !== (int) $meeting->id) {
            abort(404, 'Decision tidak sesuai dengan meeting.');
        }

        $user = Auth::user();
        if (!$user) {
            abort(403, 'User tidak ditemukan.');
        }
        if (!in_array((string) ($meeting->status ?? ''), [
            Meeting::STATUS_NOTULEN_FINAL,
            Meeting::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT,
        ], true)) {
            abort(403, 'Update tindaklanjut hanya tersedia setelah meeting berada pada tahap notulen final.');
        }
        if (!$this->canUpdateMeetingDecision($meeting, $decision, $user)) {
            abort(403, 'Update tindaklanjut hanya untuk user pada direktorat PIC keputusan.');
        }

        $request->validate([
            'update_type' => ['required', Rule::in([
                DecisionUpdate::TYPE_PROGRESS,
                DecisionUpdate::TYPE_DONE,
                DecisionUpdate::TYPE_CONTINUOUS,
                DecisionUpdate::TYPE_DROP,
            ])],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'happened_at' => ['required', 'date'],
            'is_on_target' => ['required', 'boolean'],
            'reason' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'evidence_files' => ['required', 'array', 'min:1'],
            'evidence_files.*' => ['file', UploadRule::maxRule(), 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx,ppt,pptx'],
        ], [
            'update_type.required' => 'Jenis update wajib dipilih.',
            'update_type.in' => 'Jenis update tidak valid.',
            'progress_percent.integer' => 'Progress harus berupa angka.',
            'progress_percent.min' => 'Progress minimal 0%.',
            'progress_percent.max' => 'Progress maksimal 100%.',
            'happened_at.required' => 'Tanggal realisasi wajib diisi.',
            'happened_at.date' => 'Tanggal realisasi tidak valid.',
            'is_on_target.required' => 'Status sesuai target wajib dipilih.',
            'is_on_target.boolean' => 'Status sesuai target tidak valid.',
            'evidence_files.required' => 'Bukti progress wajib diunggah.',
            'evidence_files.min' => 'Bukti progress wajib diunggah minimal 1 file.',
            'evidence_files.*.file' => 'Bukti progress harus berupa file yang valid.',
            'evidence_files.*.mimes' => 'Format bukti progress tidak didukung.',
        ]);

        if (!$request->boolean('is_on_target') && trim((string) $request->input('reason')) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan wajib diisi saat realisasi tidak sesuai target.',
            ]);
        }

        $updateType = (string) $request->input('update_type');
        $defaultProgress = (int) (
            $decision->latest_progress_percent
            ?? optional($decision->updates()->latest('id')->first())->progress_percent
            ?? 0
        );
        $progress = match ($updateType) {
            DecisionUpdate::TYPE_DONE => 100,
            DecisionUpdate::TYPE_DROP => $request->filled('progress_percent')
                ? (int) $request->input('progress_percent')
                : $defaultProgress,
            default => $request->filled('progress_percent')
                ? (int) $request->input('progress_percent')
                : $defaultProgress,
        };

        DB::transaction(function () use ($request, $meeting, $decision, $user, $updateType, $progress) {
            $updateStatus = match ($updateType) {
                DecisionUpdate::TYPE_DONE => DecisionUpdate::STATUS_DONE,
                DecisionUpdate::TYPE_CONTINUOUS => DecisionUpdate::STATUS_CONTINUOUS,
                DecisionUpdate::TYPE_DROP => DecisionUpdate::STATUS_DROPPED,
                default => DecisionUpdate::STATUS_IN_PROGRESS,
            };
            $decisionStatus = match ($updateType) {
                DecisionUpdate::TYPE_DONE => MeetingDecision::STATUS_DONE,
                DecisionUpdate::TYPE_CONTINUOUS => MeetingDecision::STATUS_CONTINUOUS,
                DecisionUpdate::TYPE_DROP => MeetingDecision::STATUS_DROPPED,
                default => MeetingDecision::STATUS_IN_PROGRESS,
            };

            $update = DecisionUpdate::create([
                'meeting_decision_id' => $decision->id,
                'progress_percent' => $progress,
                'update_type' => $updateType,
                'status' => $updateStatus,
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
                'status' => $decisionStatus,
                'closed_at' => in_array($decisionStatus, $this->resolvedDecisionStatuses(), true) ? now() : null,
                'updated_by' => $user->id,
            ]);

            $decision = $decision->fresh(['updates']);
            $this->recordDecisionOccurrence($decision, null, $user);
            $rootDecisionId = $this->resolveDecisionFamilyRootId($decision) ?? (int) $decision->id;
            $this->syncDecisionFamilySummaryByRootDecisionId($rootDecisionId);
            $this->syncMeetingDecisionFamilySummaries($meeting);
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

    public function directorNote(Request $request, Meeting $meeting)
    {
        $user = Auth::user();
        if (!$this->permissionService->canAddDirectorNote($user)) {
            abort(403, 'Anda tidak memiliki akses untuk menambahkan catatan.');
        }

        $validated = $request->validate([
            'note' => ['required', 'string'],
        ]);

        Comment::create([
            'commentable_type' => Meeting::class,
            'commentable_id' => $meeting->id,
            'body' => '[KOMENTAR VIEWER] ' . $validated['note'],
            'created_by' => $user?->id,
        ]);

        return $this->successRedirectResponse(
            $request,
            route('meeting.show', $meeting),
            'Komentar viewer berhasil disimpan.'
        );
    }

    private function validateMeetingPayload(Request $request, ?string $allowedCurrentMeetingType = null): array
    {
        $allowedMeetingTypes = array_keys(Meeting::typeOptionsFromMasterData(true));
        if ($allowedCurrentMeetingType !== null && $allowedCurrentMeetingType !== '') {
            if (!in_array($allowedCurrentMeetingType, $allowedMeetingTypes, true)) {
                $allowedMeetingTypes[] = $allowedCurrentMeetingType;
            }
        }

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'meeting_type' => ['required', Rule::in($allowedMeetingTypes)],
            'meeting_date' => ['nullable', 'date'],
            'meeting_dates' => ['nullable', 'array', 'max:31'],
            'meeting_dates.*' => ['nullable', 'date'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['nullable', 'exists:corsec_directorates,id'],
            'participant_users' => ['nullable', 'array'],
            'participant_users.*' => ['nullable', 'exists:users,id'],
            'agendas' => ['nullable', 'array'],
            'agendas.*.title' => ['required_with:agendas', 'string', 'max:255'],
            'agendas.*.description' => ['nullable', 'string'],
            'agendas.*.owner_directorate_id' => ['nullable', 'exists:corsec_directorates,id'],
            'agendas.*.pic_user_id' => ['nullable', 'exists:users,id'],
            'escalation_decision_ids' => ['nullable', 'array'],
            'escalation_decision_ids.*' => ['nullable', 'integer', 'exists:corsec_meeting_decisions,id'],
            'submit_for_approval' => ['nullable', 'boolean'],
            'submit_note' => ['nullable', 'string'],
        ]);

        $meetingType = (string) ($payload['meeting_type'] ?? '');
        $meetingDates = collect((array) ($payload['meeting_dates'] ?? []))
            ->filter()
            ->map(fn($date) => (string) $date)
            ->unique()
            ->values();

        if (Meeting::isDirektoratTypeCode($meetingType)) {
            $participantCount = $this->expandParticipantDirectorateIds((array) ($payload['participants'] ?? []))
                ->filter()
                ->unique()
                ->count();
            if ($participantCount === 0) {
                throw ValidationException::withMessages([
                    'participants' => 'Untuk rapat direktorat, minimal 1 peserta direktorat wajib dipilih.',
                ]);
            }

            if ($meetingDates->isEmpty() && empty($payload['meeting_date'])) {
                throw ValidationException::withMessages([
                    'meeting_dates' => 'Minimal 1 tanggal rapat wajib diisi untuk rapat direktorat.',
                ]);
            }
        } elseif (empty($payload['meeting_date'])) {
            throw ValidationException::withMessages([
                'meeting_date' => 'Tanggal rapat wajib diisi.',
            ]);
        }

        $payload['meeting_dates'] = $meetingDates->all();

        if (!Meeting::isDirektoratTypeCode($meetingType)) {
            $this->ensureNonDirektoratAutoPicUsersAvailable();
        }

        $payload['escalation_decision_ids'] = collect((array) ($payload['escalation_decision_ids'] ?? []))
            ->filter(fn($id) => $id !== null && $id !== '')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($this->isMancommMeetingTypeCode($meetingType) && !empty($payload['escalation_decision_ids'])) {
            $participantDirectorateIds = $this->expandParticipantDirectorateIds((array) ($payload['participants'] ?? []))
                ->merge($this->resolveMandatoryDirectorateIds())
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values();

            $selectedEscalationDecisions = $this->mancommEscalationDecisionBacklogQuery()
                ->whereIn('id', $payload['escalation_decision_ids'])
                ->get(['id', 'owner_directorate_id']);

            if ($selectedEscalationDecisions->count() !== count($payload['escalation_decision_ids'])) {
                throw ValidationException::withMessages([
                    'escalation_decision_ids' => 'Sebagian agenda eskalasi sudah tidak valid atau tidak lagi open di backlog direktorat.',
                ]);
            }

            $invalidDirectorateEscalations = $selectedEscalationDecisions
                ->filter(function (MeetingDecision $decision) use ($participantDirectorateIds) {
                    return !$participantDirectorateIds->contains((int) ($decision->owner_directorate_id ?? 0));
                });

            if ($invalidDirectorateEscalations->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'escalation_decision_ids' => 'Agenda eskalasi mancomm harus berasal dari direktorat yang sudah dipilih sebagai peserta rapat.',
                ]);
            }
        }

        if (!$this->isMancommMeetingTypeCode($meetingType)) {
            $payload['escalation_decision_ids'] = [];
        }

        return $payload;
    }

    private function buildMeetingAt(string $meetingDate, ?string $meetingTime): string
    {
        return trim($meetingDate . ' ' . ($meetingTime ?: '00:00') . ':00');
    }

    private function syncParticipants(
        Meeting $meeting,
        array $participantIds,
        array $participantUserIds,
        User $user,
        string $meetingType,
        bool $requireOperationalParticipant = true
    ): void {
        $cleanDirectorateIds = $this->expandParticipantDirectorateIds($participantIds);
        $mandatoryDirectorateIds = collect($this->resolveMandatoryDirectorateIds())
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $cleanDirectorateIds = $cleanDirectorateIds
            ->merge($mandatoryDirectorateIds)
            ->unique()
            ->values();

        $cleanUserIds = collect($participantUserIds)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if (Meeting::isDirektoratTypeCode($meetingType)) {
            $autoPicDirectorateIds = $cleanDirectorateIds
                ->reject(fn($id) => $mandatoryDirectorateIds->contains((int) $id))
                ->values();

            if ($autoPicDirectorateIds->isNotEmpty()) {
                $operationalAutoPicDirectorateIds = $this->filterOperationalMeetingDirectorateIds($autoPicDirectorateIds->all());
                if ($operationalAutoPicDirectorateIds->isEmpty()) {
                    if ($requireOperationalParticipant) {
                        throw ValidationException::withMessages([
                            'participants' => 'Peserta terpilih hanya unit monitoring. Pilih minimal 1 unit operasional untuk submit rapat direktorat.',
                        ]);
                    }

                    $operationalAutoPicDirectorateIds = collect();
                }

                if ($operationalAutoPicDirectorateIds->isNotEmpty()) {
                    $autoPicUsersByDirectorate = $this->resolveStaffPicUserIdsByDirectorate(
                        $operationalAutoPicDirectorateIds->all(),
                        true
                    );
                    $cleanUserIds = $cleanUserIds
                        ->merge(array_values($autoPicUsersByDirectorate))
                        ->filter()
                        ->map(fn($id) => (int) $id)
                        ->unique()
                        ->values();
                }
            }
        } else {
            $cleanUserIds = collect($this->resolveNonDirektoratAutoPicUserIds(true))
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values();
        }

        $participantUsers = $cleanUserIds->isEmpty()
            ? collect()
            : User::query()
            ->whereIn('id', $cleanUserIds->all())
            ->get(['id', 'directorate_id']);

        MeetingParticipant::query()->where('meeting_id', $meeting->id)->delete();
        foreach ($cleanDirectorateIds as $directorateId) {
            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'directorate_id' => $directorateId,
                'created_by' => $user->id,
            ]);
        }

        foreach ($participantUsers as $participantUser) {
            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'directorate_id' => $participantUser->directorate_id,
                'user_id' => $participantUser->id,
                'created_by' => $user->id,
            ]);
        }
    }

    private function appendParticipants(Meeting $meeting, array $participantIds, User $user, string $meetingType): void
    {
        $cleanDirectorateIds = $this->expandParticipantDirectorateIds($participantIds);
        $mandatoryDirectorateIds = collect($this->resolveMandatoryDirectorateIds())
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $cleanDirectorateIds = $cleanDirectorateIds
            ->merge($mandatoryDirectorateIds)
            ->unique()
            ->values();

        foreach ($cleanDirectorateIds as $directorateId) {
            MeetingParticipant::query()->firstOrCreate(
                [
                    'meeting_id' => $meeting->id,
                    'directorate_id' => (int) $directorateId,
                    'user_id' => null,
                ],
                [
                    'created_by' => $user->id,
                ]
            );
        }

        if (!Meeting::isDirektoratTypeCode($meetingType) || $cleanDirectorateIds->isEmpty()) {
            return;
        }

        $autoPicDirectorateIds = $cleanDirectorateIds
            ->reject(fn($id) => $mandatoryDirectorateIds->contains((int) $id))
            ->values();
        if ($autoPicDirectorateIds->isEmpty()) {
            return;
        }

        $operationalAutoPicDirectorateIds = $this->filterOperationalMeetingDirectorateIds($autoPicDirectorateIds->all());
        if ($operationalAutoPicDirectorateIds->isEmpty()) {
            return;
        }

        $autoPicUsersByDirectorate = $this->resolveStaffPicUserIdsByDirectorate($operationalAutoPicDirectorateIds->all(), true);
        if (empty($autoPicUsersByDirectorate)) {
            return;
        }

        $participantUsers = User::query()
            ->whereIn('id', array_values($autoPicUsersByDirectorate))
            ->get(['id', 'directorate_id']);

        foreach ($participantUsers as $participantUser) {
            MeetingParticipant::query()->firstOrCreate(
                [
                    'meeting_id' => $meeting->id,
                    'user_id' => (int) $participantUser->id,
                ],
                [
                    'directorate_id' => (int) ($participantUser->directorate_id ?? 0) ?: null,
                    'created_by' => $user->id,
                ]
            );
        }
    }

    private function expandParticipantDirectorateIds(array $participantIds): Collection
    {
        $selectedIds = collect($participantIds)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return collect();
        }

        $directorates = $this->meetingDirectoratesCollection();
        $selectedGroupKeys = $directorates
            ->whereIn('id', $selectedIds->all())
            ->map(fn(Directorate $directorate) => $this->directorateParticipantGroupKey($directorate))
            ->filter()
            ->unique()
            ->values();

        if ($selectedGroupKeys->isEmpty()) {
            return collect();
        }

        return $directorates
            ->filter(function (Directorate $directorate) use ($selectedGroupKeys) {
                return $selectedGroupKeys->contains($this->directorateParticipantGroupKey($directorate));
            })
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function directorateParticipantGroupKey(Directorate $directorate): string
    {
        $label = preg_replace('/\s+/', ' ', trim($directorate->displayName()));
        return strtolower((string) $label);
    }

    private function expandDirectorateVisibilityIds(?int $directorateId): Collection
    {
        $directorateId = (int) ($directorateId ?? 0);
        if ($directorateId <= 0) {
            return collect();
        }

        return $this->expandParticipantDirectorateIds([$directorateId]);
    }

    private function resolveMandatoryDirectorateIds(): array
    {
        $ids = collect();

        $corpSecretaryId = $this->resolveCorpSecretaryDirectorateId();
        if ($corpSecretaryId) {
            $ids->push((int) $corpSecretaryId);
        }

        $wakilDirekturUtamaCode = (string) config('corsec.wakil_direktur_utama_directorate_code', '');
        $wakilDirekturUtamaId = null;
        if ($wakilDirekturUtamaCode !== '') {
            $wakilDirekturUtamaId = Directorate::query()->where('code', $wakilDirekturUtamaCode)->value('id');
        }
        if (!$wakilDirekturUtamaId) {
            $wakilDirekturUtamaId = Directorate::query()
                ->where('name', 'ilike', '%wakil direktur utama%')
                ->value('id');
        }
        if ($wakilDirekturUtamaId) {
            $ids->push((int) $wakilDirekturUtamaId);
        }

        return $ids->filter()->unique()->values()->all();
    }

    private function resolveCorpSecretaryDirectorateId(): ?int
    {
        $corpSecretaryCode = (string) config('corsec.eo_corp_affair_directorate_code', '');
        if ($corpSecretaryCode !== '') {
            $directorateId = Directorate::query()
                ->where('code', $corpSecretaryCode)
                ->value('id');
            if ($directorateId) {
                return (int) $directorateId;
            }
        }

        $directorateId = Directorate::query()
            ->where('name', 'ilike', '%corporate secretary%')
            ->value('id');

        return $directorateId ? (int) $directorateId : null;
    }

    private function filterOperationalMeetingDirectorateIds(array $directorateIds): \Illuminate\Support\Collection
    {
        $directorateIds = collect($directorateIds)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();
        if ($directorateIds->isEmpty()) {
            return collect();
        }

        return Directorate::query()
            ->whereIn('id', $directorateIds->all())
            ->where('is_meeting_operational', true)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values();
    }

    private function resolveStaffPicUserIdsByDirectorate(array $directorateIds, bool $failWhenMissing = false): array
    {
        $directorateIds = collect($directorateIds)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($directorateIds->isEmpty()) {
            return [];
        }

        $operationalRoleNames = ['maker', 'checker', 'approver'];
        $mapping = [];
        $scoreByDirectorate = [];
        $assignCandidateUsers = function (Collection $candidateUsers) use (&$mapping, &$scoreByDirectorate) {
            foreach ($candidateUsers as $candidateUser) {
                $directorateId = (int) ($candidateUser->directorate_id ?? 0);
                if ($directorateId <= 0) {
                    continue;
                }

                $positionName = strtolower(trim((string) ($candidateUser->position?->name ?? '')));
                $candidateScore = $this->resolveAutoPicCandidateScore($positionName);

                if (
                    !isset($mapping[$directorateId])
                    || $candidateScore < ($scoreByDirectorate[$directorateId] ?? PHP_INT_MAX)
                    || (
                        $candidateScore === ($scoreByDirectorate[$directorateId] ?? PHP_INT_MAX)
                        && (int) $candidateUser->id < (int) $mapping[$directorateId]
                    )
                ) {
                    $mapping[$directorateId] = (int) $candidateUser->id;
                    $scoreByDirectorate[$directorateId] = $candidateScore;
                }
            }
        };

        $preferredUsers = User::query()
            ->whereIn('directorate_id', $directorateIds->all())
            ->whereHas('roles', function ($query) use ($operationalRoleNames) {
                $query->whereIn('name', $operationalRoleNames);
            })
            ->with('position:id,name')
            ->orderBy('id')
            ->get(['id', 'directorate_id', 'position_id']);
        $assignCandidateUsers($preferredUsers);

        $missingDirectorateIds = $directorateIds->filter(fn($id) => !isset($mapping[(int) $id]))->values();
        if ($missingDirectorateIds->isNotEmpty()) {
            $fallbackUsers = User::query()
                ->whereIn('directorate_id', $missingDirectorateIds->all())
                ->with('position:id,name')
                ->orderBy('id')
                ->get(['id', 'directorate_id', 'position_id']);
            $assignCandidateUsers($fallbackUsers);
        }

        if ($failWhenMissing) {
            $missingDirectorateIds = $directorateIds->filter(fn($id) => !isset($mapping[(int) $id]))->values();
            if ($missingDirectorateIds->isNotEmpty() && empty($mapping)) {
                $missingNames = Directorate::query()
                    ->whereIn('id', $missingDirectorateIds->all())
                    ->get(['id', 'name', 'tabulation_label'])
                    ->map(fn(Directorate $directorate) => $directorate->displayName())
                    ->values()
                    ->all();
                throw ValidationException::withMessages([
                    'participants' => 'Belum ada user PIC yang dapat dipilih untuk direktorat terpilih: ' . implode(', ', $missingNames),
                ]);
            }
        }

        return $mapping;
    }

    private function resolveAutoPicCandidateScore(string $positionName): int
    {
        if ($positionName !== '' && str_contains($positionName, 'staff')) {
            return 1;
        }
        if ($positionName !== '' && str_contains($positionName, 'executive officer')) {
            return 2;
        }
        if ($positionName !== '' && str_contains($positionName, 'deputy director')) {
            return 3;
        }

        return 4;
    }

    private function corpSecretaryUsersQuery()
    {
        $query = User::query();
        $corpSecretaryDirectorateId = $this->resolveCorpSecretaryDirectorateId();

        if ($corpSecretaryDirectorateId) {
            return $query->where('directorate_id', $corpSecretaryDirectorateId);
        }

        return $query->whereHas('directorate', function ($directorateQuery) {
            $directorateQuery->where('name', 'ilike', '%corporate secretary%');
        });
    }

    private function corpSecretaryStaffUsersQuery()
    {
        return $this->corpSecretaryUsersQuery()
            ->whereHas('position', function ($positionQuery) {
                $positionQuery->where('name', 'ilike', '%staff%');
            });
    }

    private function resolveNonDirektoratAutoPicUserIds(bool $failWhenMissing = false): array
    {
        $userIds = $this->corpSecretaryStaffUsersQuery()
            ->orderBy('name')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values();

        if ($failWhenMissing && $userIds->isEmpty()) {
            throw ValidationException::withMessages([
                'participant_users' => 'Belum ada user Corporate Secretary dengan posisi Staff untuk auto assign PIC rapat non-direktorat.',
            ]);
        }

        return $userIds->all();
    }

    private function ensureNonDirektoratAutoPicUsersAvailable(): void
    {
        $this->resolveNonDirektoratAutoPicUserIds(true);
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
            $picUserId = isset($agendaPayload['pic_user_id']) && $agendaPayload['pic_user_id'] !== ''
                ? (int) $agendaPayload['pic_user_id']
                : null;
            $ownerDirectorateId = $this->resolveOwnerDirectorateId(
                isset($agendaPayload['owner_directorate_id']) && $agendaPayload['owner_directorate_id'] !== ''
                    ? (int) $agendaPayload['owner_directorate_id']
                    : null,
                $picUserId
            );
            if (!$ownerDirectorateId && !$picUserId) {
                throw ValidationException::withMessages([
                    'agendas' => 'PIC user atau direktorat wajib diisi untuk setiap agenda meeting.',
                ]);
            }

            MeetingAgenda::create([
                'meeting_id' => $meeting->id,
                'order_no' => $orderNo++,
                'title' => $title,
                'description' => $agendaPayload['description'] ?? null,
                'owner_directorate_id' => $ownerDirectorateId,
                'pic_user_id' => $picUserId,
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
            $picUserId = isset($agendaPayload['pic_user_id']) && $agendaPayload['pic_user_id'] !== ''
                ? (int) $agendaPayload['pic_user_id']
                : null;
            $ownerDirectorateId = $this->resolveOwnerDirectorateId(
                isset($agendaPayload['owner_directorate_id']) && $agendaPayload['owner_directorate_id'] !== ''
                    ? (int) $agendaPayload['owner_directorate_id']
                    : null,
                $picUserId
            );
            if (!$ownerDirectorateId && !$picUserId) {
                throw ValidationException::withMessages([
                    'additional_agendas' => 'PIC user atau direktorat wajib diisi untuk agenda tambahan.',
                ]);
            }

            MeetingAgenda::create([
                'meeting_id' => $meeting->id,
                'order_no' => $nextOrder++,
                'title' => $title,
                'description' => $agendaPayload['description'] ?? null,
                'owner_directorate_id' => $ownerDirectorateId,
                'pic_user_id' => $picUserId,
            ]);
        }
    }

    private function syncOptionalMancommEscalationAgendas(Meeting $meeting, string $meetingType, array $decisionIds): void
    {
        if (
            !$this->isMancommMeetingTypeCode($meetingType)
            || !Schema::hasTable('corsec_meeting_agendas')
            || !Schema::hasColumn('corsec_meeting_agendas', 'source_decision_id')
        ) {
            return;
        }

        MeetingAgenda::query()
            ->where('meeting_id', $meeting->id)
            ->whereNotNull('source_decision_id')
            ->whereHas('sourceDecision.meeting', function ($query) {
                $query->where('meeting_type', Meeting::TYPE_DIREKTORAT);
            })
            ->delete();

        $selectedDecisionIds = collect($decisionIds)
            ->filter(fn($id) => $id !== null && $id !== '')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values();

        if ($selectedDecisionIds->isEmpty()) {
            return;
        }

        $sourceDecisions = $this->mancommEscalationDecisionBacklogQuery()
            ->whereIn('id', $selectedDecisionIds->all())
            ->with(['meeting:id,uuid,title,meeting_type,meeting_at', 'ownerDirectorate', 'picUser'])
            ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_date')
            ->orderBy('id')
            ->get();

        if ($sourceDecisions->count() !== $selectedDecisionIds->count()) {
            throw ValidationException::withMessages([
                'escalation_decision_ids' => 'Sebagian agenda eskalasi mancomm tidak lagi tersedia untuk dipilih.',
            ]);
        }

        $nextOrder = (int) MeetingAgenda::query()->where('meeting_id', $meeting->id)->max('order_no');
        $nextOrder = $nextOrder > 0 ? $nextOrder + 1 : 1;

        foreach ($sourceDecisions as $sourceDecision) {
            $sourceMeetingTitle = trim((string) ($sourceDecision->meeting?->title ?? ''));
            $sourceDecisionKey = trim((string) ($sourceDecision->issue_key ?: $sourceDecision->decision_key));

            $titleParts = collect([
                $sourceDecisionKey !== '' ? '[' . $sourceDecisionKey . ']' : null,
                trim((string) $sourceDecision->decision_text),
            ])->filter();

            $descriptionParts = collect([
                'Agenda eskalasi opsional dari rapat direktorat',
                $sourceMeetingTitle !== '' ? 'Sumber: ' . $sourceMeetingTitle : null,
                $sourceDecision->target_date ? 'Target: ' . $sourceDecision->target_date->format('d/m/Y') : null,
            ])->filter();

            MeetingAgenda::create([
                'meeting_id' => $meeting->id,
                'order_no' => $nextOrder++,
                'title' => $titleParts->implode(' '),
                'description' => $descriptionParts->implode(' | ') ?: null,
                'owner_directorate_id' => $sourceDecision->owner_directorate_id,
                'pic_user_id' => $sourceDecision->pic_user_id,
                'source_decision_id' => $sourceDecision->id,
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
        return $this->permissionService->canViewAllCorsec($user);
    }

    private function scopedMeetingsQuery(User $user)
    {
        $query = Meeting::query();
        if ($this->canViewAllMeetings($user)) {
            return $query;
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        $visibilityDirectorateIds = $this->expandDirectorateVisibilityIds($directorateId);
        return $query->where(function ($w) use ($user, $directorateId, $visibilityDirectorateIds) {
            $w->where('created_by', $user->id);
            $w->orWhereHas('participants', function ($q) use ($user) {
                $q->where('user_id', (int) $user->id);
            });
            $w->orWhereHas('agendas', function ($q) use ($user) {
                $q->where('pic_user_id', (int) $user->id);
            });
            $w->orWhereHas('decisions', function ($q) use ($user) {
                $q->where('pic_user_id', (int) $user->id);
            });
            if ($directorateId > 0) {
                $ids = $visibilityDirectorateIds->isNotEmpty() ? $visibilityDirectorateIds->all() : [$directorateId];
                $w->orWhereHas('participants', function ($q) use ($ids) {
                    $q->whereIn('directorate_id', $ids);
                });
                $w->orWhereHas('agendas', function ($q) use ($ids) {
                    $q->whereIn('owner_directorate_id', $ids);
                });
                $w->orWhereHas('decisions', function ($q) use ($ids) {
                    $q->whereIn('owner_directorate_id', $ids);
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

        if ($this->isMeetingAssignedToUser($meeting, $user)) {
            return true;
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        if ($directorateId <= 0) {
            return false;
        }

        $visibilityDirectorateIds = $this->expandDirectorateVisibilityIds($directorateId);
        $ids = $visibilityDirectorateIds->isNotEmpty() ? $visibilityDirectorateIds->all() : [$directorateId];

        if ($meeting->participants()->whereIn('directorate_id', $ids)->exists()) {
            return true;
        }

        if ($meeting->agendas()->whereIn('owner_directorate_id', $ids)->exists()) {
            return true;
        }

        return $meeting->decisions()
            ->whereIn('owner_directorate_id', $ids)
            ->exists();
    }

    private function canActAsMeetingDirectorate(Meeting $meeting, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ($meeting->isDirektoratType()) {
            return $this->isMeetingAssignedToUser($meeting, $user);
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        if ($directorateId <= 0) {
            return $this->isMeetingAssignedToUser($meeting, $user);
        }

        return $meeting->participants()->where('directorate_id', $directorateId)->exists()
            || $meeting->agendas()->where('owner_directorate_id', $directorateId)->exists()
            || $meeting->decisions()->where('owner_directorate_id', $directorateId)->exists()
            || $this->isMeetingAssignedToUser($meeting, $user);
    }

    private function canSubmitMeetingPreparation(Meeting $meeting, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ($meeting->isDirektoratType()) {
            return $this->canActAsMeetingDirectorate($meeting, $user);
        }

        if ($this->permissionService->isCorpSecretaryDirectorate($user)) {
            return false;
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        if ($directorateId <= 0) {
            return false;
        }

        return $meeting->participants()->where('directorate_id', $directorateId)->exists()
            || $meeting->agendas()->where('owner_directorate_id', $directorateId)->exists()
            || $meeting->decisions()->where('owner_directorate_id', $directorateId)->exists();
    }

    private function canUpdateMeetingDecision(Meeting $meeting, MeetingDecision $decision, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ((int) ($decision->pic_user_id ?? 0) > 0) {
            return (int) ($decision->pic_user_id ?? 0) === (int) $user->id;
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        if ($directorateId <= 0) {
            return $this->isMeetingAssignedToUser($meeting, $user);
        }

        $decisionDirectorateId = (int) ($decision->owner_directorate_id ?? 0);
        if ($decisionDirectorateId > 0) {
            return $directorateId === $decisionDirectorateId;
        }

        return $this->canActAsMeetingDirectorate($meeting, $user);
    }

    private function isMeetingAssignedToUser(Meeting $meeting, User $user): bool
    {
        return $meeting->participants()->where('user_id', (int) $user->id)->exists()
            || $meeting->agendas()->where('pic_user_id', (int) $user->id)->exists()
            || $meeting->decisions()->where('pic_user_id', (int) $user->id)->exists();
    }

    private function canUploadMeetingAgendaMaterial(MeetingAgenda $agenda, User $user): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        return (int) ($agenda->pic_user_id ?? 0) > 0
            && (int) ($agenda->pic_user_id ?? 0) === (int) $user->id;
    }

    private function materialAgendaOptionsForUser(Meeting $meeting, User $user): Collection
    {
        $meeting->loadMissing(['agendas.sourceDecision', 'agendas.picUser']);

        return $meeting->agendas
            ->filter(fn(MeetingAgenda $agenda) => $this->canUploadMeetingAgendaMaterial($agenda, $user))
            ->sortBy(function (MeetingAgenda $agenda) {
                return (int) ($agenda->order_no ?? 0);
            })
            ->values();
    }

    private function canOpenPresentationMode(Meeting $meeting): bool
    {
        if ((string) ($meeting->status ?? '') !== Meeting::STATUS_DATA_TERKIRIM) {
            return false;
        }

        $hasMinutes = $meeting->relationLoaded('minutes')
            ? $meeting->minutes !== null
            : $meeting->minutes()->exists();

        if ($hasMinutes) {
            return false;
        }

        return $meeting->relationLoaded('materials')
            ? $meeting->materials->isNotEmpty()
            : $meeting->materials()->exists();
    }

    private function buildMeetingPresentationSlides(Meeting $meeting): array
    {
        $slides = [];
        $runningIndex = 1;

        $agendas = $meeting->agendas
            ->sortBy(fn(MeetingAgenda $agenda) => (int) ($agenda->order_no ?? 0))
            ->values();

        foreach ($agendas as $agendaIndex => $agenda) {
            $agendaNo = (int) ($agenda->order_no ?? ($agendaIndex + 1));

            $materials = $agenda->materials
                ->filter(function (MeetingMaterial $material) {
                    return $material->attachment && filled($material->attachment->path);
                })
                ->sortBy(fn(MeetingMaterial $material) => (int) $material->id)
                ->map(function (MeetingMaterial $material) use ($meeting) {
                    $attachment = $material->attachment;
                    $materialName = (string) ($attachment->original_name ?: $attachment->file_name ?: 'Bahan Rapat');
                    $viewerType = $this->resolvePresentationViewerType(
                        (string) ($attachment->mime ?? ''),
                        $materialName
                    );
                    $requestUrl = rtrim((string) request()->url(), '/');
                    $materialUrl = preg_replace(
                        '#/persentation$#',
                        '/materials/' . $material->getKey() . '/file',
                        $requestUrl
                    );

                    return [
                        'material_name' => $materialName,
                        'material_url' => is_string($materialUrl) && $materialUrl !== ''
                            ? $materialUrl
                            : route('meeting.material.file', [$meeting, $material]),
                        'material_mime' => (string) ($attachment->mime ?? ''),
                        'viewer_type' => $viewerType,
                        'viewer_label' => $this->resolvePresentationViewerLabel($viewerType),
                        'file_extension' => Str::upper(pathinfo($materialName, PATHINFO_EXTENSION)),
                        'uploaded_at' => optional($material->uploaded_at)->format('d/m/Y H:i'),
                    ];
                })
                ->values();

            $inlineMaterialCount = $materials
                ->whereIn('viewer_type', ['image', 'pdf'])
                ->count();

            $slides[] = [
                'index' => $runningIndex,
                'slide_type' => 'agenda',
                'slide_label' => 'Pembuka',
                'slide_caption' => 'Ringkasan agenda dan urutan bahan',
                'agenda_no' => $agendaNo,
                'agenda_title' => (string) ($agenda->title ?? '-'),
                'agenda_description' => trim((string) ($agenda->description ?? '')),
                'agenda_owner' => $agenda->ownerDirectorate?->displayName(),
                'agenda_pic' => $agenda->picUser?->name,
                'source_meeting' => $agenda->sourceDecision?->meeting?->title,
                'source_reference' => $agenda->sourceDecision?->issue_key ?: $agenda->sourceDecision?->decision_key,
                'agenda_material_count' => $materials->count(),
                'agenda_inline_material_count' => $inlineMaterialCount,
                'material_no' => null,
                'material_name' => null,
                'material_url' => null,
                'material_mime' => null,
                'viewer_type' => 'agenda',
                'viewer_label' => 'Agenda',
                'file_extension' => null,
                'uploaded_at' => null,
            ];

            $runningIndex++;

            foreach ($materials as $materialIndex => $materialMeta) {
                $slides[] = [
                    'index' => $runningIndex,
                    'slide_type' => 'material',
                    'slide_label' => 'Bahan ' . ($materialIndex + 1),
                    'slide_caption' => $materialMeta['material_name'],
                    'agenda_no' => $agendaNo,
                    'agenda_title' => (string) ($agenda->title ?? '-'),
                    'agenda_description' => trim((string) ($agenda->description ?? '')),
                    'agenda_owner' => $agenda->ownerDirectorate?->displayName(),
                    'agenda_pic' => $agenda->picUser?->name,
                    'source_meeting' => $agenda->sourceDecision?->meeting?->title,
                    'source_reference' => $agenda->sourceDecision?->issue_key ?: $agenda->sourceDecision?->decision_key,
                    'agenda_material_count' => $materials->count(),
                    'agenda_inline_material_count' => $inlineMaterialCount,
                    'material_no' => $materialIndex + 1,
                    'material_name' => $materialMeta['material_name'],
                    'material_url' => $materialMeta['material_url'],
                    'material_mime' => $materialMeta['material_mime'],
                    'viewer_type' => $materialMeta['viewer_type'],
                    'viewer_label' => $materialMeta['viewer_label'],
                    'file_extension' => $materialMeta['file_extension'] ?: null,
                    'uploaded_at' => $materialMeta['uploaded_at'],
                ];

                $runningIndex++;
            }
        }

        return $slides;
    }

    private function buildMeetingPresentationAgendaGroups(array $slides): array
    {
        return collect($slides)
            ->groupBy(fn(array $slide) => 'agenda-' . (int) ($slide['agenda_no'] ?? 0))
            ->map(function (Collection $group) {
                $firstSlide = $group->first();

                return [
                    'agenda_no' => (int) ($firstSlide['agenda_no'] ?? 0),
                    'agenda_title' => (string) ($firstSlide['agenda_title'] ?? '-'),
                    'slide_count' => $group->count(),
                    'material_count' => $group->where('slide_type', 'material')->count(),
                    'slides' => $group->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function resolvePresentationViewerType(string $mime, string $fileName): string
    {
        $normalizedMime = Str::lower(trim($mime));
        $extension = Str::lower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (Str::startsWith($normalizedMime, 'image/')) {
            return 'image';
        }

        if ($normalizedMime === 'application/pdf' || $extension === 'pdf') {
            return 'pdf';
        }

        return 'download';
    }

    private function resolvePresentationViewerLabel(string $viewerType): string
    {
        return match ($viewerType) {
            'image' => 'Image',
            'pdf' => 'PDF',
            default => 'Open File',
        };
    }

    private function resolveOwnerDirectorateId(?int $ownerDirectorateId, ?int $picUserId): ?int
    {
        if ($ownerDirectorateId && $ownerDirectorateId > 0) {
            return $ownerDirectorateId;
        }

        if (!$picUserId || $picUserId <= 0) {
            return null;
        }

        $directorateId = User::query()
            ->where('id', $picUserId)
            ->value('directorate_id');

        return $directorateId ? (int) $directorateId : null;
    }

    private function syncInheritedDirectorateAgendas(Meeting $meeting): void
    {
        if (
            !$meeting->isDirektoratType()
            || !Schema::hasTable('corsec_meeting_agendas')
            || !Schema::hasColumn('corsec_meeting_agendas', 'source_decision_id')
        ) {
            return;
        }

        $targetDirectorateIds = $this->meetingBacklogDirectorateIds($meeting);
        if ($targetDirectorateIds->isEmpty()) {
            return;
        }

        MeetingAgenda::query()
            ->where('meeting_id', $meeting->id)
            ->whereNotNull('source_decision_id')
            ->whereDoesntHave('sourceDecision', function ($query) use ($targetDirectorateIds) {
                $query->whereIn('owner_directorate_id', $targetDirectorateIds->all())
                    ->whereHas('meeting', function ($meetingQuery) {
                        $meetingQuery->whereIn('meeting_type', [
                            Meeting::TYPE_KOMISARIS,
                            Meeting::TYPE_DIREKSI,
                            Meeting::TYPE_MANCOMM,
                        ]);
                    });
            })
            ->delete();

        $existingSourceDecisionIds = MeetingAgenda::query()
            ->where('meeting_id', $meeting->id)
            ->whereNotNull('source_decision_id')
            ->pluck('source_decision_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->values();

        $sourceDecisions = $this->inheritedDecisionBacklogQuery($meeting, $targetDirectorateIds)
            ->when($existingSourceDecisionIds->isNotEmpty(), function ($query) use ($existingSourceDecisionIds) {
                $query->whereNotIn('id', $existingSourceDecisionIds->all());
            })
            ->with(['meeting:id,uuid,title,meeting_type,meeting_at', 'ownerDirectorate', 'picUser'])
            ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_date')
            ->orderBy('id')
            ->get();

        if ($sourceDecisions->isEmpty()) {
            return;
        }

        $nextOrder = (int) MeetingAgenda::query()->where('meeting_id', $meeting->id)->max('order_no');
        $nextOrder = $nextOrder > 0 ? $nextOrder + 1 : 1;

        foreach ($sourceDecisions as $sourceDecision) {
            $sourceMeetingTitle = trim((string) ($sourceDecision->meeting?->title ?? ''));
            $sourceDecisionKey = trim((string) ($sourceDecision->decision_key ?? ''));
            $titleParts = collect([
                $sourceDecisionKey !== '' ? '[' . $sourceDecisionKey . ']' : null,
                trim((string) $sourceDecision->decision_text),
            ])->filter();

            $descriptionParts = collect([
                $sourceMeetingTitle !== '' ? 'Agenda default dari ' . $sourceMeetingTitle : null,
                $sourceDecision->target_date ? 'Target: ' . $sourceDecision->target_date->format('d/m/Y') : null,
            ])->filter();

            MeetingAgenda::create([
                'meeting_id' => $meeting->id,
                'order_no' => $nextOrder++,
                'title' => $titleParts->implode(' '),
                'description' => $descriptionParts->implode(' | ') ?: null,
                'owner_directorate_id' => $sourceDecision->owner_directorate_id,
                'pic_user_id' => $sourceDecision->pic_user_id,
                'source_decision_id' => $sourceDecision->id,
            ]);
        }
    }

    private function inheritedDecisionBacklogQuery(Meeting $meeting, \Illuminate\Support\Collection $targetDirectorateIds)
    {
        return MeetingDecision::query()
            ->whereIn('id', MeetingDecision::query()
                ->selectRaw('MAX(corsec_meeting_decisions.id)')
                ->groupBy(DB::raw('COALESCE(root_decision_id, id)')))
            ->where('meeting_id', '!=', $meeting->id)
            ->whereIn('owner_directorate_id', $targetDirectorateIds->all())
            ->whereIn('status', $this->openDecisionStatuses())
            ->whereHas('meeting', function ($query) {
                $query->whereIn('meeting_type', [
                    Meeting::TYPE_KOMISARIS,
                    Meeting::TYPE_DIREKSI,
                    Meeting::TYPE_MANCOMM,
                ]);
            });
    }

    private function crossMeetingOpenDecisions(Meeting $meeting)
    {
        $query = MeetingDecision::query()
            ->with(['meeting:id,uuid,title,meeting_type,meeting_at', 'ownerDirectorate', 'picUser'])
            ->whereIn('id', MeetingDecision::query()
                ->selectRaw('MAX(corsec_meeting_decisions.id)')
                ->groupBy(DB::raw('COALESCE(root_decision_id, id)')))
            ->where('meeting_id', '!=', $meeting->id)
            ->whereIn('status', $this->openDecisionStatuses());

        if ($meeting->isDirektoratType()) {
            $targetDirectorateIds = $this->meetingBacklogDirectorateIds($meeting);
            if ($targetDirectorateIds->isEmpty()) {
                return collect();
            }

            $query->whereIn('owner_directorate_id', $targetDirectorateIds->all())
                ->whereHas('meeting', function ($meetingQuery) {
                    $meetingQuery->whereIn('meeting_type', [
                        Meeting::TYPE_KOMISARIS,
                        Meeting::TYPE_DIREKSI,
                        Meeting::TYPE_MANCOMM,
                    ]);
                });
        }

        return $query
            ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    private function mancommEscalationDecisionBacklogQuery()
    {
        return MeetingDecision::query()
            ->whereIn('id', MeetingDecision::query()
                ->selectRaw('MAX(corsec_meeting_decisions.id)')
                ->groupBy(DB::raw('COALESCE(root_decision_id, id)')))
            ->whereNotNull('owner_directorate_id')
            ->whereIn('status', $this->openDecisionStatuses())
            ->whereHas('meeting', function ($query) {
                $query->where('meeting_type', Meeting::TYPE_DIREKTORAT);
            });
    }

    private function meetingBacklogDirectorateIds(Meeting $meeting): \Illuminate\Support\Collection
    {
        $participantDirectorateIds = MeetingParticipant::query()
            ->where('meeting_id', $meeting->id)
            ->pluck('directorate_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($participantDirectorateIds->isEmpty()) {
            return collect();
        }

        $operationalDirectorateIds = $this->filterOperationalMeetingDirectorateIds($participantDirectorateIds->all());
        if ($operationalDirectorateIds->isNotEmpty()) {
            return $operationalDirectorateIds;
        }

        $mandatoryDirectorateIds = collect($this->resolveMandatoryDirectorateIds())
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique();

        return $participantDirectorateIds
            ->reject(fn($id) => $mandatoryDirectorateIds->contains((int) $id))
            ->values();
    }

    private function isMancommMeetingTypeCode(?string $meetingType): bool
    {
        return Str::lower(trim((string) $meetingType)) === Meeting::TYPE_MANCOMM;
    }

    private function applyTabulationFilters($query, Request $request): void
    {
        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('issue_key', 'ilike', '%' . $keyword . '%')
                    ->orWhere('decision_key', 'ilike', '%' . $keyword . '%')
                    ->orWhere('decision_text', 'ilike', '%' . $keyword . '%')
                    ->orWhere('latest_update_note', 'ilike', '%' . $keyword . '%');
            });
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '' && in_array($status, $this->allDecisionStatuses(), true)) {
            $query->where('status', $status);
        } elseif ($request->boolean('only_open')) {
            $query->whereIn('status', $this->openDecisionStatuses());
        }

        $meetingType = trim((string) $request->input('meeting_type', ''));
        if ($meetingType !== '') {
            $query->whereHas('meeting', function ($builder) use ($meetingType) {
                $builder->where('meeting_type', $meetingType);
            });
        }

        $directorateId = (int) $request->input('directorate_id', 0);
        if ($directorateId > 0) {
            $query->where(function ($builder) use ($directorateId) {
                $builder->where('owner_directorate_id', $directorateId);
            });
        }

        $agingBucket = trim((string) $request->input('aging_bucket', ''));
        if (in_array($agingBucket, ['cat_1', 'cat_2', 'cat_3', 'cat_4', 'cat_5'], true)) {
            $query->where('aging_bucket', $agingBucket);
        }

        $periodStart = $request->input('period_start');
        if ($periodStart) {
            $query->whereHas('meeting', function ($builder) use ($periodStart) {
                $builder->whereDate('meeting_at', '>=', $periodStart);
            });
        }

        $periodEnd = $request->input('period_end');
        if ($periodEnd) {
            $query->whereHas('meeting', function ($builder) use ($periodEnd) {
                $builder->whereDate('meeting_at', '<=', $periodEnd);
            });
        }
    }

    private function allDecisionStatuses(): array
    {
        return [
            MeetingDecision::STATUS_PENDING,
            MeetingDecision::STATUS_IN_PROGRESS,
            MeetingDecision::STATUS_CONTINUOUS,
            MeetingDecision::STATUS_DONE,
            MeetingDecision::STATUS_DROPPED,
        ];
    }

    private function openDecisionStatuses(): array
    {
        return [
            MeetingDecision::STATUS_PENDING,
            MeetingDecision::STATUS_IN_PROGRESS,
        ];
    }

    private function resolvedDecisionStatuses(): array
    {
        return [
            MeetingDecision::STATUS_CONTINUOUS,
            MeetingDecision::STATUS_DONE,
            MeetingDecision::STATUS_DROPPED,
        ];
    }

    private function normalizeDirectorateIds(array $directorateIds): array
    {
        return collect($directorateIds)
            ->filter(fn($id) => $id !== null && $id !== '')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeDecisionStatus(string $status): string
    {
        $status = trim($status);

        return in_array($status, $this->allDecisionStatuses(), true)
            ? $status
            : MeetingDecision::STATUS_PENDING;
    }

    private function deleteMorphAttachments(string $morphType, int $morphId): void
    {
        $attachables = Attachable::query()
            ->with('attachment')
            ->where('attachable_type', $morphType)
            ->where('attachable_id', $morphId)
            ->get();

        foreach ($attachables as $attachable) {
            $attachment = $attachable->attachment;
            if ($attachment) {
                try {
                    if ($attachment->path) {
                        Storage::disk($attachment->disk ?? 'public')->delete($attachment->path);
                    }
                } catch (Exception $e) {
                    Log::warning('Failed deleting attachment file', [
                        'attachment_id' => $attachment->id,
                        'path' => $attachment->path,
                        'error' => $e->getMessage(),
                    ]);
                }

                $attachment->delete();
            }

            $attachable->delete();
        }
    }

    private function resolveDecisionFamilyRootId(?MeetingDecision $decision): ?int
    {
        if (!$decision) {
            return null;
        }

        $rootDecisionId = (int) ($decision->root_decision_id ?? 0);
        if ($rootDecisionId > 0) {
            return $rootDecisionId;
        }

        $decisionId = (int) ($decision->id ?? 0);

        return $decisionId > 0 ? $decisionId : null;
    }

    private function recordDecisionOccurrence(MeetingDecision $decision, ?MeetingDecision $sourceDecision, User $user): void
    {
        $decision->loadMissing('meeting');
        $latestUpdate = $decision->relationLoaded('updates')
            ? $decision->updates->sortByDesc('id')->first()
            : $decision->updates()->latest('id')->first();

        $progressSnapshot = (int) (
            $latestUpdate?->progress_percent
            ?? $decision->latest_progress_percent
            ?? ((string) $decision->status === MeetingDecision::STATUS_DONE ? 100 : 0)
        );
        $noteSnapshot = trim((string) (
            $latestUpdate?->note
            ?? $decision->latest_update_note
            ?? $decision->decision_text
        ));

        MeetingDecisionOccurrence::query()->updateOrCreate(
            ['meeting_decision_id' => $decision->id],
            [
                'root_decision_id' => $this->resolveDecisionFamilyRootId($decision) ?? (int) $decision->id,
                'meeting_id' => (int) $decision->meeting_id,
                'source_decision_id' => $sourceDecision?->id ?: ($decision->source_decision_id ?: null),
                'occurred_at' => $decision->meeting?->meeting_at?->toDateString() ?? now()->toDateString(),
                'status_snapshot' => $decision->status,
                'progress_snapshot' => $progressSnapshot,
                'note_snapshot' => $noteSnapshot !== '' ? $noteSnapshot : null,
                'created_by' => $user->id,
            ]
        );
    }

    private function syncDecisionFamilySummaryByRootDecisionId(int $rootDecisionId): void
    {
        $familyDecisions = MeetingDecision::query()
            ->with(['meeting:id,meeting_at'])
            ->where(function ($query) use ($rootDecisionId) {
                $query->where('id', $rootDecisionId)
                    ->orWhere('root_decision_id', $rootDecisionId);
            })
            ->get();

        if ($familyDecisions->isEmpty()) {
            return;
        }

        $issueKey = $familyDecisions->pluck('issue_key')->filter()->first() ?: $this->buildIssueKey($rootDecisionId);
        $meetingDates = $familyDecisions
            ->map(fn(MeetingDecision $decision) => $decision->meeting?->meeting_at?->copy()->startOfDay())
            ->filter();
        $firstDiscussedAt = $meetingDates->sortBy(fn($date) => $date?->timestamp ?? 0)->first();
        $lastDiscussedAt = $meetingDates->sortByDesc(fn($date) => $date?->timestamp ?? 0)->first();
        $latestDecision = $familyDecisions
            ->sortByDesc(function (MeetingDecision $decision) {
                $meetingTimestamp = $decision->meeting?->meeting_at?->timestamp ?? 0;

                return sprintf('%010d-%010d', $meetingTimestamp, (int) $decision->id);
            })
            ->first();
        $latestUpdate = DecisionUpdate::query()
            ->whereIn('meeting_decision_id', $familyDecisions->pluck('id')->all())
            ->orderByDesc('happened_at')
            ->orderByDesc('id')
            ->first();

        $latestStatus = (string) ($latestDecision?->status ?? MeetingDecision::STATUS_PENDING);
        $latestUpdateAt = $latestUpdate?->happened_at ?? $lastDiscussedAt;
        $latestProgress = $latestUpdate?->progress_percent;
        if ($latestProgress === null) {
            $latestProgress = $latestDecision?->latest_progress_percent;
        }
        if ($latestProgress === null && $latestStatus === MeetingDecision::STATUS_DONE) {
            $latestProgress = 100;
        }
        if ($latestProgress === null) {
            $latestProgress = 0;
        }

        $agingDays = null;
        if ($firstDiscussedAt && in_array($latestStatus, $this->openDecisionStatuses(), true)) {
            $agingDays = $firstDiscussedAt->diffInDays(now()->startOfDay());
        }

        $payload = [
            'status' => $latestStatus,
            'closed_at' => in_array($latestStatus, $this->resolvedDecisionStatuses(), true) ? ($latestDecision?->closed_at ?? now()) : null,
            'issue_key' => $issueKey,
            'first_discussed_at' => $firstDiscussedAt,
            'last_discussed_at' => $lastDiscussedAt,
            'discussion_count' => $familyDecisions->pluck('meeting_id')->filter()->unique()->count(),
            'latest_update_at' => $latestUpdateAt,
            'latest_update_note' => trim((string) ($latestUpdate?->note ?? $latestDecision?->decision_text ?? '')) ?: null,
            'latest_progress_percent' => (int) $latestProgress,
            'aging_days' => $agingDays,
            'aging_bucket' => $this->resolveDecisionAgingBucket($agingDays),
        ];

        MeetingDecision::query()
            ->where(function ($query) use ($rootDecisionId) {
                $query->where('id', $rootDecisionId)
                    ->orWhere('root_decision_id', $rootDecisionId);
            })
            ->update($payload);
    }

    private function syncMeetingDecisionFamilySummaries(Meeting $meeting): void
    {
        $rootDecisionIds = MeetingDecision::query()
            ->where('meeting_id', $meeting->id)
            ->get(['id', 'root_decision_id'])
            ->map(function (MeetingDecision $decision) {
                return (int) ($decision->root_decision_id ?: $decision->id);
            })
            ->filter()
            ->unique()
            ->values();

        foreach ($rootDecisionIds as $rootDecisionId) {
            $this->syncDecisionFamilySummaryByRootDecisionId((int) $rootDecisionId);
        }
    }

    private function resolveDecisionAgingBucket(?int $agingDays): ?string
    {
        if ($agingDays === null) {
            return null;
        }

        return match (true) {
            $agingDays < 30 => 'cat_1',
            $agingDays < 91 => 'cat_2',
            $agingDays < 181 => 'cat_3',
            $agingDays < 271 => 'cat_4',
            default => 'cat_5',
        };
    }

    private function buildDecisionKey(int $decisionId): string
    {
        return 'TLR-' . str_pad((string) $decisionId, 6, '0', STR_PAD_LEFT);
    }

    private function buildIssueKey(int $rootDecisionId): string
    {
        return 'ISS-' . str_pad((string) $rootDecisionId, 6, '0', STR_PAD_LEFT);
    }

    private function buildMeetingShowViewState(
        Meeting $meeting,
        array $permissionFlags
    ): array {
        $status = (string) ($meeting->status ?? '');
        $directorateResponseStatus = (string) ($meeting->directorate_response_status ?? '');
        $minutesPointPhotoCategory = 'minutes_point_photo';
        $updatableDecisionIds = collect($permissionFlags['updatable_decision_ids'] ?? []);
        $decisionsByAgendaId = $meeting->decisions
            ->filter(fn(MeetingDecision $decision) => (int) ($decision->agenda_id ?? 0) > 0)
            ->keyBy(fn(MeetingDecision $decision) => (int) $decision->agenda_id);
        $preparationCopy = $this->meetingPreparationCopy($meeting);
        $directorateResponseSummary = $this->meetingDirectorateResponseSummary($directorateResponseStatus);

        return [
            'status' => $status,
            'canCorsecUpdateAction' => (bool) ($permissionFlags['can_corsec_update_action'] ?? false),
            'canDirectorNote' => (bool) ($permissionFlags['can_director_note'] ?? false),
            'canEdit' => (bool) ($permissionFlags['can_edit'] ?? false),
            'canSubmitPlan' => (bool) ($permissionFlags['can_submit_plan'] ?? false),
            'canCorsecApproval' => (bool) ($permissionFlags['can_corsec_approval'] ?? false),
            'canMarkPendingDirectorate' => (bool) ($permissionFlags['can_mark_pending_direktorat'] ?? false),
            'canDirectorateResponse' => (bool) ($permissionFlags['can_directorate_response'] ?? false),
            'canDirectorateSubmit' => (bool) ($permissionFlags['can_directorate_submit'] ?? false),
            'canDirectorateCheckerApproval' => (bool) ($permissionFlags['can_directorate_checker_approval'] ?? false),
            'canDirectorateApproverApproval' => (bool) ($permissionFlags['can_directorate_approver_approval'] ?? false),
            'canSaveMinutes' => (bool) ($permissionFlags['can_save_minutes'] ?? false),
            'canFinalizeMinutes' => (bool) ($permissionFlags['can_finalize_minutes'] ?? false),
            'canInputFollowup' => (bool) ($permissionFlags['can_input_followup'] ?? false),
            'canCompleteFollowup' => (bool) ($permissionFlags['can_complete_followup'] ?? false),
            'canOpenPresentationMode' => $this->canOpenPresentationMode($meeting),
            'decisionCanUpdateById' => $meeting->decisions
                ->mapWithKeys(function (MeetingDecision $decision) use ($updatableDecisionIds) {
                    return [
                        (int) $decision->id => $updatableDecisionIds->contains((int) $decision->id)
                            && !in_array((string) $decision->status, $this->resolvedDecisionStatuses(), true),
                    ];
                })
                ->all(),
            'directorateResponseStatus' => $directorateResponseStatus,
            'isOnScheduleResponse' => $directorateResponseStatus === Meeting::RESPONSE_ON_SCHEDULE,
            'isRescheduleResponse' => $directorateResponseStatus === Meeting::RESPONSE_RESCHEDULE,
            'isCancelResponse' => $directorateResponseStatus === Meeting::RESPONSE_CANCEL,
            'isNoResponse' => $directorateResponseStatus === Meeting::RESPONSE_NO_RESPONSE,
            'isAwaitingDirectorateResponse' => $meeting->isAwaitingDirectorateResponse(),
            'isReminderWindow' => $meeting->isDirectorateResponseReminderWindow(),
            'isClosedNotConducted' => $meeting->isDirectorateScheduleNotConducted(),
            'directorateResponseSummaryClass' => $directorateResponseSummary['class'],
            'directorateResponseSummaryMessage' => $directorateResponseSummary['message'],
            'statusBadgeClass' => $this->meetingStatusBadgeClasses()[$status] ?? 'badge-light',
            'decisionStatusBadgeClasses' => $this->decisionStatusBadgeClasses(),
            'decisionStatusLabels' => $this->decisionStatusLabels(),
            'statusSteps' => $this->meetingStatusSteps(),
            'agingLabels' => $this->decisionAgingLabels(),
            'additionalAgendas' => $this->oldInputArray('additional_agendas'),
            'preparationCardTitle' => $preparationCopy['card_title'],
            'preparationHelperText' => $preparationCopy['helper_text'],
            'preparationNoteLabel' => $preparationCopy['note_label'],
            'preparationSubmitLabel' => $preparationCopy['submit_label'],
            'participantDisplayRows' => $this->buildParticipantDisplayRows($meeting),
            'minutes' => $meeting->minutes,
            'minutesPointPhotoCategory' => $minutesPointPhotoCategory,
            'decisionsByAgendaId' => $decisionsByAgendaId,
            'minutesAgendaDisplayRows' => $this->buildMinutesAgendaDisplayRows(
                $meeting,
                $decisionsByAgendaId,
                $minutesPointPhotoCategory
            ),
            'minutesAgendaRows' => $this->resolveMinutesAgendaRows($meeting, $decisionsByAgendaId),
            'minutesDecisionRows' => $this->resolveMinutesDecisionRows($meeting),
        ];
    }

    private function oldInputArray(string $key): array
    {
        $value = old($key);

        return is_array($value) ? $value : [];
    }

    private function meetingPreparationCopy(Meeting $meeting): array
    {
        if ($meeting->isDirektoratType()) {
            return [
                'card_title' => 'Persiapan Rapat dan Distribusi Bahan',
                'helper_text' => 'PIC direktorat melengkapi bahan, peserta tambahan, dan agenda tambahan. Tahap berikutnya hanya bisa lanjut jika seluruh agenda sudah memiliki PIC user dan bahan rapat. Flow approval mengikuti posisi PIC user: Deputy Director langsung terkirim, Executive Officer cukup approval DD, selain itu approval EO + DD Direktorat.',
                'note_label' => 'Catatan Direktorat (Opsional)',
                'submit_label' => 'Submit Persiapan Direktorat',
            ];
        }

        return [
            'card_title' => 'Koordinasi Unit Rapat dan Upload Bahan',
            'helper_text' => 'User dari direktorat terkait mengunggah bahan rapat pada tahap ini. Tahap berikutnya hanya bisa lanjut jika seluruh agenda sudah memiliki PIC user dan bahan rapat. Flow approval mengikuti posisi PIC user: Deputy Director langsung terkirim, Executive Officer cukup approval DD Direktorat, selain itu approval EO + DD Direktorat sebelum pemaparan dan notulen oleh Corporate Secretary.',
            'note_label' => 'Catatan Koordinasi Unit (Opsional)',
            'submit_label' => 'Submit Koordinasi Unit',
        ];
    }

    private function meetingDirectorateResponseSummary(string $directorateResponseStatus): array
    {
        return match ($directorateResponseStatus) {
            Meeting::RESPONSE_ON_SCHEDULE => [
                'class' => 'text-success',
                'message' => 'Meeting sudah ditandai on schedule oleh PIC direktorat.',
            ],
            Meeting::RESPONSE_RESCHEDULE => [
                'class' => 'text-warning',
                'message' => 'PIC direktorat meminta reschedule jadwal rapat ini.',
            ],
            Meeting::RESPONSE_CANCEL => [
                'class' => 'text-danger',
                'message' => 'Meeting dibatalkan oleh PIC direktorat.',
            ],
            Meeting::RESPONSE_NO_RESPONSE => [
                'class' => 'text-warning',
                'message' => 'Meeting ditutup otomatis karena tidak ada tanggapan dari direktorat sampai hari H.',
            ],
            default => [
                'class' => 'text-gray-600',
                'message' => '',
            ],
        };
    }

    private function meetingStatusBadgeClasses(): array
    {
        return [
            Meeting::STATUS_DRAFT => 'badge-light',
            Meeting::STATUS_WAITING_CORSEC_APPROVAL => 'badge-warning',
            Meeting::STATUS_WAITING_DIREKTORAT_APPROVAL => 'badge-warning',
            Meeting::STATUS_RETURNED_BY_CORSEC => 'badge-danger',
            Meeting::STATUS_RETURNED_BY_DIREKTORAT => 'badge-danger',
            Meeting::STATUS_JADWAL_TERKIRIM => 'badge-info',
            Meeting::STATUS_PENDING_DIREKTORAT => 'badge-info',
            Meeting::STATUS_DATA_TERKIRIM => 'badge-info',
            Meeting::STATUS_PROSES_PEMBUATAN_NOTULEN => 'badge-primary',
            Meeting::STATUS_PROSES_SIRKULASI_TANDATANGAN => 'badge-primary',
            Meeting::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT => 'badge-primary',
            Meeting::STATUS_NOTULEN_FINAL => 'badge-success',
            Meeting::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT => 'badge-success',
            Meeting::STATUS_CANCELLED_DIREKTORAT => 'badge-danger',
            Meeting::STATUS_CLOSED_NOT_CONDUCTED => 'badge-warning',
        ];
    }

    private function decisionStatusBadgeClasses(): array
    {
        return [
            MeetingDecision::STATUS_PENDING => 'badge-warning',
            MeetingDecision::STATUS_IN_PROGRESS => 'badge-info',
            MeetingDecision::STATUS_CONTINUOUS => 'badge-primary',
            MeetingDecision::STATUS_DONE => 'badge-success',
            MeetingDecision::STATUS_DROPPED => 'badge-danger',
        ];
    }

    private function decisionStatusLabels(): array
    {
        return [
            MeetingDecision::STATUS_PENDING => 'Pending',
            MeetingDecision::STATUS_IN_PROGRESS => 'Proses',
            MeetingDecision::STATUS_CONTINUOUS => 'Berkelanjutan',
            MeetingDecision::STATUS_DONE => 'Done',
            MeetingDecision::STATUS_DROPPED => 'Drop',
        ];
    }

    private function meetingStatusSteps(): array
    {
        return [
            Meeting::STATUS_DRAFT => 'Draft Jadwal Rapat',
            Meeting::STATUS_WAITING_CORSEC_APPROVAL => 'Corporate Secretary',
            Meeting::STATUS_JADWAL_TERKIRIM => 'Jadwal Terkirim',
            Meeting::STATUS_PENDING_DIREKTORAT => 'Koordinasi Unit Rapat',
            Meeting::STATUS_WAITING_DIREKTORAT_APPROVAL => 'Approval Direktorat',
            Meeting::STATUS_DATA_TERKIRIM => 'Data/Bahan Terkirim',
            Meeting::STATUS_PROSES_PEMBUATAN_NOTULEN => 'Input Notulen + Tindaklanjut',
            Meeting::STATUS_PROSES_SIRKULASI_TANDATANGAN => 'Sirkulasi Tandatangan',
            Meeting::STATUS_NOTULEN_FINAL => 'Notulen Final',
            Meeting::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT => 'Progress Tindaklanjut',
            Meeting::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT => 'Done',
            Meeting::STATUS_RETURNED_BY_CORSEC => 'Revisi Corsec',
            Meeting::STATUS_RETURNED_BY_DIREKTORAT => 'Revisi Direktorat',
            Meeting::STATUS_CANCELLED_DIREKTORAT => 'Batal Direktorat',
            Meeting::STATUS_CLOSED_NOT_CONDUCTED => 'Closed - Tidak Dilaksanakan',
        ];
    }

    private function decisionAgingLabels(): array
    {
        return [
            'cat_1' => 'CAT 1 (< 30 hari)',
            'cat_2' => 'CAT 2 (30 - 90 hari)',
            'cat_3' => 'CAT 3 (91 - 180 hari)',
            'cat_4' => 'CAT 4 (181 - 270 hari)',
            'cat_5' => 'CAT 5 (> 270 hari)',
        ];
    }

    private function buildParticipantDisplayRows(Meeting $meeting): Collection
    {
        return $meeting->participants
            ->groupBy(function ($participant) {
                $label = preg_replace('/\s+/', ' ', trim((string) ($participant->directorate?->displayName() ?? '-')));

                return strtolower((string) $label);
            })
            ->map(function (Collection $group) {
                $firstParticipant = $group->first();
                $directorateLabel = preg_replace(
                    '/\s+/',
                    ' ',
                    trim((string) ($firstParticipant->directorate?->displayName() ?? '-'))
                );
                $picNames = $group
                    ->map(fn($participant) => trim((string) ($participant->participantUser?->name ?? '')))
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'directorate' => $directorateLabel !== '' ? $directorateLabel : '-',
                    'pic' => $picNames->isNotEmpty() ? $picNames->implode(', ') : '-',
                ];
            })
            ->values();
    }

    private function buildMinutesAgendaDisplayRows(
        Meeting $meeting,
        Collection $decisionsByAgendaId,
        string $minutesPointPhotoCategory
    ): Collection {
        return $meeting->agendas
            ->filter(function ($agenda) use ($decisionsByAgendaId, $minutesPointPhotoCategory) {
                return trim((string) ($agenda->minutes_discussion ?? '')) !== ''
                    || ($agenda->attachables ?? collect())->where('category', $minutesPointPhotoCategory)->isNotEmpty()
                    || $decisionsByAgendaId->has((int) $agenda->id);
            })
            ->values();
    }

    private function resolveMinutesAgendaRows(Meeting $meeting, Collection $decisionsByAgendaId): array
    {
        $minutesAgendaRows = old('minutes_agendas');
        if (is_array($minutesAgendaRows)) {
            return $minutesAgendaRows;
        }

        $rows = $meeting->agendas
            ->map(function ($agenda) use ($decisionsByAgendaId) {
                /** @var MeetingDecision|null $decision */
                $decision = $decisionsByAgendaId->get((int) $agenda->id);

                return [
                    'agenda_id' => $agenda->id,
                    'source_decision_id' => $agenda->source_decision_id,
                    'title' => $agenda->title,
                    'description' => $agenda->description,
                    'minutes_discussion' => $agenda->minutes_discussion,
                    'owner_directorate_id' => $agenda->owner_directorate_id,
                    'pic_user_id' => $agenda->pic_user_id,
                    'decision_id' => $decision?->id ?? '',
                    'existing_decision_id' => $decision?->source_decision_id ?: $agenda->source_decision_id ?? '',
                    'followup_enabled' => $decision ? '1' : '',
                    'decision_text' => $decision?->decision_text ?? '',
                    'target_date' => optional($decision?->target_date)->format('Y-m-d'),
                    'status' => $decision?->status ?? MeetingDecision::STATUS_IN_PROGRESS,
                    'can_remove' => !$agenda->source_decision_id && !$decision,
                ];
            })
            ->values()
            ->all();

        if (count($rows) > 0) {
            return $rows;
        }

        return [[
            'agenda_id' => '',
            'source_decision_id' => '',
            'title' => '',
            'description' => '',
            'minutes_discussion' => '',
            'owner_directorate_id' => '',
            'pic_user_id' => '',
            'decision_id' => '',
            'existing_decision_id' => '',
            'followup_enabled' => '',
            'decision_text' => '',
            'target_date' => '',
            'status' => $meeting->isDirektoratType()
                ? MeetingDecision::STATUS_IN_PROGRESS
                : MeetingDecision::STATUS_PENDING,
            'can_remove' => true,
        ]];
    }

    private function resolveMinutesDecisionRows(Meeting $meeting): array
    {
        $minutesDecisionRows = old('decisions');
        if (is_array($minutesDecisionRows)) {
            return $minutesDecisionRows;
        }

        $rows = $meeting->decisions
            ->whereNull('agenda_id')
            ->map(function (MeetingDecision $decision) {
                return [
                    'id' => $decision->id,
                    'existing_decision_id' => '',
                    'decision_text' => $decision->decision_text,
                    'owner_directorate_id' => $decision->owner_directorate_id,
                    'pic_user_id' => $decision->pic_user_id,
                    'status' => $decision->status,
                    'target_date' => optional($decision->target_date)->format('Y-m-d'),
                ];
            })
            ->values()
            ->all();

        if ($meeting->isDirektoratType() || count($rows) > 0) {
            return $rows;
        }

        return [[
            'id' => '',
            'existing_decision_id' => '',
            'decision_text' => '',
            'owner_directorate_id' => '',
            'pic_user_id' => '',
            'status' => MeetingDecision::STATUS_PENDING,
            'target_date' => '',
        ]];
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
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }
    }

    private function authorizeCreate(): void
    {
        $user = Auth::user();
        if (!$this->permissionService->canCreateMeeting($user)) {
            abort(403, 'Tambah meeting hanya untuk maker staff Corporate Secretary.');
        }
    }

    private function authorizeUpdate(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.update')) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah meeting.');
        }
        if ($this->permissionService->isViewerRole($user)) {
            abort(403, 'Role viewer tidak memiliki akses untuk update meeting.');
        }
    }

    private function authorizeDelete(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.delete')) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus meeting.');
        }
    }

    private function authorizeAuthorize(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.authorize')) {
            abort(403, 'Anda tidak memiliki akses untuk memproses persetujuan meeting.');
        }
    }

    private function successRedirectResponse(
        Request $request,
        string $redirectUrl,
        string $message,
        string $messageType = 'success'
    ) {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => $messageType !== 'error',
                'message' => $message,
                'message_type' => $messageType,
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect()->to($redirectUrl)->with($messageType, $message);
    }

    private function meetingFormOptions(?Meeting $meeting = null): array
    {
        $directorates = $this->meetingDirectoratesCollection();

        $users = User::query()
            ->with(['directorate:id,name,tabulation_label', 'position:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'directorate_id', 'position_id']);

        $meetingPicUsers = $this->corpSecretaryStaffUsersQuery()
            ->with(['directorate:id,name,tabulation_label', 'position:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'directorate_id', 'position_id']);

        $selectedEscalationDecisionIds = $meeting
            ? MeetingAgenda::query()
            ->where('meeting_id', $meeting->id)
            ->whereNotNull('source_decision_id')
            ->pluck('source_decision_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all()
            : [];

        $escalationDecisionOptions = $this->mancommEscalationDecisionBacklogQuery()
            ->when(!empty($selectedEscalationDecisionIds), function ($query) use ($selectedEscalationDecisionIds) {
                $query->orWhereIn('id', $selectedEscalationDecisionIds);
            })
            ->with(['meeting:id,uuid,title,meeting_type,meeting_at', 'ownerDirectorate', 'picUser'])
            ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (MeetingDecision $decision) {
                return [
                    'id' => (int) $decision->id,
                    'issue_key' => (string) ($decision->issue_key ?? ''),
                    'decision_key' => (string) ($decision->decision_key ?? ''),
                    'decision_text' => trim((string) ($decision->decision_text ?? '')),
                    'owner_directorate_id' => (int) ($decision->owner_directorate_id ?? 0),
                    'owner_directorate_name' => (string) ($decision->ownerDirectorate?->displayName() ?? '-'),
                    'pic_user_name' => (string) ($decision->picUser?->name ?? '-'),
                    'source_meeting_title' => (string) ($decision->meeting?->title ?? '-'),
                    'source_meeting_at' => $decision->meeting?->meeting_at?->format('d/m/Y H:i'),
                    'target_date' => $decision->target_date?->format('d/m/Y'),
                    'status' => (string) ($decision->status ?? ''),
                ];
            })
            ->values();

        return [
            'directorates' => $directorates,
            'users' => $users,
            'meetingPicUsers' => $meetingPicUsers,
            'escalationDecisionOptions' => $escalationDecisionOptions,
        ];
    }

    private function meetingDirectoratesCollection(): Collection
    {
        return Directorate::query()
            ->orderByRaw('COALESCE(NULLIF(tabulation_label, \'\'), name)')
            ->get(['id', 'name', 'tabulation_label', 'code', 'is_meeting_operational']);
    }

    private function meetingIndexSummaryCacheKey(User $user): string
    {
        $user->loadMissing('roles:id,name');
        $roleSignature = md5($user->roles->pluck('name')->sort()->implode('|'));

        return sprintf(
            'corsec.meeting.index.summary.%d.%d.%s',
            (int) $user->id,
            (int) ($user->directorate_id ?? 0),
            $roleSignature
        );
    }
}
