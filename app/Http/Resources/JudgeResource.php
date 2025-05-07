<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JudgeResource extends JsonResource
{
    use BaseJsonResourceWrapping;
    
    /**
     * Store the resource additional data for use in the wrapper.
     *
     * @return array
     */
    public function resourceAdditional()
    {
        return $this->additional;
    }
    
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'judge_id' => $this->judge_id,
            'court_name' => $this->court_name,
            'specialization' => $this->specialization,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
} 