<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Models\LegalCase;

class CacheService
{
    /**
     * Cache time in seconds
     */
    private const CACHE_TIME = 3600; // 1 hour
    
    /**
     * Get case data from cache or database
     *
     * @param int $caseId
     * @return LegalCase|null
     */
    public function getCase(int $caseId): ?LegalCase
    {
        $cacheKey = "case:{$caseId}";
        
        return Cache::remember($cacheKey, self::CACHE_TIME, function () use ($caseId) {
            return LegalCase::with(['createdBy', 'assignedLawyer', 'caseRequest', 'publishedCase'])
                ->find($caseId);
        });
    }
    
    /**
     * Invalidate case cache
     *
     * @param int $caseId
     * @return void
     */
    public function invalidateCaseCache(int $caseId): void
    {
        Cache::forget("case:{$caseId}");
    }
    
    /**
     * Cache multiple cases by type
     *
     * @param string $caseType
     * @return array
     */
    public function getCasesByType(string $caseType): array
    {
        $cacheKey = "cases:type:{$caseType}";
        
        return Cache::remember($cacheKey, self::CACHE_TIME, function () use ($caseType) {
            return LegalCase::where('case_type', $caseType)
                ->with(['createdBy', 'assignedLawyer'])
                ->get()
                ->toArray();
        });
    }
    
    /**
     * Get lawyer's active cases
     *
     * @param int $lawyerId
     * @return array
     */
    public function getLawyerActiveCases(int $lawyerId): array
    {
        $cacheKey = "lawyer:{$lawyerId}:active_cases";
        
        return Cache::remember($cacheKey, self::CACHE_TIME, function () use ($lawyerId) {
            return LegalCase::where('assigned_lawyer_id', $lawyerId)
                ->where('status', 'active')
                ->with(['createdBy'])
                ->get()
                ->toArray();
        });
    }
} 