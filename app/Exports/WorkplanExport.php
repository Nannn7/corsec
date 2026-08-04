<?php

namespace Modules\Corsec\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Modules\Corsec\Models\WorkProgram;
use Modules\Corsec\Models\WorkProgramItem;
use Modules\Corsec\Models\WorkProgramUpdate;
use Modules\Usermanagement\Models\User;

class WorkplanExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected User $user,
        protected string $search = '',
        protected string $status = '',
        protected int $directorateId = 0,
        protected int $year = 0
    ) {}

    public function collection(): Collection
    {
        $query = WorkProgramItem::query()
            ->with([
                'program.directorate',
                'program.createdBy',
                'program.updatedBy',
                'program.authorizedBy',
                'program.attachables.attachment',
                'program.comments.createdBy',
                'program.approvals.actor',
                'creator',
                'attachables.attachment',
                'comments.createdBy',
                'updates' => function ($q) {
                    $q->latest('id');
                },
                'updates.updater',
                'updates.authorizedBy',
                'updates.attachables.attachment',
                'updates.comments.createdBy',
                'updates.approvals.actor',
            ]);

        $query->whereHas('program', function ($programQuery) {
            if ($this->status !== '') {
                $programQuery->where('status', $this->status);
            }
            if ($this->directorateId > 0) {
                $programQuery->where('directorate_id', $this->directorateId);
            }
            if ($this->year > 0) {
                $programQuery->where('year', $this->year);
            }

            if (!$this->canViewAllPrograms($this->user)) {
                $directorateId = $this->user->directorate_id ?? null;
                $programQuery->where(function ($w) use ($directorateId) {
                    $w->where('created_by', $this->user->id);
                    if ($directorateId) {
                        $w->orWhere('directorate_id', $directorateId);
                    }
                });
            }
        });

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', '%' . $search . '%')
                    ->orWhere('description', 'ilike', '%' . $search . '%')
                    ->orWhere('status', 'ilike', '%' . $search . '%')
                    ->orWhereHas('updates', function ($updateQuery) use ($search) {
                        $updateQuery->where('note', 'ilike', '%' . $search . '%')
                            ->orWhere('status', 'ilike', '%' . $search . '%')
                            ->orWhere('action', 'ilike', '%' . $search . '%');
                    })
                    ->orWhereHas('program', function ($programQuery) use ($search) {
                        $programQuery->where('title', 'ilike', '%' . $search . '%')
                            ->orWhere('description', 'ilike', '%' . $search . '%')
                            ->orWhereHas('directorate', function ($directorateQuery) use ($search) {
                                $directorateQuery->where('name', 'ilike', '%' . $search . '%')
                                    ->orWhere('code', 'ilike', '%' . $search . '%');
                            });
                    });
            });
        }

        return $query->orderByDesc('id')->get();
    }

    public function headings(): array
    {
        return [
            'No Program',
            'UUID Program',
            'Tanggal Input Program',
            'Direktorat',
            'Tahun',
            'Judul Program (Header)',
            'Deskripsi Program',
            'Status Program',
            'Authorized Status Program',
            'Authorized At Program',
            'Authorized By Program',
            'Created At Program',
            'Created By Program',
            'Updated At Program',
            'Updated By Program',
            'Lampiran Program',
            'Komentar Program',
            'Approval Program',
            'ID Item',
            'Program Kerja (Item)',
            'Deskripsi Item',
            'Bobot Item',
            'Target Awal',
            'Target Saat Ini',
            'Status Item',
            'SLA',
            'Completed At',
            'Created By Item',
            'Lampiran Item',
            'Komentar Item',
            'Progress Terakhir (%)',
            'Aksi Update Terakhir',
            'Catatan Update Terakhir',
            'Detail Update (Semua)',
        ];
    }

    public function map($row): array
    {
        /** @var WorkProgramItem $row */
        $program = $row->program;
        $latestUpdate = $row->updates->first();

        return [
            $program ? $this->programNumber($program) : '-',
            $program?->uuid ?? '-',
            $this->formatDateTime($program?->created_at),
            $program?->directorate?->name ?? '-',
            $program?->year ?? '-',
            $this->cleanText($program?->title),
            $this->cleanText($program?->description),
            $this->programStatusLabel($program?->status),
            $program?->authorized_status ?? '-',
            $this->formatDateTime($program?->authorized_at),
            $program?->authorizedBy?->name ?? '-',
            $this->formatDateTime($program?->created_at),
            $program?->createdBy?->name ?? '-',
            $this->formatDateTime($program?->updated_at),
            $program?->updatedBy?->name ?? '-',
            $this->attachableDetails($program?->attachables ?? collect()),
            $this->commentDetails($program?->comments ?? collect()),
            $this->approvalDetails($program?->approvals ?? collect()),
            $row->id,
            $this->cleanText($row->title),
            $this->cleanText($row->description),
            $row->weight ?? '-',
            $this->formatDate($row->initial_target_date),
            $this->formatDate($row->target_date),
            $this->itemStatusLabel($row->status),
            $this->itemSlaLabel($row->status),
            $this->formatDateTime($row->completed_at),
            $row->creator?->name ?? '-',
            $this->attachableDetails($row->attachables),
            $this->commentDetails($row->comments),
            $latestUpdate?->progress_percent ?? 0,
            $this->updateActionLabel($latestUpdate?->action),
            $this->cleanText($latestUpdate?->note),
            $this->updateDetails($row->updates),
        ];
    }

    private function canViewAllPrograms(User $user): bool
    {
        return $user->hasRole('administrator') || $this->isAllDataDirectorate($user);
<<<<<<< HEAD
=======
    }

    private function isAllDataDirectorate(User $user): bool
    {
        $user->loadMissing('directorate');
        $name = strtolower((string) ($user->directorate?->name ?? ''));
        $code = (string) ($user->directorate?->code ?? '');
        $complianceCode = (string) config('corsec.compliance_directorate_code', '');
        $corpCode = (string) config('corsec.eo_corp_affair_directorate_code', '');

        return ($code !== '' && $complianceCode !== '' && $code === $complianceCode)
            || ($code !== '' && $corpCode !== '' && $code === $corpCode)
            || str_contains($name, 'kepatuhan')
            || str_contains($name, 'compliance')
            || str_contains($name, 'complience')
            || str_contains($name, 'corporate secretary')
            || str_contains($name, 'skai');
>>>>>>> 41a6d587a986009fad13830696d5399143b77ee3
    }

    private function isAllDataDirectorate(User $user): bool
    {
        $user->loadMissing('directorate');
        $name = strtolower((string) ($user->directorate?->name ?? ''));
        $code = (string) ($user->directorate?->code ?? '');
        $complianceCode = (string) config('corsec.compliance_directorate_code', '');
        $corpCode = (string) config('corsec.eo_corp_affair_directorate_code', '');

        return ($code !== '' && $complianceCode !== '' && $code === $complianceCode)
            || ($code !== '' && $corpCode !== '' && $code === $corpCode)
            || str_contains($name, 'kepatuhan')
            || str_contains($name, 'compliance')
            || str_contains($name, 'complience')
            || str_contains($name, 'corporate secretary')
            || str_contains($name, 'skai');
    }

    private function programNumber(WorkProgram $program): string
    {
        $date = $program->created_at ? $program->created_at->format('Ymd') : now()->format('Ymd');
        return 'PK-' . $date . '-' . str_pad((string) $program->id, 6, '0', STR_PAD_LEFT);
    }

    private function itemStatusLabel(?string $status): string
    {
        return match ((string) $status) {
            WorkProgramItem::STATUS_PROCESS_ON_TARGET => 'PK - proses on target',
            WorkProgramItem::STATUS_DONE_ON_TARGET => 'PK - done on target',
            WorkProgramItem::STATUS_DONE_OVER_TARGET => 'PK - done over target',
            WorkProgramItem::STATUS_UNDONE => 'PK - undone',
            default => $status ?: '-',
        };
    }

    private function itemSlaLabel(?string $status): string
    {
        return match ((string) $status) {
            WorkProgramItem::STATUS_DONE_ON_TARGET => 'On Target',
            WorkProgramItem::STATUS_DONE_OVER_TARGET => 'Over Target',
            WorkProgramItem::STATUS_UNDONE => 'Undone',
            default => 'On Progress',
        };
    }

    private function updateActionLabel(?string $action): string
    {
        return match ((string) $action) {
            WorkProgramUpdate::ACTION_PROGRESS => 'Progress',
            WorkProgramUpdate::ACTION_DONE_ON_TARGET => 'Done On Target',
            WorkProgramUpdate::ACTION_DONE_OVER_TARGET => 'Done Over Target',
            WorkProgramUpdate::ACTION_REVISION => 'Revision',
            default => $action ?: '-',
        };
    }

    private function programStatusLabel(?string $status): string
    {
        return match ((string) $status) {
            WorkProgram::STATUS_DRAFT => 'Draft',
            WorkProgram::STATUS_WAITING_DIR_APPROVAL => 'Waiting Dir Approval',
            WorkProgram::STATUS_ACTIVE => 'Active',
            WorkProgram::STATUS_DONE => 'Done',
            WorkProgram::STATUS_RETURNED => 'Returned',
            WorkProgram::STATUS_REJECTED => 'Rejected',
            default => $status ?: '-',
        };
    }

    private function updateDetails(Collection $updates): string
    {
        return $this->joinValues($updates->map(function (WorkProgramUpdate $update) {
            $files = $this->attachableDetails($update->attachables);
            $comments = $this->commentDetails($update->comments);
            $approvals = $this->approvalDetails($update->approvals);

            return trim(implode(' | ', array_filter([
                'ID: ' . $update->id,
                'Action: ' . $this->updateActionLabel($update->action),
                'Status: ' . ($update->status ?? '-'),
                'Progress: ' . ((int) ($update->progress_percent ?? 0)) . '%',
                $update->revised_target_date ? 'Revised Target: ' . $this->formatDate($update->revised_target_date) : null,
                $update->note ? 'Note: ' . $this->cleanText($update->note) : null,
                $update->updater?->name ? 'By: ' . $update->updater->name : null,
                $update->created_at ? 'At: ' . $this->formatDateTime($update->created_at) : null,
                $update->authorized_status ? 'Authorized: ' . $update->authorized_status : null,
                $update->authorized_at ? 'Authorized At: ' . $this->formatDateTime($update->authorized_at) : null,
                $update->authorizedBy?->name ? 'Authorized By: ' . $update->authorizedBy->name : null,
                $files !== '-' ? 'Files: ' . $files : null,
                $comments !== '-' ? 'Comments: ' . $comments : null,
                $approvals !== '-' ? 'Approvals: ' . $approvals : null,
            ])));
        })->all());
    }

    private function attachableDetails(Collection $attachables): string
    {
        return $this->joinValues($attachables->map(function ($attachable) {
            $file = $attachable->attachment?->original_name ?? $attachable->attachment?->file_name;
            if (!$file) {
                return null;
            }

            return trim(implode(' | ', array_filter([
                $file,
                $attachable->category ? 'Category: ' . $attachable->category : null,
                $attachable->note ? 'Note: ' . $this->cleanText($attachable->note) : null,
            ])));
        })->all());
    }

    private function commentDetails(Collection $comments): string
    {
        return $this->joinValues($comments->map(function ($comment) {
            return trim(implode(' | ', array_filter([
                $comment->createdBy?->name ? 'By: ' . $comment->createdBy->name : null,
                $comment->created_at ? 'At: ' . $this->formatDateTime($comment->created_at) : null,
                'Body: ' . $this->cleanText($comment->body),
            ])));
        })->all());
    }

    private function approvalDetails(Collection $approvals): string
    {
        return $this->joinValues($approvals->map(function ($approval) {
            return trim(implode(' | ', array_filter([
                'Status: ' . ($approval->status ?? '-'),
                $approval->actor?->name ? 'By: ' . $approval->actor->name : null,
                $approval->acted_at ? 'At: ' . $this->formatDateTime($approval->acted_at) : null,
                $approval->note ? 'Note: ' . $this->cleanText($approval->note) : null,
            ])));
        })->all());
    }

    private function joinValues(array $values): string
    {
        $clean = array_values(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $values), function ($value) {
            return $value !== '' && $value !== '-';
        }));

        return empty($clean) ? '-' : implode(' || ', $clean);
    }

    private function cleanText(?string $text): string
    {
        if ($text === null) {
            return '-';
        }

        $value = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        return $value === '' ? '-' : $value;
    }

    private function formatDate($value): string
    {
        if (!$value) {
            return '-';
        }

        return $value->format('Y-m-d');
    }

    private function formatDateTime($value): string
    {
        if (!$value) {
            return '-';
        }

        return $value->format('Y-m-d H:i:s');
    }
}
