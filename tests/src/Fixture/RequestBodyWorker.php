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

/**
 * Receives JSON parameter values through the real dispatch chain.
 */
final class RequestBodyWorker extends ExampleWorker
{
    private int $calls = 0;

    /**
     * @param string|int|float|bool|array<array-key, mixed>|null $payload
     * @return string|int|float|bool|array<array-key, mixed>|null
     */
    public function receive(string|int|float|bool|array|null $payload = null): string|int|float|bool|array|null
    {
        $this->calls++;

        return $payload;
    }

    public function getCalls(): int
    {
        return $this->calls;
    }
}
