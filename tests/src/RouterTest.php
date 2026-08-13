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
use Derafu\BackboneApi\Service\Router;
use Derafu\BackboneApi\ValueObject\RouteMatch;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Router::class)]
#[UsesClass(InvalidRouteException::class)]
#[UsesClass(RouteMatch::class)]
class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testParsesTheFourSegmentsOfAJobRoute(): void
    {
        $request = new ServerRequest('POST', '/api/billing/document/builder/build');

        $route = $this->router->parse($request);

        $this->assertSame('billing', $route->getPackage());
        $this->assertSame('document', $route->getComponent());
        $this->assertSame('builder', $route->getWorker());
        $this->assertSame('build', $route->getJob());
        $this->assertSame('billing.document.builder', $route->getId());
    }

    public function testMissingTrailingSegmentsAreNull(): void
    {
        $request = new ServerRequest('GET', '/api/billing');

        $route = $this->router->parse($request);

        $this->assertSame('billing', $route->getPackage());
        $this->assertNull($route->getComponent());
        $this->assertNull($route->getWorker());
        $this->assertNull($route->getJob());
    }

    public function testEmptyPathHasNoPackageAndNoId(): void
    {
        // Note the trailing slash: a bare "/api" (no trailing slash) does
        // NOT match the "/api/" prefix check, so it is left as the literal
        // segment "api" instead of being stripped down to an empty path.
        // Real deployments never hit this because the app-level router
        // redirects a bare "/api" to "/api/index" before Router::parse()
        // is ever called.
        $request = new ServerRequest('GET', '/api/');

        $route = $this->router->parse($request);

        $this->assertNull($route->getPackage());
        $this->assertNull($route->getId());
    }

    public function testWorksTheSameWithOrWithoutTheApiPrefix(): void
    {
        $withPrefix = $this->router->parse(new ServerRequest('GET', '/api/billing/document'));
        $withoutPrefix = $this->router->parse(new ServerRequest('GET', '/billing/document'));

        $this->assertSame($withPrefix->getId(), $withoutPrefix->getId());
    }

    public function testThrowsInvalidRouteExceptionWhenThereAreMoreThanFourSegments(): void
    {
        $request = new ServerRequest('GET', '/api/a/b/c/d/e');

        $this->expectException(InvalidRouteException::class);
        $this->expectExceptionMessage(
            'The used route a/b/c/d/e is not valid. As a maximum it can have the structure: /:package/:component/:worker/:job.'
        );

        $this->router->parse($request);
    }
}
