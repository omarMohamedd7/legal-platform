<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\AuthenticationException;

class ApiResponseMiddleware
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
        try {
            $response = $next($request);
            
            // Only transform API responses
            if (!$request->is('api/*')) {
                return $response;
            }
            
            // Skip transformation for responses that are already handled by API resources
            if ($response instanceof Response && 
                $response->headers->has('X-Resource-Response')) {
                return $response;
            }
            
            // Don't transform file downloads or other non-JSON responses
            $contentType = $response->headers->get('Content-Type');
            if ($contentType && strpos($contentType, 'application/json') === false) {
                return $response;
            }
            
            if ($response instanceof Response && $response->getContent()) {
                $content = json_decode($response->getContent(), true);
                
                // Only transform if not already in our format
                if (is_array($content) && !isset($content['success'])) {
                    $statusCode = $response->getStatusCode();
                    $success = $statusCode < 400;
                    
                    $transformedContent = [
                        'success' => $success,
                        'message' => $success ? 'Operation successful' : 'Operation failed',
                        'data' => $content,
                    ];
                    
                    $response->setContent(json_encode($transformedContent));
                }
            }
            
            return $response;
        } catch (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login to continue.',
            ], 401);
        }
    }
} 