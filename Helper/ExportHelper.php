<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Helper;

use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeOptionRepository;
use Doctrine\ORM\EntityManager;

/**
 * Export helper
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Helper
 */
class ExportHelper
{
    /**
     * Entity manager
     *
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * Attribute option repository
     *
     * @var AttributeOptionRepository
     */
    protected $attributeOptionRepository;

    /**
     * ExportHelper constructor.
     *
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager, AttributeOptionRepository $attributeOptionRepository)
    {
        $this->entityManager             = $entityManager;
        $this->attributeOptionRepository = $attributeOptionRepository;
    }

    /**
     * Get value from code in a list case
     *
     * @param string $attributeKey
     * @param string $attributeValue
     * @param string $locale
     *
     * @return string
     */
    public function getValueFromCode($attributeKey, $attributeValue, $locale)
    {
        $option = $this->attributeOptionRepository->findOptionByCode($attributeKey, array($attributeValue));
        if (empty($option) || empty($option[0])) {
            return '';
        }

        $option[0]->setLocale($locale)->getTranslation();

        return $option[0]->getOptionValue()->getLabel();
    }

    /**
     * Update value to uppercase
     *
     * @param string $attributeKey
     * @param string $attributeValue
     * @param string $locale
     *
     * @return string
     */
    public function toUppercase($attributeKey, $attributeValue, $locale)
    {
        return strtoupper($attributeValue);
    }

    /**
     * Update value to lowercase
     *
     * @param string $attributeKey
     * @param string $attributeValue
     * @param string $locale
     *
     * @return string
     */
    public function toLowercase($attributeKey, $attributeValue, $locale)
    {
        return strtolower($attributeValue);
    }
}
