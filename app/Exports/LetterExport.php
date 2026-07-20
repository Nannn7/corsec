<?php

namespace Modules\Corsec\Exports;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\OutgoingLetter;
use Modules\Corsec\Services\CorsecPermissionService;
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
                'UUID',
                'No Surat',
                'Tanggal Surat',
                'Perihal',
                'Ringkasan',
                'Pengirim',
                'Pengirim Lainnya',
                'Cabang Nasabah',
                'Jenis Surat',
                'Jenis Surat Lainnya',
                'Tanggal Terima',
                'Prioritas',
                'Sirkulasi',
                'Leader Tindak Lanjut',
                'Target Date',
                'Status',
                'Followup Action',
                'Followup Detail',
                'Followup Note',
                'Followup Submitted At',
                'Followup Submitted By',
                'Route Terakhir',
                'Lampiran',
                'Response Outgoing',
                'Komentar',
                'Approval',
                'Authorized Status',
                'Authorized At',
                'Authorized By',
                'Dibuat',
                'Dibuat Oleh',
                'Diupdate',
                'Diupdate Oleh',
                'Deskripsi',
            ];
        }

        return [
            'No Registrasi',
            'UUID',
            'Tanggal Order',
            'Perihal',
            'Ringkasan',
            'Direktorat Pemohon',
            'Jenis Surat',
            'Perlu Review Kepatuhan',
            'Penerima',
            'Penerima Lainnya',
            'Jenis Perihal',
            'Referensi Surat Masuk',
            'Keterangan Perihal',
            'Catatan',
                'Nomor Surat',
                'Status Internal',
                'Status Tampilan',
                'Tanggal Final Upload',
                'Draft Attachment',
                'Compliance Attachment',
                'Final Attachment',
                'Attachment Tambahan',
                'Number Request Info',
            'Cancel Info',
            'Komentar',
            'Approval',
            'Authorized Status',
            'Authorized At',
            'Authorized By',
            'Dibuat',
            'Dibuat Oleh',
            'Diupdate',
            'Diupdate Oleh',
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
        $permissionService = app(CorsecPermissionService::class);
        $query = IncomingLetter::query()
            ->with([
                'targetDirectorate',
                'sender',
                'customerBranch',
                'letterType',
                'circulationDirectorates',
                'lastRoutedFromDirectorate',
                'lastRoutedToDirectorate',
                'lastRoutedFromUser',
                'lastRoutedToUser',
                'attachables.attachment',
                'comments.createdBy',
                'approvals.actor',
                'createdBy',
                'updatedBy',
                'authorizedBy',
                'responseOutgoingLetters.letterType',
            ])
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

        if ($this->user && !$permissionService->canViewAllCorsec($this->user)) {
            $u = $this->user;
            $directorateId = $u->directorate_id ?? $u->directorateid;
            $isEoCorpAffairActor = $permissionService->isEoCorpAffairActor($u);

            $query->where(function ($w) use ($u, $directorateId, $isEoCorpAffairActor) {
                $w->where('created_by', $u->id)
                    ->orWhere('target_directorate_id', $directorateId);

                if (!empty($directorateId)) {
                    $w->orWhereHas('circulationDirectorates', function ($circulationQuery) use ($directorateId) {
                        $circulationQuery->where('directorate_id', $directorateId);
                    });
                }

                if ($isEoCorpAffairActor) {
                    $w->orWhereNotNull('id');
                }
            });
        }

        return $query->get();
    }

    private function outgoingCollection(): Collection
    {
        $permissionService = app(CorsecPermissionService::class);
        $query = OutgoingLetter::query()
            ->with([
                'requesterDirectorate',
                'recipient',
                'letterType',
                'perihalIncomingLetter',
                'draftAttachment',
                'complianceAttachment',
                'finalAttachment',
                'attachables.attachment',
                'comments.createdBy',
                'approvals.actor',
                'numberRequestedBy',
                'cancelRequestedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
                'authorizedBy',
            ])
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

        if ($this->user && !$permissionService->canViewAllCorsec($this->user)) {
            $u = $this->user;
            $directorateId = (int) ($u->directorate_id ?? $u->directorateid ?? 0);

            $query->where(function ($builder) use ($u, $directorateId) {
                $builder->where('created_by', $u->id);
                if ($directorateId > 0) {
                    $builder->orWhere('requester_directorate_id', $directorateId);
                }
            });
        }

        return $query->get();
    }

    private function mapIncoming(IncomingLetter $row): array
    {
        $circulations = $row->circulationDirectorates?->pluck('name')->filter()->values()->all() ?? [];
        $circulationLabel = count($circulations) > 0 ? implode(', ', $circulations) : '-';

        $followupDetail = '-';
        if (is_array($row->followup_detail)) {
            $followupDetail = $this->cleanText(json_encode($row->followup_detail, JSON_UNESCAPED_UNICODE));
        }

        $routeSummary = implode(' | ', array_filter([
            $row->lastRoutedFromDirectorate?->name ? 'From Dir: ' . $row->lastRoutedFromDirectorate->name : null,
            $row->lastRoutedToDirectorate?->name ? 'To Dir: ' . $row->lastRoutedToDirectorate->name : null,
            $row->lastRoutedFromUser?->name ? 'From User: ' . $row->lastRoutedFromUser->name : null,
            $row->lastRoutedToUser?->name ? 'To User: ' . $row->lastRoutedToUser->name : null,
            $row->last_routed_at ? 'At: ' . $this->formatDateTime($row->last_routed_at) : null,
            $row->last_route_note ? 'Note: ' . $this->cleanText($row->last_route_note) : null,
        ]));
        $routeSummary = $routeSummary !== '' ? $routeSummary : '-';

        $attachmentSummary = $this->joinValues($row->attachables->map(function ($attachable) {
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

        $outgoingResponses = $this->joinValues($row->responseOutgoingLetters->map(function ($outgoingLetter) {
            return trim(implode(' | ', array_filter([
                $outgoingLetter->registration_no ? 'Reg: ' . $outgoingLetter->registration_no : null,
                $outgoingLetter->letter_no ? 'No: ' . $outgoingLetter->letter_no : null,
                $outgoingLetter->subject ? 'Perihal: ' . $this->cleanText($outgoingLetter->subject) : null,
                $outgoingLetter->status ? 'Status: ' . OutgoingLetter::displayStatusLabel($outgoingLetter->status) : null,
            ])));
        })->all());

        $commentSummary = $this->joinValues($row->comments->map(function ($comment) {
            return trim(implode(' | ', array_filter([
                $comment->createdBy?->name ? 'By: ' . $comment->createdBy->name : null,
                $comment->created_at ? 'At: ' . $this->formatDateTime($comment->created_at) : null,
                'Body: ' . $this->cleanText($comment->body),
            ])));
        })->all());

        $approvalSummary = $this->joinValues($row->approvals->map(function ($approval) {
            return trim(implode(' | ', array_filter([
                'Status: ' . ($approval->status ?? '-'),
                $approval->actor?->name ? 'By: ' . $approval->actor->name : null,
                $approval->acted_at ? 'At: ' . $this->formatDateTime($approval->acted_at) : null,
                $approval->note ? 'Note: ' . $this->cleanText($approval->note) : null,
            ])));
        })->all());

        return [
            $row->registration_no ?? '-',
            $row->uuid ?? '-',
            $row->external_letter_no ?? '-',
            $this->formatDate($row->letter_date),
            $this->cleanText($row->subject),
            $this->cleanText($row->summary),
            $row->sender?->name ?? ($row->sender_other ?? ($row->getAttribute('sender') ?? '-')),
            $this->cleanText($row->sender_other),
            $row->customerBranch?->name ?? '-',
            $row->letterType?->name ?? '-',
            $this->cleanText($row->letter_type_other),
            $this->formatDate($row->received_date),
            $this->cleanText($row->priority),
            $circulationLabel,
            $row->targetDirectorate?->name ?? '-',
            $this->formatDate($row->target_date),
            $row->status ?? '-',
            $this->cleanText($row->followup_action),
            $followupDetail,
            $this->cleanText($row->followup_note),
            $this->formatDateTime($row->followup_submitted_at),
            $row->followup_submitted_by ?? '-',
            $routeSummary,
            $attachmentSummary,
            $outgoingResponses,
            $commentSummary,
            $approvalSummary,
            $row->authorized_status ?? '-',
            $this->formatDateTime($row->authorized_at),
            $row->authorizedBy?->name ?? '-',
            $this->formatDateTime($row->created_at),
            $row->createdBy?->name ?? '-',
            $this->formatDateTime($row->updated_at),
            $row->updatedBy?->name ?? '-',
            $this->cleanText($row->description),
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

        $extraAttachmentSummary = $this->joinValues($row->attachables->map(function ($attachable) {
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

        $numberRequestInfo = implode(' | ', array_filter([
            $this->formatDateTime($row->number_requested_at) !== '-' ? 'At: ' . $this->formatDateTime($row->number_requested_at) : null,
            $row->numberRequestedBy?->name ? 'By: ' . $row->numberRequestedBy->name : null,
            $row->number_request_note ? 'Note: ' . $this->cleanText($row->number_request_note) : null,
        ]));
        $numberRequestInfo = $numberRequestInfo !== '' ? $numberRequestInfo : '-';

        $cancelInfo = implode(' | ', array_filter([
            $row->cancel_previous_status ? 'Prev: ' . OutgoingLetter::displayStatusLabel($row->cancel_previous_status) : null,
            $row->cancel_reason ? 'Reason: ' . $this->cleanText($row->cancel_reason) : null,
            $this->formatDateTime($row->cancel_requested_at) !== '-' ? 'Req At: ' . $this->formatDateTime($row->cancel_requested_at) : null,
            $row->cancelRequestedBy?->name ? 'Req By: ' . $row->cancelRequestedBy->name : null,
            $this->formatDateTime($row->cancelled_at) !== '-' ? 'Done At: ' . $this->formatDateTime($row->cancelled_at) : null,
            $row->cancelledBy?->name ? 'Done By: ' . $row->cancelledBy->name : null,
        ]));
        $cancelInfo = $cancelInfo !== '' ? $cancelInfo : '-';

        $commentSummary = $this->joinValues($row->comments->map(function ($comment) {
            return trim(implode(' | ', array_filter([
                $comment->createdBy?->name ? 'By: ' . $comment->createdBy->name : null,
                $comment->created_at ? 'At: ' . $this->formatDateTime($comment->created_at) : null,
                'Body: ' . $this->cleanText($comment->body),
            ])));
        })->all());

        $approvalSummary = $this->joinValues($row->approvals->map(function ($approval) {
            return trim(implode(' | ', array_filter([
                'Status: ' . ($approval->status ?? '-'),
                $approval->actor?->name ? 'By: ' . $approval->actor->name : null,
                $approval->acted_at ? 'At: ' . $this->formatDateTime($approval->acted_at) : null,
                $approval->note ? 'Note: ' . $this->cleanText($approval->note) : null,
            ])));
        })->all());

        return [
            $row->registration_no ?? '-',
            $row->uuid ?? '-',
            $this->formatDate($row->order_date),
            $this->cleanText($row->subject),
            $this->cleanText($row->summary),
            $row->requesterDirectorate?->name ?? '-',
            $row->letterType?->name ?? '-',
            $row->need_compliance_review ? 'Ya' : 'Tidak',
            $row->recipient?->name ?? ($row->recipient_other ?? '-'),
            $this->cleanText($row->recipient_other),
            $perihalType,
            $incomingReference,
            $this->cleanText($perihalDescription),
            $this->cleanText($row->note),
            $row->letter_no ?? '-',
            $row->status ?? '-',
            OutgoingLetter::displayStatusLabel($row->status),
            $this->formatDate($row->final_upload_date),
            $row->draftAttachment?->original_name ?? $row->draftAttachment?->file_name ?? '-',
            $row->complianceAttachment?->original_name ?? $row->complianceAttachment?->file_name ?? '-',
            $row->finalAttachment?->original_name ?? $row->finalAttachment?->file_name ?? '-',
            $extraAttachmentSummary,
            $numberRequestInfo,
            $cancelInfo,
            $commentSummary,
            $approvalSummary,
            $row->authorized_status ?? '-',
            $this->formatDateTime($row->authorized_at),
            $row->authorizedBy?->name ?? '-',
            $this->formatDateTime($row->created_at),
            $row->createdBy?->name ?? '-',
            $this->formatDateTime($row->updated_at),
            $row->updatedBy?->name ?? '-',
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
