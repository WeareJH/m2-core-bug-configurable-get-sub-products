<?php

namespace Jh\CoreBugConfigurableGetSubProducts\CatalogInventory\Helper;

/**
 * @author Michael Woodward <michael@wearejh.com>
 */
class Stock extends \Magento\CatalogInventory\Helper\Stock
{
    /**
     * @inheritdoc
     */
    public function addIsInStockFilterToCollection($collection)
    {
        $stockFlag = 'has_stock_status_filter';
        if (!$collection->hasFlag($stockFlag)) {
            $isShowOutOfStock = $this->scopeConfig->getValue(
                \Magento\CatalogInventory\Model\Configuration::XML_PATH_SHOW_OUT_OF_STOCK,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
            $resource = $this->getStockStatusResource();
            $resource->addStockDataToCollection(
                $collection,
                !$isShowOutOfStock && $collection->getFlag('require_stock_items')
            );
            $collection->setFlag($stockFlag, true);
        }
    }
}
