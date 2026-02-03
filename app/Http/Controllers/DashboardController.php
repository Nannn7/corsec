<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\OutgoingLetter;
use Modules\Corsec\Models\WorkProgram;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::user()->hasRole(['maker', 'checker', 'approver', 'administrator'])) {
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
                        ->orWhereNotIn('status', ['done', 'completed', 'sent', 'verified']);
                })
                ->count();

            $meetingOpen = Meeting::query()
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereNotIn('status', ['done', 'completed', 'closed', 'verified']);
                })
                ->count();

            $workplanOpen = WorkProgram::query()
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereNotIn('status', ['done', 'returned', 'rejected']);
                })
                ->count();

            return view('corsec::dashboard', compact('incomingOpen', 'outgoingOpen', 'meetingOpen', 'workplanOpen'));
        }

        abort(403, 'Sorry! You are not allowed to view corsec app.');
    }
}
