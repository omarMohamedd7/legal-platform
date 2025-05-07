<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserResource extends BaseResource
{
    /**
     * Get the resource specific data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function getResourceData(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'profile_pic' => $this->profile_pic,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
        
        // Include role-specific profile information
        if ($this->role === 'client' && $this->whenLoaded('client')) {
            $data['profile_info'] = new ClientResource($this->client);
        } elseif ($this->role === 'lawyer' && $this->whenLoaded('lawyer')) {
            $data['profile_info'] = new LawyerResource($this->lawyer);
        } elseif ($this->role === 'judge' && $this->whenLoaded('judge')) {
            $data['profile_info'] = new JudgeResource($this->judge);
        }
        
        return $data;
    }
} 