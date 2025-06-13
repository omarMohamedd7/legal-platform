<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class RateLimiterService
{
    /**
     * Default rate limit in requests per minute
     */
    private const DEFAULT_RATE_LIMIT = 60;
    
    /**
     * Default rate limit window in seconds
     */
    private const DEFAULT_WINDOW = 60;
    
    /**
     * Check if request is rate limited
     *
     * @param string $key Unique identifier for the rate limit (e.g. IP, user ID)
     * @param int $limit Maximum number of requests allowed in the window
     * @param int $window Time window in seconds
     * @return array
     */
    public function check(string $key, int $limit = self::DEFAULT_RATE_LIMIT, int $window = self::DEFAULT_WINDOW): array
    {
        $redisKey = "rate_limit:{$key}";
        $currentTime = time();
        $clearBefore = $currentTime - $window;
        
        // Remove expired timestamps
        Redis::zremrangebyscore($redisKey, 0, $clearBefore);
        
        // Count requests in current window
        $requestCount = Redis::zcard($redisKey);
        
        // Check if limit exceeded
        if ($requestCount >= $limit) {
            $oldestTimestamp = Redis::zrange($redisKey, 0, 0, 'WITHSCORES');
            $resetTime = $oldestTimestamp ? reset($oldestTimestamp) + $window : $currentTime + $window;
            
            return [
                'limited' => true,
                'remaining' => 0,
                'reset' => $resetTime,
                'retry_after' => $resetTime - $currentTime
            ];
        }
        
        // Add current request timestamp
        Redis::zadd($redisKey, $currentTime, $currentTime . ':' . uniqid());
        
        // Set expiry on the sorted set
        Redis::expire($redisKey, $window * 2);
        
        return [
            'limited' => false,
            'remaining' => $limit - $requestCount - 1,
            'reset' => $currentTime + $window,
            'retry_after' => 0
        ];
    }
    
    /**
     * Apply rate limiting for specific API endpoints
     *
     * @param string $endpoint API endpoint name
     * @param string $identifier User identifier (IP or user ID)
     * @return array
     */
    public function limitApiEndpoint(string $endpoint, string $identifier): array
    {
        $limits = $this->getEndpointLimits();
        $key = "{$endpoint}:{$identifier}";
        
        if (isset($limits[$endpoint])) {
            return $this->check($key, $limits[$endpoint]['limit'], $limits[$endpoint]['window']);
        }
        
        return $this->check($key);
    }
    
    /**
     * Get rate limits for specific endpoints
     *
     * @return array
     */
    private function getEndpointLimits(): array
    {
        return [
            'login' => [
                'limit' => 5,
                'window' => 60
            ],
            'register' => [
                'limit' => 3,
                'window' => 300
            ],
            'consultation_request' => [
                'limit' => 10,
                'window' => 60
            ],
            'chat_message' => [
                'limit' => 30,
                'window' => 60
            ],
            'case_create' => [
                'limit' => 5,
                'window' => 60
            ]
        ];
    }
} 