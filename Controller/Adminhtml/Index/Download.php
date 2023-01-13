<?php

namespace RCCsv\ProductAttributeImport\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;

class Download extends Action
{
    /**
     * @var FileFactory
     */
    private $fileFactory;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var File
     */
    private $file;

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param Filesystem $filesystem
     */
    public function __construct(
        Context     $context,
        FileFactory $fileFactory,
        File $file,
        Filesystem  $filesystem
    ){
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->filesystem = $filesystem;
        $this->file = $file;
    }

    public function execute()
    {
        if ($this->getRequest()->getParam('file') == 'attribute') {
            $filePath = 'log/ProductAttributeImport.log';
            $downloadName = 'ProductAttributeImport.log';
        } else {
            $filePath = 'log/RainCCliImport.log';
            $downloadName = 'RainCCliImport.log';
        }
        $content['type'] = 'filename';
        $content['value'] = $filePath;
        $content['rm'] = 0;
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        $mediaRootDir = $mediaDirectory->getAbsolutePath();
        if ($this->file->isExists($mediaRootDir . $filePath))  {
            return $this->fileFactory->create($downloadName, $content, DirectoryList::VAR_DIR);
        }
        $this->messageManager->addWarningMessage('Log file is not exist');
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
    }
}
