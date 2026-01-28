<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LetterController extends Controller
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
        return view('corsec::letter.index');
    }

    public function outgoing()
    {
        $this->authorizeRead();
        return view('corsec::letter.outgoing.index');
    }
}
