<?php

namespace Modules\User\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\User\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps cashiers out of the manager-only pages. Staff without a role keep
 * full access, so shops that existed before roles are not locked out.
 */
class EnsureManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user instanceof User && $user->isCashier(), 403);

        return $next($request);
    }
}
