<?php

/*
 * @package    agitation/base-bundle
 * @link       http://github.com/agitation/base-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\BaseBundle
{
    use Agit\BaseBundle\DependencyInjection\RegisterPluggableServicesCompilerPass;
    use Agit\BaseBundle\DependencyInjection\RegisterProcessorsCompilerPass;
    use Symfony\Component\DependencyInjection\Compiler\PassConfig;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\HttpKernel\Bundle\Bundle;

    class AgitBaseBundle extends Bundle
    {
        public function build(ContainerBuilder $containerBuilder)
        {
            parent::build($containerBuilder);

            $containerBuilder->addCompilerPass(new RegisterProcessorsCompilerPass());

            $containerBuilder->addCompilerPass(
                new RegisterPluggableServicesCompilerPass(),
                PassConfig::TYPE_AFTER_REMOVING
            );
        }
    }
}

// quick and dirty variable dumper
namespace
{
    function p()
    {
        if (php_sapi_name() !== "cli") {
            @header("Content-Type: text/plain; charset=UTF-8");
        }

        foreach (func_get_args() as $arg) {
            if (is_null($arg) || is_bool($arg)) {
                var_dump($arg);
            } else {
                print_r($arg);
            }

            echo "\n\n";
        }
    }
}
