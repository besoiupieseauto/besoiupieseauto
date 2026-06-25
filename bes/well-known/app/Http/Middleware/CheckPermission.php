<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permission)
    {
        if (!Auth::check()) {
            return redirect()->route('login'); // not logged in
        }
		
        if (Auth::user()->rol === 'manager') {
            return $next($request);
        }

        if (!Auth::user()->hasPermission($permission)) {
            abort(403, 'Nu ai acces la această pagină.');
        }

        return $next($request);
    }
}