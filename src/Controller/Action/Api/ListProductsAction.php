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

use App\Serializer\ProductNormalizer;
use BitBag\SyliusElasticsearchPlugin\Controller\RequestDataHandler\DataHandlerInterface;
use BitBag\SyliusElasticsearchPlugin\Controller\RequestDataHandler\PaginationDataHandlerInterface;
use BitBag\SyliusElasticsearchPlugin\Controller\RequestDataHandler\SortDataHandlerInterface;
use BitBag\SyliusElasticsearchPlugin\Finder\ApiProductsFinderInterface;
use FOS\RestBundle\View\View;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ViewHandlerInterface;
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

    public function __construct(
        DataHandlerInterface $apiProductListDataHandler,
        SortDataHandlerInterface $apiProductsSortDataHandler,
        PaginationDataHandlerInterface $paginationDataHandler,
        ApiProductsFinderInterface $apiProductsFinder,
        ProductNormalizer $normalizer,
        RequestConfigurationFactoryInterface $requestConfigurationFactory,
        MetadataInterface $metadata,
        ViewHandlerInterface $viewHandler,
    ) {
        $this->apiProductListDataHandler = $apiProductListDataHandler;
        $this->apiProductsSortDataHandler = $apiProductsSortDataHandler;
        $this->paginationDataHandler = $paginationDataHandler;
        $this->apiProductsFinder = $apiProductsFinder;
        $this->normalizer = $normalizer;
        $this->requestConfigurationFactory = $requestConfigurationFactory;
        $this->metadata = $metadata;
        $this->viewHandler = $viewHandler;
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

        $requestData = [
            'name' => $name,
            'brands' => [
                'brands' => []
            ],
            'options' => [],
            'attributes' => [
                'attribute_material' => [],
                'attribute_color' => [],
                'attribute_shipping-cost' => [],
                'attribute_shipping-deliverytime' => [],
                'attribute_condition' => []
            ],
            'price' => [
                'min_price' => $minPrice,
                'max_price' => $maxPrice
            ],
            'facets' => [],
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

        $products = $this->apiProductsFinder->find($data);
        foreach ($products as $product) {
            $context['groups'] = 'shop:product:read';
            $nProducts [] = $this->normalizer->normalize($product, 'json', $context);
        }

        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        $request->setRequestFormat('json');

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
}
