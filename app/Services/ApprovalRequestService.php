<?php

namespace Modules\Corsec\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Corsec\Models\ApprovalRequest;
use Modules\Corsec\Models\LetterType;
use Modules\Usermanagement\Models\User;

class ApprovalRequestService
{
    public function createRequest(
        string $modelClass,
        string $action,
        ?string $targetId,
        array $requestNew,
        ?array $requestOld = null,
        ?string $description = null
    ): ApprovalRequest {
        $userId = Auth::id();
        $approval = ApprovalRequest::create([
            'model' => $modelClass,
            'action' => $action,
            'target_id' => $targetId,
            'request_old' => $requestOld,
            'request_new' => $requestNew,
            'status' => ApprovalRequest::STATUS_PENDING,
            'description' => $description,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $approval->update([
            'checksum' => $approval->generateChecksum(),
        ]);

        return $approval;
    }

    public function approve(ApprovalRequest $approvalRequest, User $actor): void
    {
        DB::transaction(function () use ($approvalRequest, $actor) {
            $modelClass = $approvalRequest->model;
            $requestNew = $approvalRequest->request_new ?? [];

            if ($approvalRequest->action === ApprovalRequest::ACTION_CREATE) {
                if (isset($requestNew['items']) && is_array($requestNew['items'])) {
                    foreach ($requestNew['items'] as $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $payload = $this->filterFillable($modelClass, $item);
                        if (!empty($payload)) {
                            $modelClass::create($payload);
                        }
                    }
                } else {
                    $payload = $this->filterFillable($modelClass, $requestNew);
                    $modelClass::create($payload);
                }
            } elseif ($approvalRequest->action === ApprovalRequest::ACTION_UPDATE) {
                $payload = $this->filterFillable($modelClass, $requestNew);
                $modelClass::query()
                    ->where('id', $approvalRequest->target_id)
                    ->update($payload);
            } elseif ($approvalRequest->action === ApprovalRequest::ACTION_DELETE) {
                $modelClass::query()
                    ->where('id', $approvalRequest->target_id)
                    ->delete();
            }

            if ($modelClass === LetterType::class) {
                Cache::forget('corsec.letter_types.list');
                Cache::forget('corsec.letter_types.in.list');
                Cache::forget('corsec.letter_types.out.list');
            }

            $approvalRequest->update([
                'status' => ApprovalRequest::STATUS_APPROVED,
                'authorized_at' => now(),
                'authorized_by' => $actor->id,
            ]);
        });
    }

    public function reject(ApprovalRequest $approvalRequest, User $actor, ?string $notes = null): void
    {
        $approvalRequest->update([
            'status' => ApprovalRequest::STATUS_REJECTED,
            'authorized_at' => now(),
            'authorized_by' => $actor->id,
            'review_notes' => $notes,
        ]);
    }

    private function filterFillable(string $modelClass, array $payload): array
    {
        $model = new $modelClass();
        $fillable = $model->getFillable();
        if (empty($fillable)) {
            return $payload;
        }

        return array_intersect_key($payload, array_flip($fillable));
    }
}
