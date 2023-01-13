<?php

namespace RCCsv\ProductAttributeImport\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;

class Sample extends Action
{
    private $dateTimeFactory;
    private $fileFactory;
    private $directory;

    /**
     * @param Context $context
     * @param Filesystem $filesystem
     * @param FileFactory $fileFactory
     * @param DateTimeFactory $dateTimeFactory
     * @throws FileSystemException
     */
    public function __construct(
        Context $context,
        Filesystem $filesystem,
        FileFactory $fileFactory,
        DateTimeFactory $dateTimeFactory
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->dateTimeFactory = $dateTimeFactory;
    }

    /**
     * Execute action
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        try {
            $attributes[0] = [
                'attribute_label' => 'ingredient',
                'input_type' => 'text',
                'attribute_code' => 'ingredient',
                'options' => '',
                'swatch_input_type' => '',
                'options_in_hexa' => ''

            ];
            $attributes[1] = [
                'attribute_label' => 'Colors',
                'input_type' => 'select',
                'attribute_code' => 'colors',
                'options' => 'Black,Blue',
                'swatch_input_type' => 'visual',
                'options_in_hexa' => '#211021,#942894'

            ];
            $attributes[2] = [
                'attribute_label' => 'Is Enable',
                'input_type' => 'boolean',
                'attribute_code' => 'is_enable',
                'options' => '',
                'swatch_input_type' => '',
                'options_in_hexa' => ''

            ];
            $dateModel = $this->dateTimeFactory->create();
            $name = $dateModel->gmtDate('d-m-Y H:i:s');
            $filepath = 'export/export-data-' . $name . '.csv';
            $this->directory->create('export');
            $stream = $this->directory->openFile($filepath, 'w+');
            $stream->lock();
            //column name dispay in your CSV
            $columns = ['attribute_label', 'input_type', 'attribute_code', 'options', 'swatch_input_type', 'options_in_hexa'];
            foreach ($columns as $column) {
                $header[] = __($column);
            }
            $stream->writeCsv($header);
            foreach ($attributes as $attribute) {
                $stream->writeCsv($attribute);
            }
            $content = [];
            $content['type'] = 'filename';
            $content['value'] = $filepath;
            $content['rm'] = '1';

            $csvfilename = 'product_attributes' . $name . '.csv';
            return $this->fileFactory->create($csvfilename, $content, DirectoryList::VAR_DIR);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('*/*/');
        }
    }
}
