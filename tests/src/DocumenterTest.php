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

    /**
     * `ExampleWorker::create()` is deliberately documented with a `@return`
     * description, two `@throws`, and two `@link` tags — everything
     * `Inspector` now reports as `returns`/`throws`/`links`.
     */
    public function testDocumentsTheReturnsDescriptionOnTheSuccessResponse(): void
    {
        $docs = $this->documenterWithPolicy()->document();
        $post = $docs['paths']['/example_package/example_component/example_worker/create']['post'];

        $this->assertSame(
            'The created resource, with its assigned `name`.',
            $post['responses'][200]['description'],
        );
    }

    public function testDocumentsBothThrowsMergedOnTheStatusTheyResolveTo(): void
    {
        $docs = $this->documenterWithPolicy()->document();
        $post = $docs['paths']['/example_package/example_component/example_worker/create']['post'];

        // Neither InvalidArgumentException nor RuntimeException is one of
        // the dispatcher's own generic exceptions, so HttpStatusResolver
        // maps both to 500 — their descriptions are joined, not one
        // silently overwriting the other.
        $this->assertArrayHasKey(500, $post['responses']);
        $this->assertStringContainsString(
            'If `$name` is empty.',
            $post['responses'][500]['description'],
        );
        $this->assertStringContainsString(
            'If the resource could not be persisted.',
            $post['responses'][500]['description'],
        );

        // 422 still gets the generic baseline text — nothing declared on
        // this operation resolves to it.
        $this->assertSame(
            'Error: One or more parameters failed validation.',
            $post['responses'][422]['description'],
        );
    }

    /**
     * `cancel()` has no docblock at all (no `@return`/`@throws`), so every
     * response should still fall back to the generic baseline text — the
     * behavior for an operation that documents nothing must stay exactly
     * as it was before `returns`/`throws` were taken into account.
     */
    public function testFallsBackToTheGenericResponsesWhenNothingIsDocumented(): void
    {
        $docs = $this->documenterWithPolicy()->document();
        $post = $docs['paths']['/example_package/example_component/example_worker/cancel']['post'];

        $this->assertSame(
            [
                200 => ['description' => 'Success: The request was successful.'],
                422 => ['description' => 'Error: One or more parameters failed validation.'],
                500 => ['description' => 'Failed: The request failed to be processed (error in the server).'],
            ],
            $post['responses'],
        );
    }

    /**
     * `getStatus()` is documented with exactly one `@link`: it should use
     * `externalDocs`, same as before this fix — nothing appended to
     * `description`.
     */
    public function testUsesExternalDocsWhenThereIsExactlyOneLink(): void
    {
        $docs = $this->documenterWithPolicy()->document();
        $post = $docs['paths']['/example_package/example_component/example_worker/getStatus']['post'];

        $this->assertSame(
            [
                'url' => 'https://example.test/docs/get-status',
                'description' => 'Reference for this operation.',
            ],
            $post['externalDocs'],
        );
        $this->assertStringNotContainsString('Links:', $post['description']);
    }

    /**
     * `create()` is documented with two `@link` tags: OpenAPI's
     * `externalDocs` only ever holds one reference (true of every version
     * of the spec, 2.0 through 3.2.0), so with more than one link,
     * `externalDocs` is skipped entirely and every link is appended to
     * `description` instead — never both places at once, so the first
     * link is never documented twice.
     */
    public function testAppendsAllLinksToTheDescriptionWhenThereIsMoreThanOne(): void
    {
        $docs = $this->documenterWithPolicy()->document();
        $post = $docs['paths']['/example_package/example_component/example_worker/create']['post'];

        $this->assertArrayNotHasKey('externalDocs', $post);
        $this->assertStringEndsWith(
            "Links:\n" .
            "- Primary reference for this operation.: https://example.test/docs/create\n" .
            '- Schema reference for the created resource.: https://example.test/docs/create-schema',
            $post['description'],
        );
    }
}
