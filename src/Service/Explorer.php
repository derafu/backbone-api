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

use Derafu\Backbone\Contract\PackageRegistryInterface;
use Derafu\BackboneDispatcher\Service\Inspector;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Generates the data that allows the exploration of the API: HATEOAS.
 *
 * Se usa https://datatracker.ietf.org/doc/html/draft-kelly-json-hal-03
 */
class Explorer
{
    public function __construct(
        private PackageRegistryInterface $packageRegistry,
        private Inspector $inspector,
        private ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * Returns the list of packages of the application.
     *
     * @return array
     */
    public function getPackages(): array
    {
        return array_map(
            fn ($package) => $this->getPackage($package),
            array_keys($this->packageRegistry->getPackages())
        );
    }

    /**
     * Returns the list of components of a package.
     *
     * @param string $package
     * @return array
     */
    public function getComponents(string $package): array
    {
        return array_map(
            fn ($component) => $this->getComponent($package, $component),
            array_keys(
                $this->packageRegistry
                ->getPackage($package)
                ->getComponents()
            )
        );
    }

    /**
     * Returns the list of workers of a component.
     *
     * @param string $package
     * @param string $component
     * @return array
     */
    public function getWorkers(string $package, string $component): array
    {
        return array_map(
            fn ($worker) => $this->getWorker($package, $component, $worker),
            array_keys(
                $this->packageRegistry
                ->getPackage($package)
                ->getComponent($component)
                ->getWorkers()
            )
        );
    }

    /**
     * Returns the list of jobs of a worker.
     *
     * This will only return as jobs the public methods that the worker has
     * defined. It will not search for specific jobs for the worker, or search
     * for classes that implement JobInterface.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @return array
     */
    public function getJobs(
        string $package,
        string $component,
        string $worker
    ): array {
        $workerInstance =
            $this->packageRegistry
            ->getPackage($package)
            ->getComponent($component)
            ->getWorker($worker)
        ;

        $methods = $this->inspector->getPublicMethods($workerInstance);
        $methods = array_map(
            fn ($key, $value) => array_merge($value, ['name' => $key]),
            array_keys($methods),
            $methods
        );

        return array_map(
            fn ($info) => $this->getJob($package, $component, $worker, $info),
            $methods
        );
    }

    /**
     * Returns the data of a specific package.
     *
     * @param string $package
     * @param boolean $withComponents
     * @return array
     */
    public function getPackage(
        string $package,
        bool $withComponents = false
    ): array {
        $data = [
            'id' => $package,
            '_links' => [
                'self' => [
                    'href' => sprintf(
                        '%s/%s',
                        $this->getUrl(),
                        $package
                    ),
                ],
                'parent' => [
                    'href' => $this->getUrl(),
                ],
            ],
        ];

        if ($withComponents) {
            $data['components'] = $this->getComponents($package);
        }

        return $data;
    }

    /**
     * Returns the data of a specific component.
     *
     * @param string $package
     * @param string $component
     * @param boolean $withWorkers
     * @return array
     */
    public function getComponent(
        string $package,
        string $component,
        bool $withWorkers = false
    ): array {
        $data = [
            'id' => sprintf(
                '%s.%s',
                $package,
                $component
            ),
            '_links' => [
                'self' => [
                    'href' => sprintf(
                        '%s/%s/%s',
                        $this->getUrl(),
                        $package,
                        $component
                    ),
                ],
                'parent' => [
                    'href' => sprintf(
                        '%s/%s',
                        $this->getUrl(),
                        $package
                    ),
                ],
            ],
        ];

        if ($withWorkers) {
            $data['workers'] = $this->getWorkers($package, $component);
        }

        return $data;
    }

    /**
     * Returns the data of a specific worker.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param boolean $withJobs
     * @return array
     */
    public function getWorker(
        string $package,
        string $component,
        string $worker,
        bool $withJobs = false
    ): array {
        $data = [
            'id' => sprintf(
                '%s.%s.%s',
                $package,
                $component,
                $worker
            ),
            '_links' => [
                'self' => [
                    'href' => sprintf(
                        '%s/%s/%s/%s',
                        $this->getUrl(),
                        $package,
                        $component,
                        $worker
                    ),
                ],
                'parent' => [
                    'href' => sprintf(
                        '%s/%s/%s',
                        $this->getUrl(),
                        $package,
                        $component
                    ),
                ],
            ],
        ];

        if ($withJobs) {
            $data['jobs'] = $this->getJobs($package, $component, $worker);
        }

        return $data;
    }

    /**
     * Returns the data of a specific job.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param array $info
     * @return array
     */
    public function getJob(
        string $package,
        string $component,
        string $worker,
        array $info
    ): array {
        $job = $info['name'];
        unset($info['name']);

        return array_merge([
            'id' => sprintf(
                '%s.%s.%s.%s',
                $package,
                $component,
                $worker,
                $job
            ),
            '_links' => [
                'self' => [
                    'href' => sprintf(
                        '%s/%s/%s/%s/%s',
                        $this->getUrl(),
                        $package,
                        $component,
                        $worker,
                        $job
                    ),
                ],
                'parent' => [
                    'href' => sprintf(
                        '%s/%s/%s/%s',
                        $this->getUrl(),
                        $package,
                        $component,
                        $worker
                    ),
                ],
            ],
        ], $info);
    }

    /**
     * Returns the URL of the API.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->parameterBag->get('env.APP_URL') . '/api';
    }
}
