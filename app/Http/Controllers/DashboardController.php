<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\OutgoingLetter;
use Modules\Corsec\Models\WorkProgramItem;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        if ($user && $user->hasRole(['maker', 'checker', 'approver', 'administrator', 'viewer'])) {
            $incomingOpen = IncomingLetter::query()
                ->whereNotIn('status', [
                    IncomingLetter::STATUS_VERIFIED,
                    IncomingLetter::STATUS_REJECTED,
                    IncomingLetter::STATUS_RETURNED,
                ])
                ->count();

            $outgoingOpen = OutgoingLetter::query()
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereNotIn('status', ['done', 'completed', 'sent', 'verified', OutgoingLetter::STATUS_CANCELLED]);
                })
                ->where(function ($q) {
                    $q->whereNull('authorized_status')
                        ->orWhere('authorized_status', '!=', 'cancelled');
                })
                ->whereNull('cancelled_at')
                ->count();

            $meetingOpenQuery = Meeting::query()
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereNotIn('status', [
                            'done',
                            'completed',
                            'closed',
                            'verified',
                            Meeting::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT,
                        ]);
                });

            $directorateId = (int) ($user->directorate_id ?? 0);
            $meetingOpenQuery->where(function ($w) use ($user, $directorateId) {
                $w->where('created_by', $user->id)
                    ->orWhereHas('participants', function ($q) use ($user) {
                        $q->where('user_id', (int) $user->id);
                    })
                    ->orWhereHas('agendas', function ($q) use ($user) {
                        $q->where('pic_user_id', (int) $user->id);
                    })
                    ->orWhereHas('decisions', function ($q) use ($user) {
                        $q->where('pic_user_id', (int) $user->id);
                    });

                if ($directorateId > 0) {
                    $w->orWhereHas('participants', function ($q) use ($directorateId) {
                        $q->where('directorate_id', $directorateId);
                    })
                        ->orWhereHas('agendas', function ($q) use ($directorateId) {
                            $q->where('owner_directorate_id', $directorateId);
                        })
                        ->orWhereHas('decisions', function ($q) use ($directorateId) {
                            $q->where('owner_directorate_id', $directorateId);
                        });
                }
            });

            $meetingOpen = $meetingOpenQuery->count();

            $workplanOpen = WorkProgramItem::query()
                ->whereHas('program', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->whereNotIn('status', [
                    WorkProgramItem::STATUS_DONE_ON_TARGET,
                    WorkProgramItem::STATUS_DONE_OVER_TARGET,
                ])
                ->count();

            return view('corsec::dashboard', compact('incomingOpen', 'outgoingOpen', 'meetingOpen', 'workplanOpen'));
        }

        abort(403, 'Sorry! You are not allowed to view corsec app.');
    }

}
