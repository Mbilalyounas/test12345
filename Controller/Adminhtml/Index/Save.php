<?php

namespace RCCsv\ProductAttributeImport\Controller\Adminhtml\Index;

use Magento\Framework\File\Csv;
use RCCsv\ProductAttributeImport\Model\ProductAttribute;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class Save extends Action
{
    /**
     * @var ProductAttribute
     */
    private $productAttribute;
    /**
     * @var Csv
     */
    private $csv;

    public function __construct(
        Context $context,
        ProductAttribute  $productAttribute,
        Csv $csv
    ){
        parent::__construct($context);
        $this->productAttribute = $productAttribute;
        $this->csv = $csv;
    }

    public function execute()
    {
        $data = $this->csv->getData($_FILES['product_attribute']['tmp_name']);
        $headerColumn = ['attribute_label', 'input_type', 'attribute_code', 'options', 'swatch_input_type', 'options_in_hexa'];
        $headerData = current($data);
        $unKnownColumn = [];
        foreach ($headerColumn as $header) {
            if (!in_array($header, $headerData)) {
                $unKnownColumn[] = $header;
            }
        }
        if(count($unKnownColumn) > 0) {
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            $this->messageManager->addErrorMessage('Incorrect CSV format missing column ' . implode($unKnownColumn));
            return $resultRedirect;
        }
        $attributesData = [];
        foreach ($data as $key => $value) {
            if ($key > 0) {
                $attributesData[] = [
                    $headerData[0] => $value[0],
                    $headerData[1] => $value[1],
                    $headerData[2] => $value[2],
                    $headerData[3] => $value[3],
                    $headerData[4] => $value[4],
                    $headerData[5] => $value[5]

                ];
            }
        }
        $this->productAttribute->apply($attributesData);
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        $this->messageManager->addSuccessMessage('Product Attributes Imported Successfully');
        return $resultRedirect;
    }
}
