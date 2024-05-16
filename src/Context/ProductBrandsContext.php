<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.io and write us
 * an email on hello@bitbag.io.
 */

declare(strict_types=1);

namespace BitBag\SyliusElasticsearchPlugin\Context;

use BitBag\SyliusElasticsearchPlugin\Finder\ProductBrandsFinderInterface;

final class ProductBrandsContext implements ProductBrandsContextInterface
{
    private TaxonContextInterface $taxonContext;

    private ProductBrandsFinderInterface $brandsFinder;

    public function __construct(
        TaxonContextInterface $taxonContext,
        ProductBrandsFinderInterface $brandsFinder
    ) {
        $this->taxonContext = $taxonContext;
        $this->brandsFinder = $brandsFinder;
    }

    public function getBrands(): ?array
    {
        $taxon = $this->taxonContext->getTaxon();

        return $this->brandsFinder->findByTaxon($taxon);
    }
}
