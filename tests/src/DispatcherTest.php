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

use Derafu\BackboneApi\Contract\RouteMatchInterface;
use Derafu\BackboneApi\Contract\RouterInterface;
use Derafu\BackboneApi\Exception\InvalidRequestBodyException;
use Derafu\BackboneApi\Service\Dispatcher;
use Derafu\BackboneApi\Service\Documenter;
use Derafu\BackboneApi\Service\Explorer;
use Derafu\BackboneApi\Service\HttpStatusResolver;
use Derafu\BackboneApi\Service\Router;
use Derafu\BackboneApi\Translation\BackboneApiTranslationResourceProvider;
use Derafu\BackboneApi\ValueObject\RouteMatch;
use Derafu\BackboneDispatcher\Contract\OperationResultInterface;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Discovery\Explorer as DispatcherExplorer;
use Derafu\BackboneDispatcher\Service\Dispatch\DirectDispatcher;
use Derafu\BackboneDispatcher\Service\Dispatch\SafeDispatcher;
use Derafu\BackboneDispatcher\Service\Dispatch\TypedDispatcher;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Service\Resolution\Caster;
use Derafu\BackboneDispatcher\Service\Resolution\Resolver;
use Derafu\BackboneDispatcher\Service\Resolution\Validator;
use Derafu\BackboneDispatcher\Service\Serialization\Serializer;
use Derafu\TestsBackboneApi\Fixture\ExampleComponent;
use Derafu\TestsBackboneApi\Fixture\ExamplePackage;
use Derafu\TestsBackboneApi\Fixture\ExamplePackageRegistry;
use Derafu\TestsBackboneApi\Fixture\ExampleWorker;
use Derafu\TestsBackboneApi\Fixture\RequestBodyWorker;
use Derafu\Translation\Exception\Core\TranslatableLogicException as LogicException;
use Derafu\Translation\TranslatorFactory;
use JsonException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(Dispatcher::class)]
#[UsesClass(InvalidRequestBodyException::class)]
#[UsesClass(Router::class)]
#[UsesClass(RouteMatch::class)]
#[UsesClass(Explorer::class)]
#[UsesClass(Documenter::class)]
#[UsesClass(HttpStatusResolver::class)]
#[UsesClass(BackboneApiTranslationResourceProvider::class)]
final class DispatcherTest extends TestCase
{
    private function dispatcher(?RouterInterface $router = null, ?ExampleWorker $worker = null): Dispatcher
    {
        $registry = new ExamplePackageRegistry();
        $registry->registerPackage('example_package', new ExamplePackage([
            'example_component' => new ExampleComponent(['example_worker' => $worker ?? new ExampleWorker()]),
        ]));
        $inspector = new Inspector();
        $caster = new Caster(new ObjectFactoryRegistry());
        $explorer = new Explorer(
            new DispatcherExplorer($registry, $inspector),
            'https://example.test/api',
        );
        $documenter = new Documenter($caster, $explorer, new HttpStatusResolver());
        $direct = new DirectDispatcher($registry, $inspector, new Resolver($inspector, $caster, new Validator()));

        return new Dispatcher(
            $router ?? new Router(),
            $explorer,
            $documenter,
            new SafeDispatcher(new TypedDispatcher($direct), new Serializer(), environment: 'test'),
        );
    }

    /** @return array<string, array{string}> */
    public static function rootPaths(): array
    {
        return [
            'empty route' => ['/api/'],
            'index route' => ['/api/index'],
        ];
    }

    #[DataProvider('rootPaths')]
    public function testRootRoutesStillListPackages(string $path): void
    {
        $result = $this->dispatcher()->dispatch(new ServerRequest('GET', $path));

        $this->assertIsArray($result);
        $this->assertIsArray($result['_links']);
        $this->assertIsArray($result['_links']['self']);
        $this->assertSame('https://example.test/api', $result['_links']['self']['href']);
        $this->assertIsArray($result['packages']);
        $this->assertSame(['example_package'], array_column($result['packages'], 'id'));
    }

    public function testTheDocumentationRouteStillProducesTheOpenApiDocument(): void
    {
        $result = $this->dispatcher()->dispatch(new ServerRequest('GET', '/api/openapi-docs.json'));

        $this->assertIsArray($result);
        $this->assertSame('3.1.0', $result['openapi']);
        $this->assertIsArray($result['paths']);
        $this->assertArrayHasKey('/example_package/example_component/example_worker/create', $result['paths']);
    }

    public function testAPackageRouteStillIncludesItsComponents(): void
    {
        $result = $this->dispatcher()->dispatch(new ServerRequest('GET', '/api/example_package'));

        $this->assertIsArray($result);
        $this->assertSame('example_package', $result['id']);
        $this->assertIsArray($result['components']);
        $this->assertSame(['example_package.example_component'], array_column($result['components'], 'id'));
    }

    public function testAComponentRouteStillIncludesItsWorkers(): void
    {
        $result = $this->dispatcher()->dispatch(new ServerRequest('GET', '/api/example_package/example_component'));

        $this->assertIsArray($result);
        $this->assertSame('example_package.example_component', $result['id']);
        $this->assertIsArray($result['workers']);
        $this->assertSame(['example_package.example_component.example_worker'], array_column($result['workers'], 'id'));
    }

    public function testAWorkerRouteStillIncludesItsOperations(): void
    {
        $result = $this->dispatcher()->dispatch(new ServerRequest('GET', '/api/example_package/example_component/example_worker'));

        $this->assertIsArray($result);
        $this->assertSame('example_package.example_component.example_worker', $result['id']);
        $this->assertIsArray($result['operations']);
        $this->assertContains('create', array_column($result['operations'], 'name'));
    }

    public function testAnOperationRouteStillInvokesItsWorkerWithTheRequestParameters(): void
    {
        $request = new ServerRequest(
            'POST',
            '/api/example_package/example_component/example_worker/create',
            ['Content-Type' => 'application/json'],
            '{"parameters":{"name":"Widget"}}',
        );

        $result = $this->dispatcher()->dispatch($request);

        $this->assertInstanceOf(OperationResultInterface::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertSame(['name' => 'Widget'], $result->getValue());
        $this->assertNull($result->getProblem());
    }

    public function testANonRootRouteWithoutAPackageRaisesATranslatedLogicException(): void
    {
        $router = new class () implements RouterInterface {
            public function parse(ServerRequestInterface $request): RouteMatchInterface
            {
                return new class () extends RouteMatch {
                    public function getId(): string
                    {
                        return 'example_package';
                    }
                };
            }
        };

        try {
            $this->dispatcher($router)->dispatch(new ServerRequest('GET', '/api/example_package'));
            $this->fail('A non-root route without a package must be rejected.');
        } catch (LogicException $exception) {
            $this->assertSame('A non-root API route must specify a package.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneApiTranslationResourceProvider()]);
            $this->assertSame('Una ruta de la API distinta de la raíz debe especificar un paquete.', $exception->trans($translator));
        }
    }

    /** @return array<string, array{string, mixed}> */
    public static function acceptedRequestBodies(): array
    {
        return [
            'empty body' => ['', null],
            'empty envelope' => ['{}', null],
            'unrelated envelope fields' => ['{"context":"test"}', null],
            'object with a numeric envelope key' => ['{"0":"context"}', null],
            'null parameters' => ['{"parameters":null}', null],
            'empty object parameters' => ['{"parameters":{}}', null],
            'empty array parameters' => ['{"parameters":[]}', null],
            'string parameter' => ['{"parameters":{"payload":"Widget"}}', 'Widget'],
            'integer parameter' => ['{"parameters":{"payload":42}}', 42],
            'fractional parameter' => ['{"parameters":{"payload":2.5}}', 2.5],
            'boolean parameter' => ['{"parameters":{"payload":false}}', false],
            'null parameter value' => ['{"parameters":{"payload":null}}', null],
            'empty object parameter value' => ['{"parameters":{"payload":{}}}', []],
            'array parameter value' => ['{"parameters":{"payload":[1,"2",false,null,1.5]}}', [1, '2', false, null, 1.5]],
            'nested object parameter value' => [
                '{"parameters":{"payload":{"items":[{"id":1},{"id":2}],"options":{"enabled":true},"note":null}}}',
                ['items' => [['id' => 1], ['id' => 2]], 'options' => ['enabled' => true], 'note' => null],
            ],
            'numeric-looking key kept as a string by PHP' => ['{"parameters":{"01":"context"}}', null],
            'surrounding JSON whitespace' => [" \r\n\t {\"parameters\":{\"payload\":\"Widget\"}} \n", 'Widget'],
        ];
    }

    #[DataProvider('acceptedRequestBodies')]
    public function testAcceptedBodiesPreserveTheirValuesAndInvokeTheWorker(string $body, mixed $expected): void
    {
        $worker = new RequestBodyWorker();
        $request = new ServerRequest(
            'POST',
            '/api/example_package/example_component/example_worker/receive',
            ['Content-Type' => 'application/json'],
            $body,
        );

        $result = $this->dispatcher(worker: $worker)->dispatch($request);

        $this->assertInstanceOf(OperationResultInterface::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertSame($expected, $result->getValue());
        $this->assertSame(1, $worker->getCalls());
    }

    /** @return array<string, array{string, string, string, int|null}> */
    public static function rejectedRequestBodies(): array
    {
        $jsonMessage = 'The API request body must contain valid JSON.';
        $jsonTranslation = 'El cuerpo de la solicitud de la API debe contener JSON válido.';
        $rootMessage = 'The API request body must be a JSON object.';
        $rootTranslation = 'El cuerpo de la solicitud de la API debe ser un objeto JSON.';
        $parametersMessage = 'API request parameters must be an object with named parameters or an empty array.';
        $parametersTranslation = 'Los parámetros de la solicitud de la API deben ser un objeto con parámetros identificados por nombre o un arreglo vacío.';

        return [
            'truncated JSON' => ['{"parameters":', $jsonMessage, $jsonTranslation, JSON_ERROR_SYNTAX],
            'trailing content' => ['{} trailing', $jsonMessage, $jsonTranslation, JSON_ERROR_SYNTAX],
            'whitespace without JSON' => ['   ', $jsonMessage, $jsonTranslation, JSON_ERROR_SYNTAX],
            'invalid UTF-8' => ['{"parameters":{"payload":"' . chr(177) . '"}}', $jsonMessage, $jsonTranslation, JSON_ERROR_UTF8],
            'excessive nesting' => ['{"parameters":{"payload":' . str_repeat('[', 513) . str_repeat(']', 513) . '}}', $jsonMessage, $jsonTranslation, JSON_ERROR_DEPTH],
            'null root' => ['null', $rootMessage, $rootTranslation, null],
            'boolean root' => ['false', $rootMessage, $rootTranslation, null],
            'number root' => ['123', $rootMessage, $rootTranslation, null],
            'string root' => ['"body"', $rootMessage, $rootTranslation, null],
            'empty array root' => ['[]', $rootMessage, $rootTranslation, null],
            'nonempty array root' => ['[{}]', $rootMessage, $rootTranslation, null],
            'boolean parameters' => ['{"parameters":false}', $parametersMessage, $parametersTranslation, null],
            'number parameters' => ['{"parameters":123}', $parametersMessage, $parametersTranslation, null],
            'string parameters' => ['{"parameters":"name"}', $parametersMessage, $parametersTranslation, null],
            'positional parameters' => ['{"parameters":["Widget"]}', $parametersMessage, $parametersTranslation, null],
            'integer object key' => ['{"parameters":{"0":"Widget"}}', $parametersMessage, $parametersTranslation, null],
            'mixed named and integer keys' => ['{"parameters":{"payload":"Widget","1":"extra"}}', $parametersMessage, $parametersTranslation, null],
        ];
    }

    #[DataProvider('rejectedRequestBodies')]
    public function testInvalidBodiesAreTranslatedAndNeverInvokeTheWorker(
        string $body,
        string $message,
        string $translation,
        ?int $jsonError,
    ): void {
        $worker = new RequestBodyWorker();
        $request = new ServerRequest(
            'POST',
            '/api/example_package/example_component/example_worker/receive',
            ['Content-Type' => 'application/json'],
            $body,
        );

        try {
            $this->dispatcher(worker: $worker)->dispatch($request);
            $this->fail('An invalid request body must be rejected before dispatching the operation.');
        } catch (InvalidRequestBodyException $exception) {
            $this->assertSame($message, $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneApiTranslationResourceProvider()]);
            $this->assertSame($translation, $exception->trans($translator));
            if ($jsonError !== null) {
                $this->assertInstanceOf(JsonException::class, $exception->getPrevious());
                $this->assertSame($jsonError, $exception->getPrevious()->getCode());
            } else {
                $this->assertNull($exception->getPrevious());
            }
        }
        $this->assertSame(0, $worker->getCalls());
    }
}
