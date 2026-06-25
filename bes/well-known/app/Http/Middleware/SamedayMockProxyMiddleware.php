<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SamedayMockProxyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // This middleware will no longer rewrite URLs since we're using a proxy
        // Instead, it will just add a debug log for API calls
        $path = $request->path();
        
        if (strpos($path, 'api/sameday/') === 0) {
            Log::info('Sameday API request detected', [
                'path' => $path,
                'method' => $request->method()
            ]);
        }
        
        return $next($request);
    }
}