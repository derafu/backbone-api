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

use Derafu\BackboneApi\Exception\InvalidRouteException;
use Derafu\BackboneApi\Translation\BackboneApiTranslationResourceProvider;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidRouteException::class)]
#[UsesClass(BackboneApiTranslationResourceProvider::class)]
final class InvalidRouteExceptionTest extends TestCase
{
    public function testIncludesTheRejectedPathInTheMessageAndItsTranslation(): void
    {
        $exception = InvalidRouteException::forPath('billing/document/builder/build/extra');

        $this->assertSame(
            'The used route billing/document/builder/build/extra is not valid. As a maximum it can have the structure: /:package/:component/:worker/:operation.',
            $exception->getMessage(),
        );

        $translator = TranslatorFactory::create(
            'es',
            resourceProviders: [new BackboneApiTranslationResourceProvider()],
        );

        $this->assertSame(
            'La ruta utilizada billing/document/builder/build/extra no es válida. Como máximo puede tener la estructura: /:package/:component/:worker/:operation.',
            $exception->trans($translator),
        );
    }
}
