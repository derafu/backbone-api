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

use Composer\InstalledVersions;
use Derafu\BackboneApi\Contract\DispatcherInterface;
use Derafu\BackboneApi\Contract\RouterInterface;
use Derafu\BackboneApi\Exception\InvalidRouteException;
use Derafu\BackboneApi\Translation\BackboneApiTranslationResourceProvider;
use Derafu\BackboneDispatcher\Exception\MissingParameterException;
use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use Derafu\TestsBackboneApi\Fixture\ExampleController;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Exercises the production API and dispatcher composition with real HTTP requests.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function load(string $configuration = 'services.yaml'): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator([
            dirname(__DIR__, 2) . '/tests/fixtures/config',
            (InstalledVersions::getInstallPath('derafu/backbone-dispatcher')
                ?? throw new RuntimeException('derafu/backbone-dispatcher must be installed for these tests.')) . '/resources/config',
        ])))
            ->load($configuration);
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }

        return $container;
    }

    public function testTheConfiguredProviderCanBeConstructed(): void
    {
        $container = $this->load();
        $container->getDefinition(BackboneApiTranslationResourceProvider::class)->setPublic(true);
        $container->compile();
        $provider = $container->get(BackboneApiTranslationResourceProvider::class);
        $this->assertInstanceOf(BackboneApiTranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        $this->assertCount(1, $directories);
        $this->assertSame(realpath(dirname(__DIR__, 2) . '/resources/translations'), realpath($directories[0]));
    }

    public function testTaggedResourcesTranslateAnExceptionFromTheRealRouteParser(): void
    {
        $container = $this->load();
        $this->assertArrayHasKey(
            BackboneApiTranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        $container->compile();
        $translator = $container->get('app.translator');
        $this->assertInstanceOf(Translator::class, $translator);

        try {
            $router = $container->get(RouterInterface::class);
            $this->assertInstanceOf(RouterInterface::class, $router);
            $router->parse(new ServerRequest('GET', '/api/billing/document/builder/build/extra'));
            $this->fail('An API route with more than four segments was accepted.');
        } catch (InvalidRouteException $exception) {
            $this->assertSame(
                'The used route billing/document/builder/build/extra is not valid. As a maximum it can have the structure: /:package/:component/:worker/:operation.',
                $exception->getMessage()
            );
            $this->assertSame(
                'La ruta utilizada billing/document/builder/build/extra no es válida. Como máximo puede tener la estructura: /:package/:component/:worker/:operation.',
                $exception->trans($translator)
            );
        }
    }

    public function testConfiguredControllerExecutesAnOperationThroughTheSharedDispatcher(): void
    {
        $container = $this->load();
        $container->compile();
        $controller = $container->get(ExampleController::class);
        $this->assertInstanceOf(ExampleController::class, $controller);
        $result = $controller->dispatch(new ServerRequest(
            'POST',
            '/api/example_package/example_component/example_worker/create',
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            '{"parameters":{"name":"Widget"}}'
        ));
        $this->assertIsArray($result);
        $this->assertSame(['name' => 'Widget'], $result['data']);
        $this->assertIsArray($result['meta']);
        $this->assertSame('array', $result['meta']['data_type']);
    }

    public function testDiscoveryAndOpenApiUseTheCompleteConfiguredUrl(): void
    {
        $container = $this->load('custom-url.yaml');
        $container->compile();
        $dispatcher = $container->get(DispatcherInterface::class);
        $this->assertInstanceOf(DispatcherInterface::class, $dispatcher);
        $root = $dispatcher->dispatch(new ServerRequest('GET', '/api/'));
        $this->assertIsArray($root);
        $this->assertIsArray($root['_links']);
        $this->assertSame(['href' => 'https://public.example.test/api'], $root['_links']['self']);
        $this->assertIsArray($root['packages']);
        $package = $root['packages'][0];
        $this->assertIsArray($package);
        $this->assertIsArray($package['_links']);
        $this->assertSame(['href' => 'https://public.example.test/api/example_package'], $package['_links']['self']);
        $document = $dispatcher->dispatch(new ServerRequest('GET', '/api/openapi-docs.json'));
        $this->assertIsArray($document);
        $this->assertSame([['url' => 'https://public.example.test/api']], $document['servers']);
        $this->assertIsArray($document['paths']);
        $this->assertArrayHasKey('/example_package/example_component/example_worker/create', $document['paths']);
        $this->assertArrayNotHasKey('/example_package/example_component/example_worker/getStatus', $document['paths']);
    }

    public function testControllerReturnsHttpErrorsForRejectedOperationsAndParameters(): void
    {
        $container = $this->load();
        $container->compile();
        $controller = $container->get(ExampleController::class);
        $this->assertInstanceOf(ExampleController::class, $controller);
        foreach (['getStatus' => 403, 'create' => 422] as $operation => $expectedStatus) {
            $response = $controller->dispatch(new ServerRequest(
                'POST',
                '/api/example_package/example_component/example_worker/' . $operation,
                ['Content-Type' => 'application/json'],
                '{}'
            ));
            $this->assertInstanceOf(ResponseInterface::class, $response);
            $this->assertSame($expectedStatus, $response->getStatusCode());
            $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
            $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertIsArray($body);
            $expectedTitle = $operation === 'getStatus' ? OperationNotAllowedException::class : MissingParameterException::class;
            $this->assertSame($expectedTitle, $body['title']);
        }
    }

    public function testApplicationPolicyOverrideAffectsDocumentationAndExecutionTogether(): void
    {
        $container = $this->load('allow-list.yaml');
        $container->compile();
        $dispatcher = $container->get(DispatcherInterface::class);
        $this->assertInstanceOf(DispatcherInterface::class, $dispatcher);
        $document = $dispatcher->dispatch(new ServerRequest('GET', '/api/openapi-docs.json'));
        $this->assertIsArray($document);
        $this->assertIsArray($document['paths']);
        $this->assertArrayHasKey('/example_package/example_component/example_worker/getStatus', $document['paths']);
        $this->assertArrayNotHasKey('/example_package/example_component/example_worker/create', $document['paths']);
        $controller = $container->get(ExampleController::class);
        $this->assertInstanceOf(ExampleController::class, $controller);
        $result = $controller->dispatch(new ServerRequest(
            'POST',
            '/api/example_package/example_component/example_worker/getStatus',
            ['Content-Type' => 'application/json'],
            '{"parameters":{"name":"Widget"}}'
        ));
        $this->assertIsArray($result);
        $this->assertSame(['name' => 'Widget', 'status' => 'ok'], $result['data']);
        $response = $controller->dispatch(new ServerRequest(
            'POST',
            '/api/example_package/example_component/example_worker/create',
            ['Content-Type' => 'application/json'],
            '{"parameters":{"name":"Widget"}}'
        ));
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(403, $response->getStatusCode());
    }
}
