<?php

declare(strict_types=1);

namespace App\Controller\Other;

use App\Entity\Upload;
use App\Form\UploadType;
use App\Repository\UploadRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UploadController extends AbstractController
{
    #[Route('/upload', name: 'page_upload')]
    public function index(
        UploadRepository $uploadRepository,
    ): Response
    {
        return $this->render('other/upload.html.twig', [
            'uploads' => $uploadRepository->findAll(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/upload', name: 'page_upload_api')]
    public function uploadApi(
        Request $request,
        FileUploader $fileUploader,
        EntityManagerInterface $entityManager
    ): Response
    {
        /** @var UploadedFile[] $files */
        $files = $request->files->all()['files'];

        foreach ($files as $file) {
            $fileName = $fileUploader->upload($file);
            $upload = new Upload();
            $upload->setUploadedBy($this->getUser());
            $upload->setUrl($fileName);
            $entityManager->persist($upload);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Upload successful!',
        ]);

        return $this->json([
            'message' => 'Upload failed!',
        ], Response::HTTP_BAD_REQUEST);
    }
}
