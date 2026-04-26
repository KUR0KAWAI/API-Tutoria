<?php

namespace App\Controllers;

use App\Services\CronogramaService;
use App\Helpers\Response;

class CronogramaController
{
    private CronogramaService $service;

    public function __construct()
    {
        $this->service = new CronogramaService();
    }

    /**
     * GET /api/cronograma/periodos
     */
    public function getPeriodos($currentUser)
    {
        $data = $this->service->getPeriodosSimples();
        Response::json($data);
    }

    // --- TIPO DOCUMENTO ---

    public function getTiposDocumento($currentUser)
    {
        $data = $this->service->getTiposDocumento();
        Response::json($data);
    }

    public function createTipoDocumento($currentUser)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input)
            Response::error("Payload inválido");

        $result = $this->service->createTipoDocumento($input);
        Response::json($result, 201);
    }

    public function updateTipoDocumento($id)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input)
            Response::error("Payload inválido");

        $result = $this->service->updateTipoDocumento($id, $input);
        Response::json($result);
    }

    public function deleteTipoDocumento($id)
    {
        $result = $this->service->deleteTipoDocumento($id);
        Response::json($result);
    }

    // --- CRONOGRAMA ---

    public function getCronogramas($currentUser)
    {
        $data = $this->service->getCronogramas();
        Response::json($data);
    }

    public function createCronograma($currentUser)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input)
            Response::error("Payload inválido");

        $result = $this->service->createCronograma($input);
        Response::json($result, 201);
    }

    public function updateCronograma($id)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input)
            Response::error("Payload inválido");

        $result = $this->service->updateCronograma($id, $input);
        Response::json($result);
    }

    public function deleteCronograma($id)
    {
        $result = $this->service->deleteCronograma($id);
        Response::json($result);
    }
}
