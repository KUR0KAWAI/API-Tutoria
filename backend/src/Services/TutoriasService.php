<?php

namespace App\Services;

use App\Config\SupabaseClient;
use App\Helpers\Response;

class TutoriasService
{
    private SupabaseClient $supabase;

    public function __construct()
    {
        $this->supabase = new SupabaseClient();
    }

    /**
     * Obtiene los estudiantes en riesgo (P1 < 7.0) filtrados por periodo/nivel
     */
    public function getCandidatos(string $semestrePeriodoId): array
    {
        try {
            // 1. Obtener notas parciales en riesgo para el periodo específico
            $notas = $this->supabase->select('notaparcial', [
                'semestreperiodoid' => 'eq.' . $semestrePeriodoId,
                'notap1' => 'lt.7.0'
            ]);

            if (empty($notas)) {
                return [];
            }

            // 2. Obtener IDs de notas que ya tienen una tutoría asignada
            $tutorias = $this->supabase->select('tutoria');
            $assignedNotaIds = array_filter(array_column($tutorias, 'notaid'));

            // 3. Obtener catálogos para enriquecer
            $alumnos = $this->supabase->select('alumno');
            $asignaturas = $this->supabase->select('asignatura');
            $profesores = $this->supabase->select('profesor');
            $secciones = $this->supabase->select('seccion');

            $alumMap = array_column($alumnos, null, 'alumnoid');
            $asigMap = array_column($asignaturas, null, 'asignaturaid');
            $profMap = array_column($profesores, null, 'profesorid');
            $seccMap = array_column($secciones, null, 'seccionid');

            $result = [];
            foreach ($notas as $nota) {
                // SI YA TIENE TUTORÍA, SE EXCLUYE
                if (in_array($nota['notaid'], $assignedNotaIds)) {
                    continue;
                }

                $alum = $alumMap[$nota['alumnoid']] ?? null;
                $asig = $asigMap[$nota['asignaturaid']] ?? null;
                $prof = $profMap[$nota['profesorid']] ?? null;
                $secc = $seccMap[$nota['seccionid']] ?? null;

                $result[] = [
                    'notaid' => $nota['notaid'],
                    'alumnoid' => $nota['alumnoid'],
                    'alumno_nombre' => $alum ? ($alum['nombre'] . ' ' . $alum['apellidos']) : 'Desconocido',
                    'asignaturaid' => $nota['asignaturaid'],
                    'asignatura_nombre' => $asig ? $asig['nombre'] : 'Desconocida',
                    'notap1' => $nota['notap1'],
                    'profesorid' => $nota['profesorid'],
                    'profesor_nombre' => $prof ? ($prof['nombre'] . ' ' . $prof['apellidos']) : 'Desconocido',
                    'seccionid' => $nota['seccionid'] ?? null,
                    'seccion_nombre' => $secc ? $secc['nombre'] : 'Desconocida'
                ];
            }

            return $result;
        } catch (\Exception $e) {
            Response::error('Error al obtener candidatos: ' . $e->getMessage(), 500);
            return [];
        }
    }

    /**
     * Obtiene el historial de tutorías asignadas
     */
    public function getHistorial(): array
    {
        try {
            $tutorias = $this->supabase->select('tutoria');

            if (empty($tutorias)) {
                return [];
            }

            $alumnos = $this->supabase->select('alumno');
            $asignaturas = $this->supabase->select('asignatura');
            $profesores = $this->supabase->select('profesor');
            $secciones = $this->supabase->select('seccion');
            $estados = $this->supabase->select('estadotutoria');

            $alumMap = array_column($alumnos, null, 'alumnoid');
            $asigMap = array_column($asignaturas, null, 'asignaturaid');
            $profMap = array_column($profesores, null, 'profesorid');
            $seccMap = array_column($secciones, null, 'seccionid');
            $estadoMap = array_column($estados, null, 'estadotutoriaid');

            return array_map(function ($item) use ($alumMap, $asigMap, $profMap, $seccMap, $estadoMap) {
                $alum = $alumMap[$item['alumnoid']] ?? null;
                $asig = $asigMap[$item['asignaturaid']] ?? null;
                $prof = $profMap[$item['profesorid']] ?? null;
                $secc = $seccMap[$item['seccionid']] ?? null;
                $estado = $estadoMap[$item['estadotutoriaid']] ?? null;

                $item['alumno_nombre'] = $alum ? ($alum['nombre'] . ' ' . $alum['apellidos']) : 'Desconocido';
                $item['asignatura_nombre'] = $asig ? $asig['nombre'] : 'Desconocida';
                $item['profesor_nombre'] = $prof ? ($prof['nombre'] . ' ' . $prof['apellidos']) : 'Desconocido';
                $item['seccion_nombre'] = $secc ? $secc['nombre'] : 'Desconocida';
                $item['estado_nombre'] = $estado ? $estado['nombre'] : 'Desconocido';

                // Validación de objetivo vacío
                if (empty($item['objetivotutoria'])) {
                    $item['objetivotutoria'] = 'Por definir';
                }

                return $item;
            }, $tutorias);
        } catch (\Exception $e) {
            // Si la tabla no existe aún, devolvemos vacío en lugar de error fatal
            if (strpos($e->getMessage(), '404') !== false || strpos($e->getMessage(), 'not found') !== false) {
                return [];
            }
            Response::error('Error al obtener historial: ' . $e->getMessage(), 500);
            return [];
        }
    }

    /**
     * Asignar una nueva tutoría (Estado por defecto: Pendiente)
     */
    public function assignTutoria(array $data)
    {
        try {
            // 1. Limpiar campos por petición del usuario para este paso
            unset($data['objetivotutoria']);
            unset($data['observaciones']);

            // Mapeo: El cliente usualmente envía 'fecha', pero la tabla espera 'fechatutoria'
            if (isset($data['fecha']) && !isset($data['fechatutoria'])) {
                $data['fechatutoria'] = $data['fecha'];
            }
            unset($data['fecha']);

            // 2. Asignar estado por defecto si no viene en el payload
            if (!isset($data['estadotutoriaid'])) {
                // Buscamos el ID del estado 'Pendiente'
                $estados = $this->supabase->select('estadotutoria', ['nombre' => 'eq.Pendiente']);

                if (!empty($estados)) {
                    $data['estadotutoriaid'] = $estados[0]['estadotutoriaid'];
                } else {
                    // Si no existe 'Pendiente', intentamos obtener el primer estado disponible
                    $todosLosEstados = $this->supabase->select('estadotutoria');
                    if (!empty($todosLosEstados)) {
                        $data['estadotutoriaid'] = $todosLosEstados[0]['estadotutoriaid'];
                    } else {
                        throw new \Exception("No hay estados configurados en la tabla 'estadotutoria'");
                    }
                }
            }

            return $this->supabase->insert('tutoria', $data);
        } catch (\Exception $e) {
            Response::error('Error al asignar tutoría: ' . $e->getMessage(), 500);
        }
    }

    public function updateTutoria(string $id, array $data)
    {
        try {
            return $this->supabase->update('tutoria', 'tutoriaid', $id, $data);
        } catch (\Exception $e) {
            Response::error('Error al actualizar tutoría: ' . $e->getMessage(), 500);
        }
    }

    public function deleteTutoria(string $id)
    {
        try {
            // 1. Obtener datos de la tutoría antes de borrar para la notificación
            $tutoriaRaw = $this->supabase->select('tutoria', ['tutoriaid' => 'eq.' . $id]);
            if (empty($tutoriaRaw)) {
                Response::error("Tutoría no encontrada", 404);
            }
            $tutoria = $tutoriaRaw[0];

            // 2. Borrar detalles asociados primero (Cascade manual)
            $this->supabase->delete('tutoria_detalle', 'tutoriaid', $id);

            // 3. Borrar la tutoría principal
            $result = $this->supabase->delete('tutoria', 'tutoriaid', $id);

            // 4. Notificar al docente (Se asume la existencia de una tabla 'notificacion')
            $this->createNotification([
                'usuarioid' => $tutoria['profesorid'], // O el campo que vincule al docente con usuarios
                'mensaje' => "La coordinación ha eliminado la tutoría obligatoria asignada al alumno con ID: " . $tutoria['alumnoid'],
                'tipo' => 'ALERTA_ELIMINACION',
                'fechanotificacion' => date('c')
            ]);

            return $result;
        } catch (\Exception $e) {
            Response::error('Error al eliminar tutoría: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Crea una notificación para un usuario (docente/alumno)
     */
    private function createNotification(array $notifData)
    {
        try {
            // Intentamos insertar en la tabla de notificaciones
            // Si la tabla no existe aún, capturamos el error silenciosamente o notificamos al log
            return $this->supabase->insert('notificacion', $notifData);
        } catch (\Exception $e) {
            // Si falla porque no existe la tabla, no bloqueamos la eliminación de la tutoría
            // Pero dejamos el registro en los logs de Supabase
            error_log("Error al crear notificación: " . $e->getMessage());
        }
    }
}
