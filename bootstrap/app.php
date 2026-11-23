<?php

use App\Http\Middleware\ApplySeoRedirects;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureValidProspectToken;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Redirecionamentos do painel de SEO: antes do roteamento, para valer em URLs antigas.
        $middleware->append(ApplySeoRedirects::class);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'prospect.token' => EnsureValidProspectToken::class,
        ]);
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EnsureValidProspectToken::class,
        );
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->validateCsrfTokens(except: ['webhooks/mercadopago/ebooks/*']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (NotFoundHttpException $e, Request $request): ?SymfonyResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return Inertia::render('errors/404')
                ->toResponse($request)
                ->setStatusCode(404);
        });

        // Sessão expirada com o formulário do site aberto: o visitante recebe o aviso no próprio formulário,
        // sem perder o que escreveu, e o próximo envio já sai com o token novo.
        $exceptions->render(function (HttpException $e, Request $request): ?SymfonyResponse {
            if ($e->getStatusCode() !== 419 || ! $request->is('contato')) {
                return null;
            }

            return back()->withInput($request->except(['website_hp', '_token']))->withErrors([
                'form' => 'A página ficou aberta por muito tempo. Confira os dados e envie novamente.',
            ]);
        });

        // Página removida de propósito (redirecionamento 410 no painel de SEO).
        $exceptions->render(function (GoneHttpException $e, Request $request): ?SymfonyResponse {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return Inertia::render('errors/404', ['gone' => true])
                ->toResponse($request)
                ->setStatusCode(410);
        });
    })->create();
