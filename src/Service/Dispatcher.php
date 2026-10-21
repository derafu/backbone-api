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
use Derafu\BackboneApi\Exception\InvalidRequestBodyException;
use Derafu\BackboneDispatcher\Contract\OperationResultInterface;
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use Derafu\Translation\Exception\Core\TranslatableLogicException as LogicException;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handler of the requests of the application.
 *
 * Takes a route resolved by the router and executes it. The actual
 * resolution of the worker and invocation of the operation is delegated to
 * `derafu/backbone-dispatcher`, which is transport-agnostic. This class only
 * deals with the HTTP-specific concerns: parsing the route, listing
 * packages/components/workers as HATEOAS resources, serving the OpenAPI
 * documentation, and extracting the parameters from the JSON request body.
 *
 * `handleOperation()` uses `SafeDispatcherInterface` (never throws) rather
 * than a lower tier: it returns the resulting `OperationResultInterface`
 * as-is, unwrapped — `AbstractController` is the single place that turns
 * any controller result into the final API response, so the
 * success/failure envelope (and, for a failure, the actual HTTP status)
 * belongs there, not duplicated here.
 */
class Dispatcher implements DispatcherInterface
{
    /**
     * Constructor with dependencies.
     *
     * @param RouterInterface $router
     * @param Explorer $explorer
     * @param Documenter $documenter
     * @param SafeDispatcherInterface $dispatcher
     */
    public function __construct(
        private RouterInterface $router,
        private Explorer $explorer,
        private Documenter $documenter,
        private SafeDispatcherInterface $dispatcher,
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

        $package = $route->getPackage();
        if ($package === null) {
            throw new LogicException(
                'A non-root API route must specify a package.'
            );
        }

        if ($route->getComponent() === null) {
            return $this->handlePackage(
                $package
            );
        } elseif ($route->getWorker() === null) {
            return $this->handleComponent(
                $package,
                $route->getComponent()
            );
        } elseif ($route->getOperation() === null) {
            return $this->handleWorker(
                $package,
                $route->getComponent(),
                $route->getWorker()
            );
        } else {
            return $this->handleOperation(
                $request,
                $package,
                $route->getComponent(),
                $route->getWorker(),
                $route->getOperation()
            );
        }
    }

    /**
     * Handles the root page of the API.
     *
     * @return array<string, mixed>
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
     * @return array<string, mixed>
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
     * @return array<string, mixed>
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
     * @return array<string, mixed>
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
            withOperations: true
        );
    }

    /**
     * Handles the execution of an operation in the API.
     *
     * @param ServerRequestInterface $request
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param string $operation
     * @return OperationResultInterface
     */
    private function handleOperation(
        ServerRequestInterface $request,
        string $package,
        string $component,
        string $worker,
        string $operation
    ): OperationResultInterface {
        $body = $request->getBody()->getContents();
        $params = [];
        if ($body !== '') {
            try {
                $requestContent = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidRequestBodyException(
                    'The API request body must contain valid JSON.',
                    previous: $exception,
                );
            }

            // Associative decoding preserves nested arrays, but maps both JSON
            // objects and arrays to PHP arrays. Check the root token as well.
            if (!is_array($requestContent) || !str_starts_with(ltrim($body), '{')) {
                throw new InvalidRequestBodyException(
                    'The API request body must be a JSON object.'
                );
            }

            $rawParameters = $requestContent['parameters'] ?? [];
            if (!is_array($rawParameters)) {
                throw new InvalidRequestBodyException(
                    'API request parameters must be an object with named parameters or an empty array.'
                );
            }

            foreach ($rawParameters as $name => $value) {
                if (!is_string($name)) {
                    throw new InvalidRequestBodyException(
                        'API request parameters must be an object with named parameters or an empty array.'
                    );
                }
                $params[$name] = $value;
            }
        }

        $operationRequest = new OperationRequest($package, $component, $worker, $operation, $params);

        return $this->dispatcher->dispatch($operationRequest);
    }
}
