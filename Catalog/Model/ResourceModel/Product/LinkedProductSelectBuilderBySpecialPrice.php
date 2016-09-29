<?php

namespace Jh\CoreBugConfigurableGetSubProducts\Catalog\Model\ResourceModel\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;

/**
 * @author Michael Woodward <michael@wearejh.com>
 * @see https://github.com/magento/magento2/pull/7030
 */
class LinkedProductSelectBuilderBySpecialPrice implements LinkedProductSelectBuilderInterface
{
    /**
    * @var \Magento\Store\Model\StoreManagerInterface
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
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param Config $eavConfig
     * @param Data $catalogHelper
     * @param DateTime $dateTime
     * @param TimezoneInterface $localeDate
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        Config $eavConfig,
        Data $catalogHelper,
        DateTime $dateTime,
        TimezoneInterface $localeDate,
        MetadataPool $metadataPool
    ) {
        $this->storeManager  = $storeManager;
        $this->resource      = $resourceConnection;
        $this->eavConfig     = $eavConfig;
        $this->catalogHelper = $catalogHelper;
        $this->dateTime      = $dateTime;
        $this->localeDate    = $localeDate;
        $this->metadataPool  = $metadataPool;
    }

    /**
     * {@inheritdoc}
     */
    public function build($productId, $limit = 1)
    {
        $linkField             = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $connection            = $this->resource->getConnection();
        $specialPriceAttribute = $this->eavConfig->getAttribute(Product::ENTITY, 'special_price');
        $specialPriceFromDate  = $this->eavConfig->getAttribute(Product::ENTITY, 'special_from_date');
        $specialPriceToDate    = $this->eavConfig->getAttribute(Product::ENTITY, 'special_to_date');
        $timestamp             = $this->localeDate->scopeTimeStamp($this->storeManager->getStore());
        $currentDate           = $this->dateTime->formatDate($timestamp, false);
        $productTable          = $this->resource->getTableName('catalog_product_entity');

        $specialPrice = $this->resource->getConnection()->select()
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
                ['t' => $specialPriceAttribute->getBackendTable()],
                "t.$linkField = child.$linkField",
                []
            )->joinLeft(
                ['special_from' => $specialPriceFromDate->getBackendTable()],
                $connection->quoteInto(
                    "t.{$linkField} = special_from.{$linkField} AND special_from.attribute_id = ?",
                    $specialPriceFromDate->getAttributeId()
                ),
                ''
            )->joinLeft(
                ['special_to' => $specialPriceToDate->getBackendTable()],
                $connection->quoteInto(
                    "t.{$linkField} = special_to.{$linkField} AND special_to.attribute_id = ?",
                    $specialPriceToDate->getAttributeId()
                ),
                ''
            )->where('parent.entity_id = ? ', $productId)
            ->where('t.attribute_id = ?', $specialPriceAttribute->getAttributeId())
            ->where('t.value IS NOT NULL')
            ->where(
                'special_from.value IS NULL OR ' . $connection->getDatePartSql('special_from.value') .' <= ?',
                $currentDate
            )->where(
                'special_to.value IS NULL OR ' . $connection->getDatePartSql('special_to.value') .' >= ?',
                $currentDate
            )->order('t.value ' . Select::SQL_ASC)
            ->limit($limit);

        $specialPriceDefault = clone $specialPrice;
        $specialPriceDefault->where('t.store_id = ?', Store::DEFAULT_STORE_ID);
        $select[] = $specialPriceDefault;

        if (!$this->catalogHelper->isPriceGlobal()) {
            $specialPrice->where('t.store_id = ?', $this->storeManager->getStore()->getId());
            $select[] = $specialPrice;
        }

        return $select;
    }
}
