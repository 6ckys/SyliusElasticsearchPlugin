<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.io and write us
 * an email on hello@bitbag.io.
 */

declare(strict_types=1);

namespace BitBag\SyliusElasticsearchPlugin\Facet;

use BitBag\SyliusElasticsearchPlugin\PropertyNameResolver\ConcatedNameResolverInterface;
use Elastica\Aggregation\AbstractAggregation;
use Elastica\Aggregation\Terms;
use Elastica\Query\AbstractQuery;
use Elastica\Query\Terms as TermsQuery;
use RuntimeException;
use function sprintf;
use Loevgaard\SyliusBrandPlugin\Model\BrandInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class BrandFacet implements FacetInterface
{
    private ConcatedNameResolverInterface $brandNameResolver;

    private RepositoryInterface $brandRepository;

    private string $brandCode;

    private LocaleContextInterface $localeContext;

    public function __construct(
        ConcatedNameResolverInterface $brandNameResolver,
        RepositoryInterface $brandRepository,
        string $brandCode,
        LocaleContextInterface $localeContext
    ) {
        $this->brandNameResolver = $brandNameResolver;
        $this->brandRepository = $brandRepository;
        $this->brandCode = $brandCode;
        $this->localeContext = $localeContext;
    }

    public function getAggregation(): AbstractAggregation
    {
        $aggregation = new Terms('');
        $aggregation->setField($this->getFieldName());

        return $aggregation;
    }

    public function getQuery(array $selectedBuckets): AbstractQuery
    {
        return new TermsQuery($this->getFieldName(), $selectedBuckets);
    }

    public function getBucketLabel(array $bucket): string
    {
        $label = ucwords(str_replace('_', ' ', $bucket['key']));

        return sprintf('%s (%s)', $label, $bucket['doc_count']);
    }

    public function getLabel(): string
    {
        return $this->getProductBrand()->getName();
    }

    private function getFieldName(): string
    {
        return sprintf(
            '%s_%s.keyword',
            $this->brandNameResolver->resolvePropertyName($this->brandCode),
            $this->localeContext->getLocaleCode()
        );
    }

    private function getProductBrand(): BrandInterface
    {
        $brand = $this->brandRepository->findOneBy(['code' => $this->brandCode]);
        if (!$brand instanceof BrandInterface) {
            throw new RuntimeException(sprintf('Cannot find brand with code "%s"', $this->brandCode));
        }

        return $brand;
    }
}
