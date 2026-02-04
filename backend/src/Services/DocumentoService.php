<?php

namespace App\Services;

use App\Config\SupabaseClient;
use Exception;

class DocumentoService
{
    private SupabaseClient $supabase;
    private string $bucketName = 'cronograma-docs';

    public function __construct()
    {
        $this->supabase = new SupabaseClient();
    }

    /**
     * Sube un documento y lo registra en la base de datos.
     *
     * @param array $file Archivo proveniente de $_FILES
     * @param array $data Datos adicionales (cronogramaid, asignaturaid, etc.)
     * @param array $currentUser Usuario autenticado
     * @return array
     */
    public function uploadDocument(array $file, array $data, $currentUser)
    {
        // 1. Validaciones básicas
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception("Parámetros de archivo inválidos.");
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir archivo: " . $this->codeToMessage($file['error']));
        }

        $allowedTypes = ['application/pdf'];
        $mime = '';

        if (class_exists('\finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
        } else {
            // Fallback: usar el type reportado por el navegador y la extensión
            $mime = $file['type'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($extension !== 'pdf') {
                throw new Exception("Solo se permiten archivos PDF. Extensión detectada: " . $extension);
            }
        }

        if (!in_array($mime, $allowedTypes) && $mime !== 'application/x-pdf') {
            throw new Exception("Solo se permiten archivos PDF. Tipo detectado: " . $mime);
        }


        // 2. Preparar subida al Storage
        // Limpiar nombre de archivo
        $cleanName = preg_replace('/[^a-zA-Z0-9\._-]/', '', $file['name']);
        $filename = uniqid() . '_' . $cleanName;

        $content = file_get_contents($file['tmp_name']);

        // 3. Subir a Supabase Storage
        try {
            $publicUrl = $this->supabase->uploadFile($this->bucketName, $filename, $content, $mime);
        } catch (Exception $e) {
            throw new Exception("Error al guardar en Storage: " . $e->getMessage());
        }

        // 4. Registrar en base de datos (tabla 'documentosubido')
        // Validar campos requeridos
        $requiredFields = ['cronogramaid', 'asignaturaid', 'tipodocumentoid', 'semestreperiodoid', 'seccionid'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new Exception("Faltan campos requeridos: " . implode(', ', $missingFields));
        }

        $dbData = [
            'cronogramaid' => $data['cronogramaid'],
            'profesorid' => $currentUser['profesorid'],
            'asignaturaid' => $data['asignaturaid'],
            'tipodocumentoid' => $data['tipodocumentoid'],
            'semestreperiodoid' => $data['semestreperiodoid'],
            'seccionid' => $data['seccionid'],
            'nombrearchivo' => $file['name'],
            'url' => $publicUrl,
            'estado' => 'ENVIADO'
        ];

        // Remove keys with null values if necessary? Supabase handling of nulls depends on column definition.
        // Usually sending null is fine if the column is nullable.

        $result = $this->supabase->insert('documentosubido', $dbData);

        if (empty($result)) {
            throw new Exception("Error al registrar el documento en base de datos.");
        }

        return $result[0];
    }

    /**
     * Obtiene los documentos subidos por un profesor, enriquecidos con nombres de catálogos.
     */
    public function getDocumentsByProfesor($profesorId)
    {
        // 1. Obtener documentos del profesor
        $docs = $this->supabase->select('documentosubido', ['profesorid' => 'eq.' . $profesorId]);

        if (empty($docs)) {
            return [];
        }

        // 2. Obtener catálogos para enriquecer (Manual Join para evitar múltiples peticiones por fila)
        $cronogramas = $this->supabase->select('cronogramadocumento');
        $periodos = $this->supabase->select('periodo');
        $tipos = $this->supabase->select('tipodocumento');
        $asignaturas = $this->supabase->select('asignatura');
        $semestresP = $this->supabase->select('semestreperiodo');
        $semestres = $this->supabase->select('semestre');

        // Mapas para búsqueda rápida
        $cMap = array_column($cronogramas, null, 'cronogramaid');
        $pMap = array_column($periodos, null, 'periodoid');
        $tMap = array_column($tipos, null, 'tipodocumentoid');
        $aMap = array_column($asignaturas, null, 'asignaturaid');
        $spMap = array_column($semestresP, null, 'semestreperiodoid');
        $sMap = array_column($semestres, null, 'semestreid');

        return array_map(function ($doc) use ($cMap, $pMap, $tMap, $aMap, $spMap, $sMap) {
            $cronograma = $cMap[$doc['cronogramaid']] ?? null;
            $periodo = $cronograma ? ($pMap[$cronograma['periodoid']] ?? null) : null;
            $tipo = $tMap[$doc['tipodocumentoid']] ?? null;
            $asignatura = $aMap[$doc['asignaturaid']] ?? null;
            $sp = $spMap[$doc['semestreperiodoid']] ?? null;
            $semestre = $sp ? ($sMap[$sp['semestreid']] ?? null) : null;

            return [
                'id' => $doc['documentoid'],
                'fecha' => date('Y-m-d', strtotime($doc['fechasubida'])),
                'periodo' => $periodo ? $periodo['nombre'] : 'Desconocido',
                'nivel' => $semestre ? ($semestre['nombre'] ?? $semestre['codigo']) : 'N/A',
                'asignatura' => $asignatura ? $asignatura['nombre'] : 'Desconocida',
                'formato' => $tipo ? $tipo['nombre'] : 'Desconocido',
                'archivo' => $doc['nombrearchivo'],
                'url' => $doc['url'],
                'estado' => $doc['estado']
            ];
        }, $docs);
    }

    private function codeToMessage($code)
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                return "El archivo excede upload_max_filesize en php.ini";
            case UPLOAD_ERR_FORM_SIZE:
                return "El archivo excede MAX_FILE_SIZE en el formulario HTML";
            case UPLOAD_ERR_PARTIAL:
                return "El archivo fue subido parcialmente";
            case UPLOAD_ERR_NO_FILE:
                return "No se subió ningún archivo";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Falta la carpeta temporal";
            case UPLOAD_ERR_CANT_WRITE:
                return "No se pudo escribir en el disco";
            case UPLOAD_ERR_EXTENSION:
                return "Una extensión de PHP detuvo la subida del archivo";
            default:
                return "Error desconocido";
        }
    }

    /**
     * Verifica la estructura básica de un PDF (Header y Footer).
     * No garantiza que el contenido sea renderizable, pero detecta archivos truncados.
     */
    private function isValidPdfStructure($filePath)
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }

        // 1. Verificar Header (%PDF-)
        $header = fread($handle, 1024); // Leer primeros bytes
        // El header puede estar en los primeros 1024 bytes (generalmente al inicio)
        if (strpos($header, '%PDF-') === false) {
            fclose($handle);
            return false;
        }

        // 2. Verificar Trailer (%%EOF)
        // Ir al final del archivo y buscar %%EOF en los últimos bytes
        // PDF spec dice que %%EOF debe estar en las últimas líneas
        $stat = fstat($handle);
        $size = $stat['size'];

        // Si es muy pequeño, sospechoso
        if ($size < 10) {
            fclose($handle);
            return false;
        }

        $seekPos = max(0, $size - 1024); // Leer últimos 1KB
        fseek($handle, $seekPos);
        $trailer = fread($handle, 1024);
        fclose($handle);

        if (strpos($trailer, '%%EOF') === false) {
            return false;
        }

        return true;
    }
}
