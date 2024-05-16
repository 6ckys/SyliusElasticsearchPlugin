<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.io and write us
 * an email on hello@bitbag.io.
 */

declare(strict_types=1);

namespace BitBag\SyliusElasticsearchPlugin\Form\Type;

use BitBag\SyliusElasticsearchPlugin\Context\ProductBrandsContextInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductBrandsFilterType extends AbstractFilterType
{
    private ProductBrandsContextInterface $productBrandsContext;

    public function __construct(
        ProductBrandsContextInterface $productBrandsContext,
    ) {
        $this->productBrandsContext = $productBrandsContext;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($this->productBrandsContext->getBrands() as $productBrand) {
            $choices[$productBrand->getCode()] = $productBrand->getCode();
        }

        $name = 'brands';
        $choices = array_unique($choices);
        $builder->add($name, ChoiceType::class, [
            'label' => $name,
            'required' => false,
            'multiple' => true,
            'expanded' => true,
            'choices' => $choices,
        ]);

    }
}
