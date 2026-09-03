<?php

namespace App\Http\Controllers;

use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    use RedirectsToCurrentTeam;

    /**
     * Send the root URL straight to the current team's dashboard.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->to(
            $this->redirectPathForCurrentTeam($request, config('fortify.home'))
        );
    }
}
