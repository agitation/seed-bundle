<?php

/*
 * @package    agitation/seed-bundle
 * @link       http://github.com/agitation/seed-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\SeedBundle\Command;

use Agit\SeedBundle\Event\SeedEvent;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Exception;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedUpdateCommand extends ContainerAwareCommand
{
    const EVENT_REGISTRATION_KEY = "agit.seed";

    /**
     * @var EntityManager
     */
    private $entityManager;

    private $entityNames = [];

    private $metadata = [];

    private $entries = [];

    protected function configure()
    {
        $this
            ->setName("agit:seeds:update")
            ->setDescription("Loads and insert seeds in the database, or updates them, if services request this.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->entityManager = $this->getContainer()->get("doctrine.orm.entity_manager");

        $this->getContainer()->get("event_dispatcher")->dispatch(
            self::EVENT_REGISTRATION_KEY,
            new SeedEvent($this)
        );

        $this->process();
    }

    public function addSeedEntry($entityName, $entityData, $doUpdate)
    {
        try {
            // this may fail if a component ships optional entries for entities of a non-existing other component
            $metadata = $this->entityManager->getClassMetadata($entityName);

            // resolve to real class name
            $entityName = $metadata->getName();

            $this->metadata[$entityName] = $metadata;

            // initialize collector for this entity class
            if (! isset($this->entries[$entityName])) {
                $this->entries[$entityName] = [];
            }

            $idField = $this->getIdField($this->metadata[$entityName]);

            if (! isset($entityData[$idField])) {
                throw new Exception("The seed data for $entityName is missing the mandatory `$idField` field.");
            }

            $this->entries[$entityName][$entityData[$idField]] = ["data" => $entityData, "update" => $doUpdate];
        } catch (Exception $e) {
            // silently ignore
        }
    }

    public function process()
    {
        foreach ($this->entries as $entityName => $seedEntries) {
            $metadata = $this->metadata[$entityName];
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
            if (! $usesIdGenerator) {
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

    private function getIdField($metadata)
    {
        $idFields = $metadata->getIdentifier();

        if (! is_array($idFields) || count($idFields) !== 1) {
            throw new Exception("Seed entities must have exactly one ID field.");
        }

        return reset($idFields);
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
