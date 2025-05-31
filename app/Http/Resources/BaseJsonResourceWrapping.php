<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

trait BaseJsonResourceWrapping
{
    /**
     * Success status of the response.
     *
     * @var bool
     */
    protected bool $success = true;

    /**
     * Custom message for the response.
     *
     * @var string|null
     */
    protected ?string $message = null;

    /**
     * Add a message to the resource.
     *
     * @param string $message
     * @return $this
     */
    public function withMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Set the success status of the response.
     *
     * @param bool $success
     * @return $this
     */
    public function withSuccess(bool $success): self
    {
        $this->success = $success;
        return $this;
    }

    /**
     * Customize the response for a request.
     *
     * @param  Request  $request
     * @param  JsonResponse  $response
     * @return void
     */
    public function withResponse($request, $response): void
    {
        $data = $this->getOriginalData($response);
        $wrapped = $this->wrapData($data);
        
        $response->setData($wrapped);
        $response->header('X-Resource-Response', 'true');
    }
    
    /**
     * Get original data from the response.
     *
     * @param  JsonResponse  $response
     * @return array<string, mixed>
     */
    private function getOriginalData(JsonResponse $response): array
    {
        return json_decode($response->getContent(), true);
    }
    
    /**
     * Wrap data with standard format.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function wrapData(array $data): array
    {
        $wrapped = [
            'success' => $this->success,
            'data' => $data,
        ];
        
        if ($this->message !== null) {
            $wrapped['message'] = $this->message;
        }
        
        if ($this->hasAdditionalData()) {
            $wrapped = array_merge($wrapped, $this->resourceAdditional());
        }
        
        return $wrapped;
    }
    
    /**
     * Check if resource has additional data.
     *
     * @return bool
     */
    private function hasAdditionalData(): bool
    {
        return method_exists($this, 'resourceAdditional') && is_array($this->resourceAdditional());
    }
} 