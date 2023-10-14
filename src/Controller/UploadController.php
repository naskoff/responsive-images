<?php

namespace App\Controller;

use App\Service\ImageResponsiveSize;
use Imagick;
use Imagine\Gd\Font;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Format;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Imagine\Image\Point\Center;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use function Symfony\Component\String\u;

class UploadController extends AbstractController
{
    private const BASE_URL = 'http://localhost/develop/github/responsive-images/public/uploads/default/';

    private readonly Imagine $imagine;

    public function __construct(
        private readonly string $storageDirectory,
        private readonly FilesystemOperator $defaultStorage
    )
    {
        $this->imagine = new Imagine();
    }

    #[Route('/upload', name: 'app_upload')]
    public function index(Request $request): Response
    {
        if ($request->files->has('image')) {
            /** @var UploadedFile $image */
            $image = $request->files->get('image');

            $responses = $this->upload($image);

            return $this->json($responses);
        }

        $data = $request->getPayload()->all();

        $defaults = [
            'background_color' => '#B724B0', # #e2013b #111111
            'text_color' => '#FFF',
            'width' => 800,
            'height' => 2000,
            'font_file' => $this->storageDirectory.'fonts/OpenSans-Semibold.ttf',
            'font_size' => 30
        ];

        $settings = array_merge($defaults, $data, [
            'text' => u($data['text'])->wordwrap(30, PHP_EOL, true)->toString()
        ]);

        $response = $this->draw($settings);

        return new Response($response, 200, [
            'Content-Type' => 'image/webp'
        ]);
    }

    private function draw(array $settings): string
    {
        //create required instances...
        $Imagine = new Imagine();
        $Palette = new RGB();

        //...some other code...
        $TextColor = $Palette->color($settings['text_color'], 100);
        //create Text Object with font-family, font-size and color from settings
        $TextFont = new Font( $settings['font_file'] , $settings['font_size'],  $TextColor);
        //create a Box (dimensions) based on font and given string...
        $TextBox = $TextFont->box( $settings['text'] );

        //Background color of the upcoming image based on settings passed by user...
        $BackgroundColor = $Palette->color($settings['background_color'], 100);
        //create an ImageBox for upcoming image based on given width and height
        $ImageBox = new Box($settings['width'], $TextBox->getHeight() + 120);
        //get center X|Y coordinates for ImageBox
        $ImageCenterPosition = new Center($ImageBox);

        //get center X|Y coordinates for the box containing the text
        $TextCenterPosition = new Center( $TextBox );
        //calculate center X|Y coordinates from ImageBox center coordinates and FontBox center coordinates
        $CenteredTextPosition = new Point(
            $ImageCenterPosition->getX() - $TextCenterPosition->getX(),
            $ImageCenterPosition->getY() - $TextCenterPosition->getY()
        );
        //create an image with ImageBox dimensions and BackgroundColor
        $_img_ = $Imagine->create($ImageBox, $BackgroundColor);
        //now draw the text...
        $_img_->draw()->text($settings['text'], $TextFont, $CenteredTextPosition);

        return $_img_->get(Format::ID_WEBP);
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
