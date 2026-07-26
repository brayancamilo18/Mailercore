<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CabecerasSeguridad
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Panel: Tailwind CDN, Nunito (Google Fonts), mapa (d3/topojson + es-atlas).
        // Scripts de página van en public/js/ (sin inline) para no necesitar unsafe-inline.
        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data:",
            "font-src 'self' https://fonts.gstatic.com data:",
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com",
            "script-src 'self' https://cdn.tailwindcss.com https://unpkg.com",
            "connect-src 'self' https://cdn.jsdelivr.net https://unpkg.com https://code.highcharts.com",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
