<?php

namespace Jh\CoreBugConfigurableGetSubProducts\ConfigurableProduct\Pricing\Price;

use Jh\CoreBugConfigurableGetSubProducts\Catalog\Model\ResourceModel\Product\LinkedProductSelectBuilderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Pricing\Price\ConfigurableOptionsProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\RequestSafetyInterface;

/**
 * @author Michael Woodward <michael@wearejh.com>
 * @see https://github.com/magento/magento2/pull/7030
 */
class ConfigurableOptionsProvider implements ConfigurableOptionsProviderInterface
{
    /**
     * @var Configurable
     */
    private $configurable;

    /**
     * @var RequestSafetyInterface
     */
    private $requestSafety;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var LinkedProductSelectBuilderInterface
     */
    private $linkedProductSelectBuilder;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var ProductInterface[]
     */
    private $products;

    /**
     * @param Configurable $configurable
     * @param ResourceConnection $resourceConnection
     * @param LinkedProductSelectBuilderInterface $linkedProductSelectBuilder
     * @param CollectionFactory $collectionFactory
     * @param RequestSafetyInterface $requestSafety
     */
    public function __construct(
        Configurable $configurable,
        ResourceConnection $resourceConnection,
        LinkedProductSelectBuilderInterface $linkedProductSelectBuilder,
        CollectionFactory $collectionFactory,
        RequestSafetyInterface $requestSafety
    ) {
        $this->configurable               = $configurable;
        $this->resource                   = $resourceConnection;
        $this->linkedProductSelectBuilder = $linkedProductSelectBuilder;
        $this->collectionFactory          = $collectionFactory;
        $this->requestSafety              = $requestSafety;
    }

    /**
     * {@inheritdoc}
     */
    public function getProducts(ProductInterface $product)
    {
        if (!isset($this->products[$product->getId()])) {
            if ($this->requestSafety->isSafeMethod()) {
                $productIds = $this->resource->getConnection()->fetchCol(
                    '(' . implode(
                        ') UNION (',
                        $this->linkedProductSelectBuilder->build($product->getId(), PHP_INT_MAX)
                    ) . ')'
                );

                $this->products[$product->getId()] = $this->collectionFactory->create()
                    ->addAttributeToSelect(['price', 'special_price'])
                    ->addIdFilter($productIds);
            } else {
                $this->products[$product->getId()] = $this->configurable->getUsedProducts($product);
            }
        }

        return $this->products[$product->getId()];
    }
}
