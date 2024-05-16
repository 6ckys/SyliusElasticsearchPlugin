<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.io and write us
 * an email on hello@bitbag.io.
 */

declare(strict_types=1);

namespace BitBag\SyliusElasticsearchPlugin\PropertyBuilder;

use BitBag\SyliusElasticsearchPlugin\Repository\TaxonRepositoryInterface;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Loevgaard\SyliusBrandPlugin\Model\BrandInterface;
use Sylius\Component\Core\Model\TaxonInterface;

final class BrandTaxonsBuilder extends AbstractBuilder
{
    protected TaxonRepositoryInterface $taxonRepository;

    private string $taxonsProperty;

    private array $excludedBrands;

    private bool $includeAllDescendants;

    public function __construct(
        TaxonRepositoryInterface $taxonRepository,
        string $taxonsProperty,
        bool $includeAllDescendants,
        array $excludedBrands = []
    ) {
        $this->taxonRepository = $taxonRepository;
        $this->taxonsProperty = $taxonsProperty;
        $this->includeAllDescendants = $includeAllDescendants;
        $this->excludedBrands = $excludedBrands;
    }

    public function consumeEvent(PostTransformEvent $event): void
    {
        $documentBrand = $event->getObject();

        if (!$documentBrand instanceof BrandInterface
            || in_array($documentBrand->getCode(), $this->excludedBrands)
        ) {
            return;
        }

        $taxons = $this->taxonRepository->getTaxonsByBrandViaProduct($documentBrand);
        $taxonCodes = [];

        /** @var TaxonInterface $taxon */
        foreach ($taxons as $taxon) {
            $taxonCodes[] = $taxon->getCode();

            if (true === $this->includeAllDescendants) {
                foreach ($taxon->getAncestors() as $ancestor) {
                    $taxonCodes[] = $ancestor->getCode();
                }
            }
        }

        $document = $event->getDocument();

        $document->set($this->taxonsProperty, $taxonCodes);
    }
}
