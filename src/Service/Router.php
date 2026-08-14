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

use Derafu\BackboneApi\Contract\RouteMatchInterface;
use Derafu\BackboneApi\Contract\RouterInterface;
use Derafu\BackboneApi\Exception\InvalidRouteException;
use Derafu\BackboneApi\ValueObject\RouteMatch;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class for determining the routing of a URL of the API to the service of the
 * API that will process it.
 */
class Router implements RouterInterface
{
    /**
     * Processes the route that has been requested to the API.
     *
     * Returns an object with the attributes:
     *
     *  - `package`.
     *  - `component`.
     *  - `worker`.
     *  - `operation`.
     *
     * If any of the attributes is `null` it means that the attribute was not
     * requested. In that case, for example, the list associated with the
     * missing attribute can be delivered.
     *
     * @param ServerRequestInterface $request
     * @return RouteMatchInterface
     */
    public function parse(ServerRequestInterface $request): RouteMatchInterface
    {
        $path = $request->getUri()->getPath();
        if (str_starts_with($path, '/api/')) {
            $path = substr($path, 4);
        }
        $path = trim($path, '/');
        $parts = explode('/', $path);

        if (isset($parts[4])) {
            throw InvalidRouteException::forPath($path);
        }

        return new RouteMatch(
            package: $parts[0] ?: null,
            component: ($parts[1] ?? null) ?: null,
            worker: ($parts[2] ?? null) ?: null,
            operation: ($parts[3] ?? null) ?: null,
        );
    }
}
