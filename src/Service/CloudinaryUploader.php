<?php

namespace App\Service;

use Cloudinary\Cloudinary;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CloudinaryUploader
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        $cloudinaryUrl = $_ENV['CLOUDINARY_URL'] ?? $_SERVER['CLOUDINARY_URL'] ?? null;

        if (!$cloudinaryUrl) {
            throw new \RuntimeException('CLOUDINARY_URL is not configured.');
        }

        $this->cloudinary = new Cloudinary($cloudinaryUrl);
    }

    /**
     * @return array{url: string, publicId: string}
     */
    public function uploadPropertyPhoto(UploadedFile $file): array
    {
        $result = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder' => 'agentboost/properties',
                'resource_type' => 'image',
                'overwrite' => false,
            ]
        );

        $url = $result['secure_url'] ?? null;
        $publicId = $result['public_id'] ?? null;

        if (!is_string($url) || !is_string($publicId)) {
            throw new \RuntimeException('Cloudinary upload failed.');
        }

        return [
            'url' => $url,
            'publicId' => $publicId,
        ];
    }

    public function deletePropertyPhoto(string $publicId): void
    {
        $this->cloudinary->uploadApi()->destroy($publicId, [
            'resource_type' => 'image',
        ]);
    }
}
