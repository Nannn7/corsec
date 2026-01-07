<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::user()->hasRole(['maker', 'checker', 'approver', 'administrator'])) {
            return view('corsec::dashboard');
        }

        abort(403, 'Sorry! You are not allowed to view corsec app.');
    }
}
