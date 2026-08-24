<?php

declare(strict_types=1);

/**
 * Derafu: Backbone API - HTTP API with Autodiscovery.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneApi\Service;

use Derafu\Backbone\Contract\ComponentInterface;
use Derafu\Backbone\Contract\PackageRegistryInterface;
use Derafu\BackboneDispatcher\Contract\OperationPolicyInterface;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Service\Resolution\Caster;

// use Opis\JsonSchema\Errors\ErrorFormatter as JsonErrorFormatter;
// use Opis\JsonSchema\Validator as JsonValidator;

/**
 * Class that generates the documentation compatible with OpenAPI automatically.
 */
class Documenter
{
    /**
     * Constructor with dependencies.
     *
     * `$operationPolicy` is optional, `null` by default: without one, every
     * `#[Operation]`-tagged method gets documented, matching the default
     * `AllowAllOperationPolicy` behavior everywhere else in this ecosystem.
     * When the application wires a real dispatch chain with a policy of its
     * own, injecting that same policy here keeps the generated docs
     * accurate to what a caller can actually dispatch — otherwise this
     * would document operations a policy silently rejects at dispatch time.
     *
     * @param PackageRegistryInterface $packageRegistry
     * @param Inspector $inspector
     * @param Caster $caster
     * @param HttpStatusResolver $httpStatusResolver
     * @param OperationPolicyInterface|null $operationPolicy
     */
    public function __construct(
        private PackageRegistryInterface $packageRegistry,
        private Inspector $inspector,
        private Caster $caster,
        private Explorer $explorer,
        private HttpStatusResolver $httpStatusResolver,
        private ?OperationPolicyInterface $operationPolicy = null,
    ) {
    }

    /**
     * Method that generates the documentation.
     *
     * @return array
     */
    public function document(): array
    {
        $docs = [
            'openapi' => '3.1.0',
            'info' => [
                'version' => date('y.m.d'),
                'title' => 'API Specification',
                'description' => null,
            ],
            'servers' => [
                [
                    'url' => $this->explorer->getUrl(),
                ],
            ],
            'paths' => [],
            'tags' => [],
        ];
        $description = [];

        $packageRegistryDoc = $this->inspector->getClassDoc($this->packageRegistry);
        $description[] =
            $packageRegistryDoc['summary'] . "\n\n"
            . $packageRegistryDoc['description']
        ;

        $packages = $this->packageRegistry->getPackages();
        foreach ($packages as $packageName => $package) {
            $packageDoc = $this->inspector->getClassDoc($package);
            $description[] =
                '## ' . $packageDoc['summary'] . "\n\n"
                . $packageDoc['description']
            ;

            foreach ($package->getComponents() as $componentName => $component) {
                $tag = $this->getTagDocumentation($component);
                $componentHasOperations = false;
                foreach ($component->getWorkers() as $workerName => $worker) {
                    $operations = $this->inspector->getTaggedOperations($worker);

                    if ($this->operationPolicy !== null) {
                        $operations = array_filter(
                            $operations,
                            fn ($operationInfo) => $this->operationPolicy->isAllowed(
                                $packageName,
                                $componentName,
                                $workerName,
                                $operationInfo['name']
                            )
                        );
                    }

                    if (!empty($operations)) {
                        $componentHasOperations = true;
                    }
                    foreach ($operations as $operationInfo) {
                        $operationName = $operationInfo['name'];
                        $path = "/$packageName/$componentName/$workerName/$operationName";
                        $docs['paths'][$path] = $this->getOperationDocumentation(
                            array_merge($operationInfo, [
                                'resourceTags' => [$tag['name']],
                                'operationId' => $worker->getId() . '::' . $operationInfo['name'],
                            ])
                        );
                    }
                }
                if ($componentHasOperations) {
                    $docs['tags'][] = $tag;
                }
            }
        }

        $docs['info']['description'] = implode("\n\n", $description);

        // Validate JSON schema generated with the OpenAPI 3.0 format.
        //$this->validateJson(json_encode($docs));

        // Return JSON response.
        return $docs;
    }

    /**
     * Generates the documentation of a particular operation.
     *
     * @param array $operationInfo
     * @return array
     */
    private function getOperationDocumentation(array $operationInfo): array
    {
        $parameters = $operationInfo['parameters'];

        $post = [
            'tags' => $operationInfo['resourceTags'],
            'summary' => $operationInfo['summary'],
            'description' => $operationInfo['description'] ?? '',
            'operationId' => $operationInfo['operationId'],
            'deprecated' => $operationInfo['deprecated'] ?? false,
            'requestBody' => [
                // No separate text exists for this: the body is always the
                // same shape (parameters wrapped in one envelope), nothing
                // more specific than the operation's own description above.
                'description' => null,
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'parameters' => [
                                    'type' => 'object',
                                    'description' => 'Parameters specific to the operation.',
                                    'properties' => $this->getParametersSchema($parameters),
                                    'required' => array_column(
                                        array_filter(
                                            $parameters,
                                            fn ($p) => $p['required']
                                        ),
                                        'name'
                                    ),
                                    'example' => $this->getParametersExample($parameters),
                                ],
                            ],
                            'required' => !empty(array_filter(
                                $parameters,
                                fn ($p) => $p['required']
                            )
                            )
                                ? ['parameters']
                                : []
                            ,
                        ],
                    ],
                ],
            ],
            'responses' => [],
        ];

        if (!empty($operationInfo['links'])) {
            $post['externalDocs'] = [
                'url' => $operationInfo['links'][0]['url'],
                'description' => $operationInfo['links'][0]['description'],
            ];
        }

        foreach ($operationInfo['operation']['results'] ?? [] as $scenario => $result) {
            $post['responses'][$this->httpStatusResolver->resolve($scenario)] = [
                'description' => $result['description'] ?? '',
            ];
        }

        if (empty($post['responses'])) {
            $post['responses'] = [
                200 => [
                    'description' => 'Success: The request was successful.',
                ],
                422 => [
                    'description' => 'Error: One or more parameters failed validation.',
                ],
                500 => [
                    'description' => 'Failed: The request failed to be processed (error in the server).',
                ],
            ];
        }

        return [
            'post' => $post,
        ];
    }

    /**
     * Generates the documentation of a particular tag.
     *
     * @param ComponentInterface $component
     * @return array
     */
    private function getTagDocumentation(ComponentInterface $component): array
    {
        $classDoc = $this->inspector->getClassDoc($component);

        $doc = [
            'name' => $classDoc['summary'],
            'description' => $classDoc['description'],
        ];

        if (!empty($classDoc['links'])) {
            $doc['externalDocs'] = [];
            foreach ($classDoc['links'] as $link) {
                $doc['externalDocs'][] = [
                    'url' => $link['url'],
                ];
            }
        }

        return $doc;
    }

    /**
     * Builds the whole-body example for an operation's parameters from
     * whatever per-parameter `'example'` values `#[Operation(parameters:
     * ...)]` provided — `Inspector` already merges those onto the
     * reflected parameter list, so this only needs to collect them.
     *
     * @param array $parameters
     * @return array|null
     */
    private function getParametersExample(array $parameters): ?array
    {
        $example = [];

        foreach ($parameters as $param) {
            if (array_key_exists('example', $param)) {
                $example[$param['name']] = $param['example'];
            }
        }

        return $example !== [] ? $example : null;
    }

    /**
     * Generates the list of properties of an API resource.
     *
     * @param array $parameters
     * @return array
     */
    private function getParametersSchema(array $parameters): array
    {
        $schema = [];
        foreach ($parameters as $param) {
            $schema[$param['name']] = [
                'type' => $this->caster->resolveType($param['type']),
                'description' => $param['description'] ?? '',
                'default' => $param['default'],
            ];
        }

        return $schema;
    }

    // /**
    //  * Validates the JSON generated with the OpenAPI 3.0 schema.
    //  *
    //  * Schema used: https://spec.openapis.org/oas/3.1/schema/2021-09-28
    //  *
    //  * Validate online with: https://editor.swagger.io/
    //  *
    //  * @param string $json
    //  * @return void
    //  */
    // private function validateJson(string $json): void
    // {
    //     $schema = json_decode(file_get_contents(
    //         dirname(__DIR__, 2) . '/resources/schemas/openapi_3.1_2021-09-28.json'
    //     ));
    //     $json = json_decode($json);

    //     $validator = new JsonValidator();
    //     $result = $validator->validate($json, $schema);

    //     if ($result->hasError()) {
    //         $errorFormatter = new JsonErrorFormatter();
    //         $errors = [];
    //         foreach ($errorFormatter->format($result->error()) as $section => $messages) {
    //             foreach ($messages as $message) {
    //                 $errors[] = $message . ' in ' . $section . '.';
    //             }
    //         }
    //         throw new Exception(sprintf(
    //             'Error validating the JSON schema of the documentation. %s',
    //             implode(' ', $errors)
    //         ));
    //     }
    // }
}
