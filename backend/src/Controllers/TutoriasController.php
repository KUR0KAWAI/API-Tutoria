<?php

namespace App\Controllers;

use App\Services\TutoriasService;
use App\Helpers\Response;

class TutoriasController
{
    private TutoriasService $service;

    public function __construct()
    {
        $this->service = new TutoriasService();
    }

    public function getCandidatos($currentUser)
    {
        $spId = $_GET['semestrePeriodoId'] ?? null;
        if (!$spId) {
            Response::error("Falta el parámetro 'semestrePeriodoId'");
        }
        $data = $this->service->getCandidatos($spId);
        Response::json($data);
    }

    public function getHistorial($currentUser)
    {
        $data = $this->service->getHistorial();
        Response::json($data);
    }

    public function assignTutoria($currentUser)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Response::error("Payload inválido");
        }

        // Validación de campos básicos (según esquema v2)
        if (empty($input['alumnoid']) || empty($input['profesorid']) || empty($input['asignaturaid']) || empty($input['seccionid'])) {
            Response::error("Faltan campos obligatorios (alumnoid, profesorid, asignaturaid, seccionid)");
        }

        $result = $this->service->assignTutoria($input);
        Response::json($result, 201);
    }

    public function updateTutoria($id)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Response::error("Payload inválido");
        }

        $result = $this->service->updateTutoria($id, $input);
        Response::json($result);
    }

    public function deleteTutoria($id)
    {
        $this->service->deleteTutoria($id);
        Response::json(['message' => 'Tutoría eliminada correctamente']);
    }
}
