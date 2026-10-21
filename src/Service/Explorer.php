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

use Derafu\BackboneDispatcher\Contract\ExplorerInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Decorates `derafu/backbone-dispatcher`'s `Explorer` with HATEOAS `_links`
 * (https://datatracker.ietf.org/doc/html/draft-kelly-json-hal-03).
 *
 * Every id, name, description and policy-based visibility rule comes from
 * the delegate — this class only adds `self`/`parent` links derived from
 * the id it gets back.
 */
class Explorer
{
    public function __construct(
        private ExplorerInterface $explorer,
        private ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * Returns the list of packages of the application.
     *
     * @return list<array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     * }>
     */
    public function getPackages(): array
    {
        return array_map(
            fn (array $package) => $this->withLinks($package),
            $this->explorer->getPackages()
        );
    }

    /**
     * Returns the list of components of a package.
     *
     * @param string $package
     * @return list<array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     * }>
     */
    public function getComponents(string $package): array
    {
        return array_map(
            fn (array $component) => $this->withLinks($component),
            $this->explorer->getComponents($package)
        );
    }

    /**
     * Returns the list of workers of a component.
     *
     * @param string $package
     * @param string $component
     * @return list<array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     * }>
     */
    public function getWorkers(string $package, string $component): array
    {
        return array_map(
            fn (array $worker) => $this->withLinks($worker),
            $this->explorer->getWorkers($package, $component)
        );
    }

    /**
     * Returns the list of operations of a worker.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @return list<array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     parameters: list<array{
     *         name: string,
     *         type: string,
     *         required: bool,
     *         default: mixed,
     *         description: string|null,
     *         example?: mixed,
     *     }>,
     *     returns: array{type: string, description: string|null},
     *     throws: array<array{type: string, description: string|null}>,
     *     tags: array<string, list<string>>,
     *     links: list<array{url: string, description: string|null}>,
     *     deprecated: bool,
     *     operation: array{
     *         name: string|null,
     *         description: string|null,
     *         parameters: array<array-key, mixed>,
     *         results: array<array-key, mixed>,
     *     }|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     * }>
     */
    public function getOperations(
        string $package,
        string $component,
        string $worker
    ): array {
        return array_map(
            fn (array $operation) => $this->withLinks($operation),
            $this->explorer->getOperations($package, $component, $worker)
        );
    }

    /**
     * Returns the data of a specific package.
     *
     * @param string $package
     * @param boolean $withComponents
     * @return ($withComponents is true ? array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     *     components: list<array{
     *         id: string,
     *         name: string,
     *         summary: string|null,
     *         description: string|null,
     *         _links: array{self: array{href: string}, parent: array{href: string}},
     *     }>,
     * } : array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     * })
     */
    public function getPackage(
        string $package,
        bool $withComponents = false
    ): array {
        $data = $this->withLinks($this->explorer->getPackage($package));

        if ($withComponents) {
            $data['components'] = $this->getComponents($package);
        }

        return $data;
    }

    /**
     * Returns the data of a specific component.
     *
     * @param string $package
     * @param string $component
     * @param boolean $withWorkers
     * @return ($withWorkers is true ? array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     *     workers: list<array{
     *         id: string,
     *         name: string,
     *         summary: string|null,
     *         description: string|null,
     *         _links: array{self: array{href: string}, parent: array{href: string}},
     *     }>,
     * } : array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     * })
     */
    public function getComponent(
        string $package,
        string $component,
        bool $withWorkers = false
    ): array {
        $data = $this->withLinks($this->explorer->getComponent($package, $component));

        if ($withWorkers) {
            $data['workers'] = $this->getWorkers($package, $component);
        }

        return $data;
    }

    /**
     * Returns the data of a specific worker.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param boolean $withOperations
     * @return ($withOperations is true ? array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     *     operations: list<array{
     *         id: string,
     *         name: string,
     *         summary: string|null,
     *         description: string|null,
     *         parameters: list<array{
     *             name: string,
     *             type: string,
     *             required: bool,
     *             default: mixed,
     *             description: string|null,
     *             example?: mixed,
     *         }>,
     *         returns: array{type: string, description: string|null},
     *         throws: array<array{type: string, description: string|null}>,
     *         tags: array<string, list<string>>,
     *         links: list<array{url: string, description: string|null}>,
     *         deprecated: bool,
     *         operation: array{
     *             name: string|null,
     *             description: string|null,
     *             parameters: array<array-key, mixed>,
     *             results: array<array-key, mixed>,
     *         }|null,
     *         _links: array{self: array{href: string}, parent: array{href: string}},
     *     }>,
     * } : array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     * })
     */
    public function getWorker(
        string $package,
        string $component,
        string $worker,
        bool $withOperations = false
    ): array {
        $data = $this->withLinks($this->explorer->getWorker($package, $component, $worker));

        if ($withOperations) {
            $data['operations'] = $this->getOperations($package, $component, $worker);
        }

        return $data;
    }

    /**
     * Returns the data of a specific operation.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param string $operation
     * @return array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     parameters: list<array{
     *         name: string,
     *         type: string,
     *         required: bool,
     *         default: mixed,
     *         description: string|null,
     *         example?: mixed,
     *     }>,
     *     returns: array{type: string, description: string|null},
     *     throws: array<array{type: string, description: string|null}>,
     *     tags: array<string, list<string>>,
     *     links: list<array{url: string, description: string|null}>,
     *     deprecated: bool,
     *     operation: array{
     *         name: string|null,
     *         description: string|null,
     *         parameters: array<array-key, mixed>,
     *         results: array<array-key, mixed>,
     *     }|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     * }
     */
    public function getOperation(
        string $package,
        string $component,
        string $worker,
        string $operation
    ): array {
        return $this->withLinks(
            $this->explorer->getOperation($package, $component, $worker, $operation)
        );
    }

    /**
     * Returns the fully nested tree of every package, component, worker and
     * operation currently visible.
     *
     * No `_links` added here, unlike every other method above: nothing
     * renders this shape as an HTTP resource today (`Documenter` is the
     * only caller, and it only needs the plain nested data to know what a
     * real dispatch would actually allow) — `_links` on every nested node
     * would mean recursing `withLinks()` through arbitrary depth for a
     * consumer that would just ignore it.
     *
     * @param string|null $id
     * @return ($id is null|'' ? array{
     *     summary: string|null,
     *     description: string|null,
     *     packages: list<array{
     *         id: string,
     *         name: string,
     *         summary: string|null,
     *         description: string|null,
     *         components: list<array{
     *             id: string,
     *             name: string,
     *             summary: string|null,
     *             description: string|null,
     *             workers: list<array{
     *                 id: string,
     *                 name: string,
     *                 summary: string|null,
     *                 description: string|null,
     *                 operations: list<array{
     *                     id: string,
     *                     name: string,
     *                     summary: string|null,
     *                     description: string|null,
     *                     parameters: list<array{
     *                         name: string,
     *                         type: string,
     *                         required: bool,
     *                         default: mixed,
     *                         description: string|null,
     *                         example?: mixed,
     *                     }>,
     *                     returns: array{type: string, description: string|null},
     *                     throws: array<array{type: string, description: string|null}>,
     *                     tags: array<string, list<string>>,
     *                     links: list<array{url: string, description: string|null}>,
     *                     deprecated: bool,
     *                     operation: array{
     *                         name: string|null,
     *                         description: string|null,
     *                         parameters: array<array-key, mixed>,
     *                         results: array<array-key, mixed>,
     *                     }|null,
     *                 }>,
     *             }>,
     *         }>,
     *     }>,
     * } : (array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     components: list<array{
     *         id: string,
     *         name: string,
     *         summary: string|null,
     *         description: string|null,
     *         workers: list<array{
     *             id: string,
     *             name: string,
     *             summary: string|null,
     *             description: string|null,
     *             operations: list<array{
     *                 id: string,
     *                 name: string,
     *                 summary: string|null,
     *                 description: string|null,
     *                 parameters: list<array{
     *                     name: string,
     *                     type: string,
     *                     required: bool,
     *                     default: mixed,
     *                     description: string|null,
     *                     example?: mixed,
     *                 }>,
     *                 returns: array{type: string, description: string|null},
     *                 throws: array<array{type: string, description: string|null}>,
     *                 tags: array<string, list<string>>,
     *                 links: list<array{url: string, description: string|null}>,
     *                 deprecated: bool,
     *                 operation: array{
     *                     name: string|null,
     *                     description: string|null,
     *                     parameters: array<array-key, mixed>,
     *                     results: array<array-key, mixed>,
     *                 }|null,
     *             }>,
     *         }>,
     *     }>,
     * }|array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     workers: list<array{
     *         id: string,
     *         name: string,
     *         summary: string|null,
     *         description: string|null,
     *         operations: list<array{
     *             id: string,
     *             name: string,
     *             summary: string|null,
     *             description: string|null,
     *             parameters: list<array{
     *                 name: string,
     *                 type: string,
     *                 required: bool,
     *                 default: mixed,
     *                 description: string|null,
     *                 example?: mixed,
     *             }>,
     *             returns: array{type: string, description: string|null},
     *             throws: array<array{type: string, description: string|null}>,
     *             tags: array<string, list<string>>,
     *             links: list<array{url: string, description: string|null}>,
     *             deprecated: bool,
     *             operation: array{
     *                 name: string|null,
     *                 description: string|null,
     *                 parameters: array<array-key, mixed>,
     *                 results: array<array-key, mixed>,
     *             }|null,
     *         }>,
     *     }>,
     * }|array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     operations: list<array{
     *         id: string,
     *         name: string,
     *         summary: string|null,
     *         description: string|null,
     *         parameters: list<array{
     *             name: string,
     *             type: string,
     *             required: bool,
     *             default: mixed,
     *             description: string|null,
     *             example?: mixed,
     *         }>,
     *         returns: array{type: string, description: string|null},
     *         throws: array<array{type: string, description: string|null}>,
     *         tags: array<string, list<string>>,
     *         links: list<array{url: string, description: string|null}>,
     *         deprecated: bool,
     *         operation: array{
     *             name: string|null,
     *             description: string|null,
     *             parameters: array<array-key, mixed>,
     *             results: array<array-key, mixed>,
     *         }|null,
     *     }>,
     * }|array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     parameters: list<array{
     *         name: string,
     *         type: string,
     *         required: bool,
     *         default: mixed,
     *         description: string|null,
     *         example?: mixed,
     *     }>,
     *     returns: array{type: string, description: string|null},
     *     throws: array<array{type: string, description: string|null}>,
     *     tags: array<string, list<string>>,
     *     links: list<array{url: string, description: string|null}>,
     *     deprecated: bool,
     *     operation: array{
     *         name: string|null,
     *         description: string|null,
     *         parameters: array<array-key, mixed>,
     *         results: array<array-key, mixed>,
     *     }|null,
     * }))
     */
    public function tree(?string $id = null): array
    {
        return $this->explorer->tree($id);
    }

    /**
     * Returns the URL of the API.
     *
     * @return string
     */
    public function getUrl(): string
    {
        $url = $this->parameterBag->get('env.APP_URL');
        if (!is_string($url)) {
            throw new InvalidArgumentException('The env.APP_URL parameter must be a string.');
        }

        return $url . '/api';
    }

    /**
     * Adds `self`/`parent` HATEOAS links derived from a resource's id.
     *
     * The hierarchy separator (`.`) and the operation separator (`::`)
     * both become URL path segments, since package/component/worker/
     * operation names never contain either.
     *
     * @param array{id: string, name: string, summary: string|null, description: string|null}|array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     parameters: list<array{
     *         name: string,
     *         type: string,
     *         required: bool,
     *         default: mixed,
     *         description: string|null,
     *         example?: mixed,
     *     }>,
     *     returns: array{type: string, description: string|null},
     *     throws: array<array{type: string, description: string|null}>,
     *     tags: array<string, list<string>>,
     *     links: list<array{url: string, description: string|null}>,
     *     deprecated: bool,
     *     operation: array{
     *         name: string|null,
     *         description: string|null,
     *         parameters: array<array-key, mixed>,
     *         results: array<array-key, mixed>,
     *     }|null,
     * } $data
     * @return ($data is array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     parameters: list<array{
     *         name: string,
     *         type: string,
     *         required: bool,
     *         default: mixed,
     *         description: string|null,
     *         example?: mixed,
     *     }>,
     *     returns: array{type: string, description: string|null},
     *     throws: array<array{type: string, description: string|null}>,
     *     tags: array<string, list<string>>,
     *     links: list<array{url: string, description: string|null}>,
     *     deprecated: bool,
     *     operation: array{
     *         name: string|null,
     *         description: string|null,
     *         parameters: array<array-key, mixed>,
     *         results: array<array-key, mixed>,
     *     }|null,
     * } ? array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     parameters: list<array{
     *         name: string,
     *         type: string,
     *         required: bool,
     *         default: mixed,
     *         description: string|null,
     *         example?: mixed,
     *     }>,
     *     returns: array{type: string, description: string|null},
     *     throws: array<array{type: string, description: string|null}>,
     *     tags: array<string, list<string>>,
     *     links: list<array{url: string, description: string|null}>,
     *     deprecated: bool,
     *     operation: array{
     *         name: string|null,
     *         description: string|null,
     *         parameters: array<array-key, mixed>,
     *         results: array<array-key, mixed>,
     *     }|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     * } : array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     _links: array{self: array{href: string}, parent: array{href: string}},
     * })
     */
    private function withLinks(array $data): array
    {
        $segments = $data['id'] === ''
            ? []
            : explode('.', str_replace('::', '.', (string) $data['id']));

        $data['_links'] = [
            'self' => ['href' => $this->buildUrl($segments)],
            'parent' => ['href' => $this->buildUrl(array_slice($segments, 0, -1))],
        ];

        return $data;
    }

    /**
     * @param string[] $segments
     * @return string
     */
    private function buildUrl(array $segments): string
    {
        return $segments === []
            ? $this->getUrl()
            : $this->getUrl() . '/' . implode('/', $segments);
    }
}
