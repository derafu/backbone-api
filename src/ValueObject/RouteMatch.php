<?php

declare(strict_types=1);

/**
 * Derafu: Backbone API - HTTP API with Autodiscovery.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneApi\ValueObject;

use Derafu\BackboneApi\Contract\RouteMatchInterface;

class RouteMatch implements RouteMatchInterface
{
    private string $id;

    public function __construct(
        private readonly ?string $package = null,
        private readonly ?string $component = null,
        private readonly ?string $worker = null,
        private readonly ?string $operation = null
    ) {
    }

    public function getId(): ?string
    {
        if (!isset($this->id)) {
            if ($this->package === null) {
                return null;
            }
            $this->id = $this->package;
            if ($this->component !== null) {
                $this->id .= '.' . $this->component;
            }
            if ($this->worker !== null) {
                $this->id .= '.' . $this->worker;
            }
            if ($this->operation !== null) {
                //$this->id .= '.' . $this->operation;
            }
        }

        return $this->id;
    }

    public function getPackage(): ?string
    {
        return $this->package;
    }

    public function getComponent(): ?string
    {
        return $this->component;
    }

    public function getWorker(): ?string
    {
        return $this->worker;
    }

    public function getOperation(): ?string
    {
        return $this->operation;
    }
}
