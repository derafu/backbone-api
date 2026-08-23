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

use Derafu\BackboneApi\Service\Explorer;
use Derafu\BackboneDispatcher\Service\Discovery\Explorer as DispatcherExplorer;
use Derafu\BackboneDispatcher\Service\Policy\AllowListOperationPolicy;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\TestsBackboneApi\Fixture\ExampleComponent;
use Derafu\TestsBackboneApi\Fixture\ExamplePackage;
use Derafu\TestsBackboneApi\Fixture\ExamplePackageRegistry;
use Derafu\TestsBackboneApi\Fixture\ExampleWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * Real, no-mocks tests for `Explorer`: it now delegates every id, name,
 * description and policy decision to `derafu/backbone-dispatcher`'s own
 * `Explorer`, only adding HATEOAS `_links` on top. This verifies the two
 * concrete gaps that delegation closes: the `::`-based operation id
 * (previously a stale `%s.%s.%s.%s` format built independently) and
 * policy-aware pruning (previously nonexistent).
 */
#[CoversClass(Explorer::class)]
class ExplorerTest extends TestCase
{
    private ExamplePackageRegistry $registry;

    private Inspector $inspector;

    protected function setUp(): void
    {
        $worker = new ExampleWorker();
        $component = new ExampleComponent(['example_worker' => $worker]);
        $package = new ExamplePackage(['example_component' => $component]);

        $this->registry = new ExamplePackageRegistry();
        $this->registry->registerPackage('example_package', $package);

        $this->inspector = new Inspector();
    }

    private function createExplorer(?AllowListOperationPolicy $policy = null): Explorer
    {
        return new Explorer(
            new DispatcherExplorer($this->registry, $this->inspector, $policy),
            new ParameterBag(['env.APP_URL' => 'https://example.test']),
        );
    }

    public function testOperationIdUsesTheDoubleColonFormat(): void
    {
        $explorer = $this->createExplorer();

        $operation = $explorer->getOperation(
            'example_package',
            'example_component',
            'example_worker',
            'create'
        );

        $this->assertSame(
            'example_package.example_component.example_worker::create',
            $operation['id']
        );
    }

    public function testOperationLinksPointToTheOperationAndItsWorker(): void
    {
        $explorer = $this->createExplorer();

        $operation = $explorer->getOperation(
            'example_package',
            'example_component',
            'example_worker',
            'create'
        );

        $this->assertSame(
            'https://example.test/api/example_package/example_component/example_worker/create',
            $operation['_links']['self']['href']
        );
        $this->assertSame(
            'https://example.test/api/example_package/example_component/example_worker',
            $operation['_links']['parent']['href']
        );
    }

    public function testGetWorkerNestsOnlyThePoliciesAllowedOperations(): void
    {
        $explorer = $this->createExplorer(
            new AllowListOperationPolicy(['example_package.example_component.example_worker::create'])
        );

        $worker = $explorer->getWorker(
            'example_package',
            'example_component',
            'example_worker',
            withOperations: true
        );

        $names = array_column($worker['operations'], 'name');

        $this->assertSame(['create'], $names);
    }

    public function testGetPackagesDropsAPackageThePolicyLeavesEmpty(): void
    {
        $explorer = $this->createExplorer(
            new AllowListOperationPolicy(['other_package.*'])
        );

        $this->assertSame([], $explorer->getPackages());
    }
}
