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
use Derafu\BackboneApi\Service\HttpStatusResolver;
use Derafu\BackboneDispatcher\ValueObject\ExecutionMetadata;
use Derafu\BackboneDispatcher\ValueObject\OperationResult;
use Derafu\BackboneDispatcher\ValueObject\ProblemDetail;
use Derafu\BackboneDispatcher\ValueObject\SafeThrowable;
use Derafu\TestsBackboneApi\Fixture\ExampleController;
use Derafu\TestsBackboneApi\Fixture\FixedResultDispatcher;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Real `Nyholm\Psr7\Factory\Psr17Factory` (a concrete PSR-17
 * implementation, already a dev dependency for exactly this) and real
 * `derafu/backbone-dispatcher` value objects throughout — no mocks.
 *
 * The value objects (`OperationResult`, `ProblemDetail`, `SafeThrowable`,
 * `ExecutionMetadata`) are not declared with `#[UsesClass]`: they live in
 * `derafu/backbone-dispatcher`, outside this project's own coverage
 * whitelist, and PHPUnit rejects `#[UsesClass]` targets outside it.
 */
#[CoversClass(AbstractController::class)]
#[UsesClass(HttpStatusResolver::class)]
class AbstractControllerTest extends TestCase
{
    private function measure(): array
    {
        return [date(DATE_ATOM), hrtime(true), memory_get_usage(true), getrusage()];
    }

    private function controllerFor(mixed $result): ExampleController
    {
        return new ExampleController(
            new FixedResultDispatcher($result),
            new HttpStatusResolver(),
            new Psr17Factory(),
        );
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

    public function testASuccessfulOperationResultUsesItsOwnPrecomputedMetaInsteadOfRecomputingIt(): void
    {
        [$startedAt, $monotonicStart, $startMemory, $startCpuUsage] = $this->measure();
        $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, $startCpuUsage);
        $operationResult = OperationResult::success(
            ['id' => 'INV-001'],
            $metadata,
            'libredte\lib\Core\Package\Billing\Component\Document\Entity\Document',
        );

        $controller = $this->controllerFor($operationResult);
        $request = new ServerRequest('GET', '/api/foo');

        $result = $controller->dispatch($request);

        $this->assertSame(['id' => 'INV-001'], $result['data']);
        $this->assertSame(
            'libredte\lib\Core\Package\Billing\Component\Document\Entity\Document',
            $result['meta']['data_type'],
        );
        $this->assertSame($metadata->getTimestamp(), $result['meta']['timestamp']);
    }

    public function testAFailedOperationResultReturnsARealResponseWithTheResolvedStatusAndTheProblemBody(): void
    {
        [$startedAt, $monotonicStart, $startMemory, $startCpuUsage] = $this->measure();
        $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, $startCpuUsage);
        $throwable = new RuntimeException('The parameter "amount" is missing.');
        $problem = new ProblemDetail(
            detail: $throwable->getMessage(),
            throwable: SafeThrowable::fromThrowable($throwable),
            timestamp: $metadata->getTimestamp(),
            environment: 'test',
            instance: 'billing.invoice.builder::build',
        );
        $operationResult = OperationResult::failure($problem, $metadata);

        $controller = $this->controllerFor($operationResult);
        $request = new ServerRequest('GET', '/api/foo');

        $response = $controller->dispatch($request);

        // RuntimeException is not one of HttpStatusResolver's known
        // dispatcher-generic exceptions, so it falls back to 500 — real
        // and deterministic, not something this test has to special-case.
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('The parameter "amount" is missing.', $body['detail']);
        $this->assertNull($body['extensions']['data_type']);
    }
}
