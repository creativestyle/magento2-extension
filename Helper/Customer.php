<?php
/**
 * Copyright ©2019 Itegration Ltd., Inc. All rights reserved.
 * See COPYING.txt for license details.
 * @author: Perencz Tamás <tamas.perencz@itegraion.com>
 */

namespace Emartech\Emarsys\Helper;

use Emartech\Emarsys\Api\AttributesApiInterface;
use Emartech\Emarsys\Api\Data\ConfigInterface;
use Emartech\Emarsys\Api\Data\ConfigInterfaceFactory;
use Emartech\Emarsys\Api\Data\CustomerAddressInterface;
use Emartech\Emarsys\Api\Data\CustomerAddressInterfaceFactory;
use Emartech\Emarsys\Api\Data\CustomerInterface;
use Emartech\Emarsys\Api\Data\CustomerInterfaceFactory;
use Emartech\Emarsys\Api\Data\ExtraFieldsInterfaceFactory;
use Emartech\Emarsys\Model\ResourceModel\Api\Customer as CustomerResource;
use Emartech\Emarsys\Model\ResourceModel\Api\CustomerAddress as CustomerAddressResource;
use Magento\Customer\Model\Customer as CustomerModel;
use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

/**
 * Class Customer
 * @package Emartech\Emarsys\Helper
 */
class Customer extends AbstractHelper
{
    /**
     * @var string[]
     */
    private $fields = [
        'id',
        'email',
        'website_id',
        'group_id',
        'store_id',
        'is_active',
        'prefix',
        'firstname',
        'middlename',
        'lastname',
        'suffix',
        'dob',
        'taxvat',
        'gender',
        'accepts_marketing',
        'created_at',
        'updated_at',
        'default_shipping',
        'default_billing',
    ];

    /**
     * @var null|string[]
     */
    private $extraFields = null;

    /**
     * @var string[]
     */
    private $addressFields = [
        'prefix',
        'firstname',
        'middlename',
        'lastname',
        'suffix',
        'company',
        'street',
        'city',
        'country_id',
        'region',
        'postcode',
        'telephone',
        'fax',
    ];

    /**
     * @var null|string[]
     */
    private $extraAddressFields = null;

    /**
     * @var ConfigInterfaceFactory
     */
    private $configFactory;

    /**
     * @var CustomerResource
     */
    private $customerResource;

    /**
     * @var CustomerAddressResource
     */
    private $customerAddressResource;

    /**
     * @var CustomerInterfaceFactory
     */
    private $customerFactory;

    /**
     * @var array
     */
    private $customerAttributeData = [];

    /**
     * @var array
     */
    private $customerAddressAttributeData = [];

    /**
     * @var CustomerAddressInterfaceFactory
     */
    private $customerAddressFactory;

    /**
     * @var ExtraFieldsInterfaceFactory
     */
    private $extraFieldsFactory;

    /**
     * @var CustomerCollectionFactory
     */
    private $customerCollectionFactory;

    /**
     * @var CustomerCollection|null
     */
    private $customerCollection = null;

    /**
     * Customer constructor.
     *
     * @param ConfigInterfaceFactory          $configFactory
     * @param CustomerResource                $customerResource
     * @param CustomerAddressResource         $customerAddressResource
     * @param CustomerInterfaceFactory        $customerFactory
     * @param CustomerAddressInterfaceFactory $customerAddressFactory
     * @param ExtraFieldsInterfaceFactory     $extraFieldsFactory
     * @param CustomerCollectionFactory       $customerCollectionFactory
     * @param Context                         $context
     */
    public function __construct(
        ConfigInterfaceFactory $configFactory,
        CustomerResource $customerResource,
        CustomerAddressResource $customerAddressResource,
        CustomerInterfaceFactory $customerFactory,
        CustomerAddressInterfaceFactory $customerAddressFactory,
        ExtraFieldsInterfaceFactory $extraFieldsFactory,
        CustomerCollectionFactory $customerCollectionFactory,
        Context $context
    ) {
        $this->configFactory = $configFactory;
        $this->customerResource = $customerResource;
        $this->customerAddressResource = $customerAddressResource;
        $this->customerFactory = $customerFactory;
        $this->customerAddressFactory = $customerAddressFactory;
        $this->extraFieldsFactory = $extraFieldsFactory;
        $this->customerCollectionFactory = $customerCollectionFactory;

        parent::__construct($context);
    }

    /**
     * @return $this
     */
    public function initCollection()
    {
        /** @var CustomerCollection customerCollection */
        $this->customerCollection = $this->customerCollectionFactory->create();

        return $this;
    }

    /**
     * @param int  $customerId
     * @param int  $websiteId
     * @param bool $toArray
     *
     * @return CustomerInterface|array
     */
    public function getOneCustomer($customerId, $websiteId, $toArray = false)
    {
        $this
            ->initCollection()
            ->setWhere('entity_id', $customerId, $customerId)
            ->joinSubscriptionStatus()
            ->getCustomersAttributeData(
                $customerId,
                $customerId,
                $websiteId
            )->getCustomersAddressesAttributeData(
                $customerId,
                $customerId,
                $websiteId
            );

        /** @var CustomerModel $customer */
        $customer = $this->getCustomerCollection()->fetchItem();

        return $this->buildCustomerObject($customer, $websiteId, $toArray);
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
        return $this->customerResource->handleIds($page, $pageSize, $websiteId);
    }

    /**
     * @return $this
     */
    public function joinSubscriptionStatus()
    {
        $this->customerResource->joinSubscriptionStatus($this->customerCollection);

        return $this;
    }

    /**
     * @param string $linkField
     * @param int    $min
     * @param int    $max
     *
     * @return $this
     */
    public function setWhere($linkField, $min, $max)
    {
        $this->customerCollection
            ->addFieldToFilter($linkField, ['from' => $min])
            ->addFieldToFilter($linkField, ['to' => $max]);

        return $this;
    }

    /**
     * @param string $linkField
     * @param string $direction
     *
     * @return $this
     */
    public function setOrder($linkField, $direction)
    {
        $this->customerCollection
            ->groupByAttribute($linkField)
            ->setOrder($linkField, $direction);

        return $this;
    }

    /**
     * @return CustomerCollection
     */
    public function getCustomerCollection()
    {
        return $this->customerCollection;
    }

    /**
     * @return string[]
     */
    public function getCustomerFields()
    {
        return $this->fields;
    }

    public function getCustomerExtraFields($websiteId)
    {
        if (null == $this->extraFields) {
            $this->extraFields = [];

            /** @var ConfigInterface $config */
            $config = $this->configFactory->create();

            $customerAttributes = $config->getConfigValue(
                AttributesApiInterface::TYPE_CUSTOMER . ConfigInterface::ATTRIBUTE_CONFIG_POST_TAG,
                $websiteId
            );

            if (is_array($customerAttributes)) {
                $this->extraFields = array_diff($customerAttributes, $this->getCustomerFields());
            }
        }

        return $this->extraFields;
    }

    /**
     * @return string[]
     */
    public function getCustomerAddressFields()
    {
        return $this->addressFields;
    }

    /**
     * @param int $websiteId
     *
     * @return string[]
     */
    public function getCustomerAddressExtraFields($websiteId)
    {
        if (null == $this->extraAddressFields) {
            $this->extraAddressFields = [];

            /** @var ConfigInterface $config */
            $config = $this->configFactory->create();

            $customerAddressAttributes = $config->getConfigValue(
                AttributesApiInterface::TYPE_CUSTOMER_ADDRESS . ConfigInterface::ATTRIBUTE_CONFIG_POST_TAG,
                $websiteId
            );

            if (is_array($customerAddressAttributes)) {
                $this->extraAddressFields = array_diff($customerAddressAttributes, $this->getCustomerAddressFields());
            }
        }

        return $this->extraAddressFields;
    }

    /**
     * @param int           $minId
     * @param int           $maxId
     * @param int           $websiteId
     * @param null|string[] $fields
     *
     * @return $this
     */
    public function getCustomersAttributeData($minId, $maxId, $websiteId, $fields = null)
    {
        if (!$fields) {
            $fields = array_merge($this->getCustomerFields(), $this->getCustomerExtraFields($websiteId));
        }

        $this->customerAttributeData = $this->customerResource->getAttributeData($minId, $maxId, $fields);

        return $this;
    }

    /**
     * @param int           $minId
     * @param int           $maxId
     * @param int           $websiteId
     * @param null|string[] $fields
     *
     * @return $this
     */
    public function getCustomersAddressesAttributeData($minId, $maxId, $websiteId, $fields = null)
    {
        if (!$fields) {
            $fields = array_merge($this->getCustomerAddressFields(), $this->getCustomerAddressExtraFields($websiteId));
        }

        $this->customerAddressAttributeData = $this->customerAddressResource->getAttributeData($minId, $maxId, $fields);

        return $this;
    }

    /**
     * @param int    $customerId
     * @param string $attributeCode
     *
     * @return string|null
     */
    private function handleWebsiteData($customerId, $attributeCode)
    {
        if (array_key_exists($customerId, $this->customerAttributeData)
            && array_key_exists($attributeCode, $this->customerAttributeData[$customerId])
        ) {
            return $this->customerAttributeData[$customerId][$attributeCode];
        }

        return null;
    }

    /**
     * @param int    $customerId
     * @param int    $addressId
     * @param string $attributeCode
     *
     * @return string|null
     */
    private function handleAddressWebsiteData($customerId, $addressId, $attributeCode)
    {
        if (array_key_exists($customerId, $this->customerAddressAttributeData)
            && array_key_exists($addressId, $this->customerAddressAttributeData[$customerId])
            && array_key_exists($attributeCode, $this->customerAddressAttributeData[$customerId][$addressId])
        ) {
            return $this->customerAddressAttributeData[$customerId][$addressId][$attributeCode];
        }

        return null;
    }

    /**
     * @param CustomerModel $customer
     * @param int           $websiteId
     * @param bool          $toArray
     *
     * @return CustomerInterface|array
     */
    public function buildCustomerObject($customer, $websiteId, $toArray = false)
    {
        $billingAddress = $this->getAddressFromCustomer(
            $customer->getId(),
            $customer->getData('default_billing'),
            $websiteId,
            $toArray
        );

        $shippingAddress = $this->getAddressFromCustomer(
            $customer->getId(),
            $customer->getData('default_shipping'),
            $websiteId,
            $toArray
        );

        if ($toArray) {
            $billingAddress = $billingAddress->getData();
            $shippingAddress = $shippingAddress->getData();
        }

        /** @var CustomerInterface $customerItem */
        $customerItem = $this->customerFactory->create()
            ->setId($customer->getId())
            ->setRpToken($customer->getRpToken())
            ->setRpTokenCreatedAt($customer->getRpTokenCreatedAt())
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($shippingAddress)
            ->setWebsiteId($customer->getWebsiteId())
            ->setStoreId($customer->getStoreId())
            ->setEmail($customer->getEmail())
            ->setGroupId($customer->getGroupId())
            ->setIsActive($customer->getData('is_active'))
            ->setPrefix($customer->getData('prefix'))
            ->setLastname($customer->getData('lastname'))
            ->setMiddlename($customer->getData('middlename'))
            ->setFirstname($customer->getData('firstname'))
            ->setSuffix($customer->getData('suffix'))
            ->setDob($customer->getData('dob'))
            ->setTaxvat($customer->getData('taxvat'))
            ->setGender($customer->getData('gender'))
            ->setAcceptsMarketing($customer->getData('accepts_marketing'))
            ->setCreatedAt($customer->getData('created_at'))
            ->setUpdatedAt($customer->getData('updated_at'));

        if ($this->getCustomerExtraFields($websiteId)) {
            $extraFields = [];
            foreach ($this->getCustomerExtraFields($websiteId) as $field) {
                $extraField = $this->extraFieldsFactory->create()
                    ->setKey($field)
                    ->setValue($this->handleWebsiteData($customer->getId(), $field));

                if ($toArray) {
                    $extraField = $extraField->getData();
                }

                $extraFields[] = $extraField;
            }
            $customerItem->setExtraFields($extraFields);
        }

        if ($toArray) {
            $customerItem = $customerItem->getData();
        }

        return $customerItem;
    }

    /**
     * @param int  $customerId
     * @param int  $addressId
     * @param int  $websiteId
     * @param bool $toArray
     *
     * @return CustomerAddressInterface
     */
    private function getAddressFromCustomer($customerId, $addressId, $websiteId, $toArray = false)
    {
        /** @var CustomerAddressInterface $address */
        $addressItem = $this->customerAddressFactory->create()
            ->setFirstname($this->handleAddressWebsiteData($customerId, $addressId, 'firstname'))
            ->setSuffix($this->handleAddressWebsiteData($customerId, $addressId, 'suffix'))
            ->setMiddlename($this->handleAddressWebsiteData($customerId, $addressId, 'middlename'))
            ->setLastname($this->handleAddressWebsiteData($customerId, $addressId, 'lastname'))
            ->setPrefix($this->handleAddressWebsiteData($customerId, $addressId, 'prefix'))
            ->setCity($this->handleAddressWebsiteData($customerId, $addressId, 'city'))
            ->setCompany($this->handleAddressWebsiteData($customerId, $addressId, 'company'))
            ->setCountryId($this->handleAddressWebsiteData($customerId, $addressId, 'country_id'))
            ->setFax($this->handleAddressWebsiteData($customerId, $addressId, 'fax'))
            ->setPostcode($this->handleAddressWebsiteData($customerId, $addressId, 'postcode'))
            ->setRegion($this->handleAddressWebsiteData($customerId, $addressId, 'region'))
            ->setStreet($this->handleAddressWebsiteData($customerId, $addressId, 'street'))
            ->setTelephone($this->handleAddressWebsiteData($customerId, $addressId, 'telephone'));

        if ($this->getCustomerAddressExtraFields($websiteId)) {
            $extraFields = [];
            foreach ($this->getCustomerAddressExtraFields($websiteId) as $field) {
                $extraField = $this->extraFieldsFactory->create()
                    ->setKey($field)
                    ->setValue($this->handleAddressWebsiteData($customerId, $addressId, $field));

                if ($toArray) {
                    $extraField = $extraField->getData();
                }

                $extraFields[] = $extraField;
            }
            $addressItem->setExtraFields($extraFields);
        }

        return $addressItem;
    }
}
