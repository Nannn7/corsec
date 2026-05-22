<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Corsec\Models\Directorate;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\MeetingDecision;
use Modules\Corsec\Models\OutgoingLetter;
use Modules\Corsec\Models\WorkProgram;
use Modules\Corsec\Models\WorkProgramItem;
use Modules\Corsec\Services\CorsecPermissionService;
use Modules\Corsec\Services\WorkplanWorkflowService;
use Modules\Usermanagement\Models\User;

class ReportController extends Controller
{
    public function __construct(
        private readonly CorsecPermissionService $permissionService,
        private readonly WorkplanWorkflowService $workplanWorkflow
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizeRead();

        /** @var User $user */
        $user = Auth::user();
        $activeModule = $this->normalizeModule((string) $request->input('module', 'incoming'));
        $filters = $request->only([
            'module',
            'keyword',
            'directorate_id',
            'status',
            'aging_bucket',
            'period_start',
            'period_end',
            'only_open',
            'meeting_type',
        ]);

        $report = match ($activeModule) {
            'outgoing' => $this->buildOutgoingReport($request, $user),
            'meeting' => $this->buildMeetingReport($request, $user),
            'workplan' => $this->buildWorkplanReport($request, $user),
            default => $this->buildIncomingReport($request, $user),
        };

        return view('corsec::report.index', [
            'activeModule' => $activeModule,
            'moduleTabs' => $this->moduleTabs(),
            'directorates' => $this->reportingDirectorates(),
            'agingLabels' => $this->agingLabels(),
            'filters' => $filters,
            'report' => $report,
        ]);
    }

    private function authorizeRead(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('corsec.read')) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }
    }

    private function moduleTabs(): array
    {
        return [
            'incoming' => 'Incoming Letter',
            'outgoing' => 'Outgoing Letter',
            'meeting' => 'Meeting',
            'workplan' => 'Work Plan',
        ];
    }

    private function normalizeModule(string $module): string
    {
        return array_key_exists($module, $this->moduleTabs()) ? $module : 'incoming';
    }

    private function reportingDirectorates()
    {
        return Cache::remember('corsec.reporting.directorates', 300, function () {
            return Directorate::query()
                ->orderByRaw('COALESCE(NULLIF(tabulation_label, \'\'), name)')
                ->get(['id', 'name', 'tabulation_label']);
        });
    }

    private function agingLabels(): array
    {
        return [
            'cat_1' => 'CAT 1 (< 30 hari)',
            'cat_2' => 'CAT 2 (30 - 90 hari)',
            'cat_3' => 'CAT 3 (91 - 180 hari)',
            'cat_4' => 'CAT 4 (181 - 270 hari)',
            'cat_5' => 'CAT 5 (> 270 hari)',
        ];
    }

    private function metricCard(string $label, int $value, string $tone = 'text-gray-800'): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'tone' => $tone,
        ];
    }

    private function sqlStringList(array $values): string
    {
        return collect($values)
            ->map(fn($value) => "'" . str_replace("'", "''", (string) $value) . "'")
            ->implode(', ');
    }

    private function groupedCounts(Builder $query, string $column = 'status', ?string $alias = null): array
    {
        $keyAlias = $alias ?: (string) collect(explode('.', $column))->last();

        return $this->aggregateQuery($query)
            ->selectRaw("{$column} AS {$keyAlias}, COUNT(*) AS aggregate")
            ->groupBy(DB::raw($column))
            ->pluck('aggregate', $keyAlias)
            ->map(fn($count) => (int) $count)
            ->all();
    }

    private function agingBucketCaseSql(string $ageExpression): string
    {
        return "CASE
            WHEN ({$ageExpression}) IS NULL THEN NULL
            WHEN ({$ageExpression}) < 30 THEN 'cat_1'
            WHEN ({$ageExpression}) < 91 THEN 'cat_2'
            WHEN ({$ageExpression}) < 181 THEN 'cat_3'
            WHEN ({$ageExpression}) < 271 THEN 'cat_4'
            ELSE 'cat_5'
        END";
    }

    private function buildSlaSummary(
        Builder $query,
        string $statusColumn,
        array $openStatuses,
        array $doneStatuses,
        string $dueExpression,
        string $completedExpression
    ): array {
        $openStatusList = $this->sqlStringList($openStatuses);
        $doneStatusList = $this->sqlStringList($doneStatuses);

        $row = $this->aggregateQuery($query)
            ->selectRaw("SUM(CASE WHEN {$dueExpression} IS NOT NULL AND {$statusColumn} IN ({$openStatusList}) AND {$dueExpression} >= CURRENT_DATE THEN 1 ELSE 0 END) AS on_track")
            ->selectRaw("SUM(CASE WHEN {$dueExpression} IS NOT NULL AND {$statusColumn} IN ({$openStatusList}) AND {$dueExpression} < CURRENT_DATE THEN 1 ELSE 0 END) AS overdue")
            ->selectRaw("SUM(CASE WHEN {$dueExpression} IS NOT NULL AND {$statusColumn} IN ({$doneStatusList}) AND {$completedExpression} <= {$dueExpression} THEN 1 ELSE 0 END) AS done_on_time")
            ->selectRaw("SUM(CASE WHEN {$dueExpression} IS NOT NULL AND {$statusColumn} IN ({$doneStatusList}) AND {$completedExpression} > {$dueExpression} THEN 1 ELSE 0 END) AS done_overdue")
            ->selectRaw("SUM(CASE WHEN {$dueExpression} IS NULL THEN 1 ELSE 0 END) AS without_target")
            ->first();

        return [
            'on_track' => (int) ($row->on_track ?? 0),
            'overdue' => (int) ($row->overdue ?? 0),
            'done_on_time' => (int) ($row->done_on_time ?? 0),
            'done_overdue' => (int) ($row->done_overdue ?? 0),
            'without_target' => (int) ($row->without_target ?? 0),
        ];
    }

    private function buildAgingSummary(Builder $query, string $agingBucketExpression): array
    {
        $row = $this->aggregateQuery($query)
            ->selectRaw("SUM(CASE WHEN {$agingBucketExpression} = 'cat_1' THEN 1 ELSE 0 END) AS cat_1")
            ->selectRaw("SUM(CASE WHEN {$agingBucketExpression} = 'cat_2' THEN 1 ELSE 0 END) AS cat_2")
            ->selectRaw("SUM(CASE WHEN {$agingBucketExpression} = 'cat_3' THEN 1 ELSE 0 END) AS cat_3")
            ->selectRaw("SUM(CASE WHEN {$agingBucketExpression} = 'cat_4' THEN 1 ELSE 0 END) AS cat_4")
            ->selectRaw("SUM(CASE WHEN {$agingBucketExpression} = 'cat_5' THEN 1 ELSE 0 END) AS cat_5")
            ->first();

        return [
            'cat_1' => (int) ($row->cat_1 ?? 0),
            'cat_2' => (int) ($row->cat_2 ?? 0),
            'cat_3' => (int) ($row->cat_3 ?? 0),
            'cat_4' => (int) ($row->cat_4 ?? 0),
            'cat_5' => (int) ($row->cat_5 ?? 0),
        ];
    }

    private function buildStatusCards(array $counts, array $labels, array $tones = []): array
    {
        return collect($labels)
            ->map(function ($label, $status) use ($counts, $tones) {
                return $this->metricCard(
                    $label,
                    (int) ($counts[$status] ?? 0),
                    $tones[$status] ?? 'text-gray-800'
                );
            })
            ->values()
            ->all();
    }

    private function buildAgingCards(array $agingSummary): array
    {
        return collect($this->agingLabels())
            ->map(function ($label, $bucket) use ($agingSummary) {
                return $this->metricCard($label, (int) ($agingSummary[$bucket] ?? 0), 'text-sky-700');
            })
            ->values()
            ->all();
    }

    private function buildSlaCards(array $slaSummary): array
    {
        return [
            $this->metricCard('On Track', (int) ($slaSummary['on_track'] ?? 0), 'text-emerald-600'),
            $this->metricCard('Overdue', (int) ($slaSummary['overdue'] ?? 0), 'text-rose-600'),
            $this->metricCard('Done On Time', (int) ($slaSummary['done_on_time'] ?? 0), 'text-green-700'),
            $this->metricCard('Done Over SLA', (int) ($slaSummary['done_overdue'] ?? 0), 'text-orange-700'),
            $this->metricCard('Tanpa Target', (int) ($slaSummary['without_target'] ?? 0), 'text-gray-600'),
        ];
    }

    private function aggregateQuery(Builder $query): Builder
    {
        $aggregateQuery = clone $query;
        $aggregateQuery->reorder();
        $aggregateQuery->setEagerLoads([]);
        $aggregateQuery->getQuery()->columns = null;
        $aggregateQuery->getQuery()->bindings['select'] = [];
        $aggregateQuery->getQuery()->bindings['order'] = [];

        return $aggregateQuery;
    }

    private function buildIncomingReport(Request $request, User $user): array
    {
        $statusLabels = [
            IncomingLetter::STATUS_DRAFT => 'Draft',
            IncomingLetter::STATUS_ON_APPROVAL => 'On Approval',
            IncomingLetter::STATUS_DISPATCHED => 'Dispatched',
            IncomingLetter::STATUS_IN_PROGRESS => 'In Progress',
            IncomingLetter::STATUS_WAITING_DIR_APPROVAL => 'Waiting Dir Approval',
            IncomingLetter::STATUS_WAITING_RESPONSE_LETTER => 'Waiting Response Letter',
            IncomingLetter::STATUS_WAITING_VERIFICATION => 'Waiting Verification',
            IncomingLetter::STATUS_VERIFIED => 'Verified',
            IncomingLetter::STATUS_RETURNED => 'Returned',
            IncomingLetter::STATUS_REJECTED => 'Rejected',
        ];
        $statusTones = [
            IncomingLetter::STATUS_DRAFT => 'text-gray-800',
            IncomingLetter::STATUS_ON_APPROVAL => 'text-amber-600',
            IncomingLetter::STATUS_DISPATCHED => 'text-sky-700',
            IncomingLetter::STATUS_IN_PROGRESS => 'text-blue-700',
            IncomingLetter::STATUS_WAITING_DIR_APPROVAL => 'text-orange-700',
            IncomingLetter::STATUS_WAITING_RESPONSE_LETTER => 'text-cyan-700',
            IncomingLetter::STATUS_WAITING_VERIFICATION => 'text-indigo-700',
            IncomingLetter::STATUS_VERIFIED => 'text-emerald-600',
            IncomingLetter::STATUS_RETURNED => 'text-rose-600',
            IncomingLetter::STATUS_REJECTED => 'text-red-700',
        ];
        $openStatuses = [
            IncomingLetter::STATUS_ON_APPROVAL,
            IncomingLetter::STATUS_DISPATCHED,
            IncomingLetter::STATUS_IN_PROGRESS,
            IncomingLetter::STATUS_WAITING_DIR_APPROVAL,
            IncomingLetter::STATUS_WAITING_RESPONSE_LETTER,
            IncomingLetter::STATUS_WAITING_VERIFICATION,
            IncomingLetter::STATUS_RETURNED,
        ];
        $doneStatuses = [
            IncomingLetter::STATUS_VERIFIED,
            IncomingLetter::STATUS_REJECTED,
        ];
        $dueExpression = 'COALESCE(corsec_incoming_letters.target_date, corsec_incoming_letters.register_due_date)';
        $completedExpression = 'DATE(COALESCE(corsec_incoming_letters.corp_secretary_validated_at, corsec_incoming_letters.updated_at))';
        $openStatusList = $this->sqlStringList($openStatuses);
        $doneStatusList = $this->sqlStringList($doneStatuses);
        $ageExpression = "CASE
            WHEN {$dueExpression} IS NULL THEN NULL
            WHEN corsec_incoming_letters.status IN ({$openStatusList}) THEN GREATEST(CURRENT_DATE - {$dueExpression}, 0)
            WHEN corsec_incoming_letters.status IN ({$doneStatusList}) THEN GREATEST({$completedExpression} - {$dueExpression}, 0)
            ELSE GREATEST(CURRENT_DATE - {$dueExpression}, 0)
        END";
        $agingBucketExpression = $this->agingBucketCaseSql($ageExpression);

        $query = IncomingLetter::query()
            ->select('corsec_incoming_letters.*')
            ->selectRaw("{$dueExpression} AS report_due_date")
            ->selectRaw("{$ageExpression} AS report_aging_days")
            ->selectRaw("{$agingBucketExpression} AS report_aging_bucket")
            ->with([
                'targetDirectorate:id,name,tabulation_label',
                'letterType:id,name',
                'corpSecretaryValidatedBy:id,name',
            ]);

        $this->scopeIncomingVisibility($query, $user);
        $this->applyIncomingFilters($query, $request, $statusLabels, $agingBucketExpression, $openStatuses);

        $statusCounts = $this->groupedCounts($query, 'status');
        $slaSummary = $this->buildSlaSummary(
            $query,
            'corsec_incoming_letters.status',
            $openStatuses,
            $doneStatuses,
            $dueExpression,
            $completedExpression
        );
        $agingSummary = $this->buildAgingSummary($query, $agingBucketExpression);
        $validationSummary = $this->aggregateQuery($query)
            ->selectRaw('SUM(CASE WHEN corp_secretary_validation_requested_at IS NOT NULL AND corp_secretary_validated_at IS NULL THEN 1 ELSE 0 END) AS pending_validation')
            ->selectRaw("SUM(CASE WHEN corp_secretary_validation_requested_at IS NOT NULL AND corp_secretary_validated_at IS NULL AND corp_secretary_validation_requested_at + interval '1 day' < NOW() THEN 1 ELSE 0 END) AS overdue_validation")
            ->first();

        $rows = (clone $query)
            ->orderByRaw('CASE WHEN corp_secretary_validation_requested_at IS NOT NULL AND corp_secretary_validated_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw("COALESCE({$dueExpression}, DATE(corsec_incoming_letters.created_at)) ASC NULLS LAST")
            ->orderByDesc('corsec_incoming_letters.id')
            ->paginate(20)
            ->withQueryString();

        return [
            'title' => 'Reporting Incoming Letter',
            'description' => 'Rekap keseluruhan, SLA, dan tabulasi surat masuk.',
            'statusOptions' => $statusLabels,
            'statusBadgeClasses' => [
                IncomingLetter::STATUS_DRAFT => 'badge-light',
                IncomingLetter::STATUS_ON_APPROVAL => 'badge-warning',
                IncomingLetter::STATUS_DISPATCHED => 'badge-info',
                IncomingLetter::STATUS_IN_PROGRESS => 'badge-primary',
                IncomingLetter::STATUS_WAITING_DIR_APPROVAL => 'badge-warning',
                IncomingLetter::STATUS_WAITING_RESPONSE_LETTER => 'badge-info',
                IncomingLetter::STATUS_WAITING_VERIFICATION => 'badge-primary',
                IncomingLetter::STATUS_VERIFIED => 'badge-success',
                IncomingLetter::STATUS_RETURNED => 'badge-danger',
                IncomingLetter::STATUS_REJECTED => 'badge-danger',
            ],
            'summaryCards' => [
                $this->metricCard('Total Surat', (clone $query)->count()),
                $this->metricCard('On Going', array_sum(array_intersect_key($statusCounts, array_flip($openStatuses))), 'text-blue-700'),
                $this->metricCard('Verified', (int) ($statusCounts[IncomingLetter::STATUS_VERIFIED] ?? 0), 'text-emerald-600'),
                $this->metricCard('Belum Validasi EO CS', (int) ($validationSummary->pending_validation ?? 0), 'text-amber-700'),
                $this->metricCard('Validasi Overdue', (int) ($validationSummary->overdue_validation ?? 0), 'text-rose-700'),
            ],
            'statusCards' => $this->buildStatusCards($statusCounts, $statusLabels, $statusTones),
            'slaCards' => $this->buildSlaCards($slaSummary),
            'agingCards' => $this->buildAgingCards($agingSummary),
            'rows' => $rows,
        ];
    }

    private function applyIncomingFilters(
        Builder $query,
        Request $request,
        array $statusLabels,
        string $agingBucketExpression,
        array $openStatuses
    ): void {
        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('registration_no', 'ilike', '%' . $keyword . '%')
                    ->orWhere('external_letter_no', 'ilike', '%' . $keyword . '%')
                    ->orWhere('subject', 'ilike', '%' . $keyword . '%')
                    ->orWhere('summary', 'ilike', '%' . $keyword . '%');
            });
        }

        $directorateId = (int) $request->input('directorate_id', 0);
        if ($directorateId > 0) {
            $query->where('target_directorate_id', $directorateId);
        }

        $status = (string) $request->input('status', '');
        if ($status !== '' && array_key_exists($status, $statusLabels)) {
            $query->where('status', $status);
        } elseif ($request->boolean('only_open')) {
            $query->whereIn('status', $openStatuses);
        }

        $agingBucket = trim((string) $request->input('aging_bucket', ''));
        if (array_key_exists($agingBucket, $this->agingLabels())) {
            $query->whereRaw("{$agingBucketExpression} = ?", [$agingBucket]);
        }

        if ($request->filled('period_start')) {
            $query->whereRaw('COALESCE(corsec_incoming_letters.received_date, DATE(corsec_incoming_letters.created_at)) >= ?', [$request->input('period_start')]);
        }

        if ($request->filled('period_end')) {
            $query->whereRaw('COALESCE(corsec_incoming_letters.received_date, DATE(corsec_incoming_letters.created_at)) <= ?', [$request->input('period_end')]);
        }
    }

    private function scopeIncomingVisibility(Builder $query, User $user): void
    {
        if ($this->permissionService->canViewAllCorsec($user)) {
            return;
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        $query->where(function ($builder) use ($user, $directorateId) {
            $builder->where('created_by', $user->id);
            if ($directorateId > 0) {
                $builder->orWhere('target_directorate_id', $directorateId)
                    ->orWhereHas('circulationDirectorates', function ($circulationQuery) use ($directorateId) {
                        $circulationQuery->where('directorate_id', $directorateId);
                    });
                }
        });
    }

    private function buildOutgoingReport(Request $request, User $user): array
    {
        $statusLabels = [
            OutgoingLetter::STATUS_DRAFT => OutgoingLetter::displayStatusLabel(OutgoingLetter::STATUS_DRAFT),
            OutgoingLetter::STATUS_WAITING_DIR_APPROVAL => OutgoingLetter::displayStatusLabel(OutgoingLetter::STATUS_WAITING_DIR_APPROVAL),
            OutgoingLetter::STATUS_COMPLIANCE_REVIEW => OutgoingLetter::displayStatusLabel(OutgoingLetter::STATUS_COMPLIANCE_REVIEW),
            OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL => OutgoingLetter::displayStatusLabel(OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL),
            OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD => OutgoingLetter::displayStatusLabel(OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD),
            OutgoingLetter::STATUS_WAITING_CANCEL_APPROVAL => OutgoingLetter::displayStatusLabel(OutgoingLetter::STATUS_WAITING_CANCEL_APPROVAL),
            OutgoingLetter::STATUS_VERIFIED => OutgoingLetter::displayStatusLabel(OutgoingLetter::STATUS_VERIFIED),
            OutgoingLetter::STATUS_RETURNED => OutgoingLetter::displayStatusLabel(OutgoingLetter::STATUS_RETURNED),
            OutgoingLetter::STATUS_CANCELLED => OutgoingLetter::displayStatusLabel(OutgoingLetter::STATUS_CANCELLED),
        ];
        $statusTones = [
            OutgoingLetter::STATUS_DRAFT => 'text-gray-800',
            OutgoingLetter::STATUS_WAITING_DIR_APPROVAL => 'text-amber-700',
            OutgoingLetter::STATUS_COMPLIANCE_REVIEW => 'text-orange-700',
            OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL => 'text-yellow-700',
            OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD => 'text-cyan-700',
            OutgoingLetter::STATUS_WAITING_CANCEL_APPROVAL => 'text-rose-600',
            OutgoingLetter::STATUS_VERIFIED => 'text-emerald-600',
            OutgoingLetter::STATUS_RETURNED => 'text-red-700',
            OutgoingLetter::STATUS_CANCELLED => 'text-gray-600',
        ];
        $openStatuses = [
            OutgoingLetter::STATUS_WAITING_DIR_APPROVAL,
            OutgoingLetter::STATUS_COMPLIANCE_REVIEW,
            OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL,
            OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD,
            OutgoingLetter::STATUS_WAITING_CANCEL_APPROVAL,
            OutgoingLetter::STATUS_RETURNED,
        ];
        $doneStatuses = [
            OutgoingLetter::STATUS_VERIFIED,
            OutgoingLetter::STATUS_CANCELLED,
        ];
        $dueExpression = 'COALESCE(related_incoming.target_date, related_incoming.register_due_date)';
        $completedExpression = 'DATE(COALESCE(corsec_outgoing_letters.final_upload_date, corsec_outgoing_letters.updated_at))';
        $openStatusList = $this->sqlStringList($openStatuses);
        $doneStatusList = $this->sqlStringList($doneStatuses);
        $ageExpression = "CASE
            WHEN {$dueExpression} IS NULL THEN NULL
            WHEN corsec_outgoing_letters.status IN ({$openStatusList}) THEN GREATEST(CURRENT_DATE - {$dueExpression}, 0)
            WHEN corsec_outgoing_letters.status IN ({$doneStatusList}) THEN GREATEST({$completedExpression} - {$dueExpression}, 0)
            ELSE GREATEST(CURRENT_DATE - {$dueExpression}, 0)
        END";
        $agingBucketExpression = $this->agingBucketCaseSql($ageExpression);

        $query = OutgoingLetter::query()
            ->leftJoin('corsec_incoming_letters as related_incoming', 'related_incoming.id', '=', 'corsec_outgoing_letters.perihal_incoming_letter_id')
            ->select('corsec_outgoing_letters.*')
            ->selectRaw("{$dueExpression} AS report_due_date")
            ->selectRaw("{$ageExpression} AS report_aging_days")
            ->selectRaw("{$agingBucketExpression} AS report_aging_bucket")
            ->with([
                'requesterDirectorate:id,name,tabulation_label',
                'letterType:id,name',
                'perihalIncomingLetter:id,uuid,registration_no,subject,target_date,register_due_date',
            ]);

        $this->scopeOutgoingVisibility($query, $user);
        $this->applyOutgoingFilters($query, $request, $statusLabels, $agingBucketExpression, $openStatuses);

        $statusCounts = $this->groupedCounts($query, 'corsec_outgoing_letters.status', 'status');
        $slaSummary = $this->buildSlaSummary(
            $query,
            'corsec_outgoing_letters.status',
            $openStatuses,
            $doneStatuses,
            $dueExpression,
            $completedExpression
        );
        $agingSummary = $this->buildAgingSummary($query, $agingBucketExpression);
        $rows = (clone $query)
            ->orderByRaw("COALESCE({$dueExpression}, corsec_outgoing_letters.order_date) ASC NULLS LAST")
            ->orderByDesc('corsec_outgoing_letters.id')
            ->paginate(20)
            ->withQueryString();

        return [
            'title' => 'Reporting Outgoing Letter',
            'description' => 'Rekap keseluruhan, progress proses surat keluar, dan tabulasi aging.',
            'statusOptions' => $statusLabels,
            'statusBadgeClasses' => [
                OutgoingLetter::STATUS_DRAFT => 'badge-light',
                OutgoingLetter::STATUS_WAITING_DIR_APPROVAL => 'badge-warning',
                OutgoingLetter::STATUS_COMPLIANCE_REVIEW => 'badge-warning',
                OutgoingLetter::STATUS_WAITING_COMPLIANCE_APPROVAL => 'badge-warning',
                OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD => 'badge-info',
                OutgoingLetter::STATUS_WAITING_CANCEL_APPROVAL => 'badge-danger',
                OutgoingLetter::STATUS_VERIFIED => 'badge-success',
                OutgoingLetter::STATUS_RETURNED => 'badge-danger',
                OutgoingLetter::STATUS_CANCELLED => 'badge-light',
            ],
            'summaryCards' => [
                $this->metricCard('Total Surat', (clone $query)->count()),
                $this->metricCard('On Going', array_sum(array_intersect_key($statusCounts, array_flip($openStatuses))), 'text-blue-700'),
                $this->metricCard('Review Kepatuhan', (int) ($statusCounts[OutgoingLetter::STATUS_COMPLIANCE_REVIEW] ?? 0), 'text-orange-700'),
                $this->metricCard('Final Upload', (int) ($statusCounts[OutgoingLetter::STATUS_WAITING_FINAL_UPLOAD] ?? 0), 'text-cyan-700'),
                $this->metricCard('Done', (int) ($statusCounts[OutgoingLetter::STATUS_VERIFIED] ?? 0), 'text-emerald-600'),
            ],
            'statusCards' => $this->buildStatusCards($statusCounts, $statusLabels, $statusTones),
            'slaCards' => $this->buildSlaCards($slaSummary),
            'agingCards' => $this->buildAgingCards($agingSummary),
            'rows' => $rows,
        ];
    }

    private function applyOutgoingFilters(
        Builder $query,
        Request $request,
        array $statusLabels,
        string $agingBucketExpression,
        array $openStatuses
    ): void {
        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('corsec_outgoing_letters.registration_no', 'ilike', '%' . $keyword . '%')
                    ->orWhere('corsec_outgoing_letters.letter_no', 'ilike', '%' . $keyword . '%')
                    ->orWhere('corsec_outgoing_letters.subject', 'ilike', '%' . $keyword . '%')
                    ->orWhere('corsec_outgoing_letters.summary', 'ilike', '%' . $keyword . '%')
                    ->orWhere('corsec_outgoing_letters.perihal_text', 'ilike', '%' . $keyword . '%');
            });
        }

        $directorateId = (int) $request->input('directorate_id', 0);
        if ($directorateId > 0) {
            $query->where('corsec_outgoing_letters.requester_directorate_id', $directorateId);
        }

        $status = (string) $request->input('status', '');
        if ($status !== '' && array_key_exists($status, $statusLabels)) {
            $query->where('corsec_outgoing_letters.status', $status);
        } elseif ($request->boolean('only_open')) {
            $query->whereIn('corsec_outgoing_letters.status', $openStatuses);
        }

        $agingBucket = trim((string) $request->input('aging_bucket', ''));
        if (array_key_exists($agingBucket, $this->agingLabels())) {
            $query->whereRaw("{$agingBucketExpression} = ?", [$agingBucket]);
        }

        if ($request->filled('period_start')) {
            $query->whereDate('corsec_outgoing_letters.order_date', '>=', $request->input('period_start'));
        }

        if ($request->filled('period_end')) {
            $query->whereDate('corsec_outgoing_letters.order_date', '<=', $request->input('period_end'));
        }
    }

    private function scopeOutgoingVisibility(Builder $query, User $user): void
    {
        if ($this->permissionService->canViewAllCorsec($user)) {
            return;
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        $query->where(function ($builder) use ($user, $directorateId) {
            $builder->where('corsec_outgoing_letters.created_by', $user->id);
            if ($directorateId > 0) {
                $builder->orWhere('corsec_outgoing_letters.requester_directorate_id', $directorateId);
            }
        });
    }

    private function buildMeetingReport(Request $request, User $user): array
    {
        $statusLabels = [
            MeetingDecision::STATUS_PENDING => 'Pending',
            MeetingDecision::STATUS_IN_PROGRESS => 'Proses',
            MeetingDecision::STATUS_CONTINUOUS => 'Berkelanjutan',
            MeetingDecision::STATUS_DONE => 'Done',
            MeetingDecision::STATUS_DROPPED => 'Drop',
        ];
        $statusTones = [
            MeetingDecision::STATUS_PENDING => 'text-amber-700',
            MeetingDecision::STATUS_IN_PROGRESS => 'text-blue-700',
            MeetingDecision::STATUS_CONTINUOUS => 'text-indigo-700',
            MeetingDecision::STATUS_DONE => 'text-emerald-600',
            MeetingDecision::STATUS_DROPPED => 'text-rose-600',
        ];
        $openStatuses = [
            MeetingDecision::STATUS_PENDING,
            MeetingDecision::STATUS_IN_PROGRESS,
        ];
        $doneStatuses = [
            MeetingDecision::STATUS_CONTINUOUS,
            MeetingDecision::STATUS_DONE,
            MeetingDecision::STATUS_DROPPED,
        ];
        $dueExpression = 'corsec_meeting_decisions.target_date';
        $completedExpression = 'DATE(COALESCE(corsec_meeting_decisions.latest_update_at, corsec_meeting_decisions.last_discussed_at))';
        $agingBucketExpression = 'corsec_meeting_decisions.aging_bucket';

        $latestDecisionIds = MeetingDecision::query()
            ->whereIn('meeting_id', $this->scopedMeetingsQuery($user)->select('id'))
            ->selectRaw('MAX(corsec_meeting_decisions.id)')
            ->groupBy(DB::raw('COALESCE(root_decision_id, id)'));

        $query = MeetingDecision::query()
            ->with([
                'meeting:id,uuid,title,meeting_type,meeting_at,status',
                'ownerDirectorate:id,name,tabulation_label',
                'picUser:id,name,directorate_id',
                'picUser.directorate:id,name,tabulation_label',
            ])
            ->whereIn('id', $latestDecisionIds);

        $this->applyMeetingFilters($query, $request, $statusLabels, $openStatuses);

        $statusCounts = $this->groupedCounts($query, 'status');
        $slaSummary = $this->buildSlaSummary(
            $query,
            'corsec_meeting_decisions.status',
            $openStatuses,
            $doneStatuses,
            $dueExpression,
            $completedExpression
        );
        $agingSummary = $this->buildAgingSummary($query, $agingBucketExpression);
        $filteredMeetingIds = (clone $query)->select('meeting_id')->distinct();
        $meetingSummaryRow = Meeting::query()
            ->whereIn('id', $filteredMeetingIds)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS waiting_corsec_approval", [Meeting::STATUS_WAITING_CORSEC_APPROVAL])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS waiting_direktorat_approval", [Meeting::STATUS_WAITING_DIREKTORAT_APPROVAL])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS followup_open", [Meeting::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS done_followup", [Meeting::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS not_conducted", [Meeting::STATUS_CLOSED_NOT_CONDUCTED])
            ->first();

        $rows = (clone $query)
            ->orderByRaw(
                "CASE WHEN status IN ({$this->sqlStringList($openStatuses)}) THEN 0 ELSE 1 END"
            )
            ->orderByDesc(DB::raw('COALESCE(aging_days, 0)'))
            ->orderByDesc('last_discussed_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return [
            'title' => 'Reporting Meeting',
            'description' => 'Realisasi rapat, progress tindak lanjut, dan tabulasi aging issue.',
            'statusOptions' => $statusLabels,
            'statusBadgeClasses' => [
                MeetingDecision::STATUS_PENDING => 'badge-warning',
                MeetingDecision::STATUS_IN_PROGRESS => 'badge-info',
                MeetingDecision::STATUS_CONTINUOUS => 'badge-primary',
                MeetingDecision::STATUS_DONE => 'badge-success',
                MeetingDecision::STATUS_DROPPED => 'badge-danger',
            ],
            'meetingTypeOptions' => Meeting::typeOptions(),
            'summaryCards' => [
                $this->metricCard('Total Meeting', (int) ($meetingSummaryRow->total ?? 0)),
                $this->metricCard('Waiting Corsec', (int) ($meetingSummaryRow->waiting_corsec_approval ?? 0), 'text-amber-700'),
                $this->metricCard('Waiting Direktorat', (int) ($meetingSummaryRow->waiting_direktorat_approval ?? 0), 'text-orange-700'),
                $this->metricCard('Issue Open', array_sum(array_intersect_key($statusCounts, array_flip($openStatuses))), 'text-blue-700'),
                $this->metricCard('Followup Selesai', (int) ($meetingSummaryRow->done_followup ?? 0), 'text-emerald-600'),
            ],
            'statusCards' => $this->buildStatusCards($statusCounts, $statusLabels, $statusTones),
            'slaCards' => $this->buildSlaCards($slaSummary),
            'agingCards' => $this->buildAgingCards($agingSummary),
            'rows' => $rows,
        ];
    }

    private function applyMeetingFilters(
        Builder $query,
        Request $request,
        array $statusLabels,
        array $openStatuses
    ): void {
        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('issue_key', 'ilike', '%' . $keyword . '%')
                    ->orWhere('decision_key', 'ilike', '%' . $keyword . '%')
                    ->orWhere('decision_text', 'ilike', '%' . $keyword . '%')
                    ->orWhere('latest_update_note', 'ilike', '%' . $keyword . '%');
            });
        }

        $status = (string) $request->input('status', '');
        if ($status !== '' && array_key_exists($status, $statusLabels)) {
            $query->where('status', $status);
        } elseif ($request->boolean('only_open')) {
            $query->whereIn('status', $openStatuses);
        }

        $meetingType = trim((string) $request->input('meeting_type', ''));
        if ($meetingType !== '') {
            $query->whereHas('meeting', function ($builder) use ($meetingType) {
                $builder->where('meeting_type', $meetingType);
            });
        }

        $directorateId = (int) $request->input('directorate_id', 0);
        if ($directorateId > 0) {
            $query->where('owner_directorate_id', $directorateId);
        }

        $agingBucket = trim((string) $request->input('aging_bucket', ''));
        if (array_key_exists($agingBucket, $this->agingLabels())) {
            $query->where('aging_bucket', $agingBucket);
        }

        if ($request->filled('period_start')) {
            $query->whereHas('meeting', function ($builder) use ($request) {
                $builder->whereDate('meeting_at', '>=', $request->input('period_start'));
            });
        }

        if ($request->filled('period_end')) {
            $query->whereHas('meeting', function ($builder) use ($request) {
                $builder->whereDate('meeting_at', '<=', $request->input('period_end'));
            });
        }
    }

    private function scopedMeetingsQuery(User $user)
    {
        $query = Meeting::query();
        if ($this->permissionService->canViewAllCorsec($user)) {
            return $query;
        }

        $directorateId = (int) ($user->directorate_id ?? 0);
        return $query->where(function ($builder) use ($user, $directorateId) {
            $builder->where('created_by', $user->id)
                ->orWhereHas('participants', function ($participantQuery) use ($user) {
                    $participantQuery->where('user_id', (int) $user->id);
                })
                ->orWhereHas('agendas', function ($agendaQuery) use ($user) {
                    $agendaQuery->where('pic_user_id', (int) $user->id);
                })
                ->orWhereHas('decisions', function ($decisionQuery) use ($user) {
                    $decisionQuery->where('pic_user_id', (int) $user->id);
                });

            if ($directorateId > 0) {
                $builder->orWhereHas('participants', function ($participantQuery) use ($directorateId) {
                    $participantQuery->where('directorate_id', $directorateId);
                })->orWhereHas('agendas', function ($agendaQuery) use ($directorateId) {
                    $agendaQuery->where('owner_directorate_id', $directorateId);
                })->orWhereHas('decisions', function ($decisionQuery) use ($directorateId) {
                    $decisionQuery->where('owner_directorate_id', $directorateId);
                });
            }
        });
    }

    private function buildWorkplanReport(Request $request, User $user): array
    {
        $statusLabels = [
            WorkProgramItem::STATUS_PROCESS_ON_TARGET => 'Process On Target',
            WorkProgramItem::STATUS_DONE_ON_TARGET => 'Done On Target',
            WorkProgramItem::STATUS_DONE_OVER_TARGET => 'Done Over Target',
            WorkProgramItem::STATUS_UNDONE => 'Undone',
        ];
        $statusTones = [
            WorkProgramItem::STATUS_PROCESS_ON_TARGET => 'text-blue-700',
            WorkProgramItem::STATUS_DONE_ON_TARGET => 'text-emerald-600',
            WorkProgramItem::STATUS_DONE_OVER_TARGET => 'text-orange-700',
            WorkProgramItem::STATUS_UNDONE => 'text-rose-600',
        ];
        $openStatuses = [
            WorkProgramItem::STATUS_PROCESS_ON_TARGET,
            WorkProgramItem::STATUS_UNDONE,
        ];
        $doneStatuses = [
            WorkProgramItem::STATUS_DONE_ON_TARGET,
            WorkProgramItem::STATUS_DONE_OVER_TARGET,
        ];
        $dueExpression = 'corsec_work_program_items.target_date';
        $completedExpression = 'DATE(COALESCE(corsec_work_program_items.completed_at, corsec_work_program_items.updated_at))';
        $openStatusList = $this->sqlStringList($openStatuses);
        $doneStatusList = $this->sqlStringList($doneStatuses);
        $ageExpression = "CASE
            WHEN {$dueExpression} IS NULL THEN NULL
            WHEN corsec_work_program_items.status IN ({$openStatusList}) THEN GREATEST(CURRENT_DATE - {$dueExpression}, 0)
            WHEN corsec_work_program_items.status IN ({$doneStatusList}) THEN GREATEST({$completedExpression} - {$dueExpression}, 0)
            ELSE GREATEST(CURRENT_DATE - {$dueExpression}, 0)
        END";
        $agingBucketExpression = $this->agingBucketCaseSql($ageExpression);

        $query = $this->workplanWorkflow->scopedItemsQuery($user)
            ->select('corsec_work_program_items.*')
            ->selectRaw("{$dueExpression} AS report_due_date")
            ->selectRaw("{$ageExpression} AS report_aging_days")
            ->selectRaw("{$agingBucketExpression} AS report_aging_bucket")
            ->with([
                'program:id,uuid,title,directorate_id,status',
                'program.directorate:id,name,tabulation_label',
            ]);

        $this->applyWorkplanFilters($query, $request, $statusLabels, $agingBucketExpression, $openStatuses);

        $statusCounts = $this->groupedCounts($query, 'corsec_work_program_items.status', 'status');
        $slaSummary = $this->buildSlaSummary(
            $query,
            'corsec_work_program_items.status',
            $openStatuses,
            $doneStatuses,
            $dueExpression,
            $completedExpression
        );
        $agingSummary = $this->buildAgingSummary($query, $agingBucketExpression);
        $programSummaryRow = $this->workplanWorkflow->scopedProgramsQuery($user)
            ->selectRaw('COUNT(*) AS total_programs')
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS active_programs", [WorkProgram::STATUS_ACTIVE])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS waiting_dir_approval_programs", [WorkProgram::STATUS_WAITING_DIR_APPROVAL])
            ->first();
        $rows = (clone $query)
            ->orderByRaw("COALESCE({$dueExpression}, DATE(corsec_work_program_items.created_at)) ASC NULLS LAST")
            ->orderByDesc('corsec_work_program_items.id')
            ->paginate(20)
            ->withQueryString();

        return [
            'title' => 'Reporting Work Plan',
            'description' => 'Rekap program kerja, progress item, dan tabulasi aging.',
            'statusOptions' => $statusLabels,
            'statusBadgeClasses' => [
                WorkProgramItem::STATUS_PROCESS_ON_TARGET => 'badge-info',
                WorkProgramItem::STATUS_DONE_ON_TARGET => 'badge-success',
                WorkProgramItem::STATUS_DONE_OVER_TARGET => 'badge-warning',
                WorkProgramItem::STATUS_UNDONE => 'badge-danger',
            ],
            'summaryCards' => [
                $this->metricCard('Total Program', (int) ($programSummaryRow->total_programs ?? 0)),
                $this->metricCard('Total Item', (clone $query)->count()),
                $this->metricCard('Active Program', (int) ($programSummaryRow->active_programs ?? 0), 'text-blue-700'),
                $this->metricCard('Waiting Approval', (int) ($programSummaryRow->waiting_dir_approval_programs ?? 0), 'text-amber-700'),
                $this->metricCard('Item Selesai', (int) (($statusCounts[WorkProgramItem::STATUS_DONE_ON_TARGET] ?? 0) + ($statusCounts[WorkProgramItem::STATUS_DONE_OVER_TARGET] ?? 0)), 'text-emerald-600'),
            ],
            'statusCards' => $this->buildStatusCards($statusCounts, $statusLabels, $statusTones),
            'slaCards' => $this->buildSlaCards($slaSummary),
            'agingCards' => $this->buildAgingCards($agingSummary),
            'rows' => $rows,
        ];
    }

    private function applyWorkplanFilters(
        Builder $query,
        Request $request,
        array $statusLabels,
        string $agingBucketExpression,
        array $openStatuses
    ): void {
        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('corsec_work_program_items.title', 'ilike', '%' . $keyword . '%')
                    ->orWhere('corsec_work_program_items.description', 'ilike', '%' . $keyword . '%')
                    ->orWhereHas('program', function ($programQuery) use ($keyword) {
                        $programQuery->where('title', 'ilike', '%' . $keyword . '%');
                    });
            });
        }

        $directorateId = (int) $request->input('directorate_id', 0);
        if ($directorateId > 0) {
            $query->whereHas('program', function ($programQuery) use ($directorateId) {
                $programQuery->where('directorate_id', $directorateId);
            });
        }

        $status = (string) $request->input('status', '');
        if ($status !== '' && array_key_exists($status, $statusLabels)) {
            $query->where('corsec_work_program_items.status', $status);
        } elseif ($request->boolean('only_open')) {
            $query->whereIn('corsec_work_program_items.status', $openStatuses);
        }

        $agingBucket = trim((string) $request->input('aging_bucket', ''));
        if (array_key_exists($agingBucket, $this->agingLabels())) {
            $query->whereRaw("{$agingBucketExpression} = ?", [$agingBucket]);
        }

        if ($request->filled('period_start')) {
            $query->whereDate('corsec_work_program_items.target_date', '>=', $request->input('period_start'));
        }

        if ($request->filled('period_end')) {
            $query->whereDate('corsec_work_program_items.target_date', '<=', $request->input('period_end'));
        }
    }
}
