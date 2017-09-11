<?php
declare(strict_types=1);
/*
 * @package    agitation/seed-bundle
 * @link       http://github.com/agitation/seed-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\SeedBundle\Command;

use Agit\SeedBundle\Event\SeedEvent;
use Agit\SeedBundle\Service\SeedCollection;
use Doctrine\ORM\EntityManager;
use Exception;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedUpdateCommand extends ContainerAwareCommand
{
    const EVENT_REGISTRATION_KEY = 'agit.seed';

    /**
     * @var EntityManager
     */
    private $entityManager;
    /**
     * @var SeedCollection
     */
    private $collection;

    private $output;

    public function addSeedEntry($entityName, $entityData, $doUpdate)
    {
        // this may fail if a component ships optional entries for entities of a non-existing other component
        try
        {
            $metadata = $this->entityManager->getClassMetadata($entityName);
            $this->collection->addData($metadata, $entityData, $doUpdate);
        }
        catch (Exception $e)
        {
            $this->output->writeln("Entity $entityName does not exist.", OutputInterface::VERBOSITY_VERBOSE);
        }
    }

    protected function configure()
    {
        $this
            ->setName('agit:seeds:update')
            ->setDescription('Loads and insert seeds in the database, or updates them, if services request this.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->entityManager = $this->getContainer()->get('doctrine.orm.entity_manager');
        $this->seedProcessor = $this->getContainer()->get('agit.seed.processor');
        $this->collection = new SeedCollection();
        $this->output = $output;

        $this->getContainer()->get('event_dispatcher')->dispatch(
            self::EVENT_REGISTRATION_KEY,
            new SeedEvent($this)
        );

        $this->seedProcessor->process($this->collection, true);
    }
}
