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
     * Everything this class documents — which packages/components/workers/
     * operations exist, their `name`/`summary`/`description`, and each
     * operation's own reflected data — comes from `$explorer->tree()`
     * alone. This used
     * to decide visibility on its own (`Inspector::getTaggedOperations()`,
     * i.e. "has `#[Operation]`", optionally narrowed by a *separate*
     * `OperationPolicyInterface` instance passed here) — two independent
     * opinions that could silently drift from what `DirectDispatcher`
     * would actually do, documenting either less or more than the API
     * truly allows. Sourcing everything from `$explorer` instead makes
     * that impossible: whatever it says is visible is, by construction,
     * exactly what a real dispatch would accept — and there is nowhere
     * left for a second, independently-wired policy to disagree with it.
     *
     * @param Caster $caster
     * @param Explorer $explorer
     * @param HttpStatusResolver $httpStatusResolver
     */
    public function __construct(
        private Caster $caster,
        private Explorer $explorer,
        private HttpStatusResolver $httpStatusResolver,
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

        // `tree()` already dropped every package/component/worker/operation
        // the real dispatch chain's policy would reject, so nothing below
        // needs to check a policy again — including this envelope's own
        // top-level `description`, which comes from `tree()`'s own
        // `summary`/`description` (the package registry's own PHPDoc)
        // rather than a second, independent lookup.
        $tree = $this->explorer->tree();

        $description = [$tree['summary'] . "\n\n" . $tree['description']];

        foreach ($tree['packages'] as $packageTree) {
            $packageName = $packageTree['id'];
            $description[] = '## ' . $packageTree['summary'] . "\n\n" . $packageTree['description'];

            foreach ($packageTree['components'] as $componentTree) {
                // `$componentTree['id']` is `"{$packageName}.{$componentName}"`
                // — the real registry key, needed for the path below.
                $componentName = explode('.', $componentTree['id'])[1];
                $tag = $this->getTagDocumentation($componentTree);

                if ($componentTree['workers'] !== []) {
                    $docs['tags'][] = $tag;
                }

                foreach ($componentTree['workers'] as $workerTree) {
                    $workerName = explode('.', $workerTree['id'])[2];

                    foreach ($workerTree['operations'] as $operationInfo) {
                        $operationName = $operationInfo['name'];
                        $path = "/$packageName/$componentName/$workerName/$operationName";
                        $docs['paths'][$path] = $this->getOperationDocumentation(
                            array_merge($operationInfo, [
                                'resourceTags' => [$tag['name']],
                                'operationId' => $workerTree['id'] . '::' . $operationName,
                            ])
                        );
                    }
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
     * Generates the documentation of a particular tag, from its component's
     * own `tree()` entry — `summary` (as the tag's OpenAPI `name`, prose
     * meant to be read, not `id`/`name`'s routing slug) and `description`,
     * nothing more: unlike an operation, a component's `tree()` entry
     * carries no PHPDoc `@link` tags to turn into `externalDocs` (those
     * exist only per-method, via `Inspector::getPublicMethods()`, not
     * per-class).
     *
     * @param array $componentTree
     * @return array
     */
    private function getTagDocumentation(array $componentTree): array
    {
        return [
            'name' => $componentTree['summary'],
            'description' => $componentTree['description'],
        ];
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
