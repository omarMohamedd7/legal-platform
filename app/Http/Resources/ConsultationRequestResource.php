<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ConsultationRequestResource extends BaseResource
{
    /**
     * Get the resource specific data.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function getResourceData(Request $request): array
    {
        $data = $this->getBasicData();
        
        $this->addRelationshipData($data);
        
        return $data;
    }
    
    /**
     * Get the basic consultation request data.
     *
     * @return array<string, mixed>
     */
    private function getBasicData(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'lawyer_id' => $this->lawyer_id,
            'price' => $this->price,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Add relationship data to the resource.
     *
     * @param array<string, mixed> $data
     * @return void
     */
    private function addRelationshipData(array &$data): void
    {
        $this->addClientData($data);
        $this->addLawyerData($data);
        $this->addPaymentData($data);
    }
    
    /**
     * Add client data to the resource if loaded.
     *
     * @param array<string, mixed> $data
     * @return void
     */
    private function addClientData(array &$data): void
    {
        if ($this->whenLoaded('client')) {
            // Get the client's user data
            $user = $this->client->user;
            
            if ($user) {
                $data['client'] = [
                    'id' => $this->client->client_id,
                    'user_id' => $this->client->user_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $this->client->phone_number ?? null,
                    'city' => $this->client->city ?? null,
                ];
            }
        }
    }
    
    /**
     * Add lawyer data to the resource if loaded.
     *
     * @param array<string, mixed> $data
     * @return void
     */
    private function addLawyerData(array &$data): void
    {
        if ($this->whenLoaded('lawyer')) {
            // Get the lawyer's user data
            $user = $this->lawyer->user;
            
            if ($user) {
                $data['lawyer'] = [
                    'id' => $this->lawyer->lawyer_id,
                    'user_id' => $this->lawyer->user_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $this->lawyer->phone_number ?? null,
                    'specialization' => $this->lawyer->specialization ?? null,
                    'city' => $this->lawyer->city ?? null,
                    'consult_fee' => $this->lawyer->consult_fee ?? null,
                ];
            }
        }
    }
    
    /**
     * Add payment data to the resource if loaded.
     *
     * @param array<string, mixed> $data
     * @return void
     */
    private function addPaymentData(array &$data): void
    {
        if ($this->whenLoaded('payment')) {
            $data['payment'] = new PaymentResource($this->payment);
        }
    }
} 