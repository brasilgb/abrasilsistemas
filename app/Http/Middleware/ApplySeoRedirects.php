<?php

namespace App\Http\Middleware;

use App\Services\Seo\RedirectManager;
use App\Services\Seo\RobotsBuilder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

/**
 * Aplica os redirecionamentos do painel (SEO → Redirecionamentos) antes do roteamento,
 * para que funcionem também em URLs que não existem mais. Só para GET/HEAD públicos;
 * as áreas privadas nunca são redirecionadas e saem com X-Robots-Tag: noindex (o acesso
 * continua dependendo do login; o cabeçalho só evita que apareçam na busca).
 */
class ApplySeoRedirects
{
    public function __construct(private RedirectManager $redirects) {}

    public function handle(Request $request, Closure $next): Response
    {
        $path = RedirectManager::normalizeSource('/'.$request->path());

        if (self::isPrivate($path)) {
            $response = $next($request);
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

            return $response;
        }

        if (! $request->isMethodSafe() || $request->is('api/*') || RedirectManager::isProtected($path) || ! ($rule = $this->redirects->resolve($path))) {
            return $next($request);
        }

        [$target, $code, $id] = $rule;
        $this->redirects->recordHit($id);

        if ($code === 410 || $target === null) {
            throw new GoneHttpException;
        }

        // A query string da origem acompanha o destino, a menos que o destino tenha a sua.
        if ($request->getQueryString() && ! str_contains($target, '?')) {
            $target .= '?'.$request->getQueryString();
        }

        return redirect()->to($target, $code, ['X-Redirect-By' => 'ABrasil SEO']);
    }

    /** Áreas privadas do robots.txt (login, painel, atendimento...). Assets e imagens não entram. */
    public static function isPrivate(string $path): bool
    {
        foreach (RobotsBuilder::PRIVATE_PATHS as $prefix) {
            $prefix = rtrim($prefix, '/');

            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
