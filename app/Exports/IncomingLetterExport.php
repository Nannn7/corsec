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
        $query = IncomingLetter::query()->with(['targetDirectorate', 'sender', 'letterType'])->latest();

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->where('subject', 'ilike', "%{$s}%")
                    ->orWhere('external_letter_no', 'ilike', "%{$s}%")
                    ->orWhereHas('sender', function ($senderQuery) use ($s) {
                        $senderQuery->where('name', 'ilike', "%{$s}%")
                            ->orWhere('code', 'ilike', "%{$s}%");
                    })
                    ->orWhereHas('letterType', function ($letterTypeQuery) use ($s) {
                        $letterTypeQuery->where('name', 'ilike', "%{$s}%")
                            ->orWhere('code', 'ilike', "%{$s}%");
                    });
            });
        }

        // scope akses sama kayak index/dt
        if ($this->user && !$this->user->hasRole('administrator')) {
            $u = $this->user;
            $query->where(function ($w) use ($u) {
                $w->where('created_by', $u->id)
                    ->orWhere('target_directorate_id', $u->directorate_id);
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
            'Jenis Surat',
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
            $row->sender?->name ?? '-',
            $row->letterType?->name ?? '-',
            $row->received_date ? $row->received_date->format('Y-m-d') : '-',
            $row->targetDirectorate?->name ?? '-',
            $row->target_date ? $row->target_date->format('Y-m-d') : '-',
            $row->status ?? '-',
            $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '-',
        ];
    }
}
