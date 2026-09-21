<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user || !$user->role) {
            return response()->json([
                'message' => 'Accès refusé - non authentifié'
            ], 401);
        }

        // super_admin a accès à tout
        if ($user->role->nom === 'super_admin') {
            return $next($request);
        }

        if (!in_array($user->role->nom, $roles)) {
            return response()->json([
                'message' => 'Accès refusé - rôle insuffisant'
            ], 403);
        }

        return $next($request);
    }
}