<?php

namespace App\Config;

use App\Config\Env;
use Exception;

class SupabaseClient
{
    private string $url;
    private string $key;

    public function __construct()
    {
        // Asegurar que enviroment esté cargado
        Env::load(__DIR__ . '/../../.env');

        $this->url = getenv('SUPABASE_URL');
        $this->key = getenv('SUPABASE_SERVICE_KEY');

        if (!$this->url || !$this->key) {
            throw new Exception("Faltan variables de entorno SUPABASE_URL o SUPABASE_SERVICE_KEY");
        }
    }

    /**
     * Realiza una petición SELECT a la tabla especificada.
     *
     * @param string $table Nombre de la tabla
     * @param array $filters Filtros asociativos (ej: ['col' => 'eq.val'])
     * @param string $columns Columnas a seleccionar (default '*')
     * @return array Array de resultados
     */
    public function select(string $table, array $filters = [], string $columns = '*'): array
    {
        $queryParams = ['select' => $columns];
        foreach ($filters as $col => $opVal) {
            $queryParams[$col] = $opVal;
        }

        $queryString = http_build_query($queryParams);
        $endpoint = $this->url . '/rest/v1/' . $table . '?' . $queryString;

        return $this->request('GET', $endpoint);
    }

    /**
     * Realiza una petición INSERT a la tabla especificada.
     *
     * @param string $table Nombre de la tabla
     * @param array $data Datos a insertar
     * @return array El registro insertado (si exitoso)
     */
    public function insert(string $table, array $data): array
    {
        $endpoint = $this->url . '/rest/v1/' . $table;
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Realiza una petición UPDATE a la tabla especificada.
     *
     * @param string $table Nombre de la tabla
     * @param string $idColumn Nombre de la columna ID
     * @param mixed $idValue Valor del ID
     * @param array $data Datos a actualizar
     * @return array Registros actualizados
     */
    public function update(string $table, string $idColumn, $idValue, array $data): array
    {
        // endpoint format: /rest/v1/table?id=eq.value
        $endpoint = $this->url . '/rest/v1/' . $table . '?' . $idColumn . '=eq.' . urlencode($idValue);
        return $this->request('PATCH', $endpoint, $data);
    }

    /**
     * Realiza una petición DELETE a la tabla especificada.
     * 
     * @param string $table Nombre de la tabla
     * @param string $idColumn Nombre de la columna ID para filtrar
     * @param mixed $idValue Valor del ID
     * @return array
     */
    public function delete(string $table, string $idColumn, $idValue): array
    {
        $endpoint = $this->url . '/rest/v1/' . $table . '?' . $idColumn . '=eq.' . urlencode($idValue);
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Sube un archivo al Storage de Supabase.
     *
     * @param string $bucket Nombre del bucket
     * @param string $path Ruta/Nombre del archivo en el bucket
     * @param string $content Contenido binario del archivo
     * @param string $mimeType Tipo mime del archivo
     * @return string URL pública del archivo (o key, según necesidad)
     * @throws Exception
     */
    public function uploadFile(string $bucket, string $path, string $content, string $mimeType): string
    {
        $url = $this->url . '/storage/v1/object/' . $bucket . '/' . $path;

        $ch = \curl_init();

        $headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: ' . $mimeType,
            'x-upsert: true' // Sobrescribir si existe
        ];

        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_POST, true);
        \curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = \curl_error($ch);

        \curl_close($ch);

        if ($error) {
            throw new Exception("Error cURL Supabase Storage: " . $error);
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $msg = isset($decoded['message']) ? $decoded['message'] : 'Error en Supabase Storage (' . $httpCode . ')';
            throw new Exception("Supabase Storage Error: " . $msg);
        }

        // Retornar la URL pública asumiendo que el bucket es público
        // La URL format es: PROJECT_URL/storage/v1/object/public/BUCKET/PATH
        return $this->url . '/storage/v1/object/public/' . $bucket . '/' . $path;
    }

    private function request(string $method, string $url, array $data = []): array
    {
        $ch = \curl_init();

        $headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Prefer: return=representation' // Para que devuelva los datos insertados
        ];

        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix para error SSL local en Windows

        if ($method === 'POST') {
            \curl_setopt($ch, CURLOPT_POST, true);
            \curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PATCH') {
            \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            \curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = \curl_error($ch);

        \curl_close($ch);

        if ($error) {
            throw new Exception("Error cURL Supabase: " . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = isset($decoded['message']) ? $decoded['message'] : 'Error desconocido en Supabase (' . $httpCode . ')';
            throw new Exception("Supabase Error: " . $msg);
        }

        return $decoded; // Array de resultados
    }
}
