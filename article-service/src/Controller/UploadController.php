<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_USER')]
class UploadController extends AbstractController
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    #[Route('/api/media/upload', name: 'api_media_upload', methods: ['POST'])]
    public function upload(Request $request, SluggerInterface $slugger): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'Aucun fichier fourni'], 400);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return new JsonResponse(['error' => 'Fichier trop volumineux (max 5 MB)'], 400);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file->getPathname());

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return new JsonResponse(['error' => 'Type de fichier non autorisé. Formats acceptés : JPG, PNG, GIF, WEBP'], 400);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return new JsonResponse(['error' => 'Extension de fichier non autorisée'], 400);
        }

        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false) {
            return new JsonResponse(['error' => 'Le fichier n\'est pas une image valide'], 400);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . bin2hex(random_bytes(8)) . '.' . $extension;

        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads';

        try {
            $file->move($destination, $newFilename);
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'Erreur lors de l\'upload'], 500);
        }

        return new JsonResponse(['url' => '/uploads/' . $newFilename]);
    }
}
