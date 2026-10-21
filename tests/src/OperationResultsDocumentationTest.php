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
use Derafu\BackboneApi\Translation\BackboneApiTranslationResourceProvider;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Discovery\Explorer as DispatcherExplorer;
use Derafu\BackboneDispatcher\Service\Policy\AllowListOperationPolicy;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Service\Resolution\Caster;
use Derafu\TestsBackboneApi\Fixture\ExampleComponent;
use Derafu\TestsBackboneApi\Fixture\ExamplePackage;
use Derafu\TestsBackboneApi\Fixture\ExamplePackageRegistry;
use Derafu\TestsBackboneApi\Fixture\OperationResultsWorker;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

#[CoversClass(Documenter::class)]
#[UsesClass(Explorer::class)]
#[UsesClass(HttpStatusResolver::class)]
#[UsesClass(BackboneApiTranslationResourceProvider::class)]
class OperationResultsDocumentationTest extends TestCase
{
    private function documenterFor(string $operation): Documenter
    {
        $registry = new ExamplePackageRegistry();
        $registry->registerPackage('example_package', new ExamplePackage([
            'example_component' => new ExampleComponent(['example_worker' => new OperationResultsWorker()]),
        ]));
        $explorer = new Explorer(
            new DispatcherExplorer($registry, new Inspector(), new AllowListOperationPolicy([
                'example_package.example_component.example_worker::' . $operation,
            ])),
            new ParameterBag(['env.APP_URL' => 'https://example.test'])
        );

        return new Documenter(new Caster(new ObjectFactoryRegistry()), $explorer, new HttpStatusResolver());
    }

    #[TestWith(['nullResult', 'null', false])]
    #[TestWith(['stringResult', 'string', false])]
    #[TestWith(['integerResult', 'int', false])]
    #[TestWith(['floatResult', 'float', false])]
    #[TestWith(['booleanResult', 'bool', false])]
    #[TestWith(['objectResult', 'stdClass', false])]
    #[TestWith(['booleanDescription', 'bool', true])]
    #[TestWith(['integerDescription', 'int', true])]
    #[TestWith(['floatDescription', 'float', true])]
    #[TestWith(['arrayDescription', 'array', true])]
    #[TestWith(['objectDescription', 'stdClass', true])]
    public function testInvalidResultsHaveAnExplicitTranslatedConfigurationException(string $method, string $type, bool $description): void
    {
        try {
            $this->documenterFor($method)->document();
            $this->fail('Invalid operation result configuration must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $english = $description
                ? 'The description of operation result success must be string|null; ' . $type . ' given.'
                : 'Operation result success must be an array; ' . $type . ' given.';
            $spanish = $description
                ? 'La descripción del resultado de la operación success debe ser string|null; se recibió ' . $type . '.'
                : 'El resultado de la operación success debe ser un arreglo; se recibió ' . $type . '.';
            $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneApiTranslationResourceProvider()]);
            $this->assertSame($english, $exception->getMessage());
            $this->assertSame($spanish, $exception->trans($translator));
        }
    }

    /** @return array<string, array{string, int, string, bool, mixed}> */
    public static function validResults(): array
    {
        $default = 'Success: The request was successful.';

        return [
            'no description' => ['withoutDescription', 200, $default, false, null],
            'null description' => ['nullDescription', 200, $default, false, null],
            'empty description' => ['emptyDescription', 200, $default, false, null],
            'zero string keeps its existing empty treatment' => ['zeroDescription', 200, $default, false, null],
            'text description' => ['textDescription', 200, 'Documented response.', false, null],
            'explicit null example' => ['nullExample', 200, $default, true, null],
            'false example' => ['falseExample', 200, $default, true, false],
            'zero example' => ['zeroExample', 200, $default, true, 0],
            'structured example' => ['arrayExample', 200, $default, true, ['items' => [1, false, null]]],
            'example without a baseline description' => ['unlabelledForbidden', 403, '', true, null],
        ];
    }

    #[DataProvider('validResults')]
    public function testValidOptionalFieldsAndExamplesPreserveTheirExistingRepresentation(
        string $method,
        int $status,
        string $description,
        bool $hasExample,
        mixed $example
    ): void {
        $docs = $this->documenterFor($method)->document();
        $response = $docs['paths']['/example_package/example_component/example_worker/' . $method]['post']['responses'][$status];

        $this->assertSame($description, $response['description']);
        if ($hasExample) {
            $this->assertSame(['example' => $example], $response['content']['application/json'] ?? null);
        } else {
            $this->assertArrayNotHasKey('content', $response);
        }
    }

    public function testAnObjectExampleIsPreservedAndEncodedAsAJsonObject(): void
    {
        $docs = $this->documenterFor('objectExample')->document();
        $response = $docs['paths']['/example_package/example_component/example_worker/objectExample']['post']['responses'][200];

        $this->assertInstanceOf(stdClass::class, $response['content']['application/json']['example'] ?? null);
        $this->assertStringContainsString('"example":{}', json_encode($docs, JSON_THROW_ON_ERROR));
    }

    public function testRequestAndResponseExamplesRemainIndependent(): void
    {
        $docs = $this->documenterFor('echoName')->document();
        $post = $docs['paths']['/example_package/example_component/example_worker/echoName']['post'];

        $this->assertSame(['name' => 'demo'], $post['requestBody']['content']['application/json']['schema']['properties']['parameters']['example']);
        $this->assertSame('demo', $post['responses'][200]['content']['application/json']['example'] ?? null);
        $this->assertSame('The echoed name.', $post['responses'][200]['description']);
    }
}
