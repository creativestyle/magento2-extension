<?php

namespace Emartech\Emarsys\Model\Data;

use Emartech\Emarsys\Api\Data\ConfigInterface;
use Emartech\Emarsys\Api\Data\StoreConfigInterface;
use Emartech\Emarsys\Helper\Json as JsonSerializer;
use Magento\Framework\App\Config as ScopeConfig;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\DataObject;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Config
 * @package Emartech\Emarsys\Model\Data
 */
class Config extends DataObject implements ConfigInterface
{
    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var ScopeConfig
     */
    private $scopeConfig;

    /**
     * @var JsonSerializer
     */
    private $jsonSerializer;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Config constructor.
     *
     * @param WriterInterface       $configWriter
     * @param ScopeConfig           $scopeConfig
     * @param JsonSerializer        $jsonSerializer
     * @param StoreManagerInterface $storeManager
     * @param array                 $data
     */
    public function __construct(
        WriterInterface $configWriter,
        ScopeConfig $scopeConfig,
        JsonSerializer $jsonSerializer,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($data);

        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
        $this->jsonSerializer = $jsonSerializer;
        $this->storeManager = $storeManager;
    }

    /**
     * @return string
     */
    public function getCollectCustomerEvents()
    {
        return $this->getData(self::CUSTOMER_EVENTS);
    }

    /**
     * @param string $collectCustomerEvents
     *
     * @return $this
     */
    public function setCollectCustomerEvents($collectCustomerEvents)
    {
        $this->setData(self::CUSTOMER_EVENTS, $collectCustomerEvents);

        return $this;
    }

    /**
     * @return string
     */
    public function getCollectSalesEvents()
    {
        return $this->getData(self::CUSTOMER_EVENTS);
    }

    /**
     * @param string $collectSalesEvents
     *
     * @return $this
     */
    public function setCollectSalesEvents($collectSalesEvents)
    {
        $this->setData(self::SALES_EVENTS, $collectSalesEvents);

        return $this;
    }

    /**
     * @return string
     */
    public function getCollectMarketingEvents()
    {
        return $this->getData(self::MARKETING_EVENTS);
    }

    /**
     * @param string $collectMarketingEvents
     *
     * @return $this
     */
    public function setCollectMarketingEvents($collectMarketingEvents)
    {
        $this->setData(self::MARKETING_EVENTS, $collectMarketingEvents);

        return $this;
    }

    /**
     * @return string
     */
    public function getMerchantId()
    {
        return $this->getData(self::MERCHANT_ID);
    }

    /**
     * @param string $merchantId
     *
     * @return $this
     */
    public function setMerchantId($merchantId)
    {
        $this->setData(self::MERCHANT_ID, $merchantId);

        return $this;
    }

    /**
     * @return string
     */
    public function getInjectSnippet()
    {
        return $this->getData(self::INJECT_WEBEXTEND_SNIPPETS);
    }

    /**
     * @param string $injectSnippet
     *
     * @return $this
     */
    public function setInjectSnippet($injectSnippet)
    {
        $this->setData(self::INJECT_WEBEXTEND_SNIPPETS, $injectSnippet);

        return $this;
    }

    /**
     * @return string
     */
    public function getWebTrackingSnippetUrl()
    {
        return $this->getData(self::SNIPPET_URL);
    }

    /**
     * @param string $webTrackingSnippetUrl
     *
     * @return $this
     */
    public function setWebTrackingSnippetUrl($webTrackingSnippetUrl)
    {
        $this->setData(self::SNIPPET_URL, $webTrackingSnippetUrl);

        return $this;
    }

    /**
     * @param string $xmlPostPath
     * @param string $value
     * @param int    $scopeId
     * @param string $scope
     *
     * @return bool
     */
    public function setConfigValue($xmlPostPath, $value, $scopeId, $scope = ConfigInterface::SCOPE_TYPE_DEFAULT)
    {
        $xmlPath = self::XML_PATH_EMARSYS_PRE_TAG . trim($xmlPostPath, '/');

        if (is_array($value)) {
            $value = array_map(function ($item) {
                if ($item instanceof DataObject) {
                    $item = $item->toArray();
                }
                return $item;
            }, $value);
        }

        if (!is_string($value) && $value !== null) {
            $value = $this->jsonSerializer->serialize($value);
        }

        $oldConfigValue = $this->scopeConfig->getValue($xmlPath, $scope, $scopeId);

        if ($value == $oldConfigValue) {
            return false;
        }

        $this->configWriter->save($xmlPath, $value, $scope, $scopeId);

        return true;
    }

    /**
     * @param string   $key
     * @param null|int $websiteId
     *
     * @return string|string[]
     */
    public function getConfigValue($key, $websiteId = null)
    {
        if (null === $websiteId) {
            try {
                $websiteId = $this->storeManager->getWebsite()->getId();
            } catch (\Exception $e) {
                $websiteId = 0;
            }
        }

        $value = $this->scopeConfig->getValue(self::XML_PATH_EMARSYS_PRE_TAG . $key, 'websites', $websiteId);

        try {
            $returnValue = $this->jsonSerializer->unserialize($value);
        } catch (\InvalidArgumentException $e) {
            $returnValue = $value;
        } catch (\Exception $e) {
            $returnValue = '';
        }

        return $returnValue;
    }

    /**
     * @param string   $key
     * @param null|int $websiteId
     *
     * @return bool
     */
    public function isEnabledForWebsite($key, $websiteId = null)
    {
        return $this->getConfigValue($key, $websiteId) === self::CONFIG_ENABLED;
    }

    /**
     * @param string   $key
     * @param null|int $storeId
     *
     * @return bool
     */
    public function isEnabledForStore($key, $storeId = null)
    {
        try {
            if (!$storeId) {
                $storeId = $this->storeManager->getStore()->getId();
            }

            $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();

            if (!$this->isEnabledForWebsite($key, $websiteId)) {
                return false;
            }

            $stores = $this->getConfigValue(self::STORE_SETTINGS, $websiteId);
            if (is_array($stores)) {
                foreach ($stores as $store) {
                    if ($store[StoreConfigInterface::STORE_ID_KEY] == $storeId) {
                        return true;
                    }
                }
            }
        } catch (\Exception $e) { //@codingStandardsIgnoreLine
        }

        return false;
    }

    /**
     * @return void
     */
    public function cleanScope()
    {
        $this->scopeConfig->clean();
    }

    /**
     * @return StoreConfigInterface[]
     */
    public function getStoreSettings()
    {
        return $this->getData(self::STORE_SETTINGS);
    }

    /**
     * @param StoreConfigInterface[] $storeSettings
     *
     * @return $this
     */
    public function setStoreSettings($storeSettings)
    {
        $this->setData(self::STORE_SETTINGS, $storeSettings);

        return $this;
    }

    /**
     * @return \Magento\Store\Api\Data\WebsiteInterface[]
     */
    public function getAvailableWebsites()
    {
        return $this->storeManager->getWebsites();
    }
}
