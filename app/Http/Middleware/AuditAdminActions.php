<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminActions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->user() && $request->route()) {
            AuditLog::create([
                'actor_type' => get_class($request->user()),
                'actor_id' => $request->user()->getKey(),
                'action' => $request->route()->getName() ?: $request->method().' '.$request->path(),
                'route' => $request->path(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'metadata' => ['status' => $response->getStatusCode()],
            ]);
        }
        return $response;
    }
}
