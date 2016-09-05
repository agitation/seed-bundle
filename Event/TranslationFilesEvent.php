<?php

/*
 * @package    agitation/base-bundle
 * @link       http://github.com/agitation/base-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\BaseBundle\Event;

use Agit\BaseBundle\Command\TranslationCatalogCommand;
use Symfony\Component\EventDispatcher\Event;

/**
 * This event is triggered before the files for generating a bundle catalog are
 * collected and extracted. Listeners should pass them to the registerSourceFile
 * method. A listener can store temporary files under the cacheBasePath, they
 * be cleaned up automatically after processing.
 */
class TranslationFilesEvent extends Event
{
    private $bundleAlias;

    private $processor;

    private $cacheBasePath;

    public function __construct(TranslationCatalogCommand $processor, $bundleAlias, $cacheBasePath)
    {
        $this->bundleAlias = $bundleAlias;
        $this->processor = $processor;
        $this->cacheBasePath = $cacheBasePath;
    }

    public function getCacheBasePath()
    {
        return $this->cacheBasePath;
    }

    public function getBundleAlias()
    {
        return $this->bundleAlias;
    }

    public function registerSourceFile($alias, $path)
    {
        return $this->processor->registerSourceFile($alias, $path);
    }
}