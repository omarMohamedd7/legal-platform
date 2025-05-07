<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PaymentResource extends BaseResource
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
            'payment_id' => $this->payment_id,
            'consultation_request_id' => $this->consultation_request_id,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'transaction_id' => $this->transaction_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
        
        // Include related resources when they are loaded
        if ($this->whenLoaded('consultationRequest')) {
            $data['consultation_request'] = new ConsultationRequestResource($this->consultationRequest);
        }
        
        return $data;
    }
} 