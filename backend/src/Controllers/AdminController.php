<?php

namespace App\Controllers;

use App\Services\AdminService;
use App\Helpers\Response;
use Exception;

class AdminController
{
    private AdminService $adminService;

    public function __construct()
    {
        $this->adminService = new AdminService();
    }

    public function init()
    {
        try {
            $result = $this->adminService->initializeAdmin();
            Response::json([
                'message' => 'Proceso de inicialización de administrador completado',
                'details' => $result
            ]);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
