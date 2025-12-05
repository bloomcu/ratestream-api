<?php
namespace DDD\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyOrganizationAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $request->route('organization');

        // If no user is authenticated, let auth middleware handle it
        if (!$user) {
            return $next($request);
        }

        // If no organization is in the route, allow the request
        if (!$organization) {
            return $next($request);
        }

        // Verify the user belongs to the organization
        if ($user->organization_id !== $organization->id) {
            abort(403, 'You do not have access to this organization.');
        }

        return $next($request);
    }
}