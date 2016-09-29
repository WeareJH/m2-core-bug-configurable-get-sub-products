<?php

namespace Jh\CoreBugConfigurableGetSubProducts\Catalog\Model\ResourceModel\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @author Michael Woodward <michael@wearejh.com>
 * @see https://github.com/magento/magento2/pull/7030
 */
class LinkedProductSelectBuilderByCatalogRulePrice implements LinkedProductSelectBuilderInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var TimezoneInterface
     */
    private $localeDate;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param Session $customerSession
     * @param DateTime $dateTime
     * @param TimezoneInterface $localeDate
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        Session $customerSession,
        DateTime $dateTime,
        TimezoneInterface $localeDate,
        MetadataPool $metadataPool
    ) {
        $this->storeManager    = $storeManager;
        $this->resource        = $resourceConnection;
        $this->customerSession = $customerSession;
        $this->dateTime        = $dateTime;
        $this->localeDate      = $localeDate;
        $this->metadataPool    = $metadataPool;
    }
    /**
     * {@inheritdoc}
     */
    public function build($productId, $limit = 1)
    {
        $timestamp    = $this->localeDate->scopeTimeStamp($this->storeManager->getStore());
        $currentDate  = $this->dateTime->formatDate($timestamp, false);
        $linkField    = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $productTable = $this->resource->getTableName('catalog_product_entity');

        return [
            $this->resource->getConnection()->select()
                ->from(['parent' => $productTable], '')
                ->joinInner(
                    ['link' => $this->resource->getTableName('catalog_product_relation')],
                    "link.parent_id = parent.$linkField",
                    []
                )->joinInner(
                    ['child' => $productTable],
                    "child.entity_id = link.child_id",
                    ['entity_id']
                )->joinInner(
                    ['t' => $this->resource->getTableName('catalogrule_product_price')],
                    't.product_id = child.entity_id',
                    []
                )->where('parent.entity_id = ? ', $productId)
                ->where('t.website_id = ?', $this->storeManager->getStore()->getWebsiteId())
                ->where('t.customer_group_id = ?', $this->customerSession->getCustomerGroupId())
                ->where('t.rule_date = ?', $currentDate)
                ->order('t.rule_price ' . Select::SQL_ASC)
                ->limit($limit)
        ];

    }
}
