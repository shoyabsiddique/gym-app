<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class RequireAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = JWTAuth::user();

        if (!$user || !$user->isAdmin()) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        return $next($request);
    }
}
