<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Helper;

use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeOptionRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Process\Process;

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
     * Encode given $filePath with $encoding from UTF-8
     *
     * @param string $filePath
     * @param string $encoding
     *
     * @return void
     */
    public function encodeFile($filePath, $encoding)
    {
        $tempFilePath      = sprintf('%s.tmp', $filePath);
        $encodeFileCommand = sprintf(
            'iconv -f UTF-8 -t %s//TRANSLIT %s > %s',
            $encoding,
            $filePath,
            $tempFilePath
        );
        $encodeFileProcess = new Process($encodeFileCommand);
        $encodeFileProcess->mustRun();

        if (file_exists($tempFilePath)) {
            unlink($filePath);
            rename($tempFilePath, $filePath);
        }
    }
}
