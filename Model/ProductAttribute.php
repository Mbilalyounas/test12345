<?php
/**
 * Created by PhpStorm
 * User: bilalyounas
 * Date: 15/10/21
 * Time: 12:54 PM
 */
declare(strict_types=1);

namespace RCCsv\ProductAttributeImport\Model;

use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as eavAttribute;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

/**
 * This class is responsible for add attribute in products
 *
 * Class AddFormType
 */
class ProductAttribute
{
    const MULTI_SELECT_BACKEND_MODEL = "Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend";
    const BOLEAN_SOURCE_MODEL = 'Magento\Eav\Model\Entity\Attribute\Source\Boolean';
    const PRODUCT_ATTRIBUTE = ['attribute_set_id', 'id', 'type_id', 'sku', 'name', 'weight', 'price'];

    /**
     * @var AttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var ResourceConnection
     */
    private  $resource;

    /**
     * @var AdapterInterface
     */
    private $connection;
    /**
     * @var Config
     */
    private $eavConfig;
    /**
     * @var CollectionFactory
     */
    private $attrOptionCollectionFactory;
    private $alreadyOptions = [];

    protected $colorMap = [
        'Black'     => '#000000',
        'Blue'      => '#1857f7',
        'Brown'     => '#945454',
        'Gray'      => '#8f8f8f',
        'Green'     => '#53a828',
        'Lavender'  => '#ce64d4',
        'Multi'     => '#ffffff',
        'Orange'    => '#eb6703',
        'Purple'    => '#ef3dff',
        'Red'       => '#ff0000',
        'White'     => '#ffffff',
        'Yellow'    => '#ffd500',
    ];
    /**
     * @var \Magento\Swatches\Model\Swatch
     */
    private $swatch;


    /**
     * @param AttributeRepositoryInterface $attributeRepository
     * @param ResourceConnection $resource
     * @param LoggerInterface $logger
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param Config $eavConfig
     * @param CollectionFactory $attrOptionCollectionFactory
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        ResourceConnection $resource,
        LoggerInterface $logger,
        \Magento\Swatches\Model\Swatch $swatch,
        ModuleDataSetupInterface $moduleDataSetup,
        Config $eavConfig,
        CollectionFactory $attrOptionCollectionFactory,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->logger = $logger;
        $this->attributeRepository = $attributeRepository;
        $this->resource = $resource;
        $this->connection = $resource->getConnection();
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
        $this->attrOptionCollectionFactory = $attrOptionCollectionFactory;
        $this->swatch = $swatch;
    }

    /**
     * @return $this
     */
    public function apply($attributes)
    {
        try {
//            $attributes[0] = [
//                'attribute_label' => 'Color',
//                'input_type' => 'select',
//                'attribute_code' => 'bundle_product_pricing',
//                'options' => 'Black,Blue',
//                'swatch_input_type' => 'visual',
//                'options_in_hexa' => '#211021,#942894'
//
//            ];
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/ProductAttributeImport.log');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            $logger->info('Product Attribute Process Started');
            foreach ($attributes as $attribute){
                $this->alreadyOptions = [];
                try {
                    $sourceModel = '';
                    $backendModel = '';
                    $options = [];
                    if($attribute['input_type'] == 'select' || $attribute['input_type'] == 'multiselect'){
                        $options = explode(',', $attribute['options']);
                    }
                    if($attribute['input_type'] == 'multiselect') {
                        $backendModel = self::MULTI_SELECT_BACKEND_MODEL;
                    } elseif($attribute['input_type'] == 'boolean') {
                        $sourceModel = self::BOLEAN_SOURCE_MODEL;
                    }
                    if (!in_array($attribute['attribute_code'], self::PRODUCT_ATTRIBUTE) && !$this->isAttributeExist($attribute['attribute_code'])) {
                        $this->addProductAttribute($attribute, $sourceModel, $backendModel, $options);
                    } else if ($this->isAttributeExist($attribute['attribute_code'])) {
                        $this->updateProductAttribute($attribute, $sourceModel, $backendModel, $options);
                    }
                } catch (\Exception $exception) {
                    $logger->info($exception->getMessage());
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Error occur while saving attribute in database");
        }
        $logger->info('Product Attribute Process Ended');
    }

    private function addProductAttribute($attribute, $sourceModel, $backendModel, $options)
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->addAttribute(
            Product::ENTITY,
            $attribute['attribute_code'],
            [
                'type' => 'text',
                'label' => $attribute['attribute_label'],
                'input' => $attribute['input_type'],
                'source' => $sourceModel,
                'frontend' => '',
                'required' => false,
                'backend' => $backendModel,
                'sort_order' => '30',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'default' => null,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'unique' => false,
                'apply_to' => 'simple,grouped,bundle,configurable,virtual',
                'group' => 'General',
                'used_in_product_listing' => true,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => false,
            ]
        );

        $this->moduleDataSetup->getConnection()->endSetup();
        $this->eavConfig->clear();
        $oldAttribute = $this->eavConfig->getAttribute('catalog_product', $attribute['attribute_code']);
        if (!$oldAttribute) {
            return;
        }
        if($attribute['input_type'] == 'select' || $attribute['input_type'] == 'multiselect'){
            if ($attribute['swatch_input_type'] == 'visual') {
                $attributeData['option'] = $this->addExistingOptions($oldAttribute, $attribute);
                $attributeData['frontend_input'] = 'select';
                $attributeData['swatch_input_type'] = 'visual';
                $attributeData['update_product_preview_image'] = 1;
                $attributeData['use_product_image_for_swatch'] = 0;
                $attributeData['optionvisual'] = $this->getOptionSwatch($attributeData, $attribute);
                $attributeData['defaultvisual'] = $this->getOptionDefaultVisual($attributeData, $attribute);
                $attributeData['swatchvisual'] = $this->getOptionSwatchVisual($attributeData, $attribute);
            } else if ($attribute['swatch_input_type'] == 'text') {
                $attributeData['option'] = $this->addExistingOptions($oldAttribute, $attribute);
                $attributeData['frontend_input'] = 'select';
                $attributeData['swatch_input_type'] = 'text';
                $attributeData['update_product_preview_image'] = 1;
                $attributeData['use_product_image_for_swatch'] = 0;
                $attributeData['optiontext'] = $this->getOptionSwatch($attributeData, $attribute);
                $attributeData['defaulttext'] = $this->getOptionDefaultVisual($attributeData, $attribute);
                $attributeData['swatchtext']['value'] = $attributeData['optiontext']['value'];
            } else if ($attribute['swatch_input_type'] == 'dropdown') {
                $attributeData['option'] = $this->addExistingOptions($oldAttribute, $attribute);
                $attributeData['frontend_input'] = 'select';
                $attributeData['swatch_input_type'] = 'dropdown';
                $attributeData['update_product_preview_image'] = 1;
                $attributeData['use_product_image_for_swatch'] = 0;
                $attributeData['option'] = $this->getOptionSwatch($attributeData, $attribute);
            } else {
                $attributeData['option']['value'] = $this->addExistingOptions($oldAttribute, $attribute);
            }
            $oldAttribute->addData($attributeData);
            $oldAttribute->save();
        }
    }
    private function updateProductAttribute($updatedattribute, $sourceModel, $backendModel, $options)
    {
        $attribute = $this->eavConfig->getAttribute('catalog_product', $updatedattribute['attribute_code']);
        if (!$attribute) {
            return;
        }
        if($updatedattribute['input_type'] == 'select' || $updatedattribute['input_type'] == 'multiselect'){
            if ($updatedattribute['swatch_input_type'] == 'visual') {
                $attributeData['option'] = $this->addExistingOptions($attribute, $updatedattribute);
                $attributeData['frontend_input'] = 'select';
                $attributeData['update_product_preview_image'] = 1;
                $attributeData['use_product_image_for_swatch'] = 0;
                $attributeData['optionvisual'] = $this->getOptionSwatch($attributeData, $updatedattribute);
                $attributeData['defaultvisual'] = $this->getOptionDefaultVisual($attributeData, $updatedattribute);
                $attributeData['swatchvisual'] = $this->getOptionSwatchVisual($attributeData, $updatedattribute);
                $attributeData['swatch_input_type'] = 'visual';
            } else if ($updatedattribute['swatch_input_type'] == 'text') {
                $attributeData['option'] = $this->addExistingOptions($attribute, $updatedattribute);
                $attributeData['frontend_input'] = 'select';
                $attributeData['update_product_preview_image'] = 1;
                $attributeData['use_product_image_for_swatch'] = 0;
                $attributeData['optiontext'] = $this->getOptionSwatch($attributeData, $updatedattribute);
                $attributeData['defaulttext'] = $this->getOptionDefaultVisual($attributeData, $updatedattribute);
                $attributeData['swatchtext']['value'] = $attributeData['optiontext']['value'];
                $attributeData['swatch_input_type'] = 'text';
            } else if ($updatedattribute['swatch_input_type'] == 'dropdown') {
                $attributeData['option'] = $this->addExistingOptions($attribute, $updatedattribute);
                $attributeData['frontend_input'] = 'select';
                $attributeData['swatch_input_type'] = 'dropdown';
                $attributeData['update_product_preview_image'] = 1;
                $attributeData['use_product_image_for_swatch'] = 0;
                $attributeData['option'] = $this->getOptionSwatch($attributeData, $updatedattribute);
            }
            else {
                $attributeData['option']['value'] = $this->addExistingOptions($attribute, $updatedattribute);
            }
            $attribute->addData($attributeData);
            $attribute->save();
        }
    }

    /**
     * @param $attributeCode
     * @return bool
     */
    private function isAttributeExist($attributeCode) {
        try {
            $productAttribute = $this->attributeRepository->get(Product::ENTITY, $attributeCode);

        } catch (\Exception $exception) {
            $productAttribute = null;
        }
        if ($productAttribute) {
            return true;
        }
        return false;
    }

    protected function getOptionSwatch(array $attributeData, $attribute)
    {
        $optionSwatch = ['order' => [], 'value' => [], 'delete' => []];
        $i = 0;
        foreach ($attributeData['option'] as $optionKey => $optionValue) {
            $key = '' . $optionKey . '';
            if (str_contains($key, 'option')) {
                break;
            }
            $optionSwatch['delete'][$optionKey] = '';
            $optionSwatch['order'][$optionKey] = (string)$i++;
            $optionSwatch['value'][$optionKey] = [$optionValue, ''];
        }
        $flag = 2;
        $i = 0;
        $attribute['options'] = explode(',', $attribute['options']);
        foreach ($attribute['options'] as $option) {
            if (in_array($i, $this->alreadyOptions)) {
                $i++;
                continue;
            }
            $optionSwatch['delete']['option_'.$flag] = '';
            $optionSwatch['order']['option_'.$flag] = (string)$i++;
            $optionSwatch['value']['option_'.$flag] = [$option, ''];
            $flag++;
        }
        return $optionSwatch;
    }

    private function getOptionSwatchVisual(array $attributeData, $attribute)
    {
        $optionSwatch = ['value' => []];
        foreach ($attributeData['option'] as $optionKey => $optionValue) {
            $key = '' . $optionKey . '';
            if (str_contains($key, 'option')) {
                break;
            }
            $swatch = $this->swatch->getCollection()->addFieldToFilter('option_id',['eq' => $optionKey])->getFirstItem();
            if ($swatch && $swatch->getData()) {
                $optionSwatch['value'][$optionKey] = $swatch->getValue();
            } else {
                $optionSwatch['value'][$optionKey] = '#ffffff';
            }
        }
        $flag = 2;
        $i = 0;
        $attribute['options_in_hexa'] = explode(',', $attribute['options_in_hexa']);
        foreach ($attribute['options_in_hexa'] as $option) {
            if (in_array($i, $this->alreadyOptions)) {
                $i++;
                continue;
            }
            $optionSwatch['value']['option_'.$flag] = $option;
            $i++;
            $flag++;
        }
        return $optionSwatch;
    }

    private function getOptionDefaultVisual(array $attributeData, $attribute)
    {
        $optionSwatch = $this->getOptionSwatchVisual($attributeData, $attribute);
        if(isset(array_keys($optionSwatch['value'])[0]))
            return [array_keys($optionSwatch['value'])[0]];
        else
            return [''];
    }

    private function addExistingOptions($attribute, $updatedattribute)
    {
        $options = [];
        $extOptions = [];
        $attributeId = $attribute->getId();
        if ($attributeId) {
            $this->loadOptionCollection($attributeId);
            foreach ($this->optionCollection[$attributeId] as $option) {
                $extOptions[] =strtolower($option->getValue());
                $options[$option->getId()] = $option->getValue();
            }
        }
        $flag = 2;
        $i = 0;
        $updatedattribute['options'] = explode(',', $updatedattribute['options']);
        foreach ($updatedattribute['options'] as $option) {
            if (in_array(strtolower($option), $extOptions)) {
                $this->alreadyOptions[] = $i;
                $i++;
                continue;
            }
            $extOptions[] = strtolower($option);
            $options['option_'.$flag] = $option;
            $i++;
            $flag++;
        }
        return $options;
    }

    private function loadOptionCollection($attributeId)
    {
        if (empty($this->optionCollection[$attributeId])) {
            $this->optionCollection[$attributeId] = $this->attrOptionCollectionFactory->create()
                ->setAttributeFilter($attributeId)
                ->setPositionOrder('asc', true)
                ->load();
        }
    }
}
