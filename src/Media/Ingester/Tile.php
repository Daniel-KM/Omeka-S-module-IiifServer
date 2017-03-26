<?php
namespace IiifServer\Media\Ingester;

use IiifServer\Mvc\Controller\Plugin\TileBuilder;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\File\Manager as FileManager;
use Omeka\Media\Ingester\IngesterInterface;
use Omeka\Stdlib\ErrorStore;
use Zend\Filter\File\RenameUpload;
use Zend\Form\Element\File;
use Zend\InputFilter\FileInput;
use Zend\View\Renderer\PhpRenderer;

class Tile implements IngesterInterface
{
    /**
     * @var FileManager
     */
    protected $fileManager;

    /**
     * @var TileBuilder
     */
    protected $tileBuilder;

    /**
     * @var array
     */
    protected $tileParams;

    public function __construct(FileManager $fileManager, TileBuilder $tileBuilder,
        array $tileParams
    ) {
        $this->fileManager = $fileManager;
        $this->tileBuilder = $tileBuilder;
        $this->tileParams = $tileParams;
    }

    public function getLabel()
    {
        return 'Tiler'; // @translate
    }

    public function getRenderer()
    {
        return 'tile';
    }

    /**
     * {@inheritDoc}
     * @see \Omeka\Media\Ingester\IngesterInterface::ingest()
     * @see \Omeka\Media\Ingester\Upload::ingest()
     */
    public function ingest(Media $media, Request $request,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();
        $fileData = $request->getFileData();
        if (!isset($fileData['tile'])) {
            $errorStore->addError('error', 'No files were uploaded for tiling');
            return;
        }

        if (!isset($data['tile_index'])) {
            $errorStore->addError('error', 'No tiling index was specified');
            return;
        }

        $index = $data['tile_index'];
        if (!isset($fileData['tile'][$index])) {
            $errorStore->addError('error', 'No file uploaded for tiling for the specified index');
            return;
        }

        $fileManager = $this->fileManager;
        $file = $fileManager->getTempFile();

        $fileInput = new FileInput('tile');
        $fileInput->getFilterChain()->attach(new RenameUpload([
            'target' => $file->getTempPath(),
            'overwrite' => true,
        ]));

        $validatorChain = $fileInput->getValidatorChain();
        $validatorChain->attachByName('FileIsImage', [], true);
        $fileInput->setValidatorChain($validatorChain);

        $fileData = $fileData['tile'][$index];
        $fileInput->setValue($fileData);
        if (!$fileInput->isValid()) {
            foreach ($fileInput->getMessages() as $message) {
                $errorStore->addError('upload', $message);
            }
            return;
        }
        $fileInput->getValue();
        $file->setSourceName($fileData['name']);
        if (!$fileManager->validateFile($file, $errorStore)) {
            return;
        }

        $media->setStorageId($file->getStorageId());
        $media->setExtension($file->getExtension($fileManager));
        $media->setMediaType($file->getMediaType());
        $media->setSha256($file->getSha256());
        $media->setHasThumbnails($fileManager->storeThumbnails($file));
        $media->setHasOriginal(true);
        if (!array_key_exists('o:source', $data)) {
            $media->setSource($fileData['name']);
        }
        $fileManager->storeOriginal($file);
        if (file_exists($file->getTempPath())) {
            $file->delete();
        }

        $storagePath = $this->fileManager
            ->getStoragePath('original', $media->getFilename());
        $source = OMEKA_PATH
            . DIRECTORY_SEPARATOR . 'files'
            . DIRECTORY_SEPARATOR . $storagePath;

        $tileDir = OMEKA_PATH
            . DIRECTORY_SEPARATOR . 'files'
            . DIRECTORY_SEPARATOR . $this->tileParams['tile_dir'];

        $params = $this->tileParams;
        $params['storageId'] = $media->getStorageId();

        $tileBuilder = $this->tileBuilder;
        $tileBuilder($source, $tileDir, $params);
    }

    public function form(PhpRenderer $view, array $options = [])
    {
        $fileInput = new File('tile[__index__]');
        $fileInput->setOptions([
            'label' => 'Upload Image', // @translate
            'info' => $view->uploadLimit(),
        ]);
        $fileInput->setAttributes([
            'id' => 'media-tile-input-__index__',
            'required' => true,
        ]);
        $field = $view->formRow($fileInput);
        return $field . '<input type="hidden" name="o:media[__index__][tile_index]" value="__index__">';
    }
}
