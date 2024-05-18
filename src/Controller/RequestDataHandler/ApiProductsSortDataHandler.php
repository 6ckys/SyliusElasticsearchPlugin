<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.io and write us
 * an email on hello@bitbag.io.
 */

declare(strict_types=1);

namespace BitBag\SyliusElasticsearchPlugin\Controller\RequestDataHandler;

use App\Entity\Taxonomy\Taxon;
use BitBag\SyliusElasticsearchPlugin\PropertyNameResolver\ConcatedNameResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use UnexpectedValueException;

final class ApiProductsSortDataHandler implements SortDataHandlerInterface
{
    private ConcatedNameResolverInterface $channelPricingNameResolver;

    private ChannelContextInterface $channelContext;

    private EntityManagerInterface $entityManager;

    private ConcatedNameResolverInterface $taxonPositionNameResolver;

    private string $soldUnitsProperty;

    private string $createdAtProperty;

    private string $pricePropertyPrefix;

    public function __construct(
        ConcatedNameResolverInterface $channelPricingNameResolver,
        ChannelContextInterface $channelContext,
        EntityManagerInterface $entityManager,
        ConcatedNameResolverInterface $taxonPositionNameResolver,
        string $soldUnitsProperty,
        string $createdAtProperty,
        string $pricePropertyPrefix
    ) {
        $this->channelPricingNameResolver = $channelPricingNameResolver;
        $this->channelContext = $channelContext;
        $this->entityManager = $entityManager;
        $this->taxonPositionNameResolver = $taxonPositionNameResolver;
        $this->soldUnitsProperty = $soldUnitsProperty;
        $this->createdAtProperty = $createdAtProperty;
        $this->pricePropertyPrefix = $pricePropertyPrefix;
    }

    public function retrieveData(array $requestData): array
    {
        $data = [];
        $positionSortingProperty = $this->getPositionSortingProperty($requestData);

        $orderBy = $requestData[self::ORDER_BY_INDEX] ?? $positionSortingProperty;
        $sort = $requestData[self::SORT_INDEX] ?? self::SORT_ASC_INDEX;

        $availableSorters = [$positionSortingProperty, $this->soldUnitsProperty, $this->createdAtProperty, $this->pricePropertyPrefix];
        $availableSorting = [self::SORT_ASC_INDEX, self::SORT_DESC_INDEX];

        if (!in_array($orderBy, $availableSorters) || !in_array($sort, $availableSorting)) {
            throw new UnexpectedValueException();
        }

        if ($this->pricePropertyPrefix === $orderBy) {
            $channelCode = $this->channelContext->getChannel()->getCode();
            $orderBy = $this->channelPricingNameResolver->resolvePropertyName($channelCode);
        }

        $data['sort'] = [$orderBy => ['order' => strtolower($sort), 'unmapped_type' => 'keyword']];

        return $data;
    }

    private function getPositionSortingProperty(array $requestData): string
    {
        $channel = $this->channelContext->getChannel();
        $taxonCode = $this->entityManager->getRepository(Taxon::class)
            ->findOneBySlug($requestData['slug'], $channel->getDefaultLocale()->getCode())
            ->getCode();

        return $this->taxonPositionNameResolver->resolvePropertyName($taxonCode);
    }
}
