<?php

/*
 * @package    agitation/seed-bundle
 * @link       http://github.com/agitation/seed-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\SeedBundle\Command;

use Agit\BaseBundle\Command\SingletonCommandTrait;
use Agit\SeedBundle\Event\SeedEvent;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Agit\BaseBundle\Exception\InternalErrorException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class SeedUpdateCommand extends ContainerAwareCommand
{
    use SingletonCommandTrait;

    const EVENT_REGISTRATION_KEY = "agit.seed";

    private $entries = [];

    protected function configure()
    {
        $this
            ->setName("agit:seeds:update")
            ->setDescription("Loads and insert seeds in the database, or updates them, if services request this.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (! $this->flock(__FILE__)) {
            return;
        }

        $this->entityManager = $this->getContainer()->get("doctrine.orm.entity_manager");

        $this->getContainer()->get("event_dispatcher")->dispatch(
            self::EVENT_REGISTRATION_KEY,
            new SeedEvent($this)
        );

        $this->process();
    }

    public function addSeedEntry($entityName, $entityData, $doUpdate)
    {
        if (!isset($this->entries[$entityName]))
            $this->entries[$entityName] = [];

        $this->entries[$entityName][] = ["data" => $entityData, "update" => $doUpdate];
    }

    public function process()
    {
        $entityClasses = $this->entityManager->getConfiguration()
            ->getMetadataDriverImpl()->getAllClassNames();

        foreach ($this->entries as $entityName => $seedEntries) {
            $metadata = $this->entityManager->getClassMetadata($entityName);

            // we need to know now if the entity usually has a generator as we will overwrite the generator below
            $usesIdGenerator = $metadata->usesIdGenerator();

            $idField = $this->getIdField($metadata);
            $entityClass = $metadata->getName();

            // it may be that a component ships (optional) entries for another component that is not installed
            if (! in_array($entityClass, $entityClasses)) {
                continue;
            }

            $entities = $this->getExistingObjects($entityName, $idField, $metadata);

            foreach ($seedEntries as $seedEntry) {
                $data = $seedEntry["data"];

                if (! isset($data[$idField])) {
                    throw new InternalErrorException("The seed data for $entityClass is missing the mandatory `$idField` field.");
                }

                if (isset($entities[$data[$idField]])) {
                    $entity = $entities[$data[$idField]];
                    unset($entities[$data[$idField]]);

                    if (! $seedEntry["update"]) {
                        continue;
                    }
                } else {
                    $entity = new $entityClass();
                }

                foreach ($data as $key => $value) {
                    $this->setObjectValue($entity, $key, $value, $metadata);
                }

                $this->entityManager->persist($entity);

                // we overwrite the ID generator here, because we ALWAYS need fixed IDs
                $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
            }

            // remove old entries, but only for entities with natural keys
            if (! $usesIdGenerator) {
                $this->removeObsoleteObjects($entities);
            }
        }

        $this->entityManager->flush();
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
            throw new InternalErrorException("Seed entities must have exactly one ID field.");
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

                foreach ($value as $childId) {
                    $ref = $this->entityManager->getReference($targetEntity, $childId);

                    if (! $collection->contains($ref)) {
                        $collection->add($ref);
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