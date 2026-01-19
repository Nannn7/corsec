<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Corsec\Models\ApprovalRequest;
use Modules\Corsec\Services\ApprovalRequestService;

class ApproverController extends Controller
{
    public function __construct(private readonly ApprovalRequestService $approvalService)
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $requests = ApprovalRequest::query()
            ->latest()
            ->get();

        return view('corsec::approval.index', compact('requests'));
    }

    public function approve(ApprovalRequest $approvalRequest)
    {
        $this->approvalService->approve($approvalRequest, Auth::user());

        return back()->with('success', 'Approval berhasil diproses.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest)
    {
        $request->validate([
            'review_notes' => ['nullable', 'string'],
        ]);

        $this->approvalService->reject($approvalRequest, Auth::user(), $request->review_notes);

        return back()->with('success', 'Approval berhasil ditolak.');
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
    public function show($id)
    {
        return view('corsec::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('corsec::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
