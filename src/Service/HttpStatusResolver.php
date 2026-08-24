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

use Derafu\BackboneDispatcher\Exception\ClassNotFoundException;
use Derafu\BackboneDispatcher\Exception\FromArrayMethodNotFoundException;
use Derafu\BackboneDispatcher\Exception\InvalidParameterTypeException;
use Derafu\BackboneDispatcher\Exception\MissingParameterException;
use Derafu\BackboneDispatcher\Exception\NoDeserializerFoundException;
use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;

/**
 * Resolves an HTTP status code for one dispatch scenario — a real, thrown
 * exception's FQCN, or the literal `'success'`.
 *
 * Shared by two consumers: `Documenter`, which resolves a `#[Operation(
 * results: ...)]` scenario key to document in the OpenAPI spec, and
 * `AbstractController`, which resolves a real failed
 * `OperationResultInterface`'s throwable class to the actual status of
 * the response it sends — the same resolution, used for two different
 * purposes, kept in one place instead of two copies drifting apart.
 *
 * Only `derafu/backbone-dispatcher`'s own generic exceptions are mapped
 * here (the same 7 `DefaultExitCodeResolver` maps for the console bridge,
 * mirroring that same split): a business-specific exception a consuming
 * project's own Worker throws is not something this package can know
 * about, and falls back to `500`.
 */
class HttpStatusResolver
{
    /**
     * @param string $scenario `'success'`, or the FQCN of a thrown
     * exception.
     * @return int
     */
    public function resolve(string $scenario): int
    {
        return match ($scenario) {
            'success' => 200,
            OperationNotAllowedException::class => 403,
            MissingParameterException::class,
            InvalidParameterTypeException::class,
            NoDeserializerFoundException::class,
            ClassNotFoundException::class,
            FromArrayMethodNotFoundException::class => 422,
            default => 500,
        };
    }
}
