<?php

namespace App\Services;

use App\Config\SupabaseClient;
use App\Helpers\PasswordHelper;
use Exception;

class AdminService
{
    /*
     * CREDENCIALES ADMIN GENERADAS:
     * Usuario: 123456789-ADMR
     * Clave:   12345
     */
    private SupabaseClient $supabase;

    public function __construct()
    {
        $this->supabase = new SupabaseClient();
    }

    public function initializeAdmin(): array
    {
        $status = [];

        // 1. Verificar/Crear Profesor
        // Buscamos por correo institucional que es único y más seguro
        $emailAdmin = 'admin@utb.edu.ec';
        $profesores = $this->supabase->select('profesor', [
            'correoinstitucional' => 'eq.' . $emailAdmin
        ]);

        $profesorId = null;

        if (!empty($profesores)) {
            // Arrays from Supabase/Postgres are lowercase keys usually
            $profesorId = $profesores[0]['profesorid'] ?? $profesores[0]['id'] ?? $profesores[0]['profesor_id'] ?? null;
            $status['Profesor'] = 'Ya existe (Encontrado por email)';
        } else {
            // Insertar
            try {
                $inserted = $this->supabase->insert('profesor', [
                    'nombre' => 'Admin',
                    'apellidos' => 'General',
                    'correoinstitucional' => $emailAdmin
                ]);

                if (!empty($inserted)) {
                    $profesorId = $inserted[0]['profesorid'] ?? $inserted[0]['id'] ?? $inserted[0]['profesor_id'] ?? null;
                    $status['Profesor'] = 'Creado';
                } else {
                    throw new Exception("Error al crear Profesor Admin (Respuesta vacía)");
                }
            } catch (Exception $e) {
                // Capturar el error específico de llave duplicada para dar mejor feedback
                if (strpos($e->getMessage(), 'checks') !== false || strpos($e->getMessage(), 'duplicate key') !== false || strpos($e->getMessage(), 'pkey') !== false) {
                    throw new Exception("Error de consistencia en BD: " . $e->getMessage() . ". POSIBLE CUASA: La secuencia de IDs (serial) no está sincronizada con los registros existentes.");
                }
                throw $e;
            }
        }

        if (!$profesorId) {
            throw new Exception("No se pudo obtener el ID del Profesor");
        }

        // 2. Verificar/Crear Login
        $usuario = '123456789-ADMR';
        $logins = $this->supabase->select('login', ['usuario' => 'eq.' . $usuario]);

        if (!empty($logins)) {
            $status['Login'] = 'Ya existe';
        } else {
            $passwordRaw = '12345';
            $passwordHash = PasswordHelper::hashPassword($passwordRaw);

            $this->supabase->insert('login', [
                'usuario' => $usuario,
                'passwordhash' => $passwordHash, // Assuming passwordhash
                'profesorid' => $profesorId
            ]);
            $status['Login'] = 'Creado';
        }

        // 3. Obtener Rol ADMIN
        // Table likely rol_user or roluser. Trying roluser based on "RolUser"
        $roles = $this->supabase->select('roluser', ['nombre' => 'eq.ADMIN']);
        if (empty($roles)) {
            // Fallback try rol_user if roluser fails? Can't easy try/catch here. 
            // Let's assume roluser if user said RolUser.
            // or maybe rol? User said RolUser table.
            throw new Exception("El rol 'ADMIN' no existe en la tabla roluser");
        }
        $rolId = $roles[0]['rolid'] ?? $roles[0]['rol_id'] ?? $roles[0]['id'];

        // 4. Verificar/Crear ProfesorRol
        $profesorRoles = $this->supabase->select('profesorrol', [
            'profesorid' => 'eq.' . $profesorId,
            'rolid' => 'eq.' . $rolId
        ]);

        if (!empty($profesorRoles)) {
            $status['ProfesorRol'] = 'Ya existe';
        } else {
            $this->supabase->insert('profesorrol', [
                'profesorid' => $profesorId,
                'rolid' => $rolId
            ]);
            $status['ProfesorRol'] = 'Creado';
        }

        return $status;
    }
}
