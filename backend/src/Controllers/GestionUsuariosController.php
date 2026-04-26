<?php

namespace App\Controllers;

use App\Services\GestionUsuariosService;
use App\Helpers\Response;

class GestionUsuariosController
{
    private GestionUsuariosService $service;

    public function __construct()
    {
        $this->service = new GestionUsuariosService();
    }

    /**
     * GET /api/gestion-usuarios/roles
     * Obtiene todos los roles (id y nombre)
     */
    public function getRoles($currentUser)
    {
        $data = $this->service->getRoles();
        Response::json($data);
    }

    /**
     * GET /api/gestion-usuarios/docentes
     * Obtiene todos los docentes (id y nombre completo)
     */
    public function getDocentes($currentUser)
    {
        $data = $this->service->getDocentesParaGestion();
        Response::json($data);
    }

    /**
     * GET /api/gestion-usuarios/usuarios
     * Obtiene todos los usuarios
     */
    public function getUsuarios($currentUser)
    {
        $data = $this->service->getUsuarios();
        Response::json($data);
    }

    /**
     * POST /api/gestion-usuarios/usuarios
     * Crea un nuevo usuario
     */
    public function createUsuario($currentUser)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Response::error("Payload inválido");
        }

        $result = $this->service->createUsuario($input);
        Response::json($result, 201);
    }

    /**
     * PUT /api/gestion-usuarios/usuarios/{id}
     * Actualiza un usuario (nombre de usuario)
     */
    public function updateUsuario($id)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Response::error("Payload inválido");
        }

        $result = $this->service->updateUsuario($id, $input);
        Response::json($result);
    }

    /**
     * DELETE /api/gestion-usuarios/usuarios/{id}
     * Elimina un usuario
     */
    public function deleteUsuario($id)
    {
        $result = $this->service->deleteUsuario($id);
        Response::json($result);
    }
}
