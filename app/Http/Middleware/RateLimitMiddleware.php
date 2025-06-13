<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\RateLimiterService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * The rate limiter service
     *
     * @var RateLimiterService
     */
    protected RateLimiterService $rateLimiter;
    
    /**
     * Create a new middleware instance
     *
     * @param RateLimiterService $rateLimiter
     */
    public function __construct(RateLimiterService $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }
    
    /**
     * Handle an incoming request
     *
     * @param Request $request
     * @param Closure $next
     * @param string $endpoint
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $endpoint = 'default'): Response
    {
        // Use user ID if authenticated, otherwise use IP address
        $identifier = Auth::id() ?: $request->ip();
        
        // Check rate limit
        $result = $this->rateLimiter->limitApiEndpoint($endpoint, $identifier);
        
        // If rate limited, return 429 Too Many Requests
        if ($result['limited']) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $result['retry_after']
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $result['remaining'] + 1,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => $result['reset'],
                'Retry-After' => $result['retry_after']
            ]);
        }
        
        // Add rate limit headers to response
        $response = $next($request);
        
        $response->headers->add([
            'X-RateLimit-Limit' => $result['remaining'] + 1,
            'X-RateLimit-Remaining' => $result['remaining'],
            'X-RateLimit-Reset' => $result['reset']
        ]);
        
        return $response;
    }
} 