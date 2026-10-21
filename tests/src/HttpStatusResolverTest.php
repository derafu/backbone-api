<?php

declare(strict_types=1);

/**
 * Derafu: Backbone API - HTTP API with Autodiscovery.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneApi;

use Derafu\BackboneApi\Service\HttpStatusResolver;
use Derafu\BackboneDispatcher\Exception\ClassNotFoundException;
use Derafu\BackboneDispatcher\Exception\FromArrayMethodNotFoundException;
use Derafu\BackboneDispatcher\Exception\InvalidParameterTypeException;
use Derafu\BackboneDispatcher\Exception\MissingParameterException;
use Derafu\BackboneDispatcher\Exception\NoDeserializerFoundException;
use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(HttpStatusResolver::class)]
final class HttpStatusResolverTest extends TestCase
{
    #[TestWith(['success', 200])]
    #[TestWith([OperationNotAllowedException::class, 403])]
    #[TestWith([MissingParameterException::class, 422])]
    #[TestWith([InvalidParameterTypeException::class, 422])]
    #[TestWith([NoDeserializerFoundException::class, 422])]
    #[TestWith([ClassNotFoundException::class, 422])]
    #[TestWith([FromArrayMethodNotFoundException::class, 422])]
    #[TestWith([RuntimeException::class, 500])]
    public function testResolvesTheHttpStatusForEachDispatchScenario(string $scenario, int $expected): void
    {
        $this->assertSame($expected, (new HttpStatusResolver())->resolve($scenario));
    }
}
