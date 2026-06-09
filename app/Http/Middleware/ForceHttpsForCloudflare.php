<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttpsForCloudflare
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $forwardedProto = $request->headers->get('X-Forwarded-Proto');

        if (str_ends_with($host, '.spup.space') && $forwardedProto === 'http') {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
