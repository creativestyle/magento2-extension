<?php
/**
 * Copyright ©2018 Itegration Ltd., Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Emartech\Emarsys\Model\ResourceModel\Api;

use Magento\Customer\Model\ResourceModel\Attribute\CollectionFactory as CustomerAttributeCollectionFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResourceModel;
use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Eav\Model\Entity\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\RelationComposite;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\Snapshot;
use Magento\Framework\Model\ResourceModel\Iterator;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Validator\Factory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Eav\Model\Entity\Attribute;

/**
 * Class Customer
 * @package Emartech\Emarsys\Model\ResourceModel\Api
 */
class Customer extends CustomerResourceModel
{
    const CUSTOMER_ENTITY_TYPE_ID = 1;

    /**
     * @var array
     */
    private $attributeData = [];

    /**
     * @var string
     */
    private $mainTable = '';

    /**
     * @var CustomerAttributeCollectionFactory
     */
    private $customerAttributeCollectionFactory;

    /**
     * @var Iterator
     */
    private $iterator;
    /**
     * @var string
     */
    private $linkField = 'entity_id';

    /**
     * Customer constructor.
     *
     * @param CustomerAttributeCollectionFactory $customerAttributeCollectionFactory
     * @param Iterator                           $iterator
     * @param Context                            $context
     * @param Snapshot                           $entitySnapshot
     * @param RelationComposite                  $entityRelationComposite
     * @param ScopeConfigInterface               $scopeConfig
     * @param Factory                            $validatorFactory
     * @param DateTime                           $dateTime
     * @param StoreManagerInterface              $storeManager
     * @param array                              $data
     */
    public function __construct(
        CustomerAttributeCollectionFactory $customerAttributeCollectionFactory,
        Iterator $iterator,
        Context $context,
        Snapshot $entitySnapshot,
        RelationComposite $entityRelationComposite,
        ScopeConfigInterface $scopeConfig,
        Factory $validatorFactory,
        DateTime $dateTime,
        StoreManagerInterface $storeManager,
        $data = []
    ) {
        $this->customerAttributeCollectionFactory = $customerAttributeCollectionFactory;
        $this->iterator = $iterator;

        parent::__construct(
            $context,
            $entitySnapshot,
            $entityRelationComposite,
            $scopeConfig,
            $validatorFactory,
            $dateTime,
            $storeManager,
            $data
        );
    }

    /**
     * @param Collection $collection
     *
     * @return void
     */
    public function joinSubscriptionStatus($collection)
    {
        $subSelect = $this->_resource->getConnection()->select()
            ->from($this->getTable('newsletter_subscriber'), ['subscriber_status'])
            ->where('customer_id = e.entity_id')
            ->order('subscriber_id DESC')
            ->limit(1, 0);

        $collection->getSelect()->columns([
            'accepts_marketing' => $subSelect,
        ]);
    }

    /**
     * @param int       $page
     * @param int       $pageSize
     * @param int|false $websiteId
     *
     * @return array
     */
    public function handleIds($page, $pageSize, $websiteId = false)
    {
        $customerTable = $this->getTable('customer_entity');

        $itemsCountQuery = $this->_resource
            ->getConnection()
            ->select()
            ->from($customerTable, ['count' => 'count(' . $this->linkField . ')']);

        $bind = [];
        if ($websiteId) {
            $itemsCountQuery->where('website_id = :website_id');
            $bind['website_id'] = $websiteId;
        }

        $numberOfItems = $this->_resource->getConnection()->fetchOne($itemsCountQuery, $bind);

        $subFields['eid'] = $this->linkField;

        $subSelect = $this->_resource->getConnection()->select()
            ->from($customerTable, $subFields)
            ->order($this->linkField)
            ->limit($pageSize, $page);

        $fields = ['minId' => 'min(tmp.eid)', 'maxId' => 'max(tmp.eid)'];

        $idQuery = $this->_resource
            ->getConnection()
            ->select()
            ->from(['tmp' => $subSelect], $fields);

        $minMaxValues = $this->_resource->getConnection()->fetchRow($idQuery);

        $returnArray = [
            'numberOfItems' => (int)$numberOfItems,
            'minId'         => (int)$minMaxValues['minId'],
            'maxId'         => (int)$minMaxValues['maxId'],
        ];

        return $returnArray;
    }

    /**
     * @param int      $minCustomerId
     * @param int      $maxCustomerId
     * @param string[] $attributeCodes
     *
     * @return array
     */
    public function getAttributeData($minCustomerId, $maxCustomerId, $attributeCodes)
    {
        $this->mainTable = $this->getEntityTable();
        $this->attributeData = [];

        $attributeMapper = [];
        $mainTableFields = [];
        $attributeTables = [];

        $customerAttributeCollection = $this->customerAttributeCollectionFactory->create();
        $customerAttributeCollection
            ->addFieldToFilter('entity_type_id', ['eq' => self::CUSTOMER_ENTITY_TYPE_ID])
            ->addFieldToFilter('attribute_code', ['in' => $attributeCodes]);

        /** @var Attribute $customerAttribute */
        foreach ($customerAttributeCollection as $customerAttribute) {
            $attributeTable = $customerAttribute->getBackendTable();
            if ($this->mainTable === $attributeTable) {
                $mainTableFields[] = $customerAttribute->getAttributeCode();
            } else {
                if (!in_array($attributeTable, $attributeTables)) {
                    $attributeTables[] = $attributeTable;
                }
                $attributeMapper[$customerAttribute->getAttributeCode()] = (int)$customerAttribute->getId();
            }
        }

        $this
            ->getMainTableFieldItems($mainTableFields, $minCustomerId, $maxCustomerId, $attributeMapper)
            ->getAttributeTableFieldItems($attributeTables, $minCustomerId, $maxCustomerId, $attributeMapper);

        return $this->attributeData;
    }

    /**
     * @param string[] $mainTableFields
     * @param int      $minCustomerId
     * @param int      $maxCustomerId
     * @param string[] $attributeMapper
     *
     * @return $this
     */
    private function getMainTableFieldItems($mainTableFields, $minCustomerId, $maxCustomerId, $attributeMapper)
    {
        if ($mainTableFields) {
            if (!in_array($this->linkField, $mainTableFields)) {
                $mainTableFields[] = $this->linkField;
            }
            $attributesQuery = $this->_resource->getConnection()->select()
                ->from($this->mainTable, $mainTableFields)
                ->where($this->linkField . ' >= ?', $minCustomerId)
                ->where($this->linkField . ' <= ?', $maxCustomerId);

            $this->iterator->walk(
                (string)$attributesQuery,
                [[$this, 'handleMainTableAttributeDataTable']],
                [
                    'fields'          => array_diff($mainTableFields, [$this->linkField]),
                    'attributeMapper' => $attributeMapper,
                ],
                $this->_resource->getConnection()
            );
        }

        return $this;
    }

    /**
     * @param array $attributeTables
     * @param int   $minCustomerId
     * @param int   $maxCustomerId
     * @param array $attributeMapper
     *
     * @return $this
     */
    private function getAttributeTableFieldItems($attributeTables, $minCustomerId, $maxCustomerId, $attributeMapper)
    {
        $attributeQueries = [];

        foreach ($attributeTables as $attributeTable) {
            $attributeQueries[] = $this->_resource->getConnection()->select()
                ->from($attributeTable, ['attribute_id', $this->linkField, 'value'])
                ->where($this->linkField . ' >= ?', $minCustomerId)
                ->where($this->linkField . ' <= ?', $maxCustomerId)
                ->where('attribute_id IN (?)', $attributeMapper);
        }

        try {
            $unionQuery = $this->_resource->getConnection()->select()
                ->union($attributeQueries, \Zend_Db_Select::SQL_UNION_ALL); // @codingStandardsIgnoreLine
            $this->iterator->walk(
                (string)$unionQuery,
                [[$this, 'handleAttributeDataTable']],
                [
                    'attributeMapper' => $attributeMapper,
                ],
                $this->_resource->getConnection()
            );
        } catch (\Exception $e) { // @codingStandardsIgnoreLine
        }

        return $this;
    }

    /**
     * @param array $args
     *
     * @return void
     */
    public function handleMainTableAttributeDataTable($args)
    {
        $customerId = $args['row'][$this->linkField];

        foreach ($args['fields'] as $field) {
            $this->attributeData[$customerId][$field] = $args['row'][$field];
        }
    }

    /**
     * @param array $args
     *
     * @return void
     */
    public function handleAttributeDataTable($args)
    {
        $customerId = $args['row'][$this->linkField];
        $attributeCode = $this->findAttributeCodeById($args['row']['attribute_id'], $args['attributeMapper']);

        if (!array_key_exists($customerId, $this->attributeData)) {
            $this->attributeData[$customerId] = [];
        }

        $this->attributeData[$customerId][$attributeCode] = $args['row']['value'];
    }

    /**
     * @param int   $attributeId
     * @param array $attributeMapper
     *
     * @return string
     */
    private function findAttributeCodeById($attributeId, $attributeMapper)
    {
        foreach ($attributeMapper as $attributeCode => $attributeCodeId) {
            if ($attributeId == $attributeCodeId) {
                return $attributeCode;
            }
        }

        return '';
    }
}
