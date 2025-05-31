<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    use BaseJsonResourceWrapping;

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
     * Store the resource additional data for use in the wrapper.
     *
     * @return array<string, mixed>
     */
    public function resourceAdditional(): array
    {
        return $this->additional;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->getResourceData($request);
    }

    /**
     * Get the resource specific data.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    abstract public function getResourceData(Request $request): array;
} 