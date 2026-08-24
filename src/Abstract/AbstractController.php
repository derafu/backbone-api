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
use Derafu\BackboneApi\Service\HttpStatusResolver;
use Derafu\BackboneDispatcher\Contract\OperationResultInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractController
{
    public function __construct(
        private readonly DispatcherInterface $dispatcher,
        private readonly HttpStatusResolver $httpStatusResolver,
        private readonly ResponseFactoryInterface $responseFactory,
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

        if ($result instanceof OperationResultInterface) {
            return $this->sendOperationResult($result);
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

    /**
     * Turns a dispatched Backbone operation's result into the final API
     * response.
     *
     * On success: the same `{"meta": {...}, "data": ...}` envelope every
     * other controller result gets from `sendResponseApi()` above —
     * `meta.timestamp`/`meta.data_type` come straight from the result
     * itself (already known precisely) rather than being recomputed from
     * an already-serialized value.
     *
     * On failure: `SafeDispatcherInterface` never throws, so this is the
     * one place that decides what a failure actually looks like to the
     * client — a real PSR-7 response carrying the resolved HTTP status
     * (`HttpStatusResolver`, the same mapping documented in the OpenAPI
     * spec) and the `ProblemDetailInterface::toArray()` body, so a failure
     * is never silently answered with a `200`.
     *
     * @param OperationResultInterface $result
     * @return ResponseInterface|array
     */
    private function sendOperationResult(OperationResultInterface $result): ResponseInterface|array
    {
        if ($result->isSuccess()) {
            return [
                'meta' => [
                    'timestamp' => $result->getMetadata()->getTimestamp(),
                    'data_type' => $result->getDataType(),
                ],
                'data' => $result->getValue(),
            ];
        }

        $problem = $result->getProblem();
        $status = $this->httpStatusResolver->resolve($problem->getThrowable()->getClass());

        $response = $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
        ;
        $response->getBody()->write((string) json_encode($problem->toArray()));

        return $response;
    }
}
