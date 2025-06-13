<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RedisQueueService
{
    /**
     * Default job timeout in seconds
     */
    private const DEFAULT_TIMEOUT = 3600; // 1 hour
    
    /**
     * Add a job to the queue
     *
     * @param string $queue
     * @param string $jobType
     * @param array $payload
     * @param int $delay
     * @return string Job ID
     */
    public function enqueue(string $queue, string $jobType, array $payload, int $delay = 0): string
    {
        $jobId = Str::uuid()->toString();
        $timestamp = now()->timestamp;
        
        $job = [
            'id' => $jobId,
            'type' => $jobType,
            'payload' => $payload,
            'queue' => $queue,
            'attempts' => 0,
            'created_at' => $timestamp,
            'available_at' => $timestamp + $delay,
            'reserved_at' => null
        ];
        
        // Store job details
        $jobKey = "job:{$jobId}";
        Redis::set($jobKey, json_encode($job));
        
        // Add to queue with delay if specified
        if ($delay > 0) {
            Redis::zadd("queue:{$queue}:delayed", $timestamp + $delay, $jobId);
        } else {
            Redis::rpush("queue:{$queue}", $jobId);
        }
        
        return $jobId;
    }
    
    /**
     * Process delayed jobs and move them to their respective queues
     *
     * @return int Number of jobs moved
     */
    public function processDelayedJobs(): int
    {
        $count = 0;
        $now = now()->timestamp;
        
        // Get all queue names with delayed jobs
        $queueKeys = Redis::keys("queue:*:delayed");
        
        foreach ($queueKeys as $queueKey) {
            // Extract queue name from key
            $queueName = str_replace(['queue:', ':delayed'], '', $queueKey);
            
            // Get jobs that are ready to be processed
            $jobIds = Redis::zrangebyscore($queueKey, 0, $now);
            
            foreach ($jobIds as $jobId) {
                // Move job to the queue
                Redis::rpush("queue:{$queueName}", $jobId);
                
                // Remove from delayed set
                Redis::zrem($queueKey, $jobId);
                
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Get a job from the queue
     *
     * @param string $queue
     * @param int $timeout
     * @return array|null
     */
    public function dequeue(string $queue, int $timeout = self::DEFAULT_TIMEOUT): ?array
    {
        // Get job ID from queue
        $jobId = Redis::lpop("queue:{$queue}");
        
        if (!$jobId) {
            return null;
        }
        
        // Get job details
        $jobData = Redis::get("job:{$jobId}");
        
        if (!$jobData) {
            return null;
        }
        
        $job = json_decode($jobData, true);
        
        // Update job status
        $job['attempts']++;
        $job['reserved_at'] = now()->timestamp;
        
        // Store updated job
        Redis::setex("job:{$jobId}", $timeout, json_encode($job));
        
        return $job;
    }
    
    /**
     * Mark a job as completed
     *
     * @param string $jobId
     * @return bool
     */
    public function markAsCompleted(string $jobId): bool
    {
        // Get job details
        $jobData = Redis::get("job:{$jobId}");
        
        if (!$jobData) {
            return false;
        }
        
        // Delete job
        Redis::del("job:{$jobId}");
        
        return true;
    }
    
    /**
     * Mark a job as failed
     *
     * @param string $jobId
     * @param string $error
     * @return bool
     */
    public function markAsFailed(string $jobId, string $error): bool
    {
        // Get job details
        $jobData = Redis::get("job:{$jobId}");
        
        if (!$jobData) {
            return false;
        }
        
        $job = json_decode($jobData, true);
        
        // Add to failed jobs
        $job['failed_at'] = now()->timestamp;
        $job['error'] = $error;
        
        // Store in failed jobs
        Redis::set("failed_job:{$jobId}", json_encode($job));
        
        // Delete original job
        Redis::del("job:{$jobId}");
        
        return true;
    }
    
    /**
     * Retry a failed job
     *
     * @param string $jobId
     * @return bool
     */
    public function retryFailedJob(string $jobId): bool
    {
        // Get failed job details
        $jobData = Redis::get("failed_job:{$jobId}");
        
        if (!$jobData) {
            return false;
        }
        
        $job = json_decode($jobData, true);
        
        // Reset job status
        $job['attempts'] = 0;
        $job['reserved_at'] = null;
        $job['failed_at'] = null;
        $job['error'] = null;
        
        // Add back to queue
        $this->enqueue($job['queue'], $job['type'], $job['payload']);
        
        // Delete failed job
        Redis::del("failed_job:{$jobId}");
        
        return true;
    }
    
    /**
     * Get all failed jobs
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getFailedJobs(int $limit = 100, int $offset = 0): array
    {
        $failedJobs = [];
        $keys = Redis::keys("failed_job:*");
        
        // Apply pagination
        $paginatedKeys = array_slice($keys, $offset, $limit);
        
        foreach ($paginatedKeys as $key) {
            $jobData = Redis::get($key);
            if ($jobData) {
                $failedJobs[] = json_decode($jobData, true);
            }
        }
        
        return [
            'jobs' => $failedJobs,
            'total' => count($keys)
        ];
    }
} 