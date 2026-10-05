<?php declare(strict_types=1);

/*
 * Copyright 2015-2026 Daniel Berthereau
 * Copyright 2016-2017 BibLibre
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software’s author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user’s attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software’s suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace IiifServer\Controller;

use Access\Mvc\Controller\Plugin\IsAllowedMediaContent;
use Common\Stdlib\PsrMessage;
use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\File\Store\StoreInterface;

/**
 * The Media controller class.
 *
 * @package ImageServer
 */
class MediaController extends AbstractActionController
{
    use IiifServerControllerTrait;

    /**
     * @var StoreInterface
     */
    protected $store;

    /**
     * @var \Access\Mvc\Controller\Plugin\IsAllowedMediaContent
     */
    protected $isAllowedMediaContent;

    protected $routeInfo = 'mediaserver/info';

    public function __construct(
        StoreInterface $store,
        ?string $basePath,
        ?IsAllowedMediaContent $isAllowedMediaContent
    ) {
        $this->store = $store;
        $this->basePath = $basePath;
        $this->isAllowedMediaContent = $isAllowedMediaContent;
    }

    /**
     * Serve the locked file of the module Access at the size of the canvas.
     *
     * The locked file is a small image, so a viewer that gets it for a canvas
     * of a full page anchors it in a corner instead of displaying it centered.
     * So it is drawn once on a background of the size of the canvas and stored
     * in the cache, since there are only a few different sizes in a document.
     */
    public function lockedAction()
    {
        $width = (int) $this->params('width');
        $height = (int) $this->params('height');
        // A size of 0 means a missing dimension; the maximum avoids a denial of
        // service through the memory used to draw a huge image.
        if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000) {
            return $this->viewError(new PsrMessage(
                'The requested size is not managed.' // @translate
            ), \Laminas\Http\Response::STATUS_CODE_400);
        }

        $filepath = sprintf('%s/iiif/locked/%dx%d.png', $this->basePath, $width, $height);
        if (!file_exists($filepath) && !$this->createLockedFile($filepath, $width, $height)) {
            return $this->viewError(new PsrMessage(
                'The locked file cannot be prepared.' // @translate
            ), \Laminas\Http\Response::STATUS_CODE_500);
        }

        $response = $this->getResponse();
        $headers = $response->getHeaders()
            ->addHeaderLine('Content-Type', 'image/png')
            ->addHeaderLine('Content-Length', (string) filesize($filepath))
            // The image is the same for everybody and never changes.
            ->addHeaderLine('Cache-Control', 'public, max-age=86400, s-maxage=604800');
        if ($this->settings()->get('iiifserver_append_cors_headers')) {
            $headers->addHeaderLine('Access-Control-Allow-Origin', '*');
        }
        $response->setContent((string) file_get_contents($filepath));
        return $response;
    }

    /**
     * Draw the locked file centered on a background of the requested size.
     */
    protected function createLockedFile(string $filepath, int $width, int $height): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $source = $this->lockedFileAssetPath();
        if (!$source) {
            return false;
        }
        $lock = @imagecreatefrompng($source);
        if (!$lock) {
            return false;
        }

        $image = imagecreatetruecolor($width, $height);
        // A light grey background, like the one of most viewers.
        imagefill($image, 0, 0, imagecolorallocate($image, 238, 238, 238));

        // The lock takes a third of the smallest side, so it stays visible on a
        // panorama as well as on a thumbnail. It is enlarged when needed, but
        // not more than four times, else the small source image is too blurry.
        $lockWidth = imagesx($lock);
        $lockHeight = imagesy($lock);
        $target = (int) (min($width, $height) / 3);
        $target = max(1, min($target, $lockWidth * 4));
        $targetHeight = (int) round($target * $lockHeight / $lockWidth);
        imagealphablending($image, true);
        imagecopyresampled(
            $image,
            $lock,
            (int) (($width - $target) / 2),
            (int) (($height - $targetHeight) / 2),
            0,
            0,
            $target,
            $targetHeight,
            $lockWidth,
            $lockHeight
        );
        imagedestroy($lock);

        $dirpath = dirname($filepath);
        if (!file_exists($dirpath)) {
            @mkdir($dirpath, 0775, true);
        }
        // Write atomically: a concurrent request never reads a partial file.
        $tempPath = $filepath . '.' . getmypid() . '.tmp';
        $result = @imagepng($image, $tempPath);
        imagedestroy($image);
        if (!$result) {
            @unlink($tempPath);
            return false;
        }
        return @rename($tempPath, $filepath);
    }

    /**
     * The path of the locked file of the module Access, overridable by a theme.
     */
    protected function lockedFileAssetPath(): ?string
    {
        $file = 'asset/img/locked-file.png';
        $candidates = [];
        try {
            $theme = $this->getEvent()->getApplication()->getServiceManager()
                ->get('Omeka\Site\ThemeManager')->getCurrentTheme();
            if ($theme) {
                $candidates[] = OMEKA_PATH . '/themes/' . $theme->getId() . '/' . $file;
            }
        } catch (\Exception $e) {
            // No current theme.
        }
        $candidates[] = OMEKA_PATH . '/modules/Access/' . $file;
        $candidates[] = OMEKA_PATH . '/composer-addons/modules/Access/' . $file;
        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Returns the current file.
     */
    public function fetchAction()
    {
        $media = $this->fetchResource('media');
        if (!$media) {
            return $this->viewError(new PsrMessage(
                'Media #{media_id} not found.', // @translate
                ['media_id' => $this->params('id')]
            ), \Laminas\Http\Response::STATUS_CODE_404);
        }

        $response = $this->getResponse();
        $headers = $response->getHeaders();

        // TODO Upgrade message for iiif v3: audio and video are allowed, not pdf.
        // Because there is no conversion currently, the format should be
        // checked.
        $format = strtolower((string) $this->params('format'));
        if (pathinfo($media->filename(), PATHINFO_EXTENSION) != $format) {
            return $this->viewError(new PsrMessage(
                'The IIIF server encountered an unexpected error that prevented it from fulfilling the request: the requested format is not supported.' // @translate
            ), \Laminas\Http\Response::STATUS_CODE_500);
        }

        // Compatibility with module Access: rights should be checked for the
        // file, not only for the media.
        if ($this->isAllowedMediaContent
            && !$this->settings()->get('iiifserver_access_resource_skip')
            && !$this->isAllowedMediaContent->__invoke($media)
        ) {
            // Manage custom asset file from the theme.
            $mediaType = $media ? $media->mediaType() : 'image/png';
            $mediaTypeMain = strtok($mediaType, '/');
            if ($mediaTypeMain === 'image') {
                $mediaType = 'image/png';
                $file = 'img/locked-file.png';
            } elseif ($mediaType === 'application/pdf') {
                $file = 'img/locked-file.pdf';
            } elseif ($mediaTypeMain === 'audio' || $mediaTypeMain === 'video') {
                $mediaType = 'video/mp4';
                $file = 'img/locked-file.mp4';
            } elseif ($mediaType === 'application/vnd.oasis.opendocument.text') {
                $file = 'img/locked-file.odt';
            } else {
                $mediaType = 'image/png';
                $file = 'img/locked-file.png';
            }

            $viewHelpers = $this->viewHelpers();
            $assetUrl = $viewHelpers->get('assetUrl');
            $fileUrl = $assetUrl($file, 'Access', true, true, true);

            $headers
                ->addHeaderLine('Content-Transfer-Encoding: binary')
                // ->addHeaderLine(sprintf('Content-Length: %s', $filesize))
                ->addHeaderLine('Content-Type', $mediaType);

            // Header for cors, required for access of iiif.
            if ($this->settings()->get('iiifserver_append_cors_headers')) {
                $headers
                    ->addHeaderLine('Access-Control-Allow-Origin', '*');
            }

            // Redirect (302/307) to the url of the file.
            return $this->redirect()->toUrl($fileUrl);
        }

        // A check is added if the file is local: the source can be a local file
        // or an external one (Amazon S3…).
        switch (get_class($this->store)) {
            case \Omeka\File\Store\Local::class:
                $filepath = $this->basePath
                    . DIRECTORY_SEPARATOR . $this->getStoragePath('original', $media->filename());
                if (!file_exists($filepath) || filesize($filepath) == 0) {
                    return $this->viewError(new PsrMessage(
                        'The IIIF server encountered an unexpected error that prevented it from fulfilling the request: the resulting file is not found.' // @translate
                    ), \Laminas\Http\Response::STATUS_CODE_500);
                }
                break;
        }
        // TODO Check if the external url is not empty.

        // Header for cors, required for access of iiif.
        if ($this->settings()->get('iiifserver_append_cors_headers')) {
            $headers
                ->addHeaderLine('Access-Control-Allow-Origin', '*');
        }

        // TODO This is a local file (normal server): use 200.
        // Partial content range (206) is managed by the server itself.

        $headers
            ->addHeaderLine('Content-Type', $media->mediaType())
            // In most of the cases, the server support partial ranges.
            ->addHeaderLine('Accept-Ranges: bytes');

        // Redirect (302/307) to the url of the file.
        $fileUrl = $media->originalUrl();
        return $this->redirect()->toUrl($fileUrl);
    }
}
