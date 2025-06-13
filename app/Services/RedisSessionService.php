<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RedisSessionService
{
    /**
     * Session lifetime in seconds
     */
    private const SESSION_LIFETIME = 7200; // 2 hours
    
    /**
     * Get active sessions for a user
     *
     * @param int $userId
     * @return array
     */
    public function getUserActiveSessions(int $userId): array
    {
        $sessions = [];
        $pattern = "session:user:{$userId}:*";
        $keys = Redis::keys($pattern);
        
        foreach ($keys as $key) {
            $sessionData = Redis::get($key);
            if ($sessionData) {
                $sessions[] = json_decode($sessionData, true);
            }
        }
        
        return $sessions;
    }
    
    /**
     * Create a new session for a user
     *
     * @param int $userId
     * @param array $userData
     * @param string $userAgent
     * @param string $ip
     * @return array
     */
    public function createSession(int $userId, array $userData, string $userAgent, string $ip): array
    {
        $sessionId = Str::uuid()->toString();
        $sessionKey = "session:user:{$userId}:{$sessionId}";
        
        $session = [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'user_data' => $userData,
            'user_agent' => $userAgent,
            'ip_address' => $ip,
            'created_at' => now()->timestamp,
            'last_activity' => now()->timestamp
        ];
        
        // Store session in Redis with expiry
        Redis::setex(
            $sessionKey,
            self::SESSION_LIFETIME,
            json_encode($session)
        );
        
        // Add to user's active sessions set
        Redis::sadd("user:{$userId}:sessions", $sessionId);
        
        return $session;
    }
    
    /**
     * Get a session by ID
     *
     * @param int $userId
     * @param string $sessionId
     * @return array|null
     */
    public function getSession(int $userId, string $sessionId): ?array
    {
        $sessionKey = "session:user:{$userId}:{$sessionId}";
        $sessionData = Redis::get($sessionKey);
        
        if (!$sessionData) {
            return null;
        }
        
        // Update last activity
        $session = json_decode($sessionData, true);
        $session['last_activity'] = now()->timestamp;
        
        // Refresh session expiry
        Redis::setex(
            $sessionKey,
            self::SESSION_LIFETIME,
            json_encode($session)
        );
        
        return $session;
    }
    
    /**
     * Invalidate a session
     *
     * @param int $userId
     * @param string $sessionId
     * @return bool
     */
    public function invalidateSession(int $userId, string $sessionId): bool
    {
        $sessionKey = "session:user:{$userId}:{$sessionId}";
        
        // Remove from Redis
        $deleted = Redis::del($sessionKey);
        
        // Remove from user's active sessions set
        Redis::srem("user:{$userId}:sessions", $sessionId);
        
        return $deleted > 0;
    }
    
    /**
     * Invalidate all sessions for a user
     *
     * @param int $userId
     * @return int
     */
    public function invalidateAllSessions(int $userId): int
    {
        $pattern = "session:user:{$userId}:*";
        $keys = Redis::keys($pattern);
        $count = 0;
        
        foreach ($keys as $key) {
            $count += Redis::del($key);
        }
        
        // Clear user's active sessions set
        Redis::del("user:{$userId}:sessions");
        
        return $count;
    }
} 