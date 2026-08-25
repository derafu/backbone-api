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
use Derafu\BackboneDispatcher\Contract\OperationPolicyInterface;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Discovery\Explorer as DispatcherExplorer;
use Derafu\BackboneDispatcher\Service\Policy\AllowListOperationPolicy;
use Derafu\BackboneDispatcher\Service\Policy\TaggedOperationPolicy;
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
 * wired into `Explorer`'s own delegate — never into `Documenter` itself
 * (it no longer accepts one) — to verify the actual gap this class had: it
 * used to decide visibility on its own, via `#[Operation]` tagging, which
 * could (and did) diverge from what a real dispatch chain actually allows.
 */
#[CoversClass(Documenter::class)]
#[UsesClass(Explorer::class)]
#[UsesClass(HttpStatusResolver::class)]
class DocumenterTest extends TestCase
{
    private ExamplePackageRegistry $registry;

    private Inspector $inspector;

    private Caster $caster;

    protected function setUp(): void
    {
        $worker = new ExampleWorker();
        $component = new ExampleComponent(['example_worker' => $worker]);
        $package = new ExamplePackage(['example_component' => $component]);

        $this->registry = new ExamplePackageRegistry();
        $this->registry->registerPackage('example_package', $package);

        $this->inspector = new Inspector();
        $this->caster = new Caster(new ObjectFactoryRegistry());
    }

    /**
     * Builds a `Documenter` whose `Explorer` delegate was constructed with
     * `$policy` — the only place a policy can be wired in, now.
     */
    private function documenterWithPolicy(?OperationPolicyInterface $policy = null): Documenter
    {
        $explorer = new Explorer(
            new DispatcherExplorer($this->registry, $this->inspector, $policy),
            new ParameterBag(['env.APP_URL' => 'https://example.test']),
        );

        return new Documenter(
            $this->caster,
            $explorer,
            new HttpStatusResolver(),
        );
    }

    public function testDocumentsEveryTaggedOperationWithoutAPolicy(): void
    {
        $docs = $this->documenterWithPolicy()->document();

        $this->assertArrayHasKey('/example_package/example_component/example_worker/create', $docs['paths']);
        $this->assertArrayHasKey('/example_package/example_component/example_worker/cancel', $docs['paths']);
    }

    public function testInfoDescriptionIncludesTheRegistryAndPackageDocs(): void
    {
        // The one thing `Documenter` cannot get any other way than reading
        // the package registry's own PHPDoc: there is no
        // `PackageRegistryInterface` counterpart to `id`/`name` (it is a
        // registry, not a `ServiceInterface` with an identity), so
        // `Explorer::tree()['description']` is its only possible source.
        $docs = $this->documenterWithPolicy()->document();

        $this->assertStringContainsString(
            'A real, minimal package registry.',
            $docs['info']['description'],
        );
        $this->assertStringContainsString(
            'A package holding a fixed set of real components.',
            $docs['info']['description'],
        );
    }

    public function testDocumentsAnUntaggedOperationTooWhenNoPolicyRejectsIt(): void
    {
        // The exact gap this fix closes: `getStatus()` (on `ExampleWorker`)
        // was never marked `#[Operation]`, but a real `DirectDispatcher`
        // with no restrictive policy dispatches it just the same — so it
        // must show up here too, not just the two tagged methods.
        $docs = $this->documenterWithPolicy()->document();

        $this->assertArrayHasKey('/example_package/example_component/example_worker/getStatus', $docs['paths']);
    }

    public function testOnlyDocumentsOperationsThePolicyAllows(): void
    {
        $docs = $this->documenterWithPolicy(
            new AllowListOperationPolicy(['example_package.example_component.example_worker::create']),
        )->document();

        $this->assertArrayHasKey('/example_package/example_component/example_worker/create', $docs['paths']);
        $this->assertArrayNotHasKey('/example_package/example_component/example_worker/cancel', $docs['paths']);
        $this->assertArrayNotHasKey('/example_package/example_component/example_worker/getStatus', $docs['paths']);
    }

    public function testATaggedOperationPolicyHidesTheUntaggedOperation(): void
    {
        // The production-recommended policy: only what a real dispatch
        // would allow under it is documented — `getStatus()` disappears,
        // the two tagged methods stay.
        $docs = $this->documenterWithPolicy(
            new TaggedOperationPolicy($this->registry, $this->inspector),
        )->document();

        $this->assertArrayHasKey('/example_package/example_component/example_worker/create', $docs['paths']);
        $this->assertArrayHasKey('/example_package/example_component/example_worker/cancel', $docs['paths']);
        $this->assertArrayNotHasKey('/example_package/example_component/example_worker/getStatus', $docs['paths']);
    }

    public function testDropsTheComponentTagWhenThePolicyRejectsEveryOperation(): void
    {
        $docs = $this->documenterWithPolicy(
            new AllowListOperationPolicy(['other_package.*']),
        )->document();

        $this->assertSame([], $docs['paths']);
        $this->assertSame([], $docs['tags']);
    }
}
