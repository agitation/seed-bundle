<?php
/**
 * @package    agitation/core
 * @link       http://github.com/agitation/AgitCoreBundle
 * @author     Alex Günsche <http://www.agitsol.com/>
 * @copyright  2012-2015 AGITsol GmbH
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\CoreBundle\Pluggable\Strategy\Combined;

use Agit\CoreBundle\Service\ClassCollector;

/**
 * Creates object collector listeners.
 */
class CombinedPluginListenerFactory
{
    protected $ClassCollector;

    public function __construct(ClassCollector $ClassCollector)
    {
        $this->ClassCollector = $ClassCollector;
    }

    public function create($searchPath, $priority = 100)
    {
        return new CombinedPluginListener($this->ClassCollector, $searchPath, $priority);
    }
}