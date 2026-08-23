<?php

declare(strict_types=1);

/**
 * Derafu: Backbone API - HTTP API with Autodiscovery.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneApi\Fixture;

use Derafu\Backbone\Contract\PackageInterface;
use Derafu\Backbone\Contract\PackageRegistryInterface;
use Derafu\Backbone\Exception\PackageNotFoundException;

/**
 * A real, minimal package registry.
 */
class ExamplePackageRegistry implements PackageRegistryInterface
{
    /**
     * @var array<string, PackageInterface>
     */
    private array $packages = [];

    public function registerPackage(string $name, PackageInterface $package): static
    {
        $this->packages[$name] = $package;

        return $this;
    }

    public function getPackage(string $name): PackageInterface
    {
        if (!isset($this->packages[$name])) {
            throw PackageNotFoundException::forPackage($name);
        }

        return $this->packages[$name];
    }

    public function getPackages(): array
    {
        return $this->packages;
    }

    public function hasPackage(string $name): bool
    {
        return isset($this->packages[$name]);
    }
}
