<?php

declare(strict_types=1);

/**
 * Derafu: Backbone API - HTTP API with Autodiscovery.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneApi\Fixture;

use Derafu\BackboneDispatcher\Contract\ExecutionMetadataInterface;
use Derafu\BackboneDispatcher\Contract\OperationResultInterface;
use Derafu\BackboneDispatcher\Contract\ProblemDetailInterface;

/**
 * Simulates an implementation that incorrectly reports a successful result as failed.
 */
final class MisreportedOperationResult implements OperationResultInterface
{
    public function __construct(private readonly OperationResultInterface $result)
    {
    }

    public function isSuccess(): bool
    {
        return false;
    }

    public function getValue(): mixed
    {
        return $this->result->getValue();
    }

    public function getProblem(): ?ProblemDetailInterface
    {
        return $this->result->getProblem();
    }

    public function getDataType(): ?string
    {
        return $this->result->getDataType();
    }

    public function getMetadata(): ExecutionMetadataInterface
    {
        return $this->result->getMetadata();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->result->toArray();
    }

    public function jsonSerialize(): mixed
    {
        return $this->result->jsonSerialize();
    }
}
