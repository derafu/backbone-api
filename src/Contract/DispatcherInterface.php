<?php

declare(strict_types=1);

/**
 * Derafu: Backbone API - HTTP API with Autodiscovery.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneApi\Contract;

use Psr\Http\Message\ServerRequestInterface;

interface DispatcherInterface
{
    public function dispatch(ServerRequestInterface $request): mixed;
}
