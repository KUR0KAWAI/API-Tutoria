<?php

namespace App\Services;

use App\Config\SupabaseClient;
use App\Helpers\PasswordHelper;
use App\Helpers\Response;
use Exception;

class AuthService
{
    private SupabaseClient $supabase;

    public function __construct()
    {
        $this->supabase = new SupabaseClient();
    }

    /**
     * Authenticates a user.
     *
     * @param string $usuario
     * @param string $password
     * @return array User data including token
     * @throws Exception
     */
    public function login(string $usuario, string $password): array
    {
        // 1. Buscar usuario en tabla Login
        $users = $this->supabase->select('login', ['usuario' => 'eq.' . $usuario]);

        if (empty($users)) {
            throw new Exception('Credenciales inválidas');
        }

        $user = $users[0];

        // 2. Verificar contraseña
        if (!isset($user['passwordhash'])) {
            throw new Exception('Error en datos de usuario');
        }

        if (!PasswordHelper::verifyPassword($password, $user['passwordhash'])) {
            throw new Exception('Credenciales inválidas');
        }

        // 3. Generar Token
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = PasswordHelper::hashToken($rawToken);

        // 4. Insertar en LoginToken
        $tokenData = [
            'loginid' => $user['loginid'] ?? $user['id'] ?? null,
            'tokenhash' => $hashedToken,
            'fechacreacion' => date('Y-m-d H:i:s'),
            'fechaexpiracion' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'esrevocado' => false, // JSON boolean 'false' vs string 'false', Supabase handles
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        // Insert devuelve un array de filas
        $insertedToken = $this->supabase->insert('logintoken', $tokenData);

        if (empty($insertedToken)) {
            throw new Exception('Error al generar sesión');
        }

        $loginTokenId = $insertedToken[0]['logintokenid'] ?? $insertedToken[0]['id'];

        // 5. Preparar respuesta y enriquecer con datos del profesor
        unset($user['passwordhash']);
        $user = $this->enrichWithProfesor($user);

        return [
            'user' => $user,
            'token' => $loginTokenId . '|' . $rawToken
        ];
    }

    /**
     * Validates a bearer token.
     * 
     * @param string $bearerToken Format: "Bearer ID|RAW_TOKEN"
     * @return array|null User data if valid, null otherwise.
     */
    public function validateToken(string $bearerToken): ?array
    {
        if (strpos($bearerToken, 'Bearer ') !== 0) {
            return null;
        }

        $tokenString = substr($bearerToken, 7);
        $parts = explode('|', $tokenString);

        if (count($parts) !== 2) {
            return null;
        }

        [$loginTokenId, $rawToken] = $parts;

        // 1. Buscar LoginToken
        $tokens = $this->supabase->select('logintoken', ['logintokenid' => 'eq.' . $loginTokenId]);

        if (empty($tokens)) {
            return null;
        }

        $tokenRecord = $tokens[0];

        // 2. Validaciones
        $esRevocado = $tokenRecord['esrevocado'] ?? false;

        // Supabase REST devuelve booleanos JSON reales
        if ($esRevocado === true || $esRevocado === 'true') {
            return null;
        }

        if (strtotime($tokenRecord['fechaexpiracion']) < time()) {
            return null;
        }

        if (!PasswordHelper::verifyPassword($rawToken, $tokenRecord['tokenhash'])) {
            return null;
        }

        // 3. Obtener Usuario asociado
        $users = $this->supabase->select('login', ['loginid' => 'eq.' . $tokenRecord['loginid']]);

        if (empty($users)) {
            return null;
        }

        $user = $users[0];
        unset($user['passwordhash']);

        return $this->enrichWithProfesor($user);
    }

    /**
     * Enriches the user object with professor details (Name, Lastname) and Role.
     */
    private function enrichWithProfesor(array $user): array
    {
        $profesorId = $user['profesorid'] ?? null;
        if ($profesorId) {
            // 1. Datos básicos del profesor
            $profesores = $this->supabase->select('profesor', ['profesorid' => 'eq.' . $profesorId]);
            if (!empty($profesores)) {
                $profesor = $profesores[0];
                $user['nombre'] = $profesor['nombre'] ?? '';
                $user['apellidos'] = $profesor['apellidos'] ?? '';
                $user['correoinstitucional'] = $profesor['correoinstitucional'] ?? '';
            }

            // 2. Obtener Roles (Soporta múltiples roles)
            $user['roles'] = [];
            $profesorRoles = $this->supabase->select('profesorrol', ['profesorid' => 'eq.' . $profesorId]);

            foreach ($profesorRoles as $pr) {
                $roleData = $this->supabase->select('roluser', ['rolid' => 'eq.' . $pr['rolid']]);
                if (!empty($roleData)) {
                    $user['roles'][] = $roleData[0]['nombre'];
                }
            }

            // Mantener 'rol' como el primero para compatibilidad simple
            $user['rol'] = !empty($user['roles']) ? $user['roles'][0] : null;
        }
        return $user;
    }
}
