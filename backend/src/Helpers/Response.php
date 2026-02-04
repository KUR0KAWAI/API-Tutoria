<?php

namespace App\Helpers;

class Response
{
    /**
     * Devuelve una respuesta JSON estandarizada.
     *
     * @param mixed $data Datos a devolver.
     * @param int $status Código HTTP (default 200).
     * @return void
     */
    public static function json($data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Devuelve una respuesta de error JSON estandarizada.
     *
     * @param string $message Mensaje de error.
     * @param int $status Código HTTP (default 400).
     * @return void
     */
    public static function error(string $message, int $status = 400)
    {
        self::json(['error' => $message], $status);
    }
}
