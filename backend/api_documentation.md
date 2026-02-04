# Documentación API - Sistema de Tutoría

## Introducción
Esta API sirve como backend para el sistema de Notas Parciales y Gestión de Usuarios.

---

## Autenticación
Todas las peticiones a los módulos transaccionales (excepto Login) deben incluir un header de autorización:

- **Header**: `Authorization`
- **Formato**: `Bearer {tu_token}`
- **Error si falta**: `401 Unauthorized` {"message": "Token no proporcionado"}

---

## Módulo: Login (Público)

### 1. Iniciar Sesión
- **Ruta**: `POST /api/login`
- **Descripción**: Valida las credenciales del usuario y devuelve un token de acceso.
- **Valores que recibe (JSON)**:
  ```json
  {
    "usuario": "tu_usuario",
    "password": "tu_password"
  }
  ```
- **Respuesta (Éxito)**:
  ```json
  {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "loginid": 1,
      "usuario": "jdoe",
      "nombreCompleto": "John Doe",
      "rol": "DOCENTE"
    }
  }
  ```

### 2. Validar Sesión
- **Ruta**: `GET /api/auth/validate`
- **Descripción**: Verifica si el token actual es válido y devuelve los datos del usuario. Ideal para llamar al cargar la aplicación.
- **Requisito**: Header `Authorization: Bearer {tu_token}`.
- **Respuesta (Éxito)**:
  ```json
  {
    "message": "Sesión válida",
    "user": {
      "loginid": 1,
      "usuario": "jdoe",
      "nombre": "John",
      "apellidos": "Doe",
      "rol": "DOCENTE",
      "roles": ["DOCENTE"]
    }
  }
  ```
- **Respuesta (Error - Token Expirado/Invalido)**: `401 Unauthorized`

---

## Módulo: Selección de Calificaciones (Notas Parciales)

Estos endpoints se utilizan para poblar los selectores 'en cascada' (Periodo -> Nivel -> Asignatura -> Docente) y obtener listados auxiliares.

### 1. Obtener Datos Iniciales (Recomendado)
- **Ruta**: `GET /api/periodos`
- **Descripción**: Endpoint optimizado que devuelve en una sola llamada los Periodos vigentes, Niveles asociados, catálogo de Secciones y listado de Alumnos.
- **Respuesta**:
  ```json
  [
    {
      "semestreperiodoid": 65,
      "periodoid": 1,
      "periodo_nombre": "Octubre 2025 - Marzo 2026",
      "semestreid": 1,
      "nivel": "Nivel 1"
    }
  ]
  ```

### 2. Obtener Asignaturas
- **Ruta**: `GET /api/asignaturas`
- **Parámetros Query**:
  - `semestrePeriodoId`: (Obligatorio) ID que vincula periodo y nivel.
  - `seccionId`: (Opcional) Filtra asignaturas disponibles para esa sección.
- **Respuesta**: Lista de asignaturas.

### 3. Obtener Docentes
- **Ruta**: `GET /api/docentes`
- **Parámetros Query**:
  - `semestrePeriodoId`, `seccionId`, `asignaturaId`.
- **Respuesta**: Lista de docentes asignados.

### 4. Catálogos Auxiliares
- **Secciones**: `GET /api/secciones`
- **Estudiantes**: `GET /api/alumnos`
- **Niveles**: `GET /api/niveles` (Nota: Preferible usar `/api/periodos`)

---

## Módulo: Gestión de Notas (CRUD)

Estos endpoints permiten listar, guardar, editar y eliminar las notas parciales de los estudiantes.

### 1. Listar Mis Notas
- **Ruta**: `GET /api/nota-parcial`
- **Parámetros**: `periodoId` (Opcional, filtra por periodo).
- **Descripción**: Devuelve las notas registradas por el docente autenticado.

### 2. Guardar Nueva Nota
- **Ruta**: `POST /api/nota-parcial`
- **Payload**:
  ```json
  {
    "semestreperiodoid": 65,
    "seccionid": 1,
    "asignaturaid": 1,
    "profesorid": 1,
    "alumnoid": 1,
    "notap1": 9.5,
    "notap2": 8.0,
    "fecha": "2025-12-28"
  }
  ```

### 3. Actualizar Nota
- **Ruta**: `PUT /api/nota-parcial/{notaid}`
- **Payload**: Mismo formato que Guardar.

### 4. Eliminar Nota
- **Ruta**: `DELETE /api/nota-parcial/{notaid}`

---

## Módulo: Gestión de Usuarios (CRUD)

Endpoints para la administración de usuarios, roles y accesos al sistema.

### 1. Catálogos de Gestión
#### Obtener Roles
- **Ruta**: `GET /api/gestion-usuarios/roles`
- **Descripción**: Lista roles disponibles (Ej: DOCENTE, COORDINADOR, ADMIN).
- **Respuesta**: `[{ "rolid": 1, "nombre": "DOCENTE" }, ...]`

#### Obtener Docentes (Para asociar usuario)
- **Ruta**: `GET /api/gestion-usuarios/docentes`
- **Descripción**: Lista docentes con su ID y nombre completo.
- **Respuesta**: `[{ "profesorid": 1, "nombreCompleto": "Juan Perez" }, ...]`

### 2. CRUD Usuarios

#### Listar Usuarios
- **Ruta**: `GET /api/gestion-usuarios/usuarios`
- **Descripción**: Lista todos los usuarios con su rol actual y nombre de profesor asociado.
- **Respuesta**:
  ```json
  [
    {
      "loginid": 1,
      "usuario": "jdoe",
      "profesorid": 1,
      "nombreCompleto": "John Doe",
      "rol": "DOCENTE",
      "rolid": 1
    }
  ]
  ```

#### Crear Usuario
- **Ruta**: `POST /api/gestion-usuarios/usuarios`
- **Descripción**: Crea un login y asigna/actualiza el rol del profesor en `profesorrol`.
- **Payload**:
  ```json
  {
    "usuario": "nuevo.usuario",
    "password": "Password123",
    "profesorid": 5,
    "rolid": 2
  }
  ```

#### Actualizar Usuario
- **Ruta**: `PUT /api/gestion-usuarios/usuarios/{id}`
- **Descripción**: Actualiza nombre de usuario y Rol. **Ignora cambios de contraseña**.
- **Payload**:
  ```json
  {
    "usuario": "usuario.editado",
    "rolid": 3
  }
  ```
- **Nota**: El campo `password` es ignorado si se envía.

#### Eliminar Usuario
- **Ruta**: `DELETE /api/gestion-usuarios/usuarios/{id}`
- **Descripción**: Elimina el usuario (tabla `login`) y remueve el rol asociado al profesor (tabla `profesorrol`).

---

## Módulo: Cronograma y Tipo de Documento

Endpoints para la gestión del calendario de tutorías y los tipos de documentos requeridos.

---

### 1. Obtener Periodos (Simplificado)
- **Ruta**: `GET /api/cronograma/periodos`
- **Descripción**: Obtiene únicamente `periodoid` y `nombre` de los periodos que tienen el estado 'Activo'. Ideal para selectores rápidos.
- **Valores que recibe**: Ninguno (Requiere Token de autorización).
- **Respuesta**:
  ```json
  [
    {
      "periodoid": 1,
      "nombre": "PAO I 2024"
    }
  ]
  ```

---

### 2. Gestión de Tipos de Documento

#### Listar Tipos de Documento
- **Ruta**: `GET /api/cronograma/tipo-documento`
- **Descripción**: Obtiene todos los registros de la tabla `tipodocumento`.
- **Valores que recibe**: Ninguno.
- **Respuesta**:
  ```json
  [
    {
      "tipodocumentoid": 1,
      "nombre": "Diagnóstico",
      "descripcion": "Informe prueba diagnóstica",
      "estado": "Activo"
    }
  ]
  ```

#### Crear Tipo de Documento
- **Ruta**: `POST /api/cronograma/tipo-documento`
- **Descripción**: Registra un nuevo tipo de documento.
- **Valores que recibe (JSON)**:
  ```json
  {
    "nombre": "Nuevo Documento",
    "descripcion": "Descripción opcional",
    "estado": "Activo"
  }
  ```
- **Respuesta**: El registro creado.

#### Editar Tipo de Documento
- **Ruta**: `PUT /api/cronograma/tipo-documento/{id}`
- **Descripción**: Actualiza los datos de un tipo de documento existente.
- **Valores que recibe (JSON)**:
  ```json
  {
    "nombre": "Documento Actualizado",
    "descripcion": "Nueva descripción",
    "estado": "Inactivo"
  }
  ```
- **Respuesta**: Mensaje de éxito o registro actualizado.

#### Eliminar Tipo de Documento
- **Ruta**: `DELETE /api/cronograma/tipo-documento/{id}`
- **Descripción**: Elimina el registro por su ID.
- **Valores que recibe**: ID en la URL.
- **Respuesta**: Mensaje de confirmación.

---

## Módulo: Asignar Tutorías

Este módulo gestiona la identificación de estudiantes en riesgo y el registro formal de tutorías.

### Proceso de Registro
El registro de una tutoría involucra dos tablas principales:
1. **`estadotutoria`**: Almacena los estados posibles (ej: Pendiente, En curso, Realizada, Incompleta).
2. **`tutoria`**: Almacena el registro de la sesión con sus objetivos y observaciones.

**Estados de Tutoría:**
- **General**: Pendiente (por defecto al crear), En curso, Realizada, Incompleta.
- **Detalle**: Pendiente, Realizada, Inasistencia.

### 1. Obtener Candidatos (Estudiantes en Riesgo)
- **Ruta**: `GET /api/tutorias/candidatos`
- **Parámetros Query**:
  - `semestrePeriodoId`: (Obligatorio) ID que vincula periodo y nivel.
- **Descripción**: Devuelve la lista de alumnos que tienen una nota parcial (notap1) menor a 7.0 para el periodo/nivel seleccionado, junto con la asignatura, el profesor y la sección asociada. **Solo devuelve estudiantes que aún no tienen una tutoría asignada en este periodo.**
- **Respuesta**:
  ```json
  [
    {
      "notaid": 1,
      "alumnoid": 1,
      "alumno_nombre": "Juan Perez",
      "asignaturaid": 1,
      "asignatura_nombre": "Matemáticas",
      "notap1": 6.5,
      "profesorid": 1,
      "profesor_nombre": "Ing. Maria Garcia",
      "seccionid": 1,
      "seccion_nombre": "Mañana"
    }
  ]
  ```

### 2. Obtener Historial de Tutorías
- **Ruta**: `GET /api/tutorias/historial`
- **Descripción**: Lista todas las tutorías que han sido asignadas.
- **Respuesta**:
  ```json
  [
     {
       "tutoriaid": 1,
       "alumnoid": 1,
       "alumno_nombre": "...",
       "profesorid": 1,
       "profesor_nombre": "...",
       "asignaturaid": 1,
       "asignatura_nombre": "...",
       "seccionid": 1,
       "seccion_nombre": "...",
       "notaid": 123,
       "estadotutoriaid": 1,
       "estado_nombre": "Pendiente",
       "objetivotutoria": "Por definir", 
       "fechatutoria": "2024-01-04T12:00:00Z",
       "observaciones": "..."
     }
  ]
  ```

> [!NOTE]
> El campo `objetivotutoria` en el historial se entrega con el valor "Por definir" si no ha sido completado aún.

### 3. Asignar Tutoría
- **Ruta**: `POST /api/tutorias`
- **Descripción**: Registra una nueva asignación de tutoría.
- **Payload**:
  ```json
   {
     "alumnoid": 1,
     "profesorid": 1,
     "asignaturaid": 1,
     "seccionid": 1,
     "notaid": 123,
     "fecha": "2024-01-04"
   }
   ```
- **Nota**: El sistema mapea `fecha` a `fechatutoria` automáticamente. El campo `notaid` vincula la tutoría con la nota específica.
  ```
- **Respuesta**: El registro creado.

### 4. Acciones de Historial
- **Actualizar**: `PUT /api/tutorias/{id}`
- **Eliminar**: `DELETE /api/tutorias/{id}`
  - **Descripción**: Elimina la tutoría principal y todos sus registros en `tutoria_detalle`. Además, genera una notificación automática para el docente asociado.

---

## Módulo: Notificaciones (Propuesto)

Para avisar a los docentes, se recomienda la siguiente estructura de tabla:

```sql
create table notificacion (
  notificacionid serial primary key,
  usuarioid int not null, 
  mensaje varchar(500) not null,
  tipo varchar(50),
  fechanotificacion timestamptz default now(),
  leida boolean default false
);
```

---

## Módulo: Reportes Tutoria

Endpoints específicos para la generación de reportes de tutoría.

### 1. Obtener Asignaturas del Docente (Logeado)
- **Ruta**: `GET /api/reportes-tutoria/asignaturas`
- **Parámetros Query**:
  - `semestreperiodoid`: (Obligatorio) ID que vincula periodo y nivel.
  - `profesorid`: (Obligatorio) ID del profesor.
- **Descripción**: Devuelve las asignaturas que dicta el docente indicado en el nivel seleccionado.
- **Respuesta**:
  ```json
  [
    {
      "asignaturaid": 1,
      "codigo": "SIS-101",
      "nombre": "Introducción a la Programación",
      "creditos": 3,
      "seccionid": 1,
      "seccion_nombre": "Matutina"
    },
    {
      "asignaturaid": 2,
      "codigo": "SIS-102",
      "nombre": "Matemática Discreta",
      "creditos": 4,
      "seccionid": 2,
      "seccion_nombre": "Vespertina"
    }
  ]
  ```

### 2. Obtener Formatos de Tutoría
- **Ruta**: `GET /api/reportes-tutoria/formatos`
- **Descripción**: Obtiene la lista de formatos de tutoría disponibles (ej: Diagnóstico, Control mensual, etc.) para poblar el campo de selección.
- **Respuesta**:
  ```json
  [
    {
      "formatoid": 1,
      "nombre": "Diagnóstico",
      "estado": "Activo"
    },
    ...
  ]
  ```

### 3. Obtener Tipos de Documento
- **Ruta**: `GET /api/reportes-tutoria/tipos-documento`
- **Descripción**: Obtiene la lista de tipos de documento disponibles (tabla `tipodocumento`).
- **Respuesta**:
  ```json
  [
    {
      "tipodocumentoid": 1,
      "nombre": "Diagnóstico",
      "descripcion": "Informe prueba diagnóstica",
      "estado": "Activo"
    },
    ...
  ]
  ```

### 4. Obtener Estudiantes en Riesgo
- **Ruta**: `GET /api/reportes-tutoria/estudiantes-riesgo`
- **Parámetros Query**:
  - `semestreperiodoid`: (Obligatorio) ID del periodo/nivel.
  - `profesorid`: (Obligatorio) ID del profesor.
- **Descripción**: Obtiene la lista de estudiantes con notas menores a 7.0 para las asignaturas del docente en el periodo indicado.
- **Respuesta**:
  ```json
  [
    {
      "notaid": 1,
      "alumnoid": 10,
      "alumno_nombre": "Juan Perez",
      "asignaturaid": 2,
      "asignatura_nombre": "Matemáticas",
      "asignatura_codigo": "MAT-101",
      "seccionid": 1,
      "seccion_nombre": "Matutina",
      "notap1": 5.5,
      "fecha": "2025-01-01...",
      "tutoriaid": 101,
      "objetivotutoria": "Refuerzo",
      "tutorias_requeridas": 2
    }
  ]
  ```

### 5. Registrar Tutoría (Actualización)
- **Ruta**: `POST /api/reportes-tutoria/registrar`
- **Descripción**: Actualiza un registro de tutoría existente con los detalles del reporte.
- **Payload (JSON)**:
  ```json
  {
    "tutoriaid": 101,
    "objetivotutoria": "Refuerzo académico",
    "tutorias_requeridas": 3
  }
  ```

### 6. Gestión de Detalles de Tutoría (CRUD)

Estos endpoints permiten administrar los registros individuales de cada sesión de tutoría.

#### 6.1 Catálogo de Estados
- **Ruta**: `GET /api/tutoria-detalle/estados`
- **Descripción**: Obtiene todos los estados posibles para una tutoría.
- **Respuesta**:
  ```json
  [
    {
      "estadotutoriaid": 1,
      "nombre": "Pendiente",
      "descripcion": "Creada o programada, aún no realizada"
    },
    {
       "estadotutoriaid": 5, "nombre": "Incompleta", "descripcion": "..."
    }
  ]
  ```

#### 6.2 Listar Detalles de una Tutoría
- **Ruta**: `GET /api/tutoria-detalle`
- **Parámetros Query**: `tutoriaid` (Obligatorio)
- **Descripción**: Obtiene los detalles de la tutoría. Si un registro está "Pendiente" y la fecha de la tutoría ya pasó, el sistema lo actualiza automáticamente a "Incompleta".
- **Respuesta**:
  ```json
  [
    {
      "tutoriadetalleid": 1,
      "tutoriaid": 101,
      "fechatutoria": "2024-01-20",
      "motivotutoria": "Dificultades en Lógica",
      "observaciones": "El estudiante asistió...",
      "estadotutoriaid": 1,
      "estado_nombre": "Pendiente"
    }
  ]
  ```

#### 6.3 Crear Detalle de Tutoría
- **Ruta**: `POST /api/tutoria-detalle`
- **Payload (JSON)**:
  ```json
  {
    "tutoriaid": 101,
    "fechatutoria": "2024-02-05",
    "motivotutoria": "Refuerzo examen parcial",
    "observaciones": "..."
  }
  ```
- **Nota**: El estado se asigna automáticamente a "Pendiente" (1).
- **Respuesta**: El registro creado, incluyendo `estado_nombre`.

#### 6.4 Actualizar Detalle
- **Ruta**: `PUT /api/tutoria-detalle/{id}`
- **Payload (JSON)**:
  ```json
  {
    "tutoriaid": 101,
    "fechatutoria": "2024-02-05",
    "motivotutoria": "Refuerzo examen parcial",
    "observaciones": "...",
    "estadotutoriaid": 2
  }
  ```
- **Nota**: Se permite actualizar el `estadotutoriaid`.
- **Restricción**: No se puede editar si el estado actual es "Incompleta" (5).
- **Respuesta**: El registro actualizado, incluyendo `estado_nombre`.

#### 6.5 Eliminar Detalle
- **Ruta**: `DELETE /api/tutoria-detalle/{id}`

---

### 7. Gestión de Documentos

#### 1. Subir Documento PDF
- **Ruta**: `POST /api/documentos`
- **Descripción**: Permite subir un archivo PDF al Storage y registrarlo en la base de datos. Si la extensión `finfo` no está en el servidor, el sistema valida por extensión y tipo reportado.
- **Headers**:
  - `Authorization: Bearer <token>`
  - `Content-Type: multipart/form-data`
- **Body (form-data)**:
  - `archivo`: (File, Required) El archivo PDF a subir.
  - `cronogramaid`: (Int, Required) ID del cronograma asociado.
  - `asignaturaid`: (Int, Required) ID de la asignatura.
  - `tipodocumentoid`: (Int, Required) ID del tipo de documento.
  - `semestreperiodoid`: (Int, Required) ID del periodo/nivel.
  - `seccionid`: (Int, Required) ID de la sección.
- **Respuesta (Éxito)**:
  ```json
  {
    "message": "Documento subido correctamente",
    "data": { "documentoid": 15, ... }
  }
  ```

#### 2. Listar Mis Reportes
- **Ruta**: `GET /api/documentos`
- **Descripción**: Devuelve la lista de reportes subidos por el docente autenticado, enriquecidos con los nombres de periodo, nivel, asignatura y formato.
- **Respuesta (Éxito)**:
  ```json
  [
    {
      "id": 1,
      "fecha": "2024-02-03",
      "periodo": "PAO I 2024",
      "nivel": "Nivel 4",
      "asignatura": "Contabilidad",
      "formato": "Diagnóstico",
      "archivo": "reporte_final.pdf",
      "url": "https://...",
      "estado": "ENVIADO"
    }
  ]
  ```
- **Errores**:
  - `400 Bad Request`: Si no se envía archivo o no es PDF.
  - `401 Unauthorized`: Token inválido o faltante.
  - `500 Internal Server Error`: Error al subir al Storage o guardar en BD.

---

## Anexo A: Lógica de Servicios y Validaciones

Esta sección detalla las reglas de negocio y validaciones técnicas implementadas en los servicios del backend.

### 1. DocumentoService (`Backend/src/Services/DocumentoService.php`)

#### Validación de Archivos PDF
Para garantizar la integridad y seguridad de los documentos subidos, el servicio aplica un filtro en dos capas:

1. **Verificación técnica**:
   - Si la librería `finfo` está activa: Se inspeccionan los *magic bytes* del archivo buscando `application/pdf`.
   - Si `finfo` NO está activa: Se valida que la extensión sea `.pdf` y que el tipo MIME reportado por el navegador sea compatible (`application/pdf` o `application/x-pdf`).

2. **Verificación de Estructura**:
   - El sistema lee el encabezado y pie de página del archivo temporal buscando las etiquetas estándar de PDF (`%PDF-` y `%%EOF`) para detectar archivos truncados o corruptos.


