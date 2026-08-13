<?php

declare(strict_types=1);

/**
 * Derafu: Backbone API - HTTP API with Autodiscovery.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneApi\Service;

use Derafu\BackboneApi\Contract\DispatcherInterface;
use Derafu\BackboneApi\Contract\RouterInterface;
use Derafu\BackboneDispatcher\Contract\DispatcherInterface as WorkerDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handler of the requests of the application.
 *
 * Takes a route resolved by the router and executes it. The actual
 * resolution of the worker and invocation of the job is delegated to
 * `derafu/backbone-dispatcher`, which is transport-agnostic. This class only
 * deals with the HTTP-specific concerns: parsing the route, listing
 * packages/components/workers as HATEOAS resources, serving the OpenAPI
 * documentation, and extracting the parameters from the JSON request body.
 */
class Dispatcher implements DispatcherInterface
{
    /**
     * Constructor with dependencies.
     *
     * @param RouterInterface $router
     * @param Explorer $explorer
     * @param Documenter $documenter
     * @param WorkerDispatcherInterface $dispatcher
     */
    public function __construct(
        private RouterInterface $router,
        private Explorer $explorer,
        private Documenter $documenter,
        private WorkerDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Executes the application service that corresponds to the route that was
     * passed as an argument.
     *
     * @param ServerRequestInterface $request
     * @return mixed
     */
    public function dispatch(ServerRequestInterface $request): mixed
    {
        $route = $this->router->parse($request);

        if ($route->getId() === 'index' || $route->getId() === null) {
            return $this->handleRoot();
        }

        if ($route->getId() === 'openapi-docs.json') {
            return $this->documenter->document();
        }

        if ($route->getComponent() === null) {
            return $this->handlePackage(
                $route->getPackage()
            );
        } elseif ($route->getWorker() === null) {
            return $this->handleComponent(
                $route->getPackage(),
                $route->getComponent()
            );
        } elseif ($route->getJob() === null) {
            return $this->handleWorker(
                $route->getPackage(),
                $route->getComponent(),
                $route->getWorker()
            );
        } else {
            return $this->handleJob(
                $request,
                $route->getPackage(),
                $route->getComponent(),
                $route->getWorker(),
                $route->getJob()
            );
        }
    }

    /**
     * Handles the root page of the API.
     *
     * @return array
     */
    public function handleRoot(): array
    {
        return [
            '_links' => [
                'self' => [
                    'href' => $this->explorer->getUrl(),
                ],
            ],
            'packages' => $this->explorer->getPackages(),
        ];
    }

    /**
     * Handles the URL of a package in the API.
     *
     * @param string $package
     * @return array
     */
    public function handlePackage(string $package): array
    {
        return $this->explorer->getPackage($package, withComponents: true);
    }

    /**
     * Handles the URL of a component in the API.
     *
     * @param string $package
     * @param string $component
     * @return array
     */
    public function handleComponent(string $package, string $component): array
    {
        return $this->explorer->getComponent(
            $package,
            $component,
            withWorkers: true
        );
    }

    /**
     * Handles the URL of a worker in the API.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @return array
     */
    public function handleWorker(
        string $package,
        string $component,
        string $worker
    ): array {
        return $this->explorer->getWorker(
            $package,
            $component,
            $worker,
            withJobs: true
        );
    }

    /**
     * Handles the execution of a job in the API.
     *
     * @param ServerRequestInterface $request
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param string $job
     * @return mixed
     */
    private function handleJob(
        ServerRequestInterface $request,
        string $package,
        string $component,
        string $worker,
        string $job
    ): mixed {
        $requestContent = json_decode($request->getBody()->getContents(), true);
        $params = $requestContent['parameters'] ?? [];

        return $this->dispatcher->invoke($package, $component, $worker, $job, $params);
    }
}
