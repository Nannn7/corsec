<?php

namespace Modules\Corsec\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Usermanagement\Models\User;

class CorsecFlowNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly array $payload
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    public static function insertForUsers(iterable $userIds, string $eventType, array $data): void
    {
        $ids = collect($userIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $payloadData = array_merge([
            'notification_type' => $eventType,
            'notification_module' => self::resolveModule($eventType, $data),
        ], $data);

        $jsonData = json_encode($payloadData, JSON_UNESCAPED_UNICODE);
        if (!is_string($jsonData)) {
            $jsonData = '{}';
        }
        $now = now();

        $payload = $ids->map(static function ($userId) use ($jsonData, $now) {
            return [
                'id' => (string) Str::uuid(),
                'type' => self::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $userId,
                'data' => $jsonData,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::table('notifications')->insert($payload);
    }

    private static function resolveModule(string $eventType, array $data): string
    {
        if (isset($data['incoming_letter_id']) || Str::startsWith($eventType, 'incoming_letter')) {
            return 'incoming_letter';
        }

        if (isset($data['outgoing_letter_id']) || Str::startsWith($eventType, 'outgoing_letter')) {
            return 'outgoing_letter';
        }

        if (
            isset($data['work_program_id'])
            || isset($data['workplan_id'])
            || Str::startsWith($eventType, 'workplan')
        ) {
            return 'workplan';
        }

        if (isset($data['meeting_id']) || Str::startsWith($eventType, 'meeting')) {
            return 'meeting';
        }

        return 'general';
    }
}
