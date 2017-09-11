<?php
declare(strict_types=1);
/*
 * @package    agitation/seed-bundle
 * @link       http://github.com/agitation/seed-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\SeedBundle\Service;

use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

class SeedCollection
{
    use GetIdFieldTrait;

    private $metadata = [];

    private $data = [];

    public function addData(ClassMetadata $metadata, array $entityData, $doUpdate)
    {
        $entityName = $metadata->getName();
        $this->metadata[$entityName] = $metadata;

        // initialize collector for this entity class
        if (! isset($this->data[$entityName]))
        {
            $this->data[$entityName] = [];
        }

        $idField = $this->getIdField($this->metadata[$entityName]);

        if (! isset($entityData[$idField]))
        {
            throw new Exception("The seed data for $entityName is missing the mandatory `$idField` field.");
        }

        $this->data[$entityName][$entityData[$idField]] = [
            'data' => $entityData,
            'update' => $doUpdate
        ];
    }

    public function getData()
    {
        return $this->data;
    }

    public function getMeta($entityName)
    {
        return $this->metadata[$entityName];
    }
}
