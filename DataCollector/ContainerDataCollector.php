<?php

/*
 * (c) Vincent Bouzeran <vincent.bouzeran@elao.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * 
 * 
 */

namespace Elao\WebProfilerExtraBundle\DataCollector;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;

/**
 * ContainerDataCollector.
 *
 * @author Vincent Bouzeran <vincent.bouzeran@elao.com>
 */
class ContainerDataCollector extends DataCollector
{
    protected $kernel;
    protected $container;
	protected $containerBuilder;
	
    
    public function __construct(Kernel $kernel)
    {
        $this->kernel    		= $kernel;
        $this->container 		= $kernel->getContainer();
        $this->containerBuilder = $this->getContainerBuilder();
    }

    public function getKernel()
    {
        return $this->kernel;
    }
    
    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $serviceIds = $this->containerBuilder->getServiceIds();
        $parameters = array();
        $services   = array();
        
        foreach ($this->containerBuilder->getParameterBag()->all() as $key => $value)
        {
            $service = substr($key, 0, strpos($key, '.'));
            if (!isset($parameters[$service]))
            {
                $parameters[$service] = array();
            }
            $parameters[$service][$key] = $value;
        }
        
        foreach ($serviceIds as $serviceId)
        {
			$definition = $this->resolveServiceDefinition($serviceId);
			
			if ($definition instanceof Definition && $definition->isPublic()) {
				$services[$serviceId] = array('class' => $definition->getClass(), 'scope' => $definition->getScope());
	            $scope = $definition->getScope();
	            $class = $definition->getClass();
			} elseif ($definition instanceof Alias) {
				$services[$serviceId] = array('alias' => $definition);
	        } else {
	        	continue;	// We don't want private services 
			}
        }
        
        ksort($services);
        ksort($parameters);
        $this->data['parameters'] = $parameters;
        $this->data['services']   = $services;
    }
    
    public function getParameters()
    {
        return $this->data['parameters'];
    }
    
    public function getServiceCount()
    {
        return count($this->getServices());
    }
    
    public function getServices()
    {
        return $this->data['services'];
    }
    


    /**
	* Loads the ContainerBuilder from the cache.
	* @author Ryan Weaver <ryan@thatsquality.com>
	* @return ContainerBuilder
	*/
    private function getContainerBuilder()
    {
        if (!$this->getKernel()->isDebug()) {
            throw new \LogicException(sprintf('Debug information about the container is only available in debug mode.'));
        }

        if (!file_exists($cachedFile = $this->container->getParameter('debug.container.dump'))) {
            throw new \LogicException(sprintf('Debug information about the container could not be found. Please clear the cache and try again.'));
        }

        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator());
        $loader->load($cachedFile);

        return $container;
    }

    /**
    * @author Ryan Weaver <ryan@thatsquality.com>
	* Given an array of service IDs, this returns the array of corresponding
	* Definition and Alias objects that those ids represent.
	*
	* @param string $serviceId The service id to resolve
	* @return \Symfony\Component\DependencyInjection\Definition|\Symfony\Component\DependencyInjection\Alias
	*/
    private function resolveServiceDefinition($serviceId)
    {
        if ($this->containerBuilder->hasDefinition($serviceId)) {
            return $this->containerBuilder->getDefinition($serviceId);
        }

        // Some service IDs don't have a Definition, they're simply an Alias
        if ($this->containerBuilder->hasAlias($serviceId)) {
            return $this->containerBuilder->getAlias($serviceId);
        }

        // the service has been injected in some special way, just return the service
        return $this->containerBuilder->get($serviceId);
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'container';
    }
}
