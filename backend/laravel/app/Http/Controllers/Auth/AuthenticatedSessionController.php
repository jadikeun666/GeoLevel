<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('projects.index', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     * Menggunakan Inertia::location() agar browser melakukan full page reload
     * ke landing page — bukan SPA navigation yang membiarkan Vue tetap jalan.
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // location() memaksa full HTTP redirect, bukan Inertia visit
        // sehingga welcome.blade.php benar-benar di-render ulang dari server
        return Inertia::location(route('home'));
    }
}