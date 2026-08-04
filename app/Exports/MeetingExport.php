<?php

namespace Modules\Corsec\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\MeetingDecision;
use Modules\Corsec\Models\MeetingMinutes;
use Modules\Corsec\Models\MeetingParticipant;
use Modules\Usermanagement\Models\User;

class MeetingExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected User $user,
        protected string $search = ''
    ) {}

    public function collection(): Collection
    {
        $query = $this->scopedMeetingsQuery($this->user)
            ->with([
                'createdBy',
                'updatedBy',
                'authorizedBy',
                'attachables.attachment',
                'participants.directorate',
                'participants.participantUser',
                'agendas.ownerDirectorate',
                'agendas.picUser',
                'agendas.attachables.attachment',
                'materials.attachment',
                'materials.uploader',
                'materials.authorizedBy',
                'materials.agenda',
                'minutes.minutesAttachment',
                'minutes.finalMinutesAttachment',
                'minutes.submitter',
                'minutes.approver',
                'minutes.comments.createdBy',
                'minutes.approvals.actor',
                'decisions.ownerDirectorate',
                'decisions.picUser',
                'decisions.comments.createdBy',
                'decisions.attachables.attachment',
                'decisions.updates.updater',
                'decisions.updates.authorizedBy',
                'decisions.updates.attachables.attachment',
                'decisions.updates.comments.createdBy',
                'decisions.updates.approvals.actor',
                'comments.createdBy',
                'approvals.actor',
            ])
            ->withCount(['participants', 'agendas'])
            ->latest('meeting_at')
            ->latest('id');

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', '%' . $search . '%')
                    ->orWhere('meeting_type', 'ilike', '%' . $search . '%')
                    ->orWhere('status', 'ilike', '%' . $search . '%')
                    ->orWhereRaw("to_char(meeting_at, 'YYYY-MM-DD HH24:MI:SS') ilike ?", ['%' . $search . '%']);
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'UUID',
            'Tanggal Meeting',
            'Judul',
            'Kategori',
            'Lokasi',
            'Status',
            'Deskripsi',
            'Jadwal Terkirim',
            'Meeting Dimulai',
            'Meeting Selesai',
            'Lampiran Meeting',
            'Jumlah Peserta',
            'Detail Peserta',
            'Jumlah Agenda',
            'Detail Agenda',
            'Detail Material',
            'Status Notulen',
            'Isi Notulen',
            'File Notulen',
            'File Notulen Final',
            'Info Notulen',
            'Komentar Notulen',
            'Approval Notulen',
            'Detail Keputusan & Follow Up',
            'Komentar Meeting',
            'Approval Meeting',
            'Authorized Status',
            'Authorized At',
            'Authorized By',
            'Created At',
            'Created By',
            'Updated At',
            'Updated By',
        ];
    }

    public function map($row): array
    {
        /** @var Meeting $row */
        $typeLabels = Meeting::typeOptions();
        $statusLabels = Meeting::statusLabels();
        $minutes = $row->minutes;

        return [
            $row->id,
            $row->uuid ?? '-',
            $this->formatDateTime($row->meeting_at),
            $this->cleanText($row->title),
            $typeLabels[$row->meeting_type] ?? ($row->meeting_type ?? '-'),
            $this->cleanText($row->location),
            $statusLabels[$row->status] ?? ($row->status ?? '-'),
            $this->cleanText($row->description),
            $this->formatDateTime($row->schedule_sent_at),
            $this->formatDateTime($row->conducted_at),
            $this->formatDateTime($row->finished_at),
            $this->attachableDetails($row->attachables),
            (int) ($row->participants_count ?? 0),
            $this->participantDetails($row->participants),
            (int) ($row->agendas_count ?? 0),
            $this->agendaDetails($row->agendas),
            $this->materialDetails($row->materials),
            $this->minutesStatusLabel($minutes),
            $minutes ? $this->cleanText($minutes->minutes_text) : '-',
            $minutes?->minutesAttachment?->original_name ?? $minutes?->minutesAttachment?->file_name ?? '-',
            $minutes?->finalMinutesAttachment?->original_name ?? $minutes?->finalMinutesAttachment?->file_name ?? '-',
            $this->minutesInfo($minutes),
            $this->commentDetails($minutes?->comments ?? collect()),
            $this->approvalDetails($minutes?->approvals ?? collect()),
            $this->decisionDetails($row->decisions),
            $this->commentDetails($row->comments),
            $this->approvalDetails($row->approvals),
            $row->authorized_status ?? '-',
            $this->formatDateTime($row->authorized_at),
            $row->authorizedBy?->name ?? '-',
            $this->formatDateTime($row->created_at),
            $row->createdBy?->name ?? '-',
            $this->formatDateTime($row->updated_at),
            $row->updatedBy?->name ?? '-',
        ];
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
                $w->orWhereHas('participants', function ($q) use ($directorateId) {
                    $q->where('directorate_id', $directorateId);
                });
                $w->orWhereHas('agendas', function ($q) use ($directorateId) {
                    $q->where('owner_directorate_id', $directorateId);
                });
                $w->orWhereHas('decisions', function ($q) use ($directorateId) {
                    $q->where('owner_directorate_id', $directorateId);
                });
            }
        });
    }

    private function canViewAllMeetings(User $user): bool
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

    private function participantDetails(Collection $participants): string
    {
        return $this->joinValues($participants->map(function (MeetingParticipant $participant) {
            $userName = $participant->participantUser?->name;
            $directorateName = $participant->directorate?->name;
            $note = $this->cleanText($participant->note);

            return trim(implode(' | ', array_filter([
                $directorateName ? 'Direktorat: ' . $directorateName : null,
                $userName ? 'User: ' . $userName : null,
                $note !== '-' ? 'Note: ' . $note : null,
            ])));
        })->all());
    }

    private function agendaDetails(Collection $agendas): string
    {
        return $this->joinValues($agendas->map(function ($agenda) {
            $order = $agenda->order_no ? '#' . $agenda->order_no : '#-';
            $agendaFiles = $this->attachableDetails($agenda->attachables ?? collect());

            return trim(implode(' | ', array_filter([
                $order,
                $this->cleanText($agenda->title),
                $agenda->ownerDirectorate?->name ? 'Owner: ' . $agenda->ownerDirectorate->name : null,
                $agenda->picUser?->name ? 'PIC: ' . $agenda->picUser->name : null,
                $agenda->description ? 'Desc: ' . $this->cleanText($agenda->description) : null,
                $agenda->minutes_discussion ? 'Discussion: ' . $this->cleanText($agenda->minutes_discussion) : null,
                $agendaFiles !== '-' ? 'Photos: ' . $agendaFiles : null,
            ])));
        })->all());
    }

    private function materialDetails(Collection $materials): string
    {
        return $this->joinValues($materials->map(function ($material) {
            $file = $material->attachment?->original_name ?? $material->attachment?->file_name ?? '-';
            $agendaTitle = $material->agenda?->title;

            return trim(implode(' | ', array_filter([
                $agendaTitle ? 'Agenda: ' . $this->cleanText($agendaTitle) : null,
                'File: ' . $file,
                $material->uploader?->name ? 'Uploader: ' . $material->uploader->name : null,
                $material->uploaded_at ? 'Uploaded: ' . $this->formatDateTime($material->uploaded_at) : null,
                $material->authorized_status ? 'Auth: ' . $material->authorized_status : null,
                $material->authorized_at ? 'Auth At: ' . $this->formatDateTime($material->authorized_at) : null,
                $material->authorizedBy?->name ? 'Auth By: ' . $material->authorizedBy->name : null,
            ])));
        })->all());
    }

    private function minutesStatusLabel(?MeetingMinutes $minutes): string
    {
        if (!$minutes) {
            return '-';
        }

        return match ($minutes->status) {
            MeetingMinutes::STATUS_DRAFT => 'Draft',
            MeetingMinutes::STATUS_SUBMITTED => 'Submitted',
            MeetingMinutes::STATUS_APPROVED => 'Approved',
            default => $minutes->status ?? '-',
        };
    }

    private function decisionDetails(Collection $decisions): string
    {
        return $this->joinValues($decisions->map(function (MeetingDecision $decision) {
            $decisionFiles = $this->attachableDetails($decision->attachables);
            $decisionComments = $this->commentDetails($decision->comments);

            $updates = $this->joinValues($decision->updates->map(function ($update) {
                $files = $this->attachableDetails($update->attachables);
                $comments = $this->commentDetails($update->comments);
                $approvals = $this->approvalDetails($update->approvals);

                return trim(implode(' | ', array_filter([
                    'Type: ' . ($update->update_type ?? '-'),
                    'Status: ' . ($update->status ?? '-'),
                    'Progress: ' . ((int) ($update->progress_percent ?? 0)) . '%',
                    $update->happened_at ? 'Date: ' . $this->formatDate($update->happened_at) : null,
                    isset($update->is_on_target) ? 'On Target: ' . ($update->is_on_target ? 'Ya' : 'Tidak') : null,
                    $update->reason ? 'Reason: ' . $this->cleanText($update->reason) : null,
                    $update->note ? 'Note: ' . $this->cleanText($update->note) : null,
                    $update->updater?->name ? 'By: ' . $update->updater->name : null,
                    $update->authorized_status ? 'Authorized: ' . $update->authorized_status : null,
                    $update->authorized_at ? 'Authorized At: ' . $this->formatDateTime($update->authorized_at) : null,
                    $update->authorizedBy?->name ? 'Authorized By: ' . $update->authorizedBy->name : null,
                    $files !== '-' ? 'Files: ' . $files : null,
                    $comments !== '-' ? 'Comments: ' . $comments : null,
                    $approvals !== '-' ? 'Approvals: ' . $approvals : null,
                ])));
            })->all());

            return trim(implode(' | ', array_filter([
                'Decision: ' . $this->cleanText($decision->decision_text),
                $decision->ownerDirectorate?->name ? 'Owner: ' . $decision->ownerDirectorate->name : null,
                $decision->picUser?->name ? 'PIC: ' . $decision->picUser->name : null,
                $decision->target_date ? 'Target: ' . $this->formatDate($decision->target_date) : null,
                'Status: ' . ($decision->status ?? '-'),
                $decision->closed_at ? 'Closed: ' . $this->formatDateTime($decision->closed_at) : null,
                $decisionFiles !== '-' ? 'Files: ' . $decisionFiles : null,
                $decisionComments !== '-' ? 'Comments: ' . $decisionComments : null,
                $updates !== '-' ? 'Updates: ' . $updates : null,
            ])));
        })->all());
    }

    private function minutesInfo(?MeetingMinutes $minutes): string
    {
        if (!$minutes) {
            return '-';
        }

        $parts = array_values(array_filter([
            $minutes->submitter?->name ? 'Submitted By: ' . $minutes->submitter->name : null,
            $minutes->submitted_at ? 'Submitted At: ' . $this->formatDateTime($minutes->submitted_at) : null,
            $minutes->approver?->name ? 'Approved By: ' . $minutes->approver->name : null,
            $minutes->approved_at ? 'Approved At: ' . $this->formatDateTime($minutes->approved_at) : null,
            $minutes->circulated_at ? 'Circulated At: ' . $this->formatDateTime($minutes->circulated_at) : null,
            $minutes->finalized_at ? 'Finalized At: ' . $this->formatDateTime($minutes->finalized_at) : null,
        ]));

        return empty($parts) ? '-' : implode(' | ', $parts);
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
                'At: ' . $this->formatDateTime($comment->created_at),
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

    private function formatDateTime($value): string
    {
        if (!$value) {
            return '-';
        }

        return $value->format('Y-m-d H:i:s');
    }

    private function formatDate($value): string
    {
        if (!$value) {
            return '-';
        }

        return $value->format('Y-m-d');
    }
}
