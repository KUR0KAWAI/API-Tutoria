<?php

namespace App\Services;

use App\Config\SupabaseClient;
use DateTime;

class TutoriaDetalleService
{
    private $supabase;

    public function __construct()
    {
        $this->supabase = new SupabaseClient();
    }

    public function getEstados()
    {
        return $this->supabase->select('estadotutoria');
    }



    /**
     * Aplica la regla de negocio: Si fecha < hoy (GMT-5) Y estado = Pendiente (1) -> Actualiza a Incompleta (5).
     * Retorna el detalle actualizado (o el mismo si no hubo cambios).
     */
    private function applyValidationRule($detalle)
    {
        $timezone = new \DateTimeZone('America/Guayaquil');
        $now = new DateTime('now', $timezone);
        $todayStr = $now->format('Y-m-d');

        $fechaTutoria = new DateTime($detalle['fechatutoria']);
        $fechaTutoriaStr = $fechaTutoria->format('Y-m-d');

        $estadoId = $detalle['estadotutoriaid'];

        if ($estadoId == 1 && $fechaTutoriaStr < $todayStr) {
            $id = $detalle['tutoriadetalleid'];
            $this->supabase->update('tutoria_detalle', 'tutoriadetalleid', $id, ['estadotutoriaid' => 5]);
            $detalle['estadotutoriaid'] = 5;
        }
        return $detalle;
    }

    public function getByTutoriaId($tutoriaId)
    {
        $detalles = $this->supabase->select('tutoria_detalle', ['tutoriaid' => 'eq.' . $tutoriaId]);

        if (empty($detalles)) {
            return [];
        }

        // Obtener todos los estados para mapear el nombre
        $estados = $this->supabase->select('estadotutoria');
        $estadoMap = [];
        if (!empty($estados)) {
            foreach ($estados as $est) {
                $estadoMap[$est['estadotutoriaid']] = $est['nombre'];
            }
        }

        $updatedDetalles = [];

        foreach ($detalles as $det) {
            // Aplicar regla
            $det = $this->applyValidationRule($det);

            // Agregamos el nombre del estado (Usamos el ID actualizado)
            $det['estado_nombre'] = $estadoMap[$det['estadotutoriaid']] ?? 'Desconocido';

            $updatedDetalles[] = $det;
        }

        return $updatedDetalles;
    }

    private function getEstadoNombre($id)
    {
        $estados = $this->supabase->select('estadotutoria', ['estadotutoriaid' => 'eq.' . $id]);
        return $estados[0]['nombre'] ?? 'Desconocido';
    }

    public function create($data)
    {
        // Estado por defecto: 1 (Pendiente)
        $data['estadotutoriaid'] = 1;
        $result = $this->supabase->insert('tutoria_detalle', $data);

        if (!empty($result) && isset($result[0])) {
            // Aplicar regla inmediatamente (caso: crear fecha pasada)
            $result[0] = $this->applyValidationRule($result[0]);

            $result[0]['estado_nombre'] = $this->getEstadoNombre($result[0]['estadotutoriaid']);
            return $result;
        }
        return $result;
    }

    public function update($id, $data)
    {
        // Validar estado actual
        $current = $this->supabase->select('tutoria_detalle', ['tutoriadetalleid' => 'eq.' . $id]);

        if (empty($current)) {
            return ['error' => 'Registro no encontrado'];
        }

        $estadoActual = $current[0]['estadotutoriaid'];

        // Regla: Si estado es 5 (Incompleta), no se puede editar
        if ($estadoActual == 5) {
            return ['error' => 'No se puede editar una tutoría incompleta'];
        }

        $result = $this->supabase->update('tutoria_detalle', 'tutoriadetalleid', $id, $data);

        if (!empty($result) && isset($result[0])) {
            // Aplicar regla inmediatamente (caso: actualizar a fecha pasada)
            $result[0] = $this->applyValidationRule($result[0]);

            $result[0]['estado_nombre'] = $this->getEstadoNombre($result[0]['estadotutoriaid']);
            return $result;
        }

        return $result;
    }

    public function delete($id)
    {
        return $this->supabase->delete('tutoria_detalle', 'tutoriadetalleid', $id);
    }
}
