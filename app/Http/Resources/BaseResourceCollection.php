<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BaseResourceCollection extends ResourceCollection
{
    /**
     * Custom message for the response.
     *
     * @var string|null
     */
    protected ?string $message = null;
    
    /**
     * Success status for the response.
     *
     * @var bool
     */
    protected bool $success = true;

    /**
     * Set a custom message for the response.
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
     * Set success status for the response.
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
     * Transform the resource collection into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->collection,
            'meta' => $this->getPaginationMeta(),
        ];
    }
    
    /**
     * Get pagination metadata.
     *
     * @return array<string, mixed>
     */
    protected function getPaginationMeta(): array
    {
        return [
            'pagination' => [
                'total' => $this->resource->total(),
                'count' => $this->resource->count(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'total_pages' => $this->resource->lastPage(),
                'has_more_pages' => $this->resource->hasMorePages(),
            ],
        ];
    }
} 