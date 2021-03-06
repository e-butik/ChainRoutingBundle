<?php

namespace Symfony\Cmf\Bundle\ChainRoutingBundle\Routing;

use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Cmf\Bundle\ChainRoutingBundle\Resolver\ControllerResolverInterface;

/**
 * A router that reads entries from a Object-Document Mapper store.
 *
 * For Doctrine PHPCR-ODM, inject the $idPrefix to point to the node under
 * which you stored the route documents.
 *
 * For other doctrine types, inject $routeClass so that this router knows in
 * which table to look for routes. It will call find on the object manager with
 * this class and the url. Make sure to provide a repository implementation
 * that can find the document/entity by url.
 *
 * @author Philippo de Santis
 * @author David Buchmann
 */
class DoctrineRouter implements RouterInterface
{
    protected $om;
    protected $resolvers;
    protected $routeClass;
    protected $idPrefix;
    protected $context;

    /**
     * @param ObjectManager $om The doctrine entity resp. document manager
     * @param string $routeClass Class name to pass to $om->find for
     *      repositories that require the class of the Entity/Document to find.
     *      Automatically detected on phpcr-odm.
     * @param string $idPrefix A prefix to prepend to the url when looking it
     *      up in the repository, used with phpcr-odm to specify the node
     *      containing the route nodes. This must start with / and may not end
     *      with / as the url passed in will start with /.
     */
    public function __construct(ObjectManager $om, $routeClass = null, $idPrefix = '')
    {
        $this->setObjectManager($om);
        $this->routeClass = $routeClass;
        $this->idPrefix = $idPrefix;
    }

    /**
     * Add as many resolvers as you want, they are asked for the controller in
     * the order they are added here.
     *
     * @param ControllerResolverInterface $resolver a helper to resolve the
     *      controller responsible for the matched url
     */
    public function addControllerResolver(ControllerResolverInterface $resolver)
    {
        $this->resolvers[] = $resolver;
    }

    // inherit doc
    public function setContext(RequestContext $context)
    {
        $this->context = $context;
    }
    // inherit doc
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritDoc}
     *
     * @throws RouteNotFoundException If there is no such route in the database
     */
    public function generate($name, $parameters = array(), $absolute = false)
    {
        /* TODO */
        // we have to use the $this->idPrefix here too i guess
        throw new \Symfony\Component\Routing\Exception\RouteNotFoundException;
    }
    public function getRouteCollection()
    {
        /* TODO */
        return new \Symfony\Component\Routing\RouteCollection();
    }

    /**
     * Set the doctrine entity or document manager that will know the urls
     */
    public function setObjectManager(ObjectManager $om)
    {
        $this->om = $om;
    }

    /**
     * Returns an array of parameter like this
     *
     * array(
     *   "_controller" => "NameSpace\Controller::indexAction",
     *   "reference" => $document,
     * )
     *
     * The controller can be either the fully qualified class name or the
     * service name of a controller that is registered as a service. In both
     * cases, the action to call on that controller is appended, separated with
     * two colons.
     *
     * @throws ResourceNotFoundException If the requested url does not exist in the ODM
     * @throws MethodNotAllowedException If the resource was found but the request method is not allowed
     *
     * @param string $url the full requested url. TODO: is locale eaten away or kept too?
     *
     * @return array as described above
     */
    public function match($url)
    {
        $document = $this->om->find($this->routeClass, $this->idPrefix . $url);

        if (!$document instanceof RouteObjectInterface) {
            throw new \Symfony\Component\Routing\Exception\ResourceNotFoundException("No entry or not a route at '$url'");
        }

        $defaults = $document->getRouteDefaults();

        if (empty($defaults['_controller'])) {
            foreach($this->resolvers as $resolver) {
                $controller = $resolver->getController($document);
                if ($controller !== false) break;
            }
            if (false === $controller) {
                throw new \Symfony\Component\Routing\Exception\ResourceNotFoundException("The resolver was not able to determine a controller for '$url'");;
            }
            $defaults['_controller'] = $controller;
        }

        $defaults['reference'] = $document->getReference();
        $defaults['_route'] = 'whatever'; //FIXME: what is this? without, we get an undefined index in RouterListener::onKernelRequest

        return $defaults;
    }

}
