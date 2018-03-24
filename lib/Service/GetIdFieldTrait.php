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

trait GetIdFieldTrait
{
    private function getIdField(ClassMetadata $metadata)
    {
        $idFields = $metadata->getIdentifier();

        if (! is_array($idFields) || count($idFields) !== 1)
        {
            throw new Exception('Seed entities must have exactly one ID field.');
        }

        return reset($idFields);
    }
}
