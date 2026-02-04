<?php

namespace App\Services;

use App\Config\SupabaseClient;
use App\Helpers\Response;

class GestionUsuariosService
{
    private SupabaseClient $supabase;

    public function __construct()
    {
        $this->supabase = new SupabaseClient();
    }

    /**
     * Obtiene todos los roles disponibles
     * @return array Lista de roles con id y nombre
     */
    public function getRoles(): array
    {
        try {
            // Consulta a la tabla roluser
            $response = $this->supabase->select('roluser', [], 'rolid,nombre');

            if (empty($response)) {
                return [];
            }

            return $response;
        } catch (\Exception $e) {
            Response::error('Error al obtener roles: ' . $e->getMessage(), 500);
            return [];
        }
    }

    /**
     * Obtiene todos los docentes para gestión de usuarios
     * @return array Lista de docentes con id y nombre completo
     */
    public function getDocentesParaGestion(): array
    {
        try {
            // Consulta a la tabla profesor
            $response = $this->supabase->select('profesor', [], 'profesorid,nombre,apellidos');

            if (empty($response)) {
                return [];
            }

            // Formatear la respuesta para incluir nombre completo
            $docentes = array_map(function ($profesor) {
                return [
                    'profesorid' => $profesor['profesorid'],
                    'nombreCompleto' => trim($profesor['nombre'] . ' ' . $profesor['apellidos'])
                ];
            }, $response);

            return $docentes;
        } catch (\Exception $e) {
            Response::error('Error al obtener docentes: ' . $e->getMessage(), 500);
            return [];
        }
    }

    /**
     * Obtiene todos los usuarios del sistema
     */
    public function getUsuarios(): array
    {
        try {
            // 1. Obtener todos los logins
            $logins = $this->supabase->select('login', [], 'loginid,usuario,profesorid');

            if (empty($logins)) {
                return [];
            }

            $usuarios = [];

            foreach ($logins as $login) {
                $user = [
                    'loginid' => $login['loginid'],
                    'usuario' => $login['usuario'],
                    'profesorid' => $login['profesorid'],
                    'nombreCompleto' => 'N/A',
                    'rol' => 'N/A',
                    'rolid' => null
                ];

                // 2. Obtener datos del profesor
                if (!empty($login['profesorid'])) {
                    $profesorData = $this->supabase->select('profesor', ['profesorid' => 'eq.' . $login['profesorid']], 'nombre,apellidos');
                    if (!empty($profesorData)) {
                        $user['nombreCompleto'] = trim($profesorData[0]['nombre'] . ' ' . $profesorData[0]['apellidos']);
                    }

                    // 3. Obtener Rol del profesor
                    // Buscamos en profesorrol
                    $profesorRol = $this->supabase->select('profesorrol', ['profesorid' => 'eq.' . $login['profesorid']], 'rolid');

                    if (!empty($profesorRol)) {
                        $rolId = $profesorRol[0]['rolid'];
                        $user['rolid'] = $rolId;

                        // Buscamos nombre del rol
                        $rolData = $this->supabase->select('roluser', ['rolid' => 'eq.' . $rolId], 'nombre');
                        if (!empty($rolData)) {
                            $user['rol'] = $rolData[0]['nombre'];
                        }
                    }
                }

                $usuarios[] = $user;
            }

            return $usuarios;

        } catch (\Exception $e) {
            Response::error('Error al obtener usuarios: ' . $e->getMessage(), 500);
            return [];
        }
    }

    /**
     * Crea un nuevo usuario
     */
    public function createUsuario(array $data): array
    {
        // Validar datos mínimos
        if (empty($data['usuario']) || empty($data['password']) || empty($data['profesorid']) || empty($data['rolid'])) {
            Response::error('Faltan datos obligatorios', 400);
        }

        try {
            // 1. Verificar si el usuario ya existe
            $existing = $this->supabase->select('login', ['usuario' => 'eq.' . $data['usuario']]);
            if (!empty($existing)) {
                Response::error('El nombre de usuario ya existe', 400);
            }

            // 2. Verificar si el profesor ya tiene usuario (Opcional, pero recomendado 1 a 1)
            $existingProf = $this->supabase->select('login', ['profesorid' => 'eq.' . $data['profesorid']]);
            if (!empty($existingProf)) {
                Response::error('El profesor seleccionado ya tiene un usuario asignado', 400);
            }

            // 3. Crear hash de contraseña
            $passwordHash = \App\Helpers\PasswordHelper::hashPassword($data['password']);

            // 4. Insertar en Login
            $loginData = [
                'usuario' => $data['usuario'],
                'passwordhash' => $passwordHash,
                'profesorid' => $data['profesorid']
            ];

            $newLogin = $this->supabase->insert('login', $loginData);
            if (empty($newLogin)) {
                throw new \Exception('No se pudo crear el usuario en base de datos');
            }

            // 5. Asignar Rol en profesorrol
            // Primero verificamos si ya tiene rol (aunque sea nuevo login, el profe existe)
            $existingRole = $this->supabase->select('profesorrol', ['profesorid' => 'eq.' . $data['profesorid']]);

            if (empty($existingRole)) {
                $this->supabase->insert('profesorrol', [
                    'profesorid' => $data['profesorid'],
                    'rolid' => $data['rolid']
                ]);
            } else {
                // Si ya tiene rol, lo actualizamos al nuevo rol seleccionado
                // Usamos profesorid como clave para actualizar
                $this->supabase->update('profesorrol', 'profesorid', $data['profesorid'], [
                    'rolid' => $data['rolid']
                ]);
            }

            return ['message' => 'Usuario creado correctamente', 'loginid' => $newLogin[0]['loginid'] ?? null];

        } catch (\Exception $e) {
            Response::error('Error al crear usuario: ' . $e->getMessage(), 500);
            return [];
        }
    }

    /**
     * Actualiza solo el nombre de usuario
     */
    public function updateUsuario(string $id, array $data): array
    {
        if (empty($data['usuario'])) {
            Response::error('El nombre de usuario es obligatorio', 400);
        }

        try {
            // Verificar duplicados (excluyendo el actual)
            // La API simple select no permite "neq". 
            // Hacemos select y filtramos en PHP
            $existing = $this->supabase->select('login', ['usuario' => 'eq.' . $data['usuario']]);
            foreach ($existing as $ex) {
                if ($ex['loginid'] != $id) {
                    Response::error('El nombre de usuario ya está en uso', 400);
                }
            }

            // Actualizar Usuario (Login)
            $updateData = ['usuario' => $data['usuario']];

            // NO actualizamos contraseña según requerimiento
            // if (!empty($data['password'])) { ... }

            $this->supabase->update('login', 'loginid', $id, $updateData);

            // Actualizar Rol
            // 1. Obtener profesorid para saber a quién actualizar el rol
            // Podríamos haberlo traído en $existing, pero $existing es una lista y ya iteramos.
            // Hacemos consulta directa por ID para asegurar.
            $loginRecord = $this->supabase->select('login', ['loginid' => 'eq.' . $id], 'profesorid');

            if (!empty($loginRecord) && !empty($data['rolid'])) {
                $profesorId = $loginRecord[0]['profesorid'];

                // Actualizar o Insertar Rol
                $existingRole = $this->supabase->select('profesorrol', ['profesorid' => 'eq.' . $profesorId]);

                if (empty($existingRole)) {
                    $this->supabase->insert('profesorrol', [
                        'profesorid' => $profesorId,
                        'rolid' => $data['rolid']
                    ]);
                } else {
                    $this->supabase->update('profesorrol', 'profesorid', $profesorId, [
                        'rolid' => $data['rolid']
                    ]);
                }
            }

            return ['message' => 'Usuario y rol actualizados correctamente'];

        } catch (\Exception $e) {
            Response::error('Error al actualizar usuario: ' . $e->getMessage(), 500);
            return [];
        }
    }

    /**
     * Eliminar usuario
     */

    /**
     * Eliminar usuario y su rol asociado
     */
    public function deleteUsuario(string $id): array
    {
        try {
            // 1. Obtener datos antes de borrar para saber el profesorid
            $loginData = $this->supabase->select('login', ['loginid' => 'eq.' . $id], 'profesorid');

            if (empty($loginData)) {
                Response::error('Usuario no encontrado', 404);
            }

            $profesorId = $loginData[0]['profesorid'];

            // 2. Eliminar rol asociado en profesorrol
            if ($profesorId) {
                // Asumimos que puedo borrar por 'profesorid'
                $this->supabase->delete('profesorrol', 'profesorid', $profesorId);
            }

            // 3. Eliminar Login
            $this->supabase->delete('login', 'loginid', $id);

            return ['message' => 'Usuario y rol eliminados correctamente'];

        } catch (\Exception $e) {
            Response::error('Error al eliminar usuario: ' . $e->getMessage(), 500);
            return [];
        }
    }
}
