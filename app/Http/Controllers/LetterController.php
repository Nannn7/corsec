<?php

namespace Modules\Corsec\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Modules\Usermanagement\Models\User;

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
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
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
        $canCreate = $this->canCreateOutgoing($this->user);
        return view('corsec::letter.outgoing.index', compact('canCreate'));
    }

    private function canCreateOutgoing(?User $user): bool
    {
        if (!$user || !$user->can('corsec.create')) {
            return false;
        }

        return !$this->isCorpSecretaryDirectorate($user);
    }

    private function isCorpSecretaryDirectorate(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $eoDirectorateCode = (string) config('corsec.eo_corp_affair_directorate_code', '');

        $user->loadMissing('directorate');
        $directorateCode = $user->directorate?->code;
        $directorateName = $user->directorate?->name;

        if ($directorateCode && $eoDirectorateCode !== '' && $directorateCode === $eoDirectorateCode) {
            return true;
        }

        if ($directorateName) {
            $normalized = Str::lower($directorateName);
            return Str::contains($normalized, 'corporate secretary');
        }

        return false;
    }
}
