<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Administrator
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isAdministrator(), 403, 'Akses hanya untuk Administrator.');
        return $next($request);
    }
}
