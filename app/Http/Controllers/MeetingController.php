<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Corsec\Models\Meeting;

class MeetingController extends Controller
{
    protected $user;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }

    private function authorizeRead()
    {
        if (!$this->user || !$this->user->can('corsec.read')) {
            abort(403, 'Sorry! You are not allowed to access this page.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeRead();
        return view('corsec::meeting.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('corsec::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show(Meeting $meeting)
    {
        return view('corsec::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Meeting $meeting)
    {
        return view('corsec::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Meeting $meeting) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Meeting $meeting) {}
}
