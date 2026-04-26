<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Helpers\Response;
use Exception;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function login()
    {
        // Leer JSON body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['usuario']) || !isset($input['password'])) {
            Response::error('Usuario y contraseña son requeridos', 400);
        }

        try {
            $user = $this->authService->login($input['usuario'], $input['password']);
            Response::json([
                'message' => 'Login exitoso',
                'user' => $user
                // Aquí podrías generar y devolver un JWT si fuera necesario.
            ]);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 401);
        }
    }

    /**
     * Valida la sesión actual y devuelve los datos del usuario.
     * Este método asume que el usuario ya fue autenticado por el middleware.
     */
    public function validateSession($currentUser)
    {
        Response::json([
            'message' => 'Sesión válida',
            'user' => $currentUser
        ]);
    }
}
