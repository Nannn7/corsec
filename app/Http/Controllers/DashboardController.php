<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Corsec\Services\CorsecPermissionService;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CorsecPermissionService $permissionService
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        if ($this->permissionService->canAccessDashboard($user)) {
            $counts = $this->permissionService->dashboardCounts($user);
            $overview = $this->permissionService->dashboardOverviewData($counts);

            return view('corsec::dashboard', array_merge($counts, $overview));
        }

        abort(403, 'Sorry! You are not allowed to view corsec app.');
    }

}
