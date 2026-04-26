<?php

namespace App\Controllers;

use App\Services\NotasParcialesService;
use App\Validators\NotasParcialesValidator;
use App\Helpers\Response;

class NotasParcialesController
{
    private NotasParcialesService $service;

    public function __construct()
    {
        $this->service = new NotasParcialesService();
    }

    public function getPeriodos($currentUser)
    {
        $data = $this->service->getPeriodos();
        Response::json($data);
    }

    public function getNiveles($currentUser)
    {
        $periodoId = $_GET['periodoId'] ?? null;
        if (!$periodoId) {
            Response::error("Falta el parámetro 'periodoId'");
        }
        $data = $this->service->getNiveles($periodoId);
        Response::json($data);
    }

    public function getAsignaturas($currentUser)
    {
        $spId = $_GET['semestrePeriodoId'] ?? null;
        $seccId = $_GET['seccionId'] ?? $_GET['seccionid'] ?? null; // Opcional para validación

        if (!$spId) {
            Response::error("Falta el parámetro 'semestrePeriodoId'");
        }

        $data = $this->service->getAsignaturas($spId, $seccId);
        Response::json($data);
    }

    public function getDocentes($currentUser)
    {
        $spId = $_GET['semestrePeriodoId'] ?? null;
        $seccId = $_GET['seccionId'] ?? $_GET['seccionid'] ?? null;
        $asigId = $_GET['asignaturaId'] ?? null;

        if (!$spId || !$seccId || !$asigId) {
            Response::error("Faltan parámetros 'semestrePeriodoId', 'seccionid' o 'asignaturaId'");
        }

        $data = $this->service->getDocentes($spId, $seccId, $asigId);
        Response::json($data);
    }

    public function getSecciones($currentUser)
    {
        $data = $this->service->getSecciones();
        Response::json($data);
    }

    public function getAlumnos($currentUser)
    {
        $data = $this->service->getAlumnos();
        Response::json($data);
    }

    public function getNotas($currentUser)
    {
        // Devolvemos todo sin filtros
        $data = $this->service->getNotas();
        Response::json($data);
    }

    public function createNota($currentUser)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Response::error("Payload inválido");
        }

        $cleanData = NotasParcialesValidator::validateNota($input);

        $result = $this->service->createNota($cleanData);
        Response::json($result, 201);
    }

    public function updateNota($id)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Response::error("Payload inválido");
        }

        $cleanData = NotasParcialesValidator::validateNota($input);
        $result = $this->service->updateNota($id, $cleanData);
        Response::json($result);
    }

    public function deleteNota($id)
    {
        $this->service->deleteNota($id);
        Response::json(['message' => 'Nota eliminada correctamente']);
    }
}
