<?php

namespace App\Http\Middleware;

use App\Models\Queue;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PruneExpiredQueues
{
    public function handle(Request $request, Closure $next): Response
    {
        // Also runs with public live polling, even without a running scheduler.
        if ($request->isMethod('GET')) {
            Queue::expired()->delete();
        }

        return $next($request);
    }
}
