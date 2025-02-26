<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.io and write us
 * an email on hello@bitbag.io.
 */

declare(strict_types=1);

namespace BitBag\SyliusElasticsearchPlugin\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Loevgaard\SyliusBrandPlugin\Model\BrandInterface;
use Sylius\Component\Attribute\Model\AttributeInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface as BaseTaxonRepositoryInterface;

final class TaxonRepository implements TaxonRepositoryInterface
{
    private BaseTaxonRepositoryInterface|EntityRepository $baseTaxonRepository;

    private ProductRepositoryInterface|EntityRepository $productRepository;

    private string $productTaxonEntityClass;

    private string $productAttributeEntityClass;

    public function __construct(
        BaseTaxonRepositoryInterface $baseTaxonRepository,
        ProductRepositoryInterface $productRepository,
        string $productTaxonEntityClass,
        string $productAttributeEntityClass
    ) {
        $this->baseTaxonRepository = $baseTaxonRepository;
        $this->productRepository = $productRepository;
        $this->productTaxonEntityClass = $productTaxonEntityClass;
        $this->productAttributeEntityClass = $productAttributeEntityClass;
    }

    public function getTaxonsByAttributeViaProduct(AttributeInterface $attribute): array
    {
        return $this->baseTaxonRepository
            ->createQueryBuilder('t')
            ->distinct(true)
            ->select('t')
            ->leftJoin($this->productTaxonEntityClass, 'pt', Join::WITH, 'pt.taxon = t.id')
            ->where(
                'pt.product IN(' .
                $this
                    ->productRepository->createQueryBuilder('p')
                    ->leftJoin($this->productAttributeEntityClass, 'pav', Join::WITH, 'pav.subject = p.id')
                    ->where('pav.attribute = :attribute')
                    ->getQuery()
                    ->getDQL()
                . ')'
            )
            ->setParameter(':attribute', $attribute)
            ->getQuery()
            ->getResult()
        ;
    }

    public function getTaxonsByBrandViaProduct(BrandInterface $brand): array
    {
        return $this->baseTaxonRepository
            ->createQueryBuilder('t')
            ->distinct(true)
            ->select('t')
            ->leftJoin($this->productTaxonEntityClass, 'pt', Join::WITH, 'pt.taxon = t.id')
            ->where(
                'pt.product IN(' .
                $this
                    ->productRepository->createQueryBuilder('p')
                    ->where('p.brand = :brand')
                    ->getQuery()
                    ->getDQL()
                . ')'
            )
            ->setParameter(':brand', $brand)
            ->getQuery()
            ->getResult()
            ;
    }
}
