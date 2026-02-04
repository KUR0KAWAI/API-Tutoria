<?php

namespace App\Validators;

use App\Helpers\Response;

class NotasParcialesValidator
{
    public static function validateNota(array $data)
    {
        // Campos requeridos por el esquema de la tabla notaparcial
        $required = ['alumnoid', 'asignaturaid', 'semestreperiodoid', 'seccionid', 'profesorid', 'notap1', 'fecha'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                Response::error("El campo '$field' es obligatorio.");
            }
        }

        // Validar rangos (0-20)
        if (!is_numeric($data['notap1']) || $data['notap1'] < 0 || $data['notap1'] > 20) {
            Response::error("La Nota Parcial 1 debe estar entre 0 y 20.");
        }

        if (isset($data['notap2']) && $data['notap2'] !== null && $data['notap2'] !== '') {
            if (!is_numeric($data['notap2']) || $data['notap2'] < 0 || $data['notap2'] > 20) {
                Response::error("La Nota Parcial 2 debe estar entre 0 y 20.");
            }
        }

        // Retornar datos mapeados exactamente a las columnas de la base de datos
        return [
            'alumnoid' => (int) $data['alumnoid'],
            'asignaturaid' => (int) $data['asignaturaid'],
            'semestreperiodoid' => (int) $data['semestreperiodoid'],
            'seccionid' => (int) $data['seccionid'],
            'profesorid' => (int) $data['profesorid'],
            'notap1' => (float) $data['notap1'],
            'notap2' => isset($data['notap2']) && $data['notap2'] !== '' ? (float) $data['notap2'] : null,
            'fecha' => $data['fecha'] // Formato ISO o YYYY-MM-DD
        ];
    }
}
