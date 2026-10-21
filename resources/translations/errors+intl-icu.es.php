<?php

declare(strict_types=1);

/**
 * Derafu: Backbone API - HTTP API with Autodiscovery.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    'Operation result {scenario} must be an array; {type} given.' =>
        'El resultado de la operación {scenario} debe ser un arreglo; se recibió {type}.',
    'The description of operation result {scenario} must be string|null; {type} given.' =>
        'La descripción del resultado de la operación {scenario} debe ser string|null; se recibió {type}.',
    'The env.APP_URL parameter must be a string.' =>
        'El parámetro env.APP_URL debe ser un string.',
    'The API request body must contain valid JSON.' =>
        'El cuerpo de la solicitud de la API debe contener JSON válido.',
    'The API request body must be a JSON object.' =>
        'El cuerpo de la solicitud de la API debe ser un objeto JSON.',
    'API request parameters must be an object with named parameters or an empty array.' =>
        'Los parámetros de la solicitud de la API deben ser un objeto con parámetros identificados por nombre o un arreglo vacío.',
    'A non-root API route must specify a package.' =>
        'Una ruta de la API distinta de la raíz debe especificar un paquete.',
    'A failed operation result must contain problem details.' =>
        'El resultado de una operación fallida debe contener los detalles del problema.',
    'The used route {path} is not valid. As a maximum it can have the structure: /:package/:component/:worker/:operation.' =>
        'La ruta utilizada {path} no es válida. Como máximo puede tener la estructura: /:package/:component/:worker/:operation.',
];
