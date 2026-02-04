<?php

namespace App\Controllers;

use App\Services\TutoriaDetalleService;
use App\Helpers\Response;

class TutoriaDetalleController
{
    private $service;

    public function __construct()
    {
        $this->service = new TutoriaDetalleService();
    }

    public function getEstados()
    {
        $data = $this->service->getEstados();
        Response::json($data);
    }

    public function getByTutoriaId($currentUser)
    {
        $tutoriaId = $_GET['tutoriaid'] ?? null;
        if (!$tutoriaId) {
            Response::error('Faltan parámetros requeridos (tutoriaid)', 400);
        }

        $data = $this->service->getByTutoriaId($tutoriaId);
        Response::json($data);
    }

    public function create($currentUser)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Campos requeridos
        $required = ['tutoriaid', 'fechatutoria', 'motivotutoria', 'observaciones'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                Response::error("El campo '$field' es obligatorio", 400);
            }
        }

        $result = $this->service->create($data);
        if (isset($result['error'])) {
            Response::error('Error al crear: ' . json_encode($result), 500);
        }
        Response::json($result, 201);
    }

    public function update($currentUser, $id)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // El usuario puede enviar estadotutoriaid si lo desea, por lo que no lo eliminamos.

        $result = $this->service->update($id, $data);

        if (isset($result['error'])) {
            // Manejar error de negocio (bloqueo por estado)
            if (strpos($result['error'], 'No se puede editar') !== false) {
                Response::error($result['error'], 409); // Conflict or Forbidden
            }
            Response::error('Error al actualizar: ' . json_encode($result), 500);
        }

        Response::json($result);
    }

    public function delete($currentUser, $id)
    {
        $result = $this->service->delete($id);
        if (isset($result['error'])) {
            Response::error('Error al eliminar: ' . json_encode($result), 500);
        }
        Response::json(['message' => 'Eliminado correctamente']);
    }
}
