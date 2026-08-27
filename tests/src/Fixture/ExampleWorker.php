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
use InvalidArgumentException;
use RuntimeException;

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

    /**
     * Creates a new example resource.
     *
     * Deliberately documented with everything `Inspector` now reports
     * (`returns`, multiple `throws`, multiple `link`) so `DocumenterTest`
     * can verify `Documenter` actually surfaces all of it in the generated
     * OpenAPI document, not just the fields it already covered before.
     *
     * @param string $name Name of the resource to create.
     * @return array The created resource, with its assigned `name`.
     * @throws InvalidArgumentException If `$name` is empty.
     * @throws RuntimeException If the resource could not be persisted.
     * @link https://example.test/docs/create Primary reference for this operation.
     * @link https://example.test/docs/create-schema Schema reference for the created resource.
     */
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

    /**
     * Gets the status of an example resource.
     *
     * Documented with exactly one `@link`, unlike `create()` (two) — so
     * `DocumenterTest` can verify the single-link case still uses
     * `externalDocs`, instead of also being appended to `description`.
     *
     * @param string $name Name of the resource to check.
     * @return array The resource's current status.
     * @link https://example.test/docs/get-status Reference for this operation.
     */
    public function getStatus(string $name): array
    {
        return ['name' => $name, 'status' => 'ok'];
    }
}
