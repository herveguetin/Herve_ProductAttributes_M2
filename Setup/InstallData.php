<?php
/**
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author Hervé Guétin <herve.guetin@gmail.com> <@herveguetin>
 * @copyright Copyright (c) 2016 Agence Soon (http://www.agence-soon.fr)
 */

namespace Herve\ProductAttributes\Setup;


use Magento\Catalog\Api\AttributeSetManagementInterface;
use Magento\Catalog\Api\AttributeSetRepositoryInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeManagementInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Helper\Product;
use Magento\Catalog\Helper\ProductFactory;
use Magento\Eav\Api\AttributeGroupRepositoryInterface;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeGroupInterface;
use Magento\Eav\Api\Data\AttributeGroupInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeSetInterface;
use Magento\Eav\Api\Data\AttributeSetInterfaceFactory;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\Entity\AttributeFactory;
use Magento\Eav\Model\Entity\Setup\Context;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\File\Csv;
use Magento\Framework\File\CsvFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\SampleData\FixtureManager;
use Magento\Framework\Setup\SampleData\FixtureManagerFactory;

class InstallData extends EavSetup implements InstallDataInterface
{
    const FIXTURE_FILE_PATH = 'Herve_ProductAttributes::fixtures/attributes.csv';
    const DEFAULT_ATTRIBUTE_GROUP_NAME = '_default';

    /**
     * Attribute info parsed from the fixture manager
     *
     * @var array
     */
    private $attributesInfo = [];
    /**
     * @var FixtureManager
     */
    private $fixtureManager;
    /**
     * @var Csv
     */
    private $csvReader;
    /**
     * @var Product
     */
    private $productHelper;
    /**
     * @var Attribute
     */
    private $eavAttributeEntity;
    /**
     * Attribute sets used in attributes to install
     *
     * @var AttributeSetInterface[]
     */
    private $attributeSets;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var AttributeSetInterfaceFactory
     */
    private $attributeSetInterfaceFactory;
    /**
     * @var AttributeSetManagementInterface
     */
    private $attributeSetManagement;
    /**
     * @var AttributeSetRepositoryInterface
     */
    private $attributeSetRepository;
    /**
     * @var AttributeGroupRepositoryInterface
     */
    private $attributeGroupRepository;
    /**
     * @var AttributeGroupInterfaceFactory
     */
    private $attributeGroupInterfaceFactory;
    /**
     * @var array
     */
    private $attributeGroups = [];
    /**
     * @var ProductAttributeRepositoryInterface
     */
    private $productAttributeRepository;
    /**
     * @var ProductAttributeInterfaceFactory
     */
    private $productAttributeInterfaceFactory;
    /**
     * @var ProductAttributeManagementInterface
     */
    private $attributeManagement;
    /**
     * @var AttributeOptionInterfaceFactory
     */
    private $attributeOptionInterfaceFactory;
    /**
     * @var AttributeOptionManagementInterface
     */
    private $attributeOptionManagement;

    public function __construct(
        ModuleDataSetupInterface $setup,
        Context $context,
        CacheInterface $cache,
        CollectionFactory $attrGroupCollectionFactory,
        FixtureManagerFactory $fixtureManagerFactory,
        CsvFactory $csvReaderFactory,
        ProductFactory $productHelperFactory,
        AttributeFactory $eavAttributeEntityFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        AttributeSetInterfaceFactory $attributeSetInterfaceFactory,
        AttributeSetRepositoryInterface $attributeSetRepository,
        AttributeSetManagementInterface $attributeSetManagement,
        AttributeGroupRepositoryInterface $attributeGroupRepository,
        AttributeGroupInterfaceFactory $attributeGroupInterfaceFactory,
        ProductAttributeRepositoryInterface $productAttributeRepository,
        ProductAttributeInterfaceFactory $productAttributeInterfaceFactory,
        ProductAttributeManagementInterface $attributeManagement,
        AttributeOptionInterfaceFactory $attributeOptionInterfaceFactory,
        AttributeOptionManagementInterface $attributeOptionManagement
    ) {
        parent::__construct($setup, $context, $cache, $attrGroupCollectionFactory);

        $this->fixtureManager = $fixtureManagerFactory->create();
        $this->csvReader = $csvReaderFactory->create();
        $this->productHelper = $productHelperFactory->create();
        $this->eavAttributeEntity = $eavAttributeEntityFactory->create();
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->attributeSetInterfaceFactory = $attributeSetInterfaceFactory;
        $this->attributeSetManagement = $attributeSetManagement;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->attributeGroupRepository = $attributeGroupRepository;
        $this->attributeGroupInterfaceFactory = $attributeGroupInterfaceFactory;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->productAttributeInterfaceFactory = $productAttributeInterfaceFactory;
        $this->attributeManagement = $attributeManagement;
        $this->attributeOptionInterfaceFactory = $attributeOptionInterfaceFactory;
        $this->attributeOptionManagement = $attributeOptionManagement;
    }


    /**
     * Installs data for a module
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        // We populate the attributes info from a fixture file for this example
        $this->populateAttributeInfo();
        // NOTE: you can now dump $this->attributeInfo to see what the array looks like for you own use

        // Load existing product attribute sets for further use.
        // We need those in order not to create duplicates
        $this->loadExistingAttributeSets();

        // Create all the attribute sets needed for our attributes
        $this->createAttributeSets();

        // Create the attributes
        $this->createAttributes();

        // Create the attribute groups attached to our attribute sets
        $this->createAttributeGroups();

        // Assign the attributes to their proper sets and groups
        $this->assignAttributes();

        // Add the options to the attributes
        $this->addAttributesOptions();
    }

    /**
     * Populate the attribute info based on a fixture file
     * The goal is to store all usable info in the $this->attributesInfo property
     */
    private function populateAttributeInfo()
    {
        $fileName = $this->fixtureManager->getFixture(self::FIXTURE_FILE_PATH);
        $rows = $this->csvReader->getData($fileName);
        $header = array_shift($rows);

        array_map(function ($row) use ($header) {
            $data = [];
            foreach ($row as $key => $value) {
                $data[$header[$key]] = $value;
            }
            $data['attribute_set'] = explode("\n", $data['attribute_set']);
            $data['options'] = explode("\n", $data['options']);
            $data['backend_model'] = $this->productHelper->getAttributeBackendModelByInputType(
                $data['frontend_input']
            );
            $data['backend_type'] = $this->eavAttributeEntity->getBackendTypeByInput($data['frontend_input']);

            $this->attributesInfo[$data['attribute_code']] = $data;
        }, $rows);
    }

    /**
     * Load existing catalog attribute sets
     */
    private function loadExistingAttributeSets()
    {
        $attributeSets = $this->attributeSetRepository
            ->getList($this->searchCriteriaBuilder->create())
            ->getItems();

        array_map(function ($attributeSet) {
            /** @var AttributeSetInterface $attributeSet */
            $this->attributeSets[$attributeSet->getAttributeSetName()] = $attributeSet;
        }, $attributeSets);
    }

    /**
     * Create all required attribute sets
     */
    private function createAttributeSets()
    {
        array_map(function ($attributeInfo) {
            $attributeSets = $attributeInfo['attribute_set'];
            array_map(function ($attributeSetName) {
                $this->createAttributeSet($attributeSetName);
            }, $attributeSets);
        }, $this->attributesInfo);
    }

    /**
     * Create a catalog attribute set from its name
     *
     * @param string $attributeSetName
     */
    private function createAttributeSet($attributeSetName)
    {
        if (!isset($this->attributeSets[$attributeSetName])) {
            /** @var AttributeSetInterface $attributeSet */
            $attributeSet = $this->attributeSetInterfaceFactory->create();
            $attributeSet->setAttributeSetName($attributeSetName);
            $this->attributeSets[$attributeSetName] = $this->attributeSetManagement
                ->create(
                    $attributeSet,
                    $this->getDefaultAttributeSetId(ProductAttributeInterface::ENTITY_TYPE_CODE)
                );
        }
    }

    /**
     * Create the all the product attributes
     */
    private function createAttributes()
    {
        array_map(function ($attributeInfo) {
            $mustCreateAttribute = false;
            try {
                $this->productAttributeRepository->get($attributeInfo['attribute_code']);
            } catch (NoSuchEntityException $e) {
                // Yes, if there is a NoSuchEntityException, it means that the attributes does not exist
                $mustCreateAttribute = true;
            }

            if ($mustCreateAttribute) {
                $this->createAttribute($attributeInfo);
            }
        }, $this->attributesInfo);
    }

    /**
     * Create a product attribute
     *
     * @param array $attributeInfo
     */
    private function createAttribute($attributeInfo)
    {
        /** @var ProductAttributeInterface $attribute */
        $attribute = $this->productAttributeInterfaceFactory->create();
        $attribute->setAttributeCode($attributeInfo['attribute_code'])
            ->setFrontendInput($attributeInfo['frontend_input'])
            ->setEntityTypeId($this->getEntityTypeId(ProductAttributeInterface::ENTITY_TYPE_CODE))
            ->setDefaultFrontendLabel($attributeInfo['frontend_label'])
            ->setBackendType($attributeInfo['backend_type'])
            ->setBackendModel($attributeInfo['backend_model'])
            ->setIsUserDefined(true);

        $this->productAttributeRepository->save($attribute);
    }

    /**
     * Create attribute groups
     */
    private function createAttributeGroups()
    {
        array_map(function ($attributeInfo) {
            // $attributeSets are all the attribute sets for the current attribute
            $attributeSets = $attributeInfo['attribute_set'];

            array_map(function ($attributeSetName) use ($attributeInfo) {
                $attributeSetId = $this->attributeSets[$attributeSetName]->getAttributeSetId();
                $entityTypeCode = ProductAttributeInterface::ENTITY_TYPE_CODE;
                $attributeGroupName = ($attributeInfo['attribute_group']) ? $attributeInfo['attribute_group'] : self::DEFAULT_ATTRIBUTE_GROUP_NAME;

                // If the 'attribute_group' info is populated for the give attribute, we use it for $attributeGroupId...
                $attributeGroupId = ($attributeGroupName != self::DEFAULT_ATTRIBUTE_GROUP_NAME)
                    ? (string)$attributeGroupName // $attributeGroupId is string
                    // ... otherwise, we use the default attribute group for the attribute set
                    // $attributeGroupId is int
                    : (int)$this->getDefaultAttributeGroupId(
                        $entityTypeCode,
                        $attributeSetId
                    );

                // Try to find an existing attribute group
                $attributeGroup = $this->getAttributeGroup($entityTypeCode, $attributeSetId, $attributeGroupId);

                // Attribute group does not exist, create it
                if (!$attributeGroup) {
                    $attributeGroup = $this->createAttributeGroup($attributeGroupName, $attributeSetId);
                    $attributeGroupId = (int)$attributeGroup->getAttributeGroupId();
                }

                // Now we are able to populate a table containing all the attribute groups for all attribute sets
                $this->attributeGroups[$attributeSetId][$attributeGroupName] = (is_numeric($attributeGroupId))
                    ? $attributeGroupId
                    : $attributeGroup['attribute_group_id'];

            }, $attributeSets);
        }, $this->attributesInfo);
    }

    /**
     * Create attribute group
     *
     * @param string $attributeGroupName
     * @param int $attributeSetId
     * @return AttributeGroupInterface
     */
    private function createAttributeGroup($attributeGroupName, $attributeSetId)
    {
        /** @var AttributeGroupInterface $attributeGroup */
        $attributeGroup = $this->attributeGroupInterfaceFactory->create();
        $attributeGroup
            ->setAttributeGroupName($attributeGroupName)
            ->setAttributeSetId($attributeSetId);

        $attributeGroup = $this->attributeGroupRepository->save($attributeGroup);
        return $attributeGroup;
    }

    /**
     * Assign attributes to their sets and groups
     */
    private function assignAttributes()
    {
        array_map(function ($attributeInfo) {
            $attributeSets = $attributeInfo['attribute_set'];
            $attributeGroupName = ($attributeInfo['attribute_group']) ? $attributeInfo['attribute_group'] : self::DEFAULT_ATTRIBUTE_GROUP_NAME;

            array_map(function ($attributeSetName) use ($attributeInfo, $attributeGroupName) {
                $attributeSetId = $this->attributeSets[$attributeSetName]->getAttributeSetId();
                $attributeGroupId = $this->attributeGroups[$attributeSetId][$attributeGroupName];
                $this->assignAttribute($attributeSetId, $attributeGroupId, $attributeInfo['attribute_code']);
            }, $attributeSets);
        }, $this->attributesInfo);
    }

    /**
     * Assign attribute to its sets and groups
     *
     * @param int $attributeSetId
     * @param int $attributeGroupId
     * @param string $attributeCode
     */
    private function assignAttribute($attributeSetId, $attributeGroupId, $attributeCode)
    {
        $this->attributeManagement->assign($attributeSetId, $attributeGroupId, $attributeCode, null);
    }

    /**
     * Add options to all the attributes
     */
    private function addAttributesOptions()
    {
        array_map(function ($attributeInfo) {
            if (!empty($attributeInfo['options'])) {
                $this->addOptionsToAttribute($attributeInfo['attribute_code'], $attributeInfo['options']);
            }
        }, $this->attributesInfo);
    }

    /**
     * Add options to an attribute
     *
     * @param string $attributeCode
     * @param array $options
     */
    private function addOptionsToAttribute($attributeCode, $options)
    {
        array_map(function ($optionLabel) use ($attributeCode) {
            $option = $this->attributeOptionInterfaceFactory->create();
            $option->setLabel($optionLabel);

            $this->attributeOptionManagement->add(
                ProductAttributeInterface::ENTITY_TYPE_CODE,
                $attributeCode,
                $option
            );
        }, $options);
    }
}