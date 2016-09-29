<?php

namespace Jh\CoreBugConfigurableGetSubProducts\Catalog\Model\ResourceModel\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @author Michael Woodward <michael@wearejh.com>
 * @see https://github.com/magento/magento2/pull/7030
 */
class LinkedProductSelectBuilderByBasePrice implements LinkedProductSelectBuilderInterface
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
     * @var Config
     */
    private $eavConfig;

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
     * @param Config $eavConfig
     * @param Data $catalogHelper
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        Config $eavConfig,
        Data $catalogHelper,
        MetadataPool $metadataPool
    ) {
        $this->storeManager  = $storeManager;
        $this->resource      = $resourceConnection;
        $this->eavConfig     = $eavConfig;
        $this->catalogHelper = $catalogHelper;
        $this->metadataPool  = $metadataPool;
    }

    /**
     * {@inheritdoc}
     */
    public function build($productId, $limit = 1)
    {
        $linkField      = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $priceAttribute = $this->eavConfig->getAttribute(Product::ENTITY, 'price');
        $productTable   = $this->resource->getTableName('catalog_product_entity');

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
                ['t' => $priceAttribute->getBackendTable()],
                "t.$linkField = child.$linkField",
                []
            )->where('parent.entity_id = ? ', $productId)
            ->where('t.attribute_id = ?', $priceAttribute->getAttributeId())
            ->where('t.value IS NOT NULL')
            ->order('t.value ' . Select::SQL_ASC)
            ->limit($limit);

        $priceSelectDefault = clone $priceSelect;
        $priceSelectDefault->where('t.store_id = ?', Store::DEFAULT_STORE_ID);
        $select[] = $priceSelectDefault;

        if (!$this->catalogHelper->isPriceGlobal()) {
            $priceSelect->where('t.store_id = ?', $this->storeManager->getStore()->getId());
            $select[] = $priceSelect;
        }

        return $select;
    }
}
