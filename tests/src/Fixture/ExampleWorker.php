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

use Derafu\Backbone\Attribute\Operation;
use Derafu\Backbone\Contract\WorkerInterface;
use Derafu\Backbone\Trait\HandlersAwareTrait;
use Derafu\Backbone\Trait\JobsAwareTrait;
use Derafu\Config\Trait\OptionsAwareTrait;

/**
 * A real worker with two tagged operations and one untagged public method,
 * used to verify `Documenter` follows whatever `Explorer`/`ExplorerInterface`
 * says is visible — including an untagged method under a permissive policy
 * (the real gap this fixture exists to reproduce: `getStatus()` was never
 * marked `#[Operation]`, yet a real `DirectDispatcher` with no restrictive
 * policy would dispatch it just the same) — never its own, separate opinion
 * based on the `#[Operation]` tag alone.
 */
class ExampleWorker implements WorkerInterface
{
    use JobsAwareTrait;
    use HandlersAwareTrait;
    use OptionsAwareTrait;

    public function getId(): int|string
    {
        return 'example_worker';
    }

    public function getName(): string
    {
        return 'Example Worker';
    }

    public function getDescription(): ?string
    {
        return 'A worker with two tagged operations.';
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    #[Operation]
    public function create(string $name): array
    {
        return ['name' => $name];
    }

    #[Operation]
    public function cancel(string $name): array
    {
        return ['name' => $name];
    }

    public function getStatus(string $name): array
    {
        return ['name' => $name, 'status' => 'ok'];
    }
}
