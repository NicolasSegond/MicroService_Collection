<?php
// src/Entity/HelloWorld.php
    namespace App\Entity;

    use ApiPlatform\Metadata\ApiResource;
    use ApiPlatform\Metadata\Get;
    use App\Controller\HelloWorldController;

    #[ApiResource(operations: [
      new Get(
        uriTemplate: 'articles/hello',
        controller: HelloWorldController::class,
        output: false,
        read: false,    // IMPORTANT: ne passe pas par le provider/Doctrine
        name: 'hello_world'   // IMPORTANT: on renvoie déjà une Response (JsonResponse)
      )
    ])]
    class HelloWorld
    {
        public function __construct(
          private string $message = 'hello-world'
        ) {}

        public function getMessage(): string
        {
            return $this->message;
        }
    }
