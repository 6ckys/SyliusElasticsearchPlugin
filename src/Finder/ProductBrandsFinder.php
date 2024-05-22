<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.io and write us
 * an email on hello@bitbag.io.
 */

declare(strict_types=1);

namespace BitBag\SyliusElasticsearchPlugin\Finder;

use BitBag\SyliusElasticsearchPlugin\QueryBuilder\QueryBuilderInterface;
use FOS\ElasticaBundle\Finder\FinderInterface;
use Sylius\Component\Core\Model\TaxonInterface;

final class ProductBrandsFinder implements ProductBrandsFinderInterface
{
    private FinderInterface $brandsFinder;

    private QueryBuilderInterface $brandsByTaxonQueryBuilder;

    private string $taxonsProperty;

    public function __construct(
        FinderInterface $brandsFinder,
        QueryBuilderInterface $brandsByTaxonQueryBuilder,
        string $taxonsProperty
    ) {
        $this->brandsFinder = $brandsFinder;
        $this->brandsByTaxonQueryBuilder = $brandsByTaxonQueryBuilder;
        $this->taxonsProperty = $taxonsProperty;
    }

    public function findByTaxon(TaxonInterface $taxon): ?array
    {
        $data = [];
        $data[$this->taxonsProperty] = strtolower($taxon->getCode());

        $query = $this->brandsByTaxonQueryBuilder->buildQuery($data);

        return $this->brandsFinder->find($query, 2000);
    }
}
