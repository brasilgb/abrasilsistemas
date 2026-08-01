<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidProspectToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.ab_prospect.token');
        $provided = (string) $request->bearerToken();

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        return $next($request);
    }
}
