<?php

namespace Jh\CoreBugConfigurableGetSubProducts\Catalog\Model\ResourceModel\Product;

use Magento\Framework\DB\Select;

/**
 * @author Michael Woodward <michael@wearejh.com>
 * @see https://github.com/magento/magento2/pull/7030
 */
interface LinkedProductSelectBuilderInterface
{
    /**
     * @param int $productId
     * @param int $limit
     * @return Select[]
     */
    public function build($productId, $limit = 1);
}
