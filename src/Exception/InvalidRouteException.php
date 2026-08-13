<?php

declare(strict_types=1);

/**
 * Derafu: Backbone API - HTTP API with Autodiscovery.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneApi\Exception;

use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;

/**
 * Thrown when the requested URL does not match the expected
 * `/:package/:component/:worker/:job` route structure.
 */
class InvalidRouteException extends TranslatableInvalidArgumentException
{
    /**
     * Returns a new exception for a path that does not match the expected
     * `/:package/:component/:worker/:job` route structure.
     *
     * @param string $path The requested path.
     * @return self
     */
    public static function forPath(string $path): self
    {
        return new self([
            'The used route {path} is not valid. As a maximum it can have the structure: /:package/:component/:worker/:job.',
            'path' => $path,
        ]);
    }
}
