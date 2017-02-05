<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new AppBundle\AppBundle(),
        ];

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
        }

        return $bundles;
    }

    public function getRootDir()
    {
        return __DIR__;
    }

    public function getCacheDir()
    {
        return dirname(__DIR__).'/var/cache/'.$this->getEnvironment();
    }

    public function getLogDir()
    {
        return dirname(__DIR__).'/var/logs';
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->getRootDir().'/config/config_'.$this->getEnvironment().'.yml');
    }

    // =============================================================================================
    //  перенесем сюда часть методов из базового класса. чтобы было понятнее как работает
    // =============================================================================================

    /**
     * Boots the current kernel.
     */
    public function boot()
    {
        if (true === $this->booted) {
            return;
        }

        if ($this->loadClassCache) {
            $this->doLoadClassCache($this->loadClassCache[0], $this->loadClassCache[1]);
        }

        // проинициализирует список всех бандлов из вызова registerBundles()
        // с учетом зависимостей и перекрытия (оверайда)
        $this->initializeBundles();

        // !!!!!!!!!!!!!! ЭТО САМАЯ ИНТЕСНАЯ ЧАСТЬ !!!!!!!!!!!!!!!
        $this->initializeContainer();

        // инжектиться контейнер с сервисами в бандлы
        foreach ($this->getBundles() as $bundle) {
            $bundle->setContainer($this->container);
            // и выполняется их инициализаци !!!!!!! тоже очень интересное место !!!!
            $bundle->boot();
        }

        $this->booted = true;
    }

    /**
     *
     * Точка входа из index.php
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $type
     * @param bool $catch  - true - Handles an exception by trying to convert it to a Response.
     *                       false - Publishes the finish request event, then pop the request from the stack.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Symfony\Component\HttpFoundation\Request $request, $type = Symfony\Component\HttpKernel\HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        if (false === $this->booted) {
            $this->boot();
        }

        // сам handle() try-catch обертка над handleRaw(), в котором запрос-ответ оборачивается в событие и передается
        // диспатчеру , проходя последовательно стадии

        /*
       $this->requestStack->push($request);
        // request
        $event = new GetResponseEvent($this, $request, $type);
        $this->dispatcher->dispatch(KernelEvents::REQUEST, $event);
        if ($event->hasResponse()) {
            return $this->filterResponse($event->getResponse(), $request, $type);
        }
        // load controller
        if (false === $controller = $this->resolver->getController($request)) {
            throw new NotFoundHttpException(sprintf('Unable to find the controller for path "%s". The route is wrongly configured.', $request->getPathInfo()));
        }
        $event = new FilterControllerEvent($this, $controller, $request, $type);
        $this->dispatcher->dispatch(KernelEvents::CONTROLLER, $event);
        $controller = $event->getController();
        // controller arguments
        $arguments = $this->argumentResolver->getArguments($request, $controller);
        $event = new FilterControllerArgumentsEvent($this, $controller, $arguments, $request, $type);
        $this->dispatcher->dispatch(KernelEvents::CONTROLLER_ARGUMENTS, $event);
        $controller = $event->getController();
        $arguments = $event->getArguments();
        // call controller
        $response = call_user_func_array($controller, $arguments);
        // view
        if (!$response instanceof Response) {
            $event = new GetResponseForControllerResultEvent($this, $request, $type, $response);
            $this->dispatcher->dispatch(KernelEvents::VIEW, $event);
            if ($event->hasResponse()) {
                $response = $event->getResponse();
            }
            if (!$response instanceof Response) {
                $msg = sprintf('The controller must return a response (%s given).', $this->varToString($response));
                // the user may have forgotten to return something
                if (null === $response) {
                    $msg .= ' Did you forget to add a return statement somewhere in your controller?';
                }
                throw new \LogicException($msg);
            }
        }
        return $this->filterResponse($response, $request, $type);

         */


        return $this->getHttpKernel()->handle($request, $type, $catch);

    }

    /**
     * Gets a HTTP kernel from the container.
     *
     * @return Symfony\Component\HttpKernel\HttpKernel
     */
    protected function getHttpKernel()
    {
        // подключен в конфиге симфони_фреймворк_бандла
        // vendor/symfony/symfony/src/Symfony/Bundle/FrameworkBundle/Resources/config/services.xml:12
        return $this->container->get('http_kernel');
    }

}
