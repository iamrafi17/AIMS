<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordAuditLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->user()
            && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && $response->getStatusCode() < 400
            && ! str_ends_with($request->path(), '/logout')
        ) {
            $routeParameters = collect($request->route()?->parameters() ?? []);
            $subject = $routeParameters->first(fn ($value) => is_object($value) && method_exists($value, 'getKey'));

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => strtolower($request->method()).' '.$request->path(),
                'subject_type' => $subject ? class_basename($subject) : 'ApiRequest',
                'subject_id' => $subject?->getKey(),
                'new_values' => collect($request->except([
                    'password',
                    'password_confirmation',
                    'current_password',
                    'attachment',
                    'file',
                    'avatar',
                ]))->map(fn ($value) => is_scalar($value) || $value === null ? $value : '[complex value]')->all(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        }

        return $response;
    }
}
