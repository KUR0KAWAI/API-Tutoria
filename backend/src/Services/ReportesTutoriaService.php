<?php

namespace App\Services;

use App\Config\SupabaseClient;
use App\Helpers\Response;

class ReportesTutoriaService
{
    private SupabaseClient $supabase;

    public function __construct()
    {
        $this->supabase = new SupabaseClient();
    }

    /**
     * Obtiene las asignaturas que dicta un docente filtradas por semestrePeriodoId.
     * @param int $profesorId
     * @param int $semestrePeriodoId
     */
    public function getAsignaturasDocente($profesorId, $semestrePeriodoId)
    {
        // 1. Obtener el nivel académico buscando primero en SemestrePeriodo
        $sp = $this->supabase->select('semestreperiodo', ['semestreperiodoid' => 'eq.' . $semestrePeriodoId]);
        if (empty($sp)) {
            return [];
        }
        $semestreId = $sp[0]['semestreid'];

        $sem = $this->supabase->select('semestre', ['semestreid' => 'eq.' . $semestreId]);
        if (empty($sem)) {
            return [];
        }

        // Suposición: codigo es un entero o string numérico que representa el nivel
        $nivel = (int) ($sem[0]['codigo'] ?? $sem[0]['semestreid']);

        // Definir rango de códigos para este nivel
        $minCode = $nivel * 100;
        $maxCode = ($nivel * 100) + 99;

        // 2. Obtener todas las asignaciones del profesor
        $paRecs = $this->supabase->select('profesorasignatura', [
            'profesorid' => 'eq.' . $profesorId
        ]);

        if (empty($paRecs)) {
            return [];
        }

        // 3. Enriquecer y filtrar
        $result = [];

        $todasAsignaturas = $this->supabase->select('asignatura');
        $todasSecciones = $this->supabase->select('seccion');

        $asigMap = [];
        foreach ($todasAsignaturas as $a) {
            $asigMap[$a['asignaturaid']] = $a;
        }

        $seccMap = [];
        foreach ($todasSecciones as $s) {
            $seccMap[$s['seccionid']] = $s['nombre'];
        }

        foreach ($paRecs as $row) {
            $asigId = $row['asignaturaid'];

            if (!isset($asigMap[$asigId])) {
                continue;
            }

            $asigData = $asigMap[$asigId];
            $codigo = trim($asigData['codigo'] ?? '');

            // Validar si la asignatura pertenece al Nivel seleccionado
            // Usamos regex para buscar el bloque numérico de 3 dígitos al final (ej: -104)
            // O fallback a explode si regex falla

            $numericPart = 0;
            if (preg_match('/-(\d{3})$/', $codigo, $matches)) {
                $numericPart = (int) $matches[1];
            } else {
                $parts = explode('-', $codigo);
                if (count($parts) >= 3) {
                    $numericPart = (int) end($parts);
                }
            }

            if ($numericPart >= $minCode && $numericPart <= $maxCode) {
                // Coincide con el Nivel!

                $seccionNombre = $seccMap[$row['seccionid']] ?? 'Desconocida';

                $result[] = [
                    'asignaturaid' => $asigData['asignaturaid'],
                    'codigo' => $asigData['codigo'],
                    'nombre' => $asigData['nombre'],
                    'creditos' => $asigData['creditos'],
                    'seccionid' => $row['seccionid'],
                    'seccion_nombre' => $seccionNombre
                ];
            }
        }

        return $result;
    }

    /**
     * Obtiene la lista de formatos de tutoría disponibles.
     */
    public function getFormatos()
    {
        return $this->supabase->select('formatotutoria');
    }

    /**
     * Obtiene la lista de tipos de documento disponibles.
     */
    public function getTiposDocumento()
    {
        return $this->supabase->select('tipodocumento');
    }

    /**
     * Obtiene los estudiantes con nota menor a 7 para un profesor y periodo.
     * @param int $semestrePeriodoId
     * @param int $profesorId
     */
    public function getEstudiantesEnRiesgo($semestrePeriodoId, $profesorId)
    {
        // 1. Consultar notas parciales
        $notas = $this->supabase->select('notaparcial', [
            'semestreperiodoid' => 'eq.' . $semestrePeriodoId,
            'profesorid' => 'eq.' . $profesorId,
            'notap1' => 'lt.7'
        ]);

        if (empty($notas)) {
            return [];
        }

        // 2. Obtener datos relacionales (Manual Join)
        $todasAsignaturas = $this->supabase->select('asignatura');
        $todasSecciones = $this->supabase->select('seccion');
        $todosAlumnos = $this->supabase->select('alumno');

        // 2.1 Obtener tutorias asociadas a estas notas (para sacar objetivo y requeridas)
        // Optimizacion: Podriamos filtrar, pero por ahora traemos todas las del periodo si es posible, 
        // o simplemente hacemos un map buscando por notaid.
        // Como no tenemos 'in' dinamico facil, traemos tutorias y filtramos en PHP.
        // Ojo: Esto puede ser ineficiente si hay muchas tutorias.
        // Ideal: $client->select('tutoria', ['notaid' => 'in.('.implode(',',$notaIds).')']);
        // Pero usaremos select general y map.
        $tutorias = $this->supabase->select('tutoria');

        $asigMap = array_column($todasAsignaturas, null, 'asignaturaid');
        $seccMap = array_column($todasSecciones, null, 'seccionid');
        $alumMap = array_column($todosAlumnos, null, 'alumnoid');

        // Map notaid -> tutoria
        $tutoriaMap = [];
        if (!empty($tutorias)) {
            foreach ($tutorias as $t) {
                if (isset($t['notaid'])) {
                    $tutoriaMap[$t['notaid']] = $t;
                }
            }
        }

        $result = [];
        foreach ($notas as $nota) {
            $alum = $alumMap[$nota['alumnoid']] ?? null;
            $asig = $asigMap[$nota['asignaturaid']] ?? null;
            $secc = $seccMap[$nota['seccionid']] ?? null;
            $tut = $tutoriaMap[$nota['notaid']] ?? null;

            $result[] = [
                'notaid' => $nota['notaid'],
                'alumnoid' => $nota['alumnoid'],
                'alumno_nombre' => $alum ? ($alum['nombre'] . ' ' . $alum['apellidos']) : 'Desconocido',
                'asignaturaid' => $nota['asignaturaid'],
                'asignatura_nombre' => $asig ? $asig['nombre'] : 'Desconocida',
                'asignatura_codigo' => $asig ? $asig['codigo'] : '',
                'seccionid' => $nota['seccionid'],
                'seccion_nombre' => $secc ? $secc['nombre'] : 'Desconocida',
                'notap1' => $nota['notap1'],
                'fecha' => $nota['fecha'],
                // Datos de tutoria si existe
                'tutoriaid' => $tut ? $tut['tutoriaid'] : null,
                'objetivotutoria' => $tut ? ($tut['objetivotutoria'] ?? '') : '',
                'tutorias_requeridas' => $tut ? ($tut['tutorias_requeridas'] ?? 0) : 0
            ];
        }

        return $result;
    }

    /**
     * Actualiza el registro de tutoría (objetivo y cantidad).
     */
    public function registrarTutoria($data)
    {
        $tutoriaId = $data['tutoriaid'];
        unset($data['tutoriaid']); // No actualizamos el ID

        return $this->supabase->update('tutoria', 'tutoriaid', $tutoriaId, $data);
    }
}
