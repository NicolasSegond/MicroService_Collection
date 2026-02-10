<?php

namespace App\Tests\Unit\Controller;

use App\Controller\UploadController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

class UploadControllerTest extends TestCase
{
    private const MINIMAL_JPEG = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof'
        . 'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwh'
        . 'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAAR'
        . 'CAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAA'
        . 'AAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMB'
        . 'AAIRAxEAPwCwAB//2Q==';

    private ?string $tempFile = null;

    protected function tearDown(): void
    {
        if ($this->tempFile && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    private function createTempImage(): string
    {
        $this->tempFile = sys_get_temp_dir() . '/test-image-' . uniqid() . '.jpg';
        file_put_contents($this->tempFile, base64_decode(self::MINIMAL_JPEG));
        return $this->tempFile;
    }

    private function createController(string $projectDir = '/var/www/project'): UploadController
    {
        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($projectDir);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($parameterBag);

        $controller = new UploadController();
        $controller->setContainer($container);

        return $controller;
    }

    private function createSlugger(string $input, string $output): SluggerInterface
    {
        $slugger = $this->createStub(SluggerInterface::class);
        $slugger->method('slug')->with($input)->willReturn(new UnicodeString($output));
        return $slugger;
    }

    public function testUploadReturnsErrorIfNoFileProvided(): void
    {
        $controller = new UploadController();
        $controller->setContainer($this->createStub(ContainerInterface::class));

        $request = new Request();
        $slugger = $this->createStub(SluggerInterface::class);

        $response = $controller->upload($request, $slugger);

        $this->assertEquals(400, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Aucun fichier fourni', $content['error']);
    }

    public function testUploadSuccess(): void
    {
        $tempFile = $this->createTempImage();
        $controller = $this->createController();

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('Mon Image.jpg');
        $file->method('getClientOriginalExtension')->willReturn('jpg');
        $file->method('guessExtension')->willReturn('jpg');
        $file->method('getSize')->willReturn(1024);
        $file->method('getPathname')->willReturn($tempFile);
        $file->expects($this->once())
            ->method('move')
            ->with('/var/www/project/public/uploads', $this->stringEndsWith('.jpg'));

        $slugger = $this->createSlugger('Mon Image', 'mon-image');

        $request = new Request();
        $request->files->set('file', $file);

        $response = $controller->upload($request, $slugger);

        $this->assertEquals(200, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertStringContainsString('/uploads/mon-image', $content['url']);
    }

    public function testUploadHandlesException(): void
    {
        $tempFile = $this->createTempImage();
        $controller = $this->createController('/tmp');

        $file = $this->createStub(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('test.jpg');
        $file->method('getClientOriginalExtension')->willReturn('jpg');
        $file->method('guessExtension')->willReturn('jpg');
        $file->method('getSize')->willReturn(1024);
        $file->method('getPathname')->willReturn($tempFile);
        $file->method('move')->willThrowException(new FileException('Erreur permission'));

        $slugger = $this->createSlugger('test', 'test');

        $request = new Request();
        $request->files->set('file', $file);

        $response = $controller->upload($request, $slugger);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Erreur lors de l\'upload', $content['error']);
    }
}
