<?php
declare(strict_types=1);

/*
 * @package    agitation/seed-bundle
 * @link       http://github.com/agitation/seed-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\SeedBundle\Event;

use Agit\SeedBundle\Command\SeedUpdateCommand;
use Symfony\Component\EventDispatcher\Event;

class SeedEvent extends Event
{
    private $command;

    public function __construct(SeedUpdateCommand $command)
    {
        $this->command = $command;
    }

    public function addSeedEntry($entityName, $entityData, $doUpdate = false)
    {
        $this->command->addSeedEntry($entityName, $entityData, $doUpdate);
    }
}
