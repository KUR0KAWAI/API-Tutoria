<?php

namespace App\Middleware;

use App\Services\AuthService;
use App\Helpers\Response;

class AuthMiddleware
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Authenticates the incoming request.
     * Returns the authenticated user data if successful, otherwise terminates execution.
     */
    public function authenticate()
    {
        $headers = null;
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        }

        $authHeader = null;
        if ($headers) {
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }

        if (!$authHeader) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        }

        if (!$authHeader) {
            Response::error('Token no proporcionado', 401);
        }

        $user = $this->authService->validateToken($authHeader);

        if (!$user) {
            Response::error('Token inválido o expirado', 401);
        }

        return $user;
    }
}
