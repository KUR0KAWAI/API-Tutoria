<?php

namespace App\Controllers;

use App\Services\DocumentoService;
use App\Helpers\Response;
use Exception;

class DocumentoController
{
    private DocumentoService $service;

    public function __construct()
    {
        $this->service = new DocumentoService();
    }

    public function upload($currentUser)
    {
        // DEBUG: Log the request
        $logFile = __DIR__ . '/../../debug_upload.log';
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'post' => $_POST,
            'files' => $_FILES,
            'user' => $currentUser,
            'headers' => function_exists('getallheaders') ? getallheaders() : []
        ];
        file_put_contents($logFile, "--- REQUEST ---\n" . json_encode($logData, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        // Verificar si hay archivo
        if (empty($_FILES['archivo'])) {
            $msg = 'No se ha enviado ningún archivo. Asegúrate de usar el key "archivo" en el form-data.';
            file_put_contents($logFile, "--- ERROR ---\n$msg\n", FILE_APPEND);
            Response::error($msg);
        }

        try {
            $data = $_POST;
            $result = $this->service->uploadDocument($_FILES['archivo'], $data, $currentUser);

            file_put_contents($logFile, "--- SUCCESS ---\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

            Response::json([
                'message' => 'Documento subido correctamente',
                'data' => $result
            ], 201);

        } catch (Exception $e) {
            $errorMsg = "Exception: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString();
            file_put_contents($logFile, "--- EXCEPTION ---\n$errorMsg\n", FILE_APPEND);
            Response::error($e->getMessage(), 500);
        }
    }
    public function getDocuments($currentUser)
    {
        try {
            $profesorId = $currentUser['profesorid'];
            $semestrePeriodoId = $_GET['semestreperiodoid'] ?? null;

            $result = $this->service->getDocumentsByProfesor($profesorId, $semestrePeriodoId);
            Response::json($result);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function getReporteDocumentos($currentUser)
    {
        try {
            // 1. Validar Rol (Coordinador)
            // Se valida por ID del rol (2 = COORDINADOR) para evitar problemas de casing
            $rolesIds = $currentUser['roles_ids'] ?? [];
            if (!in_array(2, $rolesIds)) {
                Response::error('No tiene permisos para acceder a este recurso', 403);
            }

            // 2. Validar Parámetros
            $semestrePeriodoId = $_GET['semestreperiodoid'] ?? null;
            if (!$semestrePeriodoId) {
                Response::error('El parámetro semestreperiodoid es requerido', 400);
            }

            // 3. Obtener Reporte
            $result = $this->service->getReporteDocumentos($semestrePeriodoId);
            Response::json($result);

        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
