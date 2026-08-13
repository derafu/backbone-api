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

use Derafu\BackboneApi\Abstract\AbstractController;
use Derafu\TestsBackboneApi\Fixture\ExampleController;
use Derafu\TestsBackboneApi\Fixture\FixedResultDispatcher;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractController::class)]
class AbstractControllerTest extends TestCase
{
    private function controllerFor(mixed $result): ExampleController
    {
        return new ExampleController(new FixedResultDispatcher($result));
    }

    public function testReturnsAResponseInterfaceFromTheDispatcherUnchanged(): void
    {
        $response = new Response(200, [], 'raw body');
        $controller = $this->controllerFor($response);

        $result = $controller->dispatch(new ServerRequest('GET', '/api'));

        $this->assertSame($response, $result);
    }

    public function testReturnsAnOpenApiDocumentUnchangedEvenWhenJsonIsAccepted(): void
    {
        $openApiDocument = ['openapi' => '3.1.0', 'paths' => []];
        $controller = $this->controllerFor($openApiDocument);
        $request = new ServerRequest('GET', '/api/openapi-docs.json', ['Accept' => 'application/json']);

        $result = $controller->dispatch($request);

        $this->assertSame($openApiDocument, $result);
    }

    public function testReturnsTheRawResultWhenJsonIsNotAccepted(): void
    {
        $controller = $this->controllerFor(['foo' => 'bar']);
        $request = new ServerRequest('GET', '/api/foo', ['Accept' => 'text/html']);

        $result = $controller->dispatch($request);

        $this->assertSame(['foo' => 'bar'], $result);
    }

    public function testWrapsTheResultInAMetaEnvelopeWhenJsonIsAccepted(): void
    {
        $controller = $this->controllerFor(['foo' => 'bar']);
        $request = new ServerRequest('GET', '/api/foo', ['Accept' => 'application/json']);

        $result = $controller->dispatch($request);

        $this->assertSame(['foo' => 'bar'], $result['data']);
        $this->assertSame('array', $result['meta']['data_type']);
        $this->assertIsFloat($result['meta']['timestamp']);
    }

    public function testWrapsTheResultWhenTheWildcardAcceptHeaderIsUsed(): void
    {
        $controller = $this->controllerFor('a plain string result');
        $request = new ServerRequest('GET', '/api/foo', ['Accept' => '*/*']);

        $result = $controller->dispatch($request);

        $this->assertSame('a plain string result', $result['data']);
        $this->assertSame('string', $result['meta']['data_type']);
    }

    public function testReturnsTheRawResultWhenItCannotBeJsonEncoded(): void
    {
        $controller = $this->controllerFor(NAN);
        $request = new ServerRequest('GET', '/api/foo', ['Accept' => 'application/json']);

        $result = $controller->dispatch($request);

        $this->assertNan($result);
    }
}
