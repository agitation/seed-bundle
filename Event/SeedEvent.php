<?php

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

    public function addSeed($entity, array $entries)
    {
    }
}
