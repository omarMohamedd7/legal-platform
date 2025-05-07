<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ConsultationRequestCollection extends BaseResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = ConsultationRequestResource::class;
} 