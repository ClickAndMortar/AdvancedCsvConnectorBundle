<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Doctrine\ORM\Repository;

use Pim\Bundle\CatalogBundle\Doctrine\ORM\Repository\AttributeOptionRepository as baseRepository;

/**
 * Class AttributeOptionRepository
 */
class AttributeOptionRepository extends baseRepository
{
    /**
     * @param string $attributeCode
     * @param array  $optionCode
     *
     * @return array
     */
    public function findOptionByCode($attributeCode, array $optionCode)
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.attribute', 'a')
            ->where('a.code = :attribute_code')
            ->andWhere('o.code IN (:option_codes)')
            ->setParameters(array(
                'attribute_code' => $attributeCode,
                'option_codes'   => $optionCode
            ))
            ->getQuery()
            ->getResult();
    }
}
