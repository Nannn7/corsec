<?php

namespace Modules\Corsec\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Usermanagement\Models\User;

class IncomingLetterDirectorateNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly IncomingLetter $incomingLetter,
        private readonly User $actor
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Surat masuk baru',
            'message' => 'Surat masuk perlu tindak lanjut direktorat.',
            'incoming_letter_id' => $this->incomingLetter->id,
            'registration_no' => $this->incomingLetter->registration_no,
            'subject' => $this->incomingLetter->subject,
            'sender' => $this->incomingLetter->sender,
            'status' => $this->incomingLetter->status,
            'target_directorate_id' => $this->incomingLetter->target_directorate_id,
            'created_by' => [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ],
        ];
    }
}
