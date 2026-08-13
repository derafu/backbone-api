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

use Derafu\BackboneApi\Contract\DispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A real, minimal DispatcherInterface that always returns a fixed result,
 * used to test AbstractController in isolation from the real routing and
 * job invocation logic.
 */
class FixedResultDispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly mixed $result,
    ) {
    }

    public function dispatch(ServerRequestInterface $request): mixed
    {
        return $this->result;
    }
}
