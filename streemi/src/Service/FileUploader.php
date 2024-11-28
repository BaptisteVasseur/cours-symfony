<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

readonly class FileUploader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')] private string $targetDirectory,
        private SluggerInterface $slugger,
        private LoggerInterface  $logger,
    ) {
    }

    public function upload(UploadedFile $file, $folder = '/uploads'): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        try {
            $file->move($this->targetDirectory . $folder, $fileName);
        } catch (FileException $e) {
            $this->logger->error('An error occurred while uploading the file: '.$e->getMessage());
        }

        return $folder . '/' . $fileName;
    }
}
