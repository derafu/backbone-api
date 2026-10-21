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

use Derafu\Backbone\Attribute\Operation;
use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use stdClass;

/**
 * Result configurations inspected through the real operation attributes.
 */
class OperationResultsWorker extends ExampleWorker
{
    #[Operation(results: ['success' => null])]
    public function nullResult(): void
    {
    }

    #[Operation(results: ['success' => 'invalid'])]
    public function stringResult(): void
    {
    }

    #[Operation(results: ['success' => 1])]
    public function integerResult(): void
    {
    }

    #[Operation(results: ['success' => 1.5])]
    public function floatResult(): void
    {
    }

    #[Operation(results: ['success' => true])]
    public function booleanResult(): void
    {
    }

    #[Operation(results: ['success' => new stdClass()])]
    public function objectResult(): void
    {
    }

    #[Operation(results: ['success' => ['description' => false]])]
    public function booleanDescription(): void
    {
    }

    #[Operation(results: ['success' => ['description' => 0]])]
    public function integerDescription(): void
    {
    }

    #[Operation(results: ['success' => ['description' => 1.5]])]
    public function floatDescription(): void
    {
    }

    #[Operation(results: ['success' => ['description' => []]])]
    public function arrayDescription(): void
    {
    }

    #[Operation(results: ['success' => ['description' => new stdClass()]])]
    public function objectDescription(): void
    {
    }

    #[Operation(results: ['success' => []])]
    public function withoutDescription(): void
    {
    }

    #[Operation(results: ['success' => ['description' => null]])]
    public function nullDescription(): void
    {
    }

    #[Operation(results: ['success' => ['description' => '']])]
    public function emptyDescription(): void
    {
    }

    #[Operation(results: ['success' => ['description' => '0']])]
    public function zeroDescription(): void
    {
    }

    #[Operation(results: ['success' => ['description' => 'Documented response.']])]
    public function textDescription(): void
    {
    }

    #[Operation(results: ['success' => ['description' => null, 'example' => null]])]
    public function nullExample(): void
    {
    }

    #[Operation(results: ['success' => ['example' => false]])]
    public function falseExample(): void
    {
    }

    #[Operation(results: ['success' => ['example' => 0]])]
    public function zeroExample(): void
    {
    }

    #[Operation(results: ['success' => ['example' => ['items' => [1, false, null]]]])]
    public function arrayExample(): void
    {
    }

    #[Operation(results: ['success' => ['example' => new stdClass()]])]
    public function objectExample(): void
    {
    }

    #[Operation(results: [OperationNotAllowedException::class => ['example' => null]])]
    public function unlabelledForbidden(): void
    {
    }

    #[Operation(
        parameters: ['name' => ['example' => 'demo']],
        results: ['success' => ['description' => 'The echoed name.', 'example' => 'demo']]
    )]
    public function echoName(string $name): string
    {
        return $name;
    }
}
