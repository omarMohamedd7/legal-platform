<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LawyerResource extends JsonResource
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
            'lawyer_id' => $this->lawyer_id,
            'phone' => $this->phone,
            'specialization' => $this->specialization,
            'city' => $this->city,
            'consult_fee' => $this->consult_fee,
            'bio' => $this->bio,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
} 