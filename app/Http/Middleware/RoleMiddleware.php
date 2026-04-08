<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect('login');
        }

        $userRole = auth()->user()->role;
        
        // $roles contains the arguments passed to the middleware: [ 'kasir', 'admin', 'manajer' ]
        if (!in_array($userRole, $roles)) {
            abort(403, 'Akses Ditolak: Peran Anda tidak memiliki izin untuk halaman ini.');
        }

        return $next($request);
    }
}
