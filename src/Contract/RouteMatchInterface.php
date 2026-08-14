<?php

declare(strict_types=1);

/**
 * Derafu: Backbone API - HTTP API with Autodiscovery.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneApi\Contract;

interface RouteMatchInterface
{
    public function getId(): ?string;

    public function getPackage(): ?string;

    public function getComponent(): ?string;

    public function getWorker(): ?string;

    public function getOperation(): ?string;
}
