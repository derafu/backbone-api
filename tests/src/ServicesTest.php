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

use Derafu\BackboneApi\Exception\InvalidRouteException;
use Derafu\BackboneApi\Service\Router;
use Derafu\BackboneApi\Translation\BackboneApiTranslationResourceProvider;
use Derafu\Translation\TranslatorFactory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Import the provider-only YAML and consume its tagged translation resources.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function load(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/resources/config')))
            ->load('backbone-api-services.yaml');

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
        $container->register('app.translator', Translator::class)
            ->setFactory([TranslatorFactory::class, 'create'])
            ->setArguments(['es', ['en'], new TaggedIteratorArgument('derafu_translation.resource_provider')])
            ->setPublic(true);
        $container->compile();
        $translator = $container->get('app.translator');
        $this->assertInstanceOf(Translator::class, $translator);

        // The consumer creates its router; the YAML only supplies catalogues.
        try {
            (new Router())->parse(new ServerRequest('GET', '/api/billing/document/builder/build/extra'));
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
}
