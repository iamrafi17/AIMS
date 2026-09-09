<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffSecuritySetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->requiresStaffOnboarding()) {
            return response()->json([
                'message' => 'Complete your first-login account setup before accessing the staff portal.',
                'onboarding_step' => $request->user()->onboardingStep(),
                'requires_security_setup' => (bool) $request->user()->requires_security_setup,
            ], 403);
        }

        return $next($request);
    }
}
