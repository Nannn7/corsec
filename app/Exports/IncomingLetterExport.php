<?php

namespace Modules\Corsec\Exports;

use Modules\Usermanagement\Models\User;
use Modules\Corsec\Models\IncomingLetter;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class IncomingLetterExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected string $search = '',
        protected ?User $user = null
    ) {}

    public function collection()
    {
        $query = IncomingLetter::query()->with(['targetBranch'])->latest();

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->where('subject', 'ilike', "%{$s}%")
                    ->orWhere('sender', 'ilike', "%{$s}%")
                    ->orWhere('external_letter_no', 'ilike', "%{$s}%");
            });
        }

        // scope akses sama kayak index/dt
        if ($this->user && !$this->user->hasRole('administrator')) {
            $u = $this->user;
            $query->where(function ($w) use ($u) {
                $w->where('created_by', $u->id)
                    ->orWhere('target_branch_id', $u->branch_id);
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'No Surat',
            'Perihal',
            'Pengirim',
            'Tanggal Terima',
            'Direktorat',
            'Target Date',
            'Status',
            'Dibuat',
        ];
    }

    public function map($row): array
    {
        return [
            $row->external_letter_no ?? '-',
            $row->subject ?? '-',
            $row->sender ?? '-',
            $row->received_date ? $row->received_date->format('Y-m-d') : '-',
            $row->targetBranch?->name ?? '-',
            $row->target_date ? $row->target_date->format('Y-m-d') : '-',
            $row->status ?? '-',
            $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '-',
        ];
    }
}
