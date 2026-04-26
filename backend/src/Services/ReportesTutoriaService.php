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

            // Validacion: Solo estudiantes con registro en tabla tutoria
            if (!$tut) {
                continue;
            }

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
                'tutoriaid' => $tut['tutoriaid'],
                'objetivotutoria' => $tut['objetivotutoria'] ?? '',
                'tutorias_requeridas' => $tut['tutorias_requeridas'] ?? 0
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
    /**
     * Obtiene estadísticas de participación y asistencia por jornada.
     * Retorna estructura para gráfico de barras apiladas.
     */
    public function getEstadisticasJornada($semestrePeriodoId, $profesorId = null)
    {
        // 1. Obtener notas del periodo (base para filtrar tutorias)
        $filters = ['semestreperiodoid' => 'eq.' . $semestrePeriodoId];
        if ($profesorId) {
            $filters['profesorid'] = 'eq.' . $profesorId;
        }

        $notas = $this->supabase->select('notaparcial', $filters, 'notaid, seccionid');

        if (empty($notas)) {
            return $this->getEmptyJornadaStats();
        }

        $notaIds = array_column($notas, 'notaid');
        $seccionIds = array_unique(array_column($notas, 'seccionid'));

        // 2. Obtener tutorias asociadas
        // Nota: Supabase no tiene 'IN' nativo fácil en wrapper simple, traemos todo (optimizar luego si es lento)
        // O hacemos un loop si son pocos, o traemos todas las tutorias y filtramos en PHP.
        // Dado el scope, traemos 'tutoria' y filtramos en memoria por 'notaid'.
        $allTutorias = $this->supabase->select('tutoria');

        // Filtrar tutorias del periodo
        $tutoriasDelPeriodo = array_filter($allTutorias, function ($t) use ($notaIds) {
            return in_array($t['notaid'], $notaIds);
        });

        if (empty($tutoriasDelPeriodo)) {
            return $this->getEmptyJornadaStats();
        }

        // 3. Obtener Secciones para determinar Jornada
        $secciones = $this->supabase->select('seccion');
        $seccionMap = [];
        foreach ($secciones as $s) {
            $seccionMap[$s['seccionid']] = $s;
        }

        // Map nota -> seccion
        $notaSeccionMap = [];
        foreach ($notas as $n) {
            $notaSeccionMap[$n['notaid']] = $n['seccionid'];
        }

        // 4. Obtener Estados (para mapear nombres a códigos si necesario, o usar IDs fijos)
        // IDs esperados: 
        // REA (Realizada) -> Asumiremos ID o buscaremos por nombre.
        // INC (Incompleta) 
        // INA (Inasistencia)
        $estados = $this->supabase->select('estadotutoria');
        $estadoNombreMap = [];
        foreach ($estados as $e) {
            $estadoNombreMap[$e['estadotutoriaid']] = strtoupper($e['nombre']);
        }

        // 5. Agregar contadores
        // Estructura: [Jornada][Estado] = count
        $jornadas = ['Mañana', 'Tarde', 'Noche'];
        $stats = [];
        foreach ($jornadas as $j) {
            $stats[$j] = [
                'REA' => 0,
                'INC' => 0,
                'INA' => 0
            ];
        }

        foreach ($tutoriasDelPeriodo as $tut) {
            $notaId = $tut['notaid'];
            $seccionId = $notaSeccionMap[$notaId] ?? null;
            $seccion = $seccionMap[$seccionId] ?? null;

            // Determinar Jornada
            $jornada = 'Noche'; // Default
            if ($seccion) {
                // Lógica heurística basada en nombre de sección
                $nombre = strtoupper($seccion['nombre']);
                if (strpos($nombre, 'MAT') !== false || strpos($nombre, 'DIA') !== false || strpos($nombre, 'M') !== false) {
                    // Cuidado con 'M' simple, puede ser falso positivo. 
                    // Mejor: buscar rangos horarios si existen, sino keywords.
                    // Asumiremos keywords comunes de institutos.
                    $jornada = 'Mañana';
                } elseif (strpos($nombre, 'VESP') !== false || strpos($nombre, 'TARDE') !== false) {
                    $jornada = 'Tarde';
                } elseif (strpos($nombre, 'NOC') !== false || strpos($nombre, 'NOCHE') !== false) {
                    $jornada = 'Noche';
                }
            }

            // Determinar Estado
            // Mapeamos el nombre del estado a nuestras claves REA, INC, INA
            $estadoId = $tut['estadotutoriaid'];
            $nombreEstado = $estadoNombreMap[$estadoId] ?? '';

            $key = null;
            // Ajustar estos strings según la DB real
            if (strpos($nombreEstado, 'REALIZAD') !== false)
                $key = 'REA';
            elseif (strpos($nombreEstado, 'INCOMPLET') !== false)
                $key = 'INC';
            elseif (strpos($nombreEstado, 'INASISTEN') !== false || strpos($nombreEstado, 'NO ASIST') !== false)
                $key = 'INA';

            if ($key && isset($stats[$jornada][$key])) {
                $stats[$jornada][$key]++;
            }
        }

        // 6. Formatear respuesta para el frontend
        // Eje X: ["Mañana", "Tarde", "Noche"]
        // Series: cada una es un array de datos ordenados por el eje X

        $dataRea = [];
        $dataInc = [];
        $dataIna = [];

        foreach ($jornadas as $j) {
            $dataRea[] = $stats[$j]['REA'];
            $dataInc[] = $stats[$j]['INC'];
            $dataIna[] = $stats[$j]['INA'];
        }

        return [
            'categories' => $jornadas,
            'series' => [
                [
                    'name' => 'REA (Realizada)',
                    'data' => $dataRea,
                    'color' => '#1E88E5'
                ],
                [
                    'name' => 'INC (Incompleta)',
                    'data' => $dataInc,
                    'color' => '#CFD8DC'
                ],
                [
                    'name' => 'INA (Inasistencia)',
                    'data' => $dataIna,
                    'color' => '#EF5350'
                ]
            ]
        ];
    }

    private function getEmptyJornadaStats()
    {
        return [
            'categories' => ['Mañana', 'Tarde', 'Noche'],
            'series' => [
                ['name' => 'REA (Realizada)', 'data' => [0, 0, 0], 'color' => '#1E88E5'],
                ['name' => 'INC (Incompleta)', 'data' => [0, 0, 0], 'color' => '#CFD8DC'],
                ['name' => 'INA (Inasistencia)', 'data' => [0, 0, 0], 'color' => '#EF5350']
            ]
        ];
    }
    /**
     * KPI: Tasa de Gestión Docente.
     * Retorna { value: float, trend: float }
     * Formula: (Sesiones Realizadas / Total Requeridas) * 100
     */
    public function getTasaGestionDocente($semestrePeriodoId, $profesorId = null)
    {
        // 1. Calcular Tasa Actual
        $currentStats = $this->calculateTasaForPeriod($semestrePeriodoId, $profesorId);

        // 2. Calcular Tasa Anterior (Heurística: ID - 1, o buscar el periodo inmediatamente anterior activo)
        // Por simplicidad, intentaremos el ID anterior.
        $previousStats = $this->calculateTasaForPeriod($semestrePeriodoId - 1, $profesorId);

        // Si el periodo anterior no tiene requeridas (ej: no existe o es inicio), trend es 0 o igual al valor actual.
        // Definiremos Trend = Current - Previous.
        $trend = 0;
        if ($previousStats['requeridas'] > 0) {
            $trend = $currentStats['tasa'] - $previousStats['tasa'];
        }

        return [
            'value' => round($currentStats['tasa'], 2),
            'trend' => round($trend, 2),
            'label' => 'Tasa de Gestión Docente',
            'meta' => [
                'realizadas' => $currentStats['realizadas'],
                'requeridas' => $currentStats['requeridas']
            ]
        ];
    }

    private function calculateTasaForPeriod($semestrePeriodoId, $profesorId)
    {
        // a. Obtener tutorias
        // El KPI suele ser global para el coordinador, o especifico para un docente.
        // Si tiene notaid, filtramos por notaparcial para segurar el periodo.

        $notaFilters = ['semestreperiodoid' => 'eq.' . $semestrePeriodoId];
        if ($profesorId) {
            $notaFilters['profesorid'] = 'eq.' . $profesorId;
        }

        $notas = $this->supabase->select('notaparcial', $notaFilters, 'notaid');
        if (empty($notas)) {
            return ['tasa' => 0, 'realizadas' => 0, 'requeridas' => 0];
        }

        $notaIds = array_column($notas, 'notaid');

        // b. Traer tutorias asociadas
        $allTutorias = $this->supabase->select('tutoria');
        $myTutorias = array_filter($allTutorias, function ($t) use ($notaIds) {
            return in_array($t['notaid'], $notaIds);
        });

        if (empty($myTutorias)) {
            return ['tasa' => 0, 'realizadas' => 0, 'requeridas' => 0];
        }

        // c. Sumar requeridas
        $requeridas = 0;
        $tutoriaIds = [];
        foreach ($myTutorias as $t) {
            $req = isset($t['tutorias_requeridas']) ? (int) $t['tutorias_requeridas'] : 0;
            $requeridas += $req;
            $tutoriaIds[] = $t['tutoriaid'];
        }

        if ($requeridas == 0) {
            return ['tasa' => 0, 'realizadas' => 0, 'requeridas' => 0];
        }

        // d. Contar Realizadas (Tutoria Detalle con estado Realizada)
        // Necesitamos el ID del estado 'Realizada'
        // Lo cacheamos o buscamos. Leemos todos.
        $estados = $this->supabase->select('estadotutoria');
        $idRealizada = null;
        foreach ($estados as $e) {
            if (stripos($e['nombre'], 'Realizada') !== false) {
                $idRealizada = $e['estadotutoriaid'];
                break;
            }
        }

        // Si no encontramos 'Realizada', asumimos que no podemos contar realizadas
        if (!$idRealizada) {
            return ['tasa' => 0, 'realizadas' => 0, 'requeridas' => $requeridas];
        }

        // Traer detalles
        $allDetalles = $this->supabase->select('tutoria_detalle'); // Optimizar si fuera prod, aqui ok.
        $realizadasCount = 0;

        foreach ($allDetalles as $det) {
            if (in_array($det['tutoriaid'], $tutoriaIds) && $det['estadotutoriaid'] == $idRealizada) {
                $realizadasCount++;
            }
        }

        $tasa = ($realizadasCount / $requeridas) * 100;

        return [
            'tasa' => $tasa,
            'realizadas' => $realizadasCount,
            'requeridas' => $requeridas
        ];
    }
    /**
     * KPI: Asistencia Efectiva.
     * Retorna { value: float, trend: float }
     * Formula: (Realizada / (Realizada + Inasistencia)) * 100
     */
    public function getAsistenciaEfectiva($semestrePeriodoId, $profesorId = null)
    {
        $currentStats = $this->calculateAsistenciaForPeriod($semestrePeriodoId, $profesorId);
        $previousStats = $this->calculateAsistenciaForPeriod($semestrePeriodoId - 1, $profesorId);

        $trend = 0;
        // Solo calculamos trend si hubo actividad en periodo anterior
        if ($previousStats['total_eventos'] > 0) {
            $trend = $currentStats['tasa'] - $previousStats['tasa'];
        }

        return [
            'value' => round($currentStats['tasa'], 2),
            'trend' => round($trend, 2),
            'label' => 'Asistencia Efectiva',
            'meta' => [
                'asistencias' => $currentStats['asistencias'],
                'inasistencias' => $currentStats['inasistencias']
            ]
        ];
    }

    private function calculateAsistenciaForPeriod($semestrePeriodoId, $profesorId)
    {
        // 1. Filtrar notas del periodo
        $notaFilters = ['semestreperiodoid' => 'eq.' . $semestrePeriodoId];
        if ($profesorId) {
            $notaFilters['profesorid'] = 'eq.' . $profesorId;
        }

        $notas = $this->supabase->select('notaparcial', $notaFilters, 'notaid');
        if (empty($notas)) {
            return ['tasa' => 0, 'asistencias' => 0, 'inasistencias' => 0, 'total_eventos' => 0];
        }

        $notaIds = array_column($notas, 'notaid');

        // 2. Filtrar tutorias
        $allTutorias = $this->supabase->select('tutoria');
        $myTutorias = array_filter($allTutorias, function ($t) use ($notaIds) {
            return in_array($t['notaid'], $notaIds);
        });

        if (empty($myTutorias)) {
            return ['tasa' => 0, 'asistencias' => 0, 'inasistencias' => 0, 'total_eventos' => 0];
        }

        $tutoriaIds = array_column($myTutorias, 'tutoriaid');

        // 3. Obtener estados de interés (ID de Realizada e Inasistencia)
        $estados = $this->supabase->select('estadotutoria');
        $idRealizada = null;
        $idInasistencia = null;

        foreach ($estados as $e) {
            $nombre = strtoupper($e['nombre']);
            if (strpos($nombre, 'REALIZAD') !== false) {
                $idRealizada = $e['estadotutoriaid'];
            } elseif (strpos($nombre, 'INASISTEN') !== false || strpos($nombre, 'NO ASIST') !== false) {
                $idInasistencia = $e['estadotutoriaid'];
            }
        }

        if (!$idRealizada || !$idInasistencia) {
            // Fallback o retorno 0 si no estan configurados
            return ['tasa' => 0, 'asistencias' => 0, 'inasistencias' => 0, 'total_eventos' => 0];
        }

        // 4. Contar en detalles
        $allDetalles = $this->supabase->select('tutoria_detalle');

        $reaCount = 0;
        $inaCount = 0;

        foreach ($allDetalles as $det) {
            if (in_array($det['tutoriaid'], $tutoriaIds)) {
                if ($det['estadotutoriaid'] == $idRealizada) {
                    $reaCount++;
                } elseif ($det['estadotutoriaid'] == $idInasistencia) {
                    $inaCount++;
                }
            }
        }

        $total = $reaCount + $inaCount;
        $tasa = 0;
        if ($total > 0) {
            $tasa = ($reaCount / $total) * 100;
        }

        return [
            'tasa' => $tasa,
            'asistencias' => $reaCount,
            'inasistencias' => $inaCount,
            'total_eventos' => $total
        ];
    }
    /**
     * KPI: Impacto Académico Global.
     * Retorna { value: float, trend: float }
     * Formula: Promedio de (NotaP2 - NotaP1) para estudiantes tutorados.
     */
    public function getImpactoAcademico($semestrePeriodoId, $profesorId = null)
    {
        $currentStats = $this->calculateImpactoForPeriod($semestrePeriodoId, $profesorId);
        $previousStats = $this->calculateImpactoForPeriod($semestrePeriodoId - 1, $profesorId);

        $trend = 0;
        if ($previousStats['count'] > 0) {
            $trend = $currentStats['avg_delta'] - $previousStats['avg_delta'];
        }

        return [
            'value' => round($currentStats['avg_delta'], 2),
            // Si el valor es positivo, agregamos el signo +, si es negativo se mantiene.
            // El frontend o la etiqueta 'value' podría manejarlo, pero aqui devolvemos el float puro.
            'trend' => round($trend, 2),
            'label' => 'Impacto Académico Global',
            'meta' => [
                'estudiantes_evaluados' => $currentStats['count']
            ]
        ];
    }

    private function calculateImpactoForPeriod($semestrePeriodoId, $profesorId)
    {
        // 1. Obtener notas candidatos (P1 < 7) que YA tengan P2 (> 0)
        // Nota: Asumimos que P2=0 o NULL significa 'no evaluado aun'.
        $filters = [
            'semestreperiodoid' => 'eq.' . $semestrePeriodoId,
            'notap1' => 'lt.7', // Solo los que estaban en riesgo
            'notap2' => 'gt.0'  // Solo los que ya tienen segunda nota
        ];

        if ($profesorId) {
            $filters['profesorid'] = 'eq.' . $profesorId;
        }

        $notas = $this->supabase->select('notaparcial', $filters, 'notaid, notap1, notap2');

        if (empty($notas)) {
            return ['avg_delta' => 0, 'count' => 0];
        }

        $notaIds = array_column($notas, 'notaid');

        // 2. Verificar cuáles de estas notas tuvieron tutoría REALIZADA
        // Traemos todas las tutorias (o filtramos)
        $allTutorias = $this->supabase->select('tutoria');

        // Filtramos tutorias asociadas a nuestras notas
        $myTutorias = array_filter($allTutorias, function ($t) use ($notaIds) {
            return in_array($t['notaid'], $notaIds);
        });

        if (empty($myTutorias)) {
            return ['avg_delta' => 0, 'count' => 0];
        }

        // Necesitamos saber si la tutoria fue REALIZADA.
        // Miramos 'tutoria_detalle'. Si tiene al menos un detalle 'Realizada', cuenta.
        $tutoriaIds = array_column($myTutorias, 'tutoriaid');

        // Obtener ID Realizada
        $estados = $this->supabase->select('estadotutoria');
        $idRealizada = null;
        foreach ($estados as $e) {
            if (stripos($e['nombre'], 'Realizada') !== false) {
                $idRealizada = $e['estadotutoriaid'];
                break;
            }
        }

        if (!$idRealizada) {
            return ['avg_delta' => 0, 'count' => 0];
        }

        $allDetalles = $this->supabase->select('tutoria_detalle');
        $realizadasTutoriaIds = [];

        foreach ($allDetalles as $det) {
            if (in_array($det['tutoriaid'], $tutoriaIds) && $det['estadotutoriaid'] == $idRealizada) {
                $realizadasTutoriaIds[$det['tutoriaid']] = true;
            }
        }

        // 3. Calcular Deltas solo para notas con tutoria realizada
        $totalDelta = 0;
        $count = 0;

        // Mapear Tutoria -> Nota
        $tutoriaNotaMap = [];
        foreach ($myTutorias as $t) {
            $tutoriaNotaMap[$t['tutoriaid']] = $t['notaid'];
        }

        // Invertir: Nota -> tieneTutoriaRealizada
        $notaTieneTutoria = [];
        foreach ($realizadasTutoriaIds as $tid => $val) {
            if (isset($tutoriaNotaMap[$tid])) {
                $nid = $tutoriaNotaMap[$tid];
                $notaTieneTutoria[$nid] = true;
            }
        }

        foreach ($notas as $n) {
            if (isset($notaTieneTutoria[$n['notaid']])) {
                $delta = $n['notap2'] - $n['notap1'];
                $totalDelta += $delta;
                $count++;
            }
        }

        $avg = 0;
        if ($count > 0) {
            $avg = $totalDelta / $count;
        }

        return ['avg_delta' => $avg, 'count' => $count];
    }
}




