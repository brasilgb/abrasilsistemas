<?php

namespace App\Http\Middleware;

use App\Services\VisitOrigin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Registra a origem da visita nas páginas públicas do site (ver VisitOrigin). */
class CaptureVisitOrigin
{
    public function __construct(private VisitOrigin $origin) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Recarregamentos parciais e prefetch do Inertia não são uma página de entrada.
        if ($request->isMethod('GET') && $request->hasSession() && ! $request->prefetch()) {
            $this->origin->capture($request);
        }

        return $next($request);
    }
}
