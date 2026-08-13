<?php

declare(strict_types=1);

/**
 * Derafu: Backbone API - HTTP API with Autodiscovery.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneApi\Abstract;

use Derafu\BackboneApi\Contract\DispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractController
{
    public function __construct(
        private readonly DispatcherInterface $dispatcher,
    ) {
    }

    public function dispatch(ServerRequestInterface $request): mixed
    {
        $result = $this->dispatcher->dispatch($request);

        return $this->sendResponseApi($request, $result);
    }

    protected function sendResponseApi(
        ServerRequestInterface $request,
        mixed $result
    ): mixed {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if (is_array($result) && !empty($result['openapi'])) {
            return $result;
        }

        $accept = $request->getHeader('Accept');
        if (!in_array('application/json', $accept) && !in_array('*/*', $accept)) {
            return $result;
        }

        json_encode($result);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $result;
        }

        return [
            'meta' => [
                'timestamp' => microtime(true),
                'data_type' => is_object($result) ? get_class($result) : gettype($result),
            ],
            'data' => $result,
        ];
    }
}
