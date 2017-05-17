<?php

/*
 * @package    agitation/seed-bundle
 * @link       http://github.com/agitation/seed-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\SeedBundle\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class SeedProcessor
{
    use GetIdFieldTrait;

    /**
     * @var EntityManager
     */
    private $entityManager;

    private $metadata = [];

    private $entries = [];

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function process(SeedCollection $collection, $removeObsolete = false)
    {
        foreach ($collection->getData() as $entityName => $seedEntries) {
            $metadata = $collection->getMeta($entityName);
            $idField = $this->getIdField($metadata);
            $entities = $this->getExistingObjects($entityName, $idField, $metadata);

            // we need to know now if the entity usually has a generator as we will overwrite the generator below
            $usesIdGenerator = $metadata->usesIdGenerator();

            foreach ($seedEntries as $seedEntry) {
                $data = $seedEntry["data"];

                if (isset($entities[$data[$idField]])) {
                    $entity = $entities[$data[$idField]];
                    unset($entities[$data[$idField]]);

                    if (! $seedEntry["update"]) {
                        continue;
                    }
                } else {
                    $entity = new $entityName();
                }

                foreach ($data as $key => $value) {
                    $this->setObjectValue($entity, $key, $value, $metadata);
                }

                $this->entityManager->persist($entity);

                // we temporarily overwrite the ID generator here, because we ALWAYS need fixed IDs
                $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
            }

            // remove old entries, but only for entities with natural keys
            if ($removeObsolete && ! $usesIdGenerator) {
                $this->removeObsoleteObjects($entities);
            }
        }

        $this->entityManager->flush();

        // clear all result caches to avoid using stale entities
        $this->entityManager->getConfiguration()->getResultCacheImpl()->deleteAll();
    }

    private function getExistingObjects($entityName, $idField, $metadata)
    {
        $entities = [];

        foreach ($this->entityManager->getRepository($entityName)->findAll() as $entity) {
            $entities[$metadata->getFieldValue($entity, $idField)] = $entity;
        }

        return $entities;
    }

    private function setObjectValue($entity, $key, $value, $metadata)
    {
        if ($value && isset($metadata->associationMappings[$key])) {
            $mapping = $metadata->getAssociationMapping($key);
            $targetEntity = $metadata->associationMappings[$key]["targetEntity"];

            if ($mapping["type"] & ClassMetadataInfo::TO_MANY) {
                $collection = $metadata->getFieldValue($entity, $key);

                $oldValues = array_flip(array_map(function ($e) { return $e->getId(); }, $collection->getValues()));

                foreach ($value as $childId) {
                    $ref = $this->entityManager->getReference($targetEntity, $childId);

                    if (isset($oldValues[$childId])) {
                        unset($oldValues[$childId]);
                    }

                    if (! $collection->contains($ref)) {
                        $collection->add($ref);
                    }
                }

                // Currently limited to MANY-to-many. Should also work with ONE-to-many, but is untested.
                if ($mapping["type"] & ClassMetadataInfo::MANY_TO_MANY) {
                    foreach (array_flip($oldValues) as $childId) {
                        $ref = $this->entityManager->getReference($targetEntity, $childId);
                        $collection->removeElement($ref);
                    }
                }

                $value = $collection;
            } else {
                $value = $this->entityManager->getReference($targetEntity, $value);
            }
        }

        $metadata->setFieldValue($entity, $key, $value);
    }

    private function removeObsoleteObjects($entities)
    {
        foreach ($entities as $entity) {
            $this->entityManager->remove($entity);
        }
    }
}
