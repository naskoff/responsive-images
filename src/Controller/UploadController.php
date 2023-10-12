<?php

namespace App\Controller;

use App\Service\ImageResponsiveSize;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Format;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UploadController extends AbstractController
{
    private const BASE_URL = 'http://localhost/develop/github/responsive-images/public/uploads/default/';

    private readonly Imagine $imagine;

    public function __construct(
        private readonly FilesystemOperator $defaultStorage
    )
    {
        $this->imagine = new Imagine();
    }

    #[Route('/upload', name: 'app_upload')]
    public function index(Request $request): JsonResponse
    {
        if ($request->files->has('image')) {
            /** @var UploadedFile $image */
            $image = $request->files->get('image');

            $responses = $this->upload($image);
        }

        return $this->json($responses ?? []);
    }

    private function upload(UploadedFile $file): array
    {
        ini_set('memory_limit', '256M');

        try {
            $extension = '.'.$file->guessExtension();
            $filename = md5($file->getClientOriginalName().microtime());
            foreach (ImageResponsiveSize::cases() as $size) {
                $resolution = $size->resolution();
                if (null === $resolution) {
                    $this->defaultStorage->write($filename.$extension, $file->getContent());

                    if ($this->defaultStorage instanceof PublicUrlGenerator) {
                        $responses[$size->value] = $this->defaultStorage->publicUrl($filename, []);
                    } else {
                        $responses[$size->value] = self::BASE_URL.$filename.$extension;
                    }
                } else {
                    [$width, $height] = $resolution;
                    $location = $filename.'-'.$size->value.'.webp';
                    $content = $this->imagine->open($file->getRealPath())
                        ->resize(new Box($width, $height))
                        ->get(Format::ID_WEBP);
                    $this->defaultStorage->write($location, $content);

                    if ($this->defaultStorage instanceof PublicUrlGenerator) {
                        $responses[$size->value] = $this->defaultStorage->publicUrl($filename, []);
                    } else {
                        $responses[$size->value] = self::BASE_URL.$location;
                    }
                }
            }
        } catch (FilesystemException $e) {
            $responses[] = 'Error occurred - '.$e->getMessage();
        }

        return $responses ?? [];
    }
}
