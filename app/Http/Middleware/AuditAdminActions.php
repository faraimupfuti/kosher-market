<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminActions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $path = ltrim($request->path(), '/');
        $actor = Auth::guard('web')->user();
        if (str_starts_with($path, 'admin/') && $actor && $request->route()) {
            AuditLog::create([
                'actor_type' => get_class($actor),
                'actor_id' => $actor->getKey(),
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
