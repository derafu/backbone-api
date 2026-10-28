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
use Derafu\BackboneApi\Translation\BackboneApiTranslationResourceProvider;
use Derafu\BackboneDispatcher\Service\Discovery\Explorer as DispatcherExplorer;
use Derafu\BackboneDispatcher\Service\Policy\AllowListOperationPolicy;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\TestsBackboneApi\Fixture\ExampleComponent;
use Derafu\TestsBackboneApi\Fixture\ExamplePackage;
use Derafu\TestsBackboneApi\Fixture\ExamplePackageRegistry;
use Derafu\TestsBackboneApi\Fixture\ExampleWorker;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Real, no-mocks tests for `Explorer`: it now delegates every id, name,
 * description and policy decision to `derafu/backbone-dispatcher`'s own
 * `Explorer`, only adding HATEOAS `_links` on top. This verifies the two
 * concrete gaps that delegation closes: the `::`-based operation id
 * (previously a stale `%s.%s.%s.%s` format built independently) and
 * policy-aware pruning (previously nonexistent).
 */
#[CoversClass(Explorer::class)]
#[UsesClass(BackboneApiTranslationResourceProvider::class)]
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
            'https://example.test/api',
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

    /** @return array<string, array{mixed}> */
    public static function invalidApiUrls(): array
    {
        return [
            'null' => [null],
            'integer' => [123],
            'float' => [12.5],
            'boolean' => [false],
            'array' => [['url' => 'https://example.test']],
        ];
    }

    #[DataProvider('invalidApiUrls')]
    public function testApiUrlRejectsNonStringConfigurationWithATranslatedException(mixed $url): void
    {
        try {
            new Explorer(
                new DispatcherExplorer($this->registry, $this->inspector),
                $url
            );
            $this->fail('A non-string API URL must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('The API URL must be a string.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneApiTranslationResourceProvider()]);
            $this->assertSame('La URL de la API debe ser un string.', $exception->trans($translator));
        }
    }

    #[TestWith(['https://example.test/api', 'https://example.test/api'])]
    #[TestWith(['https://example.test//api', 'https://example.test//api'])]
    #[TestWith(['/api', '/api'])]
    public function testCompleteApiUrlsAreReturnedWithoutNormalization(string $url, string $expected): void
    {
        $explorer = new Explorer(
            new DispatcherExplorer($this->registry, $this->inspector),
            $url
        );

        $this->assertSame($expected, $explorer->getUrl());
    }

    public function testConfiguredBaseUrlIsUsedThroughoutExpandedResourceLinks(): void
    {
        $explorer = $this->createExplorer();
        $packages = $explorer->getPackages();
        $package = $explorer->getPackage('example_package', withComponents: true);
        $component = $explorer->getComponent('example_package', 'example_component', withWorkers: true);

        $this->assertSame('https://example.test/api', $packages[0]['_links']['parent']['href']);
        $this->assertSame('https://example.test/api/example_package', $package['_links']['self']['href']);
        $this->assertSame('https://example.test/api/example_package/example_component', $package['components'][0]['_links']['self']['href']);
        $this->assertSame('https://example.test/api/example_package/example_component/example_worker', $component['workers'][0]['_links']['self']['href']);
        $this->assertArrayNotHasKey('components', $explorer->getPackage('example_package'));
        $this->assertArrayNotHasKey('workers', $explorer->getComponent('example_package', 'example_component'));
    }

    public function testAnEmptyPackageNameUsesTheBaseUrlForBothLinks(): void
    {
        $this->registry->registerPackage('', new ExamplePackage([]));

        $package = $this->createExplorer()->getPackage('');

        $this->assertSame('', $package['id']);
        $this->assertSame('Example Package', $package['name']);
        $this->assertSame([
            'self' => ['href' => 'https://example.test/api'],
            'parent' => ['href' => 'https://example.test/api'],
        ], $package['_links']);
    }

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['example_package'])]
    #[TestWith(['example_package.example_component'])]
    #[TestWith(['example_package.example_component.example_worker'])]
    #[TestWith(['example_package.example_component.example_worker::create'])]
    public function testDocumentationTreeDelegatesWithoutAddingHttpLinks(?string $id): void
    {
        $explorer = $this->createExplorer();
        $delegate = new DispatcherExplorer($this->registry, $this->inspector);

        $this->assertSame($delegate->tree($id), $explorer->tree($id));
        $this->assertArrayNotHasKey('_links', $explorer->tree($id));
    }
}
