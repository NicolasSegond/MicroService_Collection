<?php

namespace App\Tests\Unit\Controller;

use App\Controller\UploadController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

class UploadControllerTest extends TestCase
{
    public function testUploadReturnsErrorIfNoFileProvided(): void
    {
        // 1. Préparation
        $controller = new UploadController();
        // STUB (pas d'attente dessus)
        $controller->setContainer($this->createStub(ContainerInterface::class));

        // STUB
        $slugger = $this->createStub(SluggerInterface::class);
        $request = new Request();

        // 2. Exécution
        $response = $controller->upload($request, $slugger);

        // 3. Assertions
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Aucun fichier fourni', $content['error']);
    }

    public function testUploadSuccess(): void
    {
        // 1. STUBS pour le Container et ParameterBag
        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')
            ->with('kernel.project_dir')
            ->willReturn('/var/www/project');

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->with('parameter_bag')->willReturn(true);
        $container->method('get')->with('parameter_bag')->willReturn($parameterBag);

        $controller = new UploadController();
        $controller->setContainer($container);

        // 2. MOCK pour le Fichier (car on utilise expects($this->once()))
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('Mon Image.jpg');
        $file->method('guessExtension')->willReturn('jpg');

        // Ici on garde createMock car on VERIFIE l'appel à move()
        $file->expects($this->once())
            ->method('move')
            ->with(
                '/var/www/project/public/uploads',
                $this->stringEndsWith('.jpg')
            );

        // STUB pour le Slugger
        $slugger = $this->createStub(SluggerInterface::class);
        $slugger->method('slug')
            ->with('Mon Image')
            ->willReturn(new UnicodeString('mon-image'));

        // 3. Requête avec le fichier
        $request = new Request();
        $request->files->set('file', $file);

        // 4. Exécution
        $response = $controller->upload($request, $slugger);

        // 5. Assertions
        $this->assertEquals(200, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);

        $this->assertStringContainsString('/uploads/mon-image', $content['url']);
    }

    public function testUploadHandlesException(): void
    {
        // 1. Configuration des STUBS
        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn('/tmp');

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($parameterBag);

        $controller = new UploadController();
        $controller->setContainer($container);

        // 2. STUB pour le fichier qui échoue (on change createMock en createStub car on ne met pas d'expectation stricte, juste un comportement)
        $file = $this->createStub(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('test.jpg');
        $file->method('guessExtension')->willReturn('jpg');
        $file->method('move')->willThrowException(new FileException('Erreur permission'));

        $request = new Request();
        $request->files->set('file', $file);

        $slugger = $this->createStub(SluggerInterface::class);
        $slugger->method('slug')->willReturn(new UnicodeString('test'));

        // 3. Exécution
        $response = $controller->upload($request, $slugger);

        // 4. Assertions
        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Erreur lors de l\'upload', $content['error']);
    }
}
