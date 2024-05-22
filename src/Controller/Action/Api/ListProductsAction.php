<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.io and write us
 * an email on hello@bitbag.io.
 */

declare(strict_types=1);

namespace BitBag\SyliusElasticsearchPlugin\Controller\Action\Api;

use App\Entity\Product\ProductAttribute;
use App\Entity\Product\ProductOption;
use App\Entity\Taxonomy\Taxon;
use App\Serializer\ProductNormalizer;
use BitBag\SyliusElasticsearchPlugin\Controller\RequestDataHandler\DataHandlerInterface;
use BitBag\SyliusElasticsearchPlugin\Controller\RequestDataHandler\PaginationDataHandlerInterface;
use BitBag\SyliusElasticsearchPlugin\Controller\RequestDataHandler\SortDataHandlerInterface;
use BitBag\SyliusElasticsearchPlugin\Finder\ApiProductsFinderInterface;
use BitBag\SyliusElasticsearchPlugin\Finder\ProductAttributesFinder;
use BitBag\SyliusElasticsearchPlugin\Finder\ProductBrandsFinder;
use BitBag\SyliusElasticsearchPlugin\Finder\ProductOptionsFinder;
use BitBag\SyliusElasticsearchPlugin\Form\Type\ChoiceMapper\ProductAttributesMapperInterface;
use BitBag\SyliusElasticsearchPlugin\Form\Type\ChoiceMapper\ProductOptionsMapperInterface;
use BitBag\SyliusElasticsearchPlugin\Model\SearchFacets;
use BitBag\SyliusElasticsearchPlugin\PropertyNameResolver\ConcatedNameResolverInterface;
use FOS\RestBundle\View\View;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ViewHandlerInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class ListProductsAction
{
    private DataHandlerInterface $apiProductListDataHandler;

    private SortDataHandlerInterface $apiProductsSortDataHandler;

    private PaginationDataHandlerInterface $paginationDataHandler;

    private ApiProductsFinderInterface $apiProductsFinder;

    private ProductBrandsFinder $productBrandsFinder;

    private ProductAttributesFinder $productAttributesFinder;

    private ProductOptionsFinder $productOptionsFinder;

    private ConcatedNameResolverInterface $attributeNameResolver;

    private ProductAttributesMapperInterface $productAttributesMapper;

    private ConcatedNameResolverInterface $optionNameResolver;

    private ProductOptionsMapperInterface $productOptionsMapper;

    private array $excludedAttributes;

    private array $excludedBrands;

    private array $excludedOptions;

    public function __construct(
        DataHandlerInterface $apiProductListDataHandler,
        SortDataHandlerInterface $apiProductsSortDataHandler,
        PaginationDataHandlerInterface $paginationDataHandler,
        ApiProductsFinderInterface $apiProductsFinder,
        ProductNormalizer $normalizer,
        RequestConfigurationFactoryInterface $requestConfigurationFactory,
        MetadataInterface $metadata,
        ViewHandlerInterface $viewHandler,
        ProductBrandsFinder $productBrandsFinder,
        ProductAttributesFinder $productAttributesFinder,
        ProductOptionsFinder $productOptionsFinder,
        ConcatedNameResolverInterface $attributeNameResolver,
        ProductAttributesMapperInterface $productAttributesMapper,
        ConcatedNameResolverInterface $optionNameResolver,
        ProductOptionsMapperInterface $productOptionsMapper,
        array $excludedAttributes,
        array $excludedBrands,
        array $excludedOptions,
    ) {
        $this->apiProductListDataHandler = $apiProductListDataHandler;
        $this->apiProductsSortDataHandler = $apiProductsSortDataHandler;
        $this->paginationDataHandler = $paginationDataHandler;
        $this->apiProductsFinder = $apiProductsFinder;
        $this->normalizer = $normalizer;
        $this->requestConfigurationFactory = $requestConfigurationFactory;
        $this->metadata = $metadata;
        $this->viewHandler = $viewHandler;
        $this->productBrandsFinder = $productBrandsFinder;
        $this->productAttributesFinder = $productAttributesFinder;
        $this->productOptionsFinder = $productOptionsFinder;
        $this->attributeNameResolver = $attributeNameResolver;
        $this->productAttributesMapper = $productAttributesMapper;
        $this->productOptionsMapper = $productOptionsMapper;
        $this->optionNameResolver = $optionNameResolver;
        $this->excludedAttributes = $excludedAttributes;
        $this->excludedBrands = $excludedBrands;
        $this->excludedOptions = $excludedOptions;
    }

    public function __invoke(Request $request): Response
    {
        $taxonSlug = $this->resolveQueryParameter($request, 'taxonSlug', null);
        Assert::notNull($taxonSlug, 'taxonSlug cannot be null');
        $name = $this->resolveQueryParameter($request, 'name', null);
        $minPrice = $this->resolveQueryParameter($request, 'minPrice', null);
        $maxPrice = $this->resolveQueryParameter($request, 'maxPrice', null);
        $page = $this->resolveQueryParameter($request, 'page', 1);
        $itemsPerPage = $this->resolveQueryParameter($request, 'itemsPerPage', 8);
        $showFilter = $this->resolveQueryParameter($request, 'showFilter', "yes");

        $orderBy = $this->resolveQueryParameter($request, 'orderBy', 'price');
        Assert::inArray(
            $orderBy,
            ['product_created_at', 'price', 'sold_units'],
            'orderBy must be one of these: product_created_at, price, sold_units'
        );

        $sort = $this->resolveQueryParameter($request, 'sort', 'asc');
        Assert::inArray(
            $sort,
            ['asc', 'desc'],
            'Order by must be one of these: asc, desc'
        );

        $brands = $this->resolveQueryArrayParameter($request, 'brands', []);
        $attributes = $this->resolveQueryArrayParameter($request, 'attributes', []);
        $attributes = $this->handleQueryArrayParameter($attributes);
        $options = $this->resolveQueryArrayParameter($request, 'options', []);
        $options = $this->handleQueryArrayParameter($options);

        $requestData = [
            'name' => $name,
            'brands' => [
                'brands' => $brands
            ],
            'options' => $options,
            'attributes' => $attributes,
            'price' => [
                'min_price' => $minPrice,
                'max_price' => $maxPrice
            ],
            'facets' => new SearchFacets(),
            'limit' => $itemsPerPage,
            'page' => $page,
            'order_by' => $orderBy,
            'sort' => $sort,
            'slug' => $taxonSlug
        ];

        $data = array_merge(
            $this->apiProductListDataHandler->retrieveData($requestData),
            $this->apiProductsSortDataHandler->retrieveData($requestData),
            $this->paginationDataHandler->retrieveData($requestData)
        );

        $products = $this->apiProductsFinder->find($data)->getCurrentPageResults();
        foreach ($products as $product) {
            $context['groups'] = 'shop:product:read';
            $nProducts [] = $this->normalizer->normalize($product, 'json', $context);
        }

        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $request->setRequestFormat('json');

        $nProducts [] = $this->generatePaginationSection($data);
        $nProducts [] = $this->generateFilterSection($data, $requestData, $showFilter);
        $nProducts [] = $this->generateTaxonSection($data);

        return $this->viewHandler->handle($configuration, View::create($nProducts));

    }

    public function resolveQueryParameter(Request $request, string $parameterName, null|int|string $defaultParameter): null|int|string
    {
        if ($request->query->has($parameterName)) {
            $parameter = $request->query->get($parameterName);
        } else {
            $parameter = $defaultParameter;
        }
        return $parameter;
    }

    public function resolveQueryArrayParameter(Request $request, string $parameterName, array|int|string $defaultParameter): array|int|string
    {
        if ($request->query->has($parameterName)) {
            $parameter = $request->query->getIterator()[$parameterName];
        } else {
            $parameter = $defaultParameter;
        }
        return $parameter;
    }

    public function handleQueryArrayParameter(array $arrayParameter): array
    {
        $handledArrayPrameter = [];
        foreach ($arrayParameter as $parameter) {
            list($parameterName, $parameterValue) = explode('=', $parameter, 2);
            $handledArrayPrameter[$parameterName][] = $parameterValue;
        }
        return $handledArrayPrameter;
    }

    public function generatePaginationSection(array $data): array
    {
        $paginationData = [
            'pagination' =>[
                'first' => 1,
                'current' => $this->apiProductsFinder->find($data)->getCurrentPage(),
                'last' => $this->apiProductsFinder->find($data)->getNbPages(),
                'previous' => ($this->apiProductsFinder->find($data)->getCurrentPage() > 1) ? $this->apiProductsFinder->find($data)->getPreviousPage() : 1,
                'next' => ($this->apiProductsFinder->find($data)->getCurrentPage() < $this->apiProductsFinder->find($data)->getNbPages()) ? $this->apiProductsFinder->find($data)->getNextPage() : $this->apiProductsFinder->find($data)->getNbPages(),
                'totalItems' => $this->apiProductsFinder->find($data)->count(),
                'perPage' => $this->apiProductsFinder->find($data)->getMaxPerPage(),
            ]
        ];
        return $paginationData;
    }

    public function generateFilterSection(array $data, array $requestData, string $showFilter): array
    {
        /** @var Taxon $taxon */
        $taxon = $data['taxon'];
        $brands = $this->generateFilterBrandsSection($taxon, $data);
        if($showFilter==="yes") {
            $attributes = $this->generateFilterAttributesSection($taxon, $requestData);
            $options = $this->generateFilterOptionsSection($taxon);
            $filterData = [
                'filter' =>[
                    $brands,
                    $attributes,
                    $options,
                ]
            ];
        } else {
            $filterData = [
                'filter' =>[
                    $brands,
                ]
            ];
        }
        return $filterData;
    }

    public function generateFilterBrandsSection(TaxonInterface $taxon, array $data): array
    {
        $brands = $this->productBrandsFinder->findByTaxon($taxon);
        $excludedBrands= [];
        foreach ($this->excludedBrands as $excludedBrand) {
            $excludedBrands[$excludedBrand] = $excludedBrand;
        }


        $brandChoices = [];
        foreach ($brands as $brand) {
            if(isset($excludedBrands[$brand->getCode()])) {
                continue;
            }
            $brandChoices[$brand->getCode()] = [
                'code' => $brand->getCode(),
                'name' => $brand->getName(),
                'isSelectedForFilter' => false,
            ];
        }

        foreach ($data['brand'] as $brand) {
            if(isset($brandChoices[$brand])){
                $brandChoices[$brand]['isSelectedForFilter'] = true;
            }
        }

        $filterData = [
            'brands' => array_values($brandChoices),
        ];
        return $filterData;
    }

    public function generateFilterAttributesSection(TaxonInterface $taxon, array $requestData): array
    {
        $attributes = $this->productAttributesFinder->findByTaxon($taxon);
        $excludedAttributes= [];
        foreach ($this->excludedAttributes as $excludedAttribute) {
            $excludedAttributes[$excludedAttribute] = $excludedAttribute;
        }

        $attributeChoices = [];
        /** @var ProductAttribute $attribute */
        foreach ($attributes as $attribute) {
            if(isset($excludedAttributes['attribute_'.$attribute->getCode()])){
                continue;
            }
            $elasticCode = $this->attributeNameResolver->resolvePropertyName($attribute->getCode());
            $choices = $this->productAttributesMapper->mapToChoicesApi($attribute, $taxon, $excludedAttributes);
            $attributeChoices[$elasticCode] = [
                'code' => $elasticCode,
                'name' => $attribute->getName(),
                'isSelectedForFilter' => false,
                'values' => $choices,
            ];
        }

        if(isset($requestData['attributes'])) {
            foreach ($requestData['attributes'] as $key => $attributes) {
                if (isset($attributeChoices[$key])) {
                    $attributeChoices[$key]['isSelectedForFilter'] = true;
                }
                foreach ($attributes as $attributeCode){
                    if (isset($attributeChoices[$key]['values'][$attributeCode])) {
                        $attributeChoices[$key]['values'][$attributeCode]['isSelectedForFilter'] = true;
                    }
                }
            }
        }

        foreach ($attributeChoices as &$attributeChoice) {
            $attributeChoice['values'] = array_values($attributeChoice['values']);
        }

        $filterData = [
            'attributes' => array_values($attributeChoices),
        ];
        return $filterData;
    }

    public function generateFilterOptionsSection(TaxonInterface $taxon): array
    {
        $options = $this->productOptionsFinder->findByTaxon($taxon);
        $excludedOptions= [];
        foreach ($this->excludedOptions as $excludedOption) {
            $excludedOptions[$excludedOption] = $excludedOption;
        }

        $optionChoices = [];
        /** @var ProductOption $option */
        foreach ($options as $option) {
            if(isset($excludedAttributes['option_'.$option->getCode()])){
                continue;
            }
            $optionCode = $this->optionNameResolver->resolvePropertyName($option->getCode());
            $choices = $this->productOptionsMapper->mapToChoices($option);
            $choices = array_unique($choices);
            $optionChoices[] = [
                'code' => $optionCode,
                'name' => $option->getName(),
                'isSelectedForFilter' => false,
                'values' => $choices,
            ];
        }

        $filterData = [
            'options' => array_values($optionChoices),
        ];

        return $filterData;
    }

    public function generateTaxonSection(array $data): array
    {
        $taxon = $data['taxon'];
        $taxonToGetName = $taxon;
        $taxonFullName = $taxonToGetName->getName();
        while($taxonToGetName->getParent()!==null){
            $taxonToGetName = $taxonToGetName->getParent();
            $taxonFullName= $taxonToGetName->getName()." / ".$taxonFullName;
        }
        $taxons['taxonAncestors'][$taxon->getCode()] = [
            'code' => $taxon->getCode(),
            'slug' => $taxon->getTranslations()->last()->getSlug(),
            'name' => $taxon->getName(),
            'fullName' => $taxonFullName,
        ];
        foreach ($taxon->getAncestors() as $oneSelectedTaxonAncestor){
            $taxons['taxonAncestors'][$oneSelectedTaxonAncestor->getCode()] = [
                'code' => $oneSelectedTaxonAncestor->getCode(),
                'slug' => $oneSelectedTaxonAncestor->getTranslations()->last()->getSlug(),
                'name' => $oneSelectedTaxonAncestor->getName()
            ];
        }
        if(isset($taxons['taxonAncestors'])) {
            $taxons['taxonAncestors'] = array_values($taxons['taxonAncestors']);
        }
        $taxons['taxonSelected'] = [
            'code' => $taxon->getCode(),
            'slug' => $taxon->getTranslations()->last()->getSlug(),
            'name' => $taxon->getName(),
            'fullName' => $taxonFullName,
        ];
        try {
            $taxons['taxonSelected']['referenceableContent'] = [
                'notIndexable' => $taxon->isNotIndexable(),
                'metadataTitle' => $taxon->getMetadataTitle(),
                'metadataDescription' => $taxon->getMetadataDescription()
            ];
        } catch (\Throwable $e) {
            $taxons['taxonSelected']['referenceableContent'] = null;
        }
        try {
            $taxons['taxonSelected']['referenceableContent']['openGraph'] = [
                'openGraphMetadataTitle' => $taxon->getOpenGraphMetadataTitle(),
                'openGraphMetadataDescription' => $taxon->getOpenGraphMetadataDescription(),
                'openGraphMetadataUrl' => $taxon->getOpenGraphMetadataUrl(),
                'openGraphMetadataImage' => $taxon->getOpenGraphMetadataImage(),
                'openGraphMetadataType' => $taxon->getOpenGraphMetadataType(),
            ];
        } catch (\Throwable $e) {
            if($taxons['taxonSelected']['referenceableContent'] !== null) {
                $taxons['taxonSelected']['referenceableContent']['openGraph'] = null;
            }
        }
        if ($taxon->getParent() !== null) {
            $oneSelectedTaxonParent = $taxon->getParent();
            $taxons['taxonParent'] = [
                'code' => $oneSelectedTaxonParent->getCode(),
                'slug' => $oneSelectedTaxonParent->getTranslations()->last()->getSlug(),
                'name' => $oneSelectedTaxonParent->getName(),
            ];
            foreach ($oneSelectedTaxonParent->getEnabledChildren() as $oneSelectedTaxonParentChildren){
                if($oneSelectedTaxonParentChildren!==$taxon) {
                    $taxons['taxonSiblings'][$oneSelectedTaxonParentChildren->getCode()] = [
                        'code' => $oneSelectedTaxonParentChildren->getCode(),
                        'slug' => $oneSelectedTaxonParentChildren->getTranslations()->last()->getSlug(),
                        'name' => $oneSelectedTaxonParentChildren->getName(),
                    ];
                }
            }
            if(isset($taxons['taxonSiblings'])) {
                $taxons['taxonSiblings'] = array_values($taxons['taxonSiblings']);
            }
        }
        foreach ($taxon->getEnabledChildren() as $oneSelectedTaxonChildren){
            $taxons['taxonChildren'][$oneSelectedTaxonChildren->getCode()] = [
                'code' => $oneSelectedTaxonChildren->getCode(),
                'slug' => $oneSelectedTaxonChildren->getTranslations()->last()->getSlug(),
                'name' => $oneSelectedTaxonChildren->getName(),
            ];
        }
        if(isset($taxons['taxonChildren'])) {
            $taxons['taxonChildren'] = array_values($taxons['taxonChildren']);
        }
        $taxons['filterTaxonName'] = $taxon->getName();
        $taxons['searchedName'] = $data['name'];
        return $taxons;
    }
}
