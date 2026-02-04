# API - Sistema de Gestión de Tutorías Académicas 🎓

Este es el backend encargado de gestionar los procesos de tutoría, seguimiento académico y carga de documentación para el sistema de tutorías.

## 🚀 Tecnologías Utilizadas
- **Lenguaje:** PHP 8.x
- **Base de Datos & Auth:** [Supabase](https://supabase.com/) (PostgreSQL + GoTrue)
- **Autenticación:** JWT (JSON Web Tokens)
- **Almacenamiento:** Supabase Storage (para reportes PDF)

## ✨ Funcionalidades Principales
- **Autenticación Segura:** Manejo de sesiones y roles (Docente, Coordinador, Administrador).
- **Gestión de Calificaciones:** Registro y consulta de notas parciales.
- **Detección de Riesgo:** Identificación automática de estudiantes con bajo rendimiento para tutoría.
- **Módulo de Tutorías:** Asignación, seguimiento y registro de sesiones de refuerzo.
- **Gestión de Documentos:** Subida y visualización de reportes oficiales en formato PDF.
- **Cronograma:** Control de periodos académicos y tipos de documentos requeridos.

## 🛠️ Instalación y Configuración

Sigue estos pasos para poner en marcha el proyecto en tu entorno local:

### 1. Clonar el repositorio
```bash
git clone https://github.com/tu-usuario/tu-repo.git
cd tu-repo/backend
```

### 2. Configuración de Variables de Entorno (.env)
El sistema utiliza variables de entorno para conectarse a Supabase. Debes crear un archivo `.env` en la carpeta `backend`.

1. Copia el archivo de ejemplo:
   ```bash
   cp .env.example .env
   ```
2. Abre el archivo `.env` y completa la información con tus credenciales de Supabase:
   ```env
   SUPABASE_URL=https://tu-proyecto.supabase.co
   SUPABASE_SERVICE_KEY=tu-service-key-muy-larga-y-secreta
   ```

> [!IMPORTANT]
> Nunca subas el archivo `.env` a GitHub. Ya está incluido en el `.gitignore` para tu seguridad.

### 3. Requisitos del Sistema y Preparación de PHP

El sistema requiere **PHP >= 8.0**. Si no lo tienes instalado, sigue esta guía rápida usando [Scoop](https://scoop.sh/) (recomendado para Windows):

#### Instalación rápida con Scoop (PowerShell):
1. Abre PowerShell y ejecuta:
   ```powershell
   # Instalar Scoop si no lo tienes
   Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
   Invoke-RestMethod -Uri https://get.scoop.sh | Options
   
   # Instalar PHP
   scoop install php
   ```
2. Scoop agregará automáticamente PHP a tu **PATH**. Puedes verificarlo con `php -v`.

#### Configuración de PHP (php.ini):
Es obligatorio activar las siguientes extensiones para que la API funcione (CURL, MBSTRING y OPENSSL). 

1. Localiza tu archivo `php.ini` (usualmente en la carpeta donde se instaló PHP).
2. Busca las siguientes líneas y asegúrate de que **NO** tengan un punto y coma `;` al inicio:
   ```ini
   extension=curl
   extension=mbstring
   extension=openssl
   ```
   *Si la línea tiene un `;` al principio, elimínalo para activar la extensión.*

3. Guarda el archivo y reinicia tu servidor web o el comando de PHP.

### 4. Ejecución
Usa el servidor interno de PHP para pruebas rápidas:
```bash
php -S localhost:8000 -t backend/public
```

## 📖 Documentación de la API
La documentación detallada de cada endpoint, los métodos aceptados y ejemplos de respuesta se encuentran en:
👉 [api_documentation.md](file:///backend/api_documentation.md)

---
Desarrollado para la gestión académica de Tutorías 7mo Semestre.
