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

use Derafu\BackboneApi\Service\Documenter;
use Derafu\BackboneApi\Service\Explorer;
use Derafu\BackboneApi\Service\HttpStatusResolver;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Discovery\Explorer as DispatcherExplorer;
use Derafu\BackboneDispatcher\Service\Policy\AllowListOperationPolicy;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Service\Resolution\Caster;
use Derafu\TestsBackboneApi\Fixture\ExampleComponent;
use Derafu\TestsBackboneApi\Fixture\ExamplePackage;
use Derafu\TestsBackboneApi\Fixture\ExamplePackageRegistry;
use Derafu\TestsBackboneApi\Fixture\ExampleWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * Real, no-mocks tests for `Documenter`: a real package/component/worker
 * chain, a real `Inspector`/`Caster`, and a real `OperationPolicyInterface`
 * to verify the actual gap this class had — it never checked any policy at
 * all, so it documented operations a real dispatch chain would reject.
 */
#[CoversClass(Documenter::class)]
#[UsesClass(Explorer::class)]
#[UsesClass(HttpStatusResolver::class)]
class DocumenterTest extends TestCase
{
    private ExamplePackageRegistry $registry;

    private Inspector $inspector;

    private Caster $caster;

    private Explorer $explorer;

    protected function setUp(): void
    {
        $worker = new ExampleWorker();
        $component = new ExampleComponent(['example_worker' => $worker]);
        $package = new ExamplePackage(['example_component' => $component]);

        $this->registry = new ExamplePackageRegistry();
        $this->registry->registerPackage('example_package', $package);

        $this->inspector = new Inspector();
        $this->caster = new Caster(new ObjectFactoryRegistry());
        $this->explorer = new Explorer(
            new DispatcherExplorer($this->registry, $this->inspector),
            new ParameterBag(['env.APP_URL' => 'https://example.test']),
        );
    }

    public function testDocumentsEveryTaggedOperationWithoutAPolicy(): void
    {
        $documenter = new Documenter(
            $this->registry,
            $this->inspector,
            $this->caster,
            $this->explorer,
            new HttpStatusResolver(),
        );

        $docs = $documenter->document();

        $this->assertArrayHasKey('/example_package/example_component/example_worker/create', $docs['paths']);
        $this->assertArrayHasKey('/example_package/example_component/example_worker/cancel', $docs['paths']);
    }

    public function testOnlyDocumentsOperationsThePolicyAllows(): void
    {
        $documenter = new Documenter(
            $this->registry,
            $this->inspector,
            $this->caster,
            $this->explorer,
            new HttpStatusResolver(),
            new AllowListOperationPolicy(['example_package.example_component.example_worker::create']),
        );

        $docs = $documenter->document();

        $this->assertArrayHasKey('/example_package/example_component/example_worker/create', $docs['paths']);
        $this->assertArrayNotHasKey('/example_package/example_component/example_worker/cancel', $docs['paths']);
    }

    public function testDropsTheComponentTagWhenThePolicyRejectsEveryOperation(): void
    {
        $documenter = new Documenter(
            $this->registry,
            $this->inspector,
            $this->caster,
            $this->explorer,
            new HttpStatusResolver(),
            new AllowListOperationPolicy(['other_package.*']),
        );

        $docs = $documenter->document();

        $this->assertSame([], $docs['paths']);
        $this->assertSame([], $docs['tags']);
    }
}
