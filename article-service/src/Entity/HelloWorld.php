<?php
// src/Entity/HelloWorld.php
    namespace App\Entity;

    use ApiPlatform\Metadata\ApiResource;
    use ApiPlatform\Metadata\Get;
    use App\Controller\HelloWorldController;

    #[ApiResource(operations: [
      new Get(
          uriTemplate: 'articles/hello-world',
          controller: HelloWorldController::class,
          security: 'is_granted("ROLE_USER")',
          output: false,
          read: false,
          name: 'hello_world'
      )
    ])]
    class HelloWorld
    {
        public function __construct(
          private readonly string $message = 'hello-world'
        ) {}

        public function getMessage(): string
        {
            return $this->message;
        }
    }
