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
use Derafu\Backbone\Contract\WorkerInterface;
use Derafu\Backbone\Exception\WorkerNotFoundException;
use Derafu\Config\Trait\OptionsAwareTrait;

/**
 * A real component holding a fixed set of real workers.
 */
class ExampleComponent implements ComponentInterface
{
    use OptionsAwareTrait;

    /**
     * @param array<string, WorkerInterface> $workers
     */
    public function __construct(
        private readonly array $workers,
    ) {
    }

    public function getId(): int|string
    {
        return 'example_component';
    }

    public function getName(): string
    {
        return 'Example Component';
    }

    public function getDescription(): ?string
    {
        return 'A component holding a fixed set of real workers.';
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getWorker(string $name): WorkerInterface
    {
        if (!isset($this->workers[$name])) {
            throw WorkerNotFoundException::forWorker($name);
        }

        return $this->workers[$name];
    }

    public function getWorkers(): array
    {
        return $this->workers;
    }
}
