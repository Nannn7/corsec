<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Corsec\Models\IncomingLetter;
use Modules\Corsec\Models\Meeting;
use Modules\Corsec\Models\OutgoingLetter;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::user()->hasRole(['maker', 'checker', 'approver', 'administrator'])) {
            $incomingOpen = IncomingLetter::query()
                ->whereNotIn('status', ['verified'])
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

            return view('corsec::dashboard', compact('incomingOpen', 'outgoingOpen', 'meetingOpen'));
        }

        abort(403, 'Sorry! You are not allowed to view corsec app.');
    }
}
