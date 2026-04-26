<?php

namespace App\Services;

use App\Config\SupabaseClient;
use App\Helpers\Response;

class NotasParcialesService
{
    private SupabaseClient $supabase;

    public function __construct()
    {
        $this->supabase = new SupabaseClient();
    }

    /**
     * 1. Periodos y Niveles (Consolidado para optimización)
     * Devuelve: semestreperiodoid, periodoid, periodo_nombre, semestreid, nivel
     */
    public function getPeriodos()
    {
        // 1. Obtener SemestrePeriodo activos
        $spRecs = $this->supabase->select('semestreperiodo', ['estado' => 'eq.Activo']);

        // 2. Obtener Periodos y Semestres para mapeo rápido
        $periodos = $this->supabase->select('periodo');
        $semestres = $this->supabase->select('semestre');

        $periodoMap = [];
        foreach ($periodos as $p)
            $periodoMap[$p['periodoid']] = $p['nombre'];

        $semestreMap = [];
        foreach ($semestres as $s)
            $semestreMap[$s['semestreid']] = $s['nivel'];

        $result = [];
        foreach ($spRecs as $sp) {
            $result[] = [
                'semestreperiodoid' => $sp['semestreperiodoid'],
                'periodoid' => $sp['periodoid'],
                'periodo_nombre' => $periodoMap[$sp['periodoid']] ?? 'Desconocido',
                'semestreid' => $sp['semestreid'],
                'nivel' => $semestreMap[$sp['semestreid']] ?? 'Desconocido'
            ];
        }

        return $result;
    }

    /**
     * 2. Niveles (Obsolento para carga inicial, pero se mantiene si es necesario por id)
     */
    public function getNiveles($periodoId)
    {
        $sp = $this->supabase->select('semestreperiodo', [
            'periodoid' => 'eq.' . $periodoId,
            'estado' => 'eq.Activo'
        ]);

        $result = [];
        foreach ($sp as $row) {
            $sem = $this->supabase->select('semestre', ['semestreid' => 'eq.' . $row['semestreid']]);
            if (!empty($sem)) {
                $nivelData = $sem[0];
                $nivelData['semestreperiodoid'] = $row['semestreperiodoid'];
                $nivelData['periodoid'] = $row['periodoid'];
                $result[] = $nivelData;
            }
        }
        return $result;
    }

    /**
     * 3. Asignaturas (Filtrado por Código del Semestre)
     */
    public function getAsignaturas($semestrePeriodoId, $seccionId = null)
    {
        // 1. Obtener el nivel académico para filtrar por código (ej. Nivel 1 -> 100-199)
        $sp = $this->supabase->select('semestreperiodo', ['semestreperiodoid' => 'eq.' . $semestrePeriodoId]);
        if (empty($sp))
            return [];
        $semestreId = $sp[0]['semestreid'];

        $sem = $this->supabase->select('semestre', ['semestreid' => 'eq.' . $semestreId]);
        if (empty($sem))
            return [];
        $nivel = (int) ($sem[0]['codigo'] ?? $sem[0]['semestreid']);

        // 2. Si hay seccionId, verificar disponibilidad en la tabla profesorasignatura
        $inscritasIds = null;
        if ($seccionId) {
            $paRecs = $this->supabase->select('profesorasignatura', [
                'seccionid' => 'eq.' . $seccionId
            ]);

            if (empty($paRecs)) {
                Response::error("No hay cursos disponibles para esta sección", 404);
            }
            $inscritasIds = array_unique(array_column($paRecs, 'asignaturaid'));
        }

        // 3. Obtener todas las asignaturas y filtrar por nivel + presencia en sección (si aplica)
        $allAsig = $this->supabase->select('asignatura');
        $minCode = $nivel * 100;
        $maxCode = ($nivel * 100) + 99;

        $result = [];
        foreach ($allAsig as $asig) {
            // Filtro por sección (solo si se provee seccionId)
            if ($inscritasIds !== null && !in_array($asig['asignaturaid'], $inscritasIds)) {
                continue;
            }

            $codigo = $asig['codigo'] ?? '';
            $parts = explode('-', $codigo);
            if (count($parts) >= 3) {
                $numericPart = (int) end($parts);
                if ($numericPart >= $minCode && $numericPart <= $maxCode) {
                    $result[] = $asig;
                }
            }
        }

        if ($seccionId && empty($result)) {
            Response::error("No hay asignaturas programadas para este nivel en la sección seleccionada", 404);
        }

        return $result;
    }

    /**
     * 4. Docentes (Filtrado por materia y sección)
     */
    public function getDocentes($semestrePeriodoId, $seccionId, $asignaturaId)
    {
        // 1. Buscar los profesores asignados a esa materia en esa sección específica
        $paRecs = $this->supabase->select('profesorasignatura', [
            'asignaturaid' => 'eq.' . $asignaturaId,
            'seccionid' => 'eq.' . $seccionId
        ]);

        if (empty($paRecs))
            return [];

        $docentes = [];
        foreach ($paRecs as $row) {
            $prof = $this->supabase->select('profesor', ['profesorid' => 'eq.' . $row['profesorid']]);
            if (!empty($prof)) {
                $docentes[$prof[0]['profesorid']] = $prof[0];
            }
        }

        return array_values($docentes);
    }

    /**
     * 5. Secciones (Mañana, Tarde, Noche)
     */
    public function getSecciones()
    {
        return $this->supabase->select('seccion');
    }

    /**
     * 6. Estudiantes (Todos los estudiantes)
     */
    public function getAlumnos()
    {
        return $this->supabase->select('alumno');
    }

    /**
     * Obtener listado de notas enriquecido (Global - Sin filtros)
     */
    public function getNotas()
    {
        // 1. Obtener todas las notas sin filtros para esta página auxiliar
        $notas = $this->supabase->select('notaparcial');
        if (empty($notas))
            return [];

        // 2. Cargar catálogos para mapeos descriptivos
        $map_sp = $this->getPeriodos();
        $secciones = $this->supabase->select('seccion');
        $asignaturas = $this->supabase->select('asignatura');
        $alumnos = $this->supabase->select('alumno');
        $profesores = $this->supabase->select('profesor');

        // Mapeos rápidos
        $spMap = [];
        foreach ($map_sp as $m)
            $spMap[$m['semestreperiodoid']] = $m;

        $seccMap = [];
        foreach ($secciones as $s)
            $seccMap[$s['seccionid']] = $s['nombre'];

        $asigMap = [];
        foreach ($asignaturas as $a)
            $asigMap[$a['asignaturaid']] = $a['nombre'];

        $alumMap = [];
        foreach ($alumnos as $al)
            $alumMap[$al['alumnoid']] = $al['nombre'] . ' ' . $al['apellidos'];

        $profMap = [];
        foreach ($profesores as $p)
            $profMap[$p['profesorid']] = $p['nombre'] . ' ' . $p['apellidos'];

        // 3. Formatear respuesta
        $result = [];
        foreach ($notas as $nota) {
            $spInfo = $spMap[$nota['semestreperiodoid'] ?? ''] ?? null;

            $result[] = [
                'notaid' => $nota['notaid'],
                'periodoid' => $spInfo['periodoid'] ?? null,
                'periodo_nombre' => $spInfo['periodo_nombre'] ?? 'N/A',
                'semestreid' => $spInfo['semestreid'] ?? null,
                'nivel' => $spInfo['nivel'] ?? 'N/A',
                'seccionid' => $nota['seccionid'],
                'seccion_nombre' => $seccMap[$nota['seccionid'] ?? ''] ?? 'N/A',
                'asignaturaid' => $nota['asignaturaid'],
                'asignatura_nombre' => $asigMap[$nota['asignaturaid'] ?? ''] ?? 'N/A',
                'profesorid' => $nota['profesorid'],
                'profesor_nombre' => $profMap[$nota['profesorid'] ?? ''] ?? 'N/A',
                'alumnoid' => $nota['alumnoid'],
                'alumno_nombre' => $alumMap[$nota['alumnoid'] ?? ''] ?? 'N/A',
                'notap1' => $nota['notap1'],
                'notap2' => $nota['notap2'],
                'fecha' => $nota['fecha']
            ];
        }

        return $result;
    }

    public function createNota($data)
    {
        return $this->supabase->insert('notaparcial', $data);
    }

    public function updateNota($id, $data)
    {
        return $this->supabase->update('notaparcial', 'notaid', $id, $data);
    }

    public function deleteNota($id)
    {
        return $this->supabase->delete('notaparcial', 'notaid', $id);
    }
}
