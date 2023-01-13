<?php

namespace RCCsv\ProductAttributeImport\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;

class Delete extends Action
{
    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    private $fileFactory;

    public function __construct(
        Context $context,
        Filesystem $filesystem,
        File $file
    ){
        parent::__construct($context);
        $this->fileFactory = $file;
        $this->filesystem = $filesystem;
    }

    public function execute()
    {
        if ($this->getRequest()->getParam('file') == 'attribute') {
            $fileName = 'log/ProductAttributeImport.log';
        } else {
            $fileName = 'log/RainCCliImport.log';
        }
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        $mediaRootDir = $mediaDirectory->getAbsolutePath();

        if ($this->fileFactory->isExists($mediaRootDir . $fileName))  {

            $this->fileFactory->deleteFile($mediaRootDir . $fileName);
        }
        $this->messageManager->addSuccessMessage('Log file Deleted Successfully');
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
    }
}
