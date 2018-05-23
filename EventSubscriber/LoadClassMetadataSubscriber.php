<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

/**
 * class LoadClassMetadataSubscriber
 */
class LoadClassMetadataSubscriber implements EventSubscriber
{
    /**
     * @inheritdoc
     */
    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata
        ];
    }

    /**
     * @param LoadClassMetadataEventArgs $eventArgs
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        /**
         * @var \Doctrine\ORM\Mapping\ClassMetadata $classMetadata
         */
        $classMetadata = $eventArgs->getClassMetadata();
        if ($classMetadata->getName() !== 'Pim\Bundle\CatalogBundle\Entity\AttributeOption') {
            return;
        }

        $classMetadata->customRepositoryClassName = 'ClickAndMortar\AdvancedCsvConnectorBundle\Doctrine\ORM\Repository\AttributeOptionRepository';
    }
}
