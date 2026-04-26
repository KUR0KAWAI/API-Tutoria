<?php

namespace App\Services;

use App\Config\SupabaseClient;
use App\Helpers\Response;

class CronogramaService
{
    private SupabaseClient $supabase;

    public function __construct()
    {
        $this->supabase = new SupabaseClient();
    }

    /**
     * Obtiene solo los periodos activos con ID y Nombre básicos
     */
    public function getPeriodosSimples(): array
    {
        try {
            return $this->supabase->select('periodo', [], 'periodoid,nombre');
        } catch (\Exception $e) {
            Response::error('Error al obtener periodos: ' . $e->getMessage(), 500);
            return [];
        }
    }

    // --- TIPO DOCUMENTO CRUD ---

    public function getTiposDocumento(): array
    {
        try {
            return $this->supabase->select('tipodocumento');
        } catch (\Exception $e) {
            Response::error('Error al obtener tipos de documento: ' . $e->getMessage(), 500);
            return [];
        }
    }

    public function createTipoDocumento(array $data)
    {
        try {
            return $this->supabase->insert('tipodocumento', $data);
        } catch (\Exception $e) {
            Response::error('Error al crear tipo de documento: ' . $e->getMessage(), 500);
        }
    }

    public function updateTipoDocumento(string $id, array $data)
    {
        try {
            return $this->supabase->update('tipodocumento', 'tipodocumentoid', $id, $data);
        } catch (\Exception $e) {
            Response::error('Error al actualizar tipo de documento: ' . $e->getMessage(), 500);
        }
    }

    public function deleteTipoDocumento(string $id)
    {
        try {
            return $this->supabase->delete('tipodocumento', 'tipodocumentoid', $id);
        } catch (\Exception $e) {
            Response::error('Error al eliminar tipo de documento: ' . $e->getMessage(), 500);
        }
    }

    // --- CRONOGRAMA CRUD ---

    public function getCronogramas(): array
    {
        try {
            $cronogramas = $this->supabase->select('cronogramadocumento');

            if (empty($cronogramas))
                return [];

            // Enriquecer datos (Periodo y Tipo Documento)
            $periodos = $this->supabase->select('periodo', [], 'periodoid,nombre');
            $tipos = $this->supabase->select('tipodocumento', [], 'tipodocumentoid,nombre');

            $periodoMap = array_column($periodos, 'nombre', 'periodoid');
            $tipoMap = array_column($tipos, 'nombre', 'tipodocumentoid');

            return array_map(function ($item) use ($periodoMap, $tipoMap) {
                $item['periodo_nombre'] = $periodoMap[$item['periodoid']] ?? 'Desconocido';
                $item['tipo_documento_nombre'] = $tipoMap[$item['tipodocumentoid']] ?? 'Desconocido';
                return $item;
            }, $cronogramas);

        } catch (\Exception $e) {
            Response::error('Error al obtener cronogramas: ' . $e->getMessage(), 500);
            return [];
        }
    }

    public function createCronograma(array $data)
    {
        try {
            return $this->supabase->insert('cronogramadocumento', $data);
        } catch (\Exception $e) {
            Response::error('Error al crear cronograma: ' . $e->getMessage(), 500);
        }
    }

    public function updateCronograma(string $id, array $data)
    {
        try {
            return $this->supabase->update('cronogramadocumento', 'cronogramaid', $id, $data);
        } catch (\Exception $e) {
            Response::error('Error al actualizar cronograma: ' . $e->getMessage(), 500);
        }
    }

    public function deleteCronograma(string $id)
    {
        try {
            return $this->supabase->delete('cronogramadocumento', 'cronogramaid', $id);
        } catch (\Exception $e) {
            Response::error('Error al eliminar cronograma: ' . $e->getMessage(), 500);
        }
    }
}
