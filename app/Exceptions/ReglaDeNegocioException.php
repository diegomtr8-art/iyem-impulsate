<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una regla de negocio impidio la operacion (ventana cerrada, cupo lleno,
 * horario ocupado...). No sabe nada de HTTP: bootstrap/app.php la traduce a
 * back()->withErrors() en la web y a un 422 JSON en la API.
 */
class ReglaDeNegocioException extends RuntimeException
{
    public function __construct(string $mensaje, public readonly string $campo = 'error')
    {
        parent::__construct($mensaje);
    }
}
