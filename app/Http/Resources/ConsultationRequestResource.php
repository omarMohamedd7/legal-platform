<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ConsultationRequestResource extends BaseResource
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
            'client_id' => $this->client_id,
            'lawyer_id' => $this->lawyer_id,
            'price' => $this->price,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
        
        // Include related resources when they are loaded
        if ($this->whenLoaded('client')) {
            $data['client'] = new UserResource($this->client);
        }
        
        if ($this->whenLoaded('lawyer')) {
            $data['lawyer'] = new UserResource($this->lawyer);
        }
        
        if ($this->whenLoaded('payment')) {
            $data['payment'] = new PaymentResource($this->payment);
        }
        
        return $data;
    }
} 