<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Controller\HelloWorldController;

#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/articles/hello-world',
        controller: HelloWorldController::class,
        security: 'is_granted("ROLE_USER")',
        read: false,
        name: 'hello_world'
    )
])]
class HelloWorld
{
    public function __construct()
    {
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
