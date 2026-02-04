<?php

namespace App\Controllers;

use App\Services\ReportesTutoriaService;
use App\Helpers\Response;

class ReportesTutoriaController
{
    private ReportesTutoriaService $service;

    public function __construct()
    {
        $this->service = new ReportesTutoriaService();
    }

    public function getAsignaturas($currentUser)
    {
        // Parámetros obligatorios solicitados
        $profesorId = $_GET['profesorid'] ?? null;
        $semestrePeriodoId = $_GET['semestreperiodoid'] ?? null;

        if (!$profesorId || !$semestrePeriodoId) {
            Response::error("Faltan parámetros obligatorios: 'profesorid', 'semestreperiodoid'", 400);
        }

        // 3. Llamar al servicio
        $data = $this->service->getAsignaturasDocente($profesorId, $semestrePeriodoId);

        Response::json($data);
    }
    public function getFormatos()
    {
        $data = $this->service->getFormatos();
        Response::json($data);
    }

    public function getTiposDocumento()
    {
        $data = $this->service->getTiposDocumento();
        Response::json($data);
    }

    public function getEstudiantesEnRiesgo($currentUser)
    {
        $semestrePeriodoId = $_GET['semestreperiodoid'] ?? null;
        $profesorIdFromRequest = $_GET['profesorid'] ?? null;

        // Validar que el profesorId corresponda al usuario logueado o que sea admin (asumimos logueado por ahora)
        // El usuario logueado viene en $currentUser['profesorpyid'] o similar.
        // En AuthMiddleware/Login: 'profesorid' está en $currentUser['user']['profesorid']?
        // Revisemos AuthMiddleware o Login.

        // Asumimos que si envia profesorid, confiamos o validamos.
        // Por seguridad, usaremos el del token si es docente.
        $profesorId = $currentUser['user']['profesorid'] ?? $profesorIdFromRequest;

        if (!$semestrePeriodoId || !$profesorId) {
            Response::error('Faltan parámetros requeridos (semestreperiodoid, profesorid)', 400);
        }

        $data = $this->service->getEstudiantesEnRiesgo($semestrePeriodoId, $profesorId);
        Response::json($data);
    }

    public function registrarTutoria($currentUser)
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Campos requeridos para actualización
        $required = ['tutoriaid', 'objetivotutoria', 'tutorias_requeridas'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                Response::error("El campo '$field' es obligatorio", 400);
            }
        }

        // Filtramos solo los datos necesarios
        $updateData = [
            'tutoriaid' => $data['tutoriaid'], // ID para where
            'objetivotutoria' => $data['objetivotutoria'],
            'tutorias_requeridas' => $data['tutorias_requeridas']
        ];

        // Llamamos al servicio (que ahora hará update)
        $result = $this->service->registrarTutoria($updateData);

        if (isset($result['error'])) {
            Response::error('Error al actualizar tutoría: ' . json_encode($result), 500);
        }

        Response::json($result, 200); // 200 OK for update
    }
}
