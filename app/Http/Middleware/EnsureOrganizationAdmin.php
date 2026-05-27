<?php

namespace DDD\Http\Middleware;

use Closure;
use DDD\Domain\Base\Users\Enums\RoleEnum;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $role = $request->user()?->role;

        if ($role === RoleEnum::Admin || $role === RoleEnum::SuperAdmin) {
            return $next($request);
        }

        abort(403, 'Only organization admins can manage this resource.');
    }
}
