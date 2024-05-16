<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.io and write us
 * an email on hello@bitbag.io.
 */

declare(strict_types=1);

namespace BitBag\SyliusElasticsearchPlugin\QueryBuilder;

use BitBag\SyliusElasticsearchPlugin\PropertyNameResolver\ConcatedNameResolverInterface;
use Elastica\Query\AbstractQuery;
use Elastica\Query\MatchQuery;
use Elastica\Query\MultiMatch;
use Elastica\Query\Wildcard;
use Elastica\Query\QueryString;
use Elastica\Query\SimpleQueryString;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final class ContainsNameQueryBuilder implements QueryBuilderInterface
{
    private LocaleContextInterface $localeContext;

    private ConcatedNameResolverInterface $productNameNameResolver;

    private string $namePropertyPrefix;

    public function __construct(
        LocaleContextInterface $localeContext,
        ConcatedNameResolverInterface $productNameNameResolver,
        string $namePropertyPrefix
    ) {
        $this->localeContext = $localeContext;
        $this->productNameNameResolver = $productNameNameResolver;
        $this->namePropertyPrefix = $namePropertyPrefix;
    }

    public function buildQuery(array $data): ?AbstractQuery
    {
        $localeCode = $this->localeContext->getLocaleCode();
        $propertyName = $this->productNameNameResolver->resolvePropertyName($localeCode);

        if (!$name = $data[$this->namePropertyPrefix]) {
            return null;
        }

        if (count(explode(" ", $name)) > 1) {
            $nameQuery = new SimpleQueryString($name);
            $nameQuery->setQuery("*".$name."*");
            $nameQuery->setDefaultOperator("AND");
            $nameQuery->setFields([$propertyName]);
        } else {
            $nameQuery = new QueryString();
            $nameQuery->setQuery("*".$name."*");
            $nameQuery->setAnalyzeWildcard(true);
            $nameQuery->setFields([$propertyName]);
        }
        return $nameQuery;
    }
}
