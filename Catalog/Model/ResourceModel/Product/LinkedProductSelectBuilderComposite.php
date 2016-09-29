<?php

namespace Jh\CoreBugConfigurableGetSubProducts\Catalog\Model\ResourceModel\Product;

/**
 * @author Michael Woodward <michael@wearejh.com>
 * @see https://github.com/magento/magento2/pull/7030
 */
class LinkedProductSelectBuilderComposite implements LinkedProductSelectBuilderInterface
{
    /**
     * @var LinkedProductSelectBuilderInterface[]
     */
    private $linkedProductSelectBuilder;

    /**
     * @param LinkedProductSelectBuilderInterface[] $linkedProductSelectBuilder
     */
    public function __construct($linkedProductSelectBuilder)
    {
        $this->linkedProductSelectBuilder = $linkedProductSelectBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function build($productId, $limit = 1)
    {
        $select = [];
        foreach ($this->linkedProductSelectBuilder as $productSelectBuilder) {
            $select = array_merge($select, $productSelectBuilder->build($productId, $limit));
        }

        return $select;
    }
}
