<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserCollection extends BaseResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = UserResource::class;
} 