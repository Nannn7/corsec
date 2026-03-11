<?php

namespace Modules\Corsec\Exports;

use Modules\Corsec\Models\Directorate;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DirectorateExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected string $search = ''
    ) {}

    public function collection()
    {
        $query = Directorate::query()->latest();

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->where('code', 'ilike', "%{$s}%")
                    ->orWhere('name', 'ilike', "%{$s}%")
                    ->orWhere('description', 'ilike', "%{$s}%");
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Nama',
            'Deskripsi',
            'Tipe Unit Meeting',
            'Status',
            'Dibuat',
        ];
    }

    public function map($row): array
    {
        return [
            $row->code ?? '-',
            $row->name ?? '-',
            $row->description ?? '-',
            (bool) ($row->is_meeting_operational ?? false) ? 'Unit Operasional' : 'Monitoring Only',
            $row->status ? 'Aktif' : 'Non-Aktif',
            $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '-',
        ];
    }
}
