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

use Derafu\BackboneApi\ValueObject\RouteMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteMatch::class)]
final class RouteMatchTest extends TestCase
{
    #[TestWith([null, null, null, null, null])]
    #[TestWith(['billing', null, null, null, 'billing'])]
    #[TestWith(['billing', 'document', null, null, 'billing.document'])]
    #[TestWith(['billing', 'document', 'builder', null, 'billing.document.builder'])]
    #[TestWith(['billing', 'document', 'builder', 'build', 'billing.document.builder'])]
    public function testPreservesRouteSegmentsAndReturnsAStableResourceId(
        ?string $package,
        ?string $component,
        ?string $worker,
        ?string $operation,
        ?string $expectedId
    ): void {
        $route = new RouteMatch($package, $component, $worker, $operation);

        $this->assertSame($package, $route->getPackage());
        $this->assertSame($component, $route->getComponent());
        $this->assertSame($worker, $route->getWorker());
        $this->assertSame($operation, $route->getOperation());
        $this->assertSame(
            [$expectedId, $expectedId],
            [$route->getId(), $route->getId()],
        );
    }

    public function testOperationsOnTheSameWorkerShareTheResourceId(): void
    {
        $build = new RouteMatch('billing', 'document', 'builder', 'build');
        $validate = new RouteMatch('billing', 'document', 'builder', 'validate');

        $this->assertSame('billing.document.builder', $build->getId());
        $this->assertSame($build->getId(), $validate->getId());
        $this->assertSame('build', $build->getOperation());
        $this->assertSame('validate', $validate->getOperation());
    }
}
