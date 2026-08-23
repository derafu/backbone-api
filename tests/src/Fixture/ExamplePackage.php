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

use Derafu\Backbone\Contract\ComponentInterface;
use Derafu\Backbone\Contract\PackageInterface;
use Derafu\Backbone\Exception\ComponentNotFoundException;
use Derafu\Config\Trait\OptionsAwareTrait;

/**
 * A real package holding a fixed set of real components.
 */
class ExamplePackage implements PackageInterface
{
    use OptionsAwareTrait;

    /**
     * @param array<string, ComponentInterface> $components
     */
    public function __construct(
        private readonly array $components,
    ) {
    }

    public function getId(): int|string
    {
        return 'example_package';
    }

    public function getName(): string
    {
        return 'Example Package';
    }

    public function getDescription(): ?string
    {
        return 'A package holding a fixed set of real components.';
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getComponent(string $name): ComponentInterface
    {
        if (!isset($this->components[$name])) {
            throw ComponentNotFoundException::forComponent($name);
        }

        return $this->components[$name];
    }

    public function getComponents(): array
    {
        return $this->components;
    }
}
