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
                'updates' => function ($q) {
                    $q->latest('id');
                },
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
            'Tanggal Input',
            'Direktorat',
            'Tahun',
            'Judul Program (Header)',
            'Program Kerja (Item)',
            'Target',
            'Status Item',
            'SLA',
            'Progress Terakhir (%)',
            'Aksi Update Terakhir',
            'Catatan Update Terakhir',
            'Status Program',
            'Authorized Status',
        ];
    }

    public function map($row): array
    {
        /** @var WorkProgramItem $row */
        $program = $row->program;
        $latestUpdate = $row->updates->first();

        return [
            $program ? $this->programNumber($program) : '-',
            $program && $program->created_at ? $program->created_at->format('Y-m-d') : '-',
            $program?->directorate?->name ?? '-',
            $program?->year ?? '-',
            $program?->title ?? '-',
            $row->title ?? '-',
            $row->target_date ? $row->target_date->format('Y-m-d') : '-',
            $this->itemStatusLabel($row->status),
            $this->itemSlaLabel($row->status),
            $latestUpdate?->progress_percent ?? 0,
            $this->updateActionLabel($latestUpdate?->action),
            $latestUpdate?->note ?? '-',
            $this->programStatusLabel($program?->status),
            $program?->authorized_status ?? '-',
        ];
    }

    private function canViewAllPrograms(User $user): bool
    {
        return $user->hasRole('administrator') || $user->hasRole('checker') || $user->hasRole('approver');
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
            default => $status ?: '-',
        };
    }
}
