<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class UploadController extends AbstractController
{
    #[Route('/api/media/upload', name: 'api_media_upload', methods: ['POST'])]
    public function upload(Request $request, SluggerInterface $slugger): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            return new JsonResponse(['error' => 'Aucun fichier fourni'], 400);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        $destination = $this->getParameter('kernel.project_dir').'/public/uploads';

        try {
            $file->move($destination, $newFilename);
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'Erreur lors de l\'upload : ' . $e->getMessage()], 500);
        }

        // Return the URL of the uploaded file (assuming it's served from /uploads/)
        return new JsonResponse([
            'url' => '/uploads/'.$newFilename
        ]);
    }
}
