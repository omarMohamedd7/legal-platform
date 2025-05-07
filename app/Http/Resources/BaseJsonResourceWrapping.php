<?php

namespace App\Http\Resources;

trait BaseJsonResourceWrapping
{
    /**
     * @var bool
     */
    protected $success = true;

    /**
     * @var string|null
     */
    protected $message = null;

    /**
     * Add a message to the resource
     *
     * @param string $message
     * @return $this
     */
    public function withMessage(string $message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Set the success status of the response
     *
     * @param bool $success
     * @return $this
     */
    public function withSuccess(bool $success)
    {
        $this->success = $success;
        return $this;
    }

    /**
     * Customize the response for a request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\JsonResponse  $response
     * @return void
     */
    public function withResponse($request, $response)
    {
        // Get original data from the resource
        $data = json_decode($response->getContent(), true);
        
        // Wrap data with our standard format
        $wrapped = [
            'success' => $this->success,
            'data' => $data,
        ];
        
        // Add message if it exists
        if ($this->message !== null) {
            $wrapped['message'] = $this->message;
        }
        
        // Get additional data using method from JsonResource
        if (method_exists($this, 'resourceAdditional') && is_array($this->resourceAdditional())) {
            $wrapped = array_merge($wrapped, $this->resourceAdditional());
        }
        
        // Set the wrapped data as the response content
        $response->setData($wrapped);
        
        // Add header to identify as a resource response
        $response->header('X-Resource-Response', 'true');
    }
} 