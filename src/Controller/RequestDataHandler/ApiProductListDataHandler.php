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

use App\Entity\Channel\Channel;
use App\Entity\Taxonomy\Taxon;
use App\Entity\Taxonomy\TaxonTranslation;
use BitBag\SyliusElasticsearchPlugin\Finder\ProductAttributesFinderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Attribute\AttributeType\CheckboxAttributeType;
use Sylius\Component\Attribute\AttributeType\IntegerAttributeType;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Product\Model\ProductAttribute;
use Webmozart\Assert\Assert;

final class ApiProductListDataHandler implements DataHandlerInterface
{
    private EntityManagerInterface $entityManager;

    private ChannelContextInterface $channelContext;

    private ProductAttributesFinderInterface $attributesFinder;

    private string $namePropertyPrefix;

    private string $taxonsProperty;

    private string $optionPropertyPrefix;

    private string $attributePropertyPrefix;

    private string $brandPropertyPrefix;

    public function __construct(
        EntityManagerInterface $entityManager,
        ChannelContextInterface $channelContext,
        ProductAttributesFinderInterface $attributesFinder,
        string $namePropertyPrefix,
        string $taxonsProperty,
        string $optionPropertyPrefix,
        string $attributePropertyPrefix,
        string $brandPropertyPrefix
    ) {
        $this->entityManager = $entityManager;
        $this->channelContext = $channelContext;
        $this->attributesFinder = $attributesFinder;
        $this->namePropertyPrefix = $namePropertyPrefix;
        $this->taxonsProperty = $taxonsProperty;
        $this->optionPropertyPrefix = $optionPropertyPrefix;
        $this->attributePropertyPrefix = $attributePropertyPrefix;
        $this->brandPropertyPrefix = $brandPropertyPrefix;
    }

    public function retrieveData(array $requestData): array
    {
        /** @var Channel $channel */
        $channel = $this->channelContext->getChannel();
        $taxon = $this->entityManager->getRepository(Taxon::class)->findOneBySlug($requestData['slug'], $channel->getDefaultLocale()->getCode());
        Assert::notNull($taxon, 'Taxon not found.');

        $data[$this->namePropertyPrefix] = (string) $requestData[$this->namePropertyPrefix];
        $data[$this->taxonsProperty] = strtolower($taxon->getCode());
        $data['taxon'] = $taxon;
        $data = array_merge(
            $data,
            $requestData['price'] ?? [],
            ['facets' => $requestData['facets'] ?? []],
        );

        $attributesDefinitions = $this->attributesFinder->findByTaxon($taxon);

        $this->handleBrandsPrefixedProperty($requestData, $data);
        $this->handleOptionsPrefixedProperty($requestData, $data);
        $this->handleAttributesPrefixedProperty($requestData, $data, $attributesDefinitions);
        $data['taxon'] = $taxon;

        return $data;
    }

    private function handleOptionsPrefixedProperty(
        array $requestData,
        array &$data
    ): void {
        if (!isset($requestData['options'])) {
            return;
        }

        foreach ($requestData['options'] as $key => $value) {
            if (is_array($value) && 0 === strpos($key, $this->optionPropertyPrefix)) {
                $data[$key] = array_map(function (string $property): string {
                    return strtolower($property);
                }, $value);
            }
        }
    }

    private function handleBrandsPrefixedProperty(
        array $requestData,
        array &$data
    ): void {
        if (!isset($requestData['brands'])) {
            return;
        }

        foreach ($requestData['brands'] as $key => $value) {
            if (is_array($value) && 0 === strpos($key, $this->brandPropertyPrefix)) {
                $data['brand'] = array_map(function (string $property): string {
                    return strtolower($property);
                }, $value);
            }
        }
    }

    private function handleAttributesPrefixedProperty(
        array $requestData,
        array &$data,
        ?array $attributesDefinitions = []
    ): void {
        if (!isset($requestData['attributes'])) {
            return;
        }

        $attributeTypes = $this->getAttributeTypes($attributesDefinitions);

        foreach ($requestData['attributes'] as $key => $value) {
            if (!is_array($value) || 0 !== strpos($key, $this->attributePropertyPrefix)) {
                continue;
            }
            $data[$key] = $this->reformatAttributeArrayValues($value, $key, $attributeTypes);
        }
    }

    private function getAttributeTypes(array $attributesDefinitions): array
    {
        $data = [];
        /** @var ProductAttribute $attributesDefinition */
        foreach ($attributesDefinitions as $attributesDefinition) {
            $data['attribute_' . $attributesDefinition->getCode()] = $attributesDefinition->getType();
        }

        return $data;
    }

    private function reformatAttributeArrayValues(
        array $attributeValues,
        string $property,
        array $attributesDefinitions
    ): array {
        $reformattedValues = [];
        foreach ($attributeValues as $attributeValue) {
            Assert::keyExists($attributesDefinitions, $property, $property.' does not exist.');
            switch ($attributesDefinitions[$property]) {
                case CheckboxAttributeType::TYPE:
                    $value = (bool) ($attributeValue);

                    break;
                case IntegerAttributeType::TYPE:
                    $value = (float) ($attributeValue);

                    break;
                default:
                    $value = strtolower($attributeValue);
            }
            $reformattedValues[] = $value;
        }

        return $reformattedValues;
    }
}
