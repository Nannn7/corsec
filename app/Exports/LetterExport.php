<?php

namespace Modules\Corsec\Exports;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\OutgoingLetter;
use Modules\Usermanagement\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LetterExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected string $type,
        protected string $search = '',
        protected ?User $user = null,
        protected string $status = ''
    ) {
        if (!in_array($this->type, ['incoming', 'outgoing'], true)) {
            throw new InvalidArgumentException('Unsupported letter export type.');
        }
    }

    public function collection(): Collection
    {
        if ($this->type === 'incoming') {
            return $this->incomingCollection();
        }

        return $this->outgoingCollection();
    }

    public function headings(): array
    {
        if ($this->type === 'incoming') {
            return [
                'No Registrasi',
                'No Surat',
                'Tanggal Surat',
                'Perihal',
                'Ringkasan',
                'Pengirim',
                'Jenis Surat',
                'Tanggal Terima',
                'Sirkulasi',
                'Leader Tindak Lanjut',
                'Target Date',
                'Status',
                'Dibuat',
            ];
        }

        return [
            'No Registrasi',
            'Tanggal Order',
            'Perihal',
            'Jenis Surat',
            'Ringkasan',
            'Perlu Review Kepatuhan',
            'Penerima',
            'Jenis Perihal',
            'Referensi Surat Masuk',
            'Keterangan Perihal',
            'Nomor Surat',
            'Status',
            'Dibuat',
        ];
    }

    public function map($row): array
    {
        if ($this->type === 'incoming') {
            return $this->mapIncoming($row);
        }

        return $this->mapOutgoing($row);
    }

    private function incomingCollection(): Collection
    {
        $query = IncomingLetter::query()
            ->with(['targetDirectorate', 'sender', 'letterType', 'circulationDirectorates'])
            ->latest();

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->where('registration_no', 'ilike', "%{$s}%")
                    ->orWhere('subject', 'ilike', "%{$s}%")
                    ->orWhere('summary', 'ilike', "%{$s}%")
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

        if ($this->user && !$this->user->hasRole('administrator')) {
            $u = $this->user;
            $query->where(function ($w) use ($u) {
                $w->where('created_by', $u->id)
                    ->orWhere('target_directorate_id', $u->directorate_id);
            });
        }

        return $query->get();
    }

    private function outgoingCollection(): Collection
    {
        $query = OutgoingLetter::query()
            ->with(['requesterDirectorate', 'recipient', 'letterType', 'perihalIncomingLetter'])
            ->latest();

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->where('subject', 'ilike', "%{$s}%")
                    ->orWhere('registration_no', 'ilike', "%{$s}%")
                    ->orWhere('letter_no', 'ilike', "%{$s}%");
            });
        }

        return $query->get();
    }

    private function mapIncoming(IncomingLetter $row): array
    {
        $circulations = $row->circulationDirectorates?->pluck('name')->filter()->values()->all() ?? [];
        $circulationLabel = count($circulations) > 0 ? implode(', ', $circulations) : '-';

        return [
            $row->registration_no ?? '-',
            $row->external_letter_no ?? '-',
            $row->letter_date ? $row->letter_date->format('Y-m-d') : '-',
            $row->subject ?? '-',
            $row->summary ?? '-',
            $row->sender?->name ?? ($row->sender_other ?? ($row->getAttribute('sender') ?? '-')),
            $row->letterType?->name ?? '-',
            $row->received_date ? $row->received_date->format('Y-m-d') : '-',
            $circulationLabel,
            $row->targetDirectorate?->name ?? '-',
            $row->target_date ? $row->target_date->format('Y-m-d') : '-',
            $row->status ?? '-',
            $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '-',
        ];
    }

    private function mapOutgoing(OutgoingLetter $row): array
    {
        $perihalType = $this->outgoingPerihalTypeLabel($row->perihal_type);

        $incomingReference = '-';
        $perihalDescription = '-';
        if ($row->perihal_type === 'tanggapan_surat_masuk') {
            $incomingReference = $row->perihalIncomingLetter?->registration_no ?? '-';
            $perihalDescription = $row->perihalIncomingLetter?->subject ?? '-';
        } elseif (in_array((string) $row->perihal_type, ['rutinitas', 'insidentil'], true)) {
            $perihalDescription = $row->perihal_text ?? '-';
        }

        return [
            $row->registration_no ?? '-',
            $row->order_date ? $row->order_date->format('Y-m-d') : '-',
            $row->subject ?? '-',
            $row->letterType?->name ?? '-',
            $row->summary ?? '-',
            $row->need_compliance_review ? 'Ya' : 'Tidak',
            $row->recipient?->name ?? ($row->recipient_other ?? '-'),
            $perihalType,
            $incomingReference,
            $perihalDescription,
            $row->letter_no ?? '-',
            OutgoingLetter::displayStatusLabel($row->status),
            $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '-',
        ];
    }

    private function outgoingPerihalTypeLabel(?string $type): string
    {
        return match ($type) {
            'tanggapan_surat_masuk' => 'Tanggapan Surat Masuk',
            'rutinitas' => 'Rutinitas',
            'insidentil' => 'Insidentil',
            default => $type ?? '-',
        };
    }
}
