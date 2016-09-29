<?php

namespace Jh\CoreBugConfigurableGetSubProducts\Catalog\Model\ResourceModel\Product\Indexer;

use Jh\CoreBugConfigurableGetSubProducts\Catalog\Model\ResourceModel\Product\LinkedProductSelectBuilderInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DB\Select;

/**
 * @author Michael Woodward <michael@wearejh.com>
 * @see https://github.com/magento/magento2/pull/7030
 */
class LinkedProductSelectBuilderByIndexPrice implements LinkedProductSelectBuilderInterface
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
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param Session $customerSession
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        Session $customerSession,
        MetadataPool $metadataPool
    ) {
        $this->storeManager    = $storeManager;
        $this->resource        = $resourceConnection;
        $this->customerSession = $customerSession;
        $this->metadataPool    = $metadataPool;
    }
    /**
     * {@inheritdoc}
     */
    public function build($productId, $limit = 1)
    {
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
                    ['t' => $this->resource->getTableName('catalog_product_index_price')],
                    't.entity_id = child.entity_id',
                    []
                )->where('parent.entity_id = ? ', $productId)
                ->where('t.website_id = ?', $this->storeManager->getStore()->getWebsiteId())
                ->where('t.customer_group_id = ?', $this->customerSession->getCustomerGroupId())
                ->order('t.min_price ' . Select::SQL_ASC)
                ->limit($limit)
        ];
    }
}
