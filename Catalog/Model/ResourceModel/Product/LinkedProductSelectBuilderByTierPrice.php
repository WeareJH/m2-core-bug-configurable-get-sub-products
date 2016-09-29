<?php

namespace Jh\CoreBugConfigurableGetSubProducts\Catalog\Model\ResourceModel\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Data;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @author Michael Woodward <michael@wearejh.com>
 * @see https://github.com/magento/magento2/pull/7030
 */
class LinkedProductSelectBuilderByTierPrice implements LinkedProductSelectBuilderInterface
{
    /**
     * Default website id
     */
    const DEFAULT_WEBSITE_ID = 0;

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
     * @var Data
     */
    private $catalogHelper;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param Session $customerSession
     * @param Data $catalogHelper
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        Session $customerSession,
        Data $catalogHelper,
        MetadataPool $metadataPool
    ) {
        $this->storeManager    = $storeManager;
        $this->resource        = $resourceConnection;
        $this->customerSession = $customerSession;
        $this->catalogHelper   = $catalogHelper;
        $this->metadataPool    = $metadataPool;
    }

    /**
     * {@inheritdoc}
     */
    public function build($productId, $limit = 1)
    {
        $linkField    = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $productTable = $this->resource->getTableName('catalog_product_entity');

        $priceSelect = $this->resource->getConnection()->select()
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
                ['t' => $this->resource->getTableName('catalog_product_entity_tier_price')],
                "t.$linkField = child.$linkField",
                []
            )->where('parent.entity_id = ? ', $productId)
            ->where('t.all_groups = 1 OR customer_group_id = ?', $this->customerSession->getCustomerGroupId())
            ->where('t.qty = ?', 1)
            ->order('t.value ' . Select::SQL_ASC)
            ->limit($limit);

        $priceSelectDefault = clone $priceSelect;
        $priceSelectDefault->where('t.website_id = ?', self::DEFAULT_WEBSITE_ID);
        $select[] = $priceSelectDefault;

        if (!$this->catalogHelper->isPriceGlobal()) {
            $priceSelect->where('t.website_id = ?', $this->storeManager->getStore()->getWebsiteId());
            $select[] = $priceSelect;
        }

        return $select;
    }
}
