<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

class Uploader
{

    public function __construct(
        private string $profileFolder,
        private string $profilePublicPath,
        private Filesystem $fs
    ) {}

    public function uploadProfileImage(UploadedFile $picture, ?string $oldPicturePath = null)
    {

        $folder = $this->profileFolder;
        $ext = $picture->guessExtension() ?? 'bin';
        $filename = pathinfo($picture->getClientOriginalName(), PATHINFO_FILENAME) . '-' . bin2hex(random_bytes(12)) . $ext;

        if ($oldPicturePath) {
            // On utilise basename pour ne pas récupérer 2 fois le /profiles car $oldPicturePath = /profiles/nomDuFichier.extension
            $this->fs->remove($folder . '/' . pathinfo($oldPicturePath, PATHINFO_BASENAME));
        }
        $picture->move($folder, $filename);

        return $this->profilePublicPath . '/' . $filename;
    }
}
