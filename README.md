# DigiTurno UNAD - Sistema de Gestion de Turnos

Sistema offline para la gestion de turnos de visitantes y estudiantes de la **Universidad Nacional Abierta y a Distancia (UNAD)** - CEAD Acacias.

## Estructura del Proyecto

```
digiturno-unad/
├── api/                        # Backend PHP (API REST)
│   ├── config.php              # Configuracion general
│   ├── auth.php                # Sesiones y control de acceso por rol
│   ├── login.php               # POST: Iniciar sesion por perfil
│   ├── logout.php              # Cerrar sesion
│   ├── db.php                  # Conexion SQLite
│   ├── register.php            # POST: Registrar visitante
│   ├── call_turn.php           # POST: Llamar turno
│   ├── finish_turn.php         # POST: Finalizar atencion/salida
│   ├── no_show.php             # POST: Marcar no asistio
│   ├── get_dependencies.php    # GET:  Lista dependencias
│   ├── get_display.php         # GET:  Datos para pantalla espera
│   ├── get_dependency_turns.php# GET:  Turnos por dependencia
│   ├── get_lists.php           # GET:  Listas/escuelas por dependencia
│   ├── get_history.php         # GET:  Historial/Reportes
│   ├── get_usuarios.php        # GET:  Lista usuarios (solo admin)
│   ├── save_usuario.php        # POST: Crear/editar funcionario (solo admin)
│   ├── delete_usuario.php      # POST: Eliminar funcionario (solo admin)
│   └── cambiar_password_admin.php # POST: Cambiar contrasena del admin
├── assets/
│   ├── css/
│   │   └── style.css           # Estilos (paleta UNAD)
│   ├── js/
│   │   ├── reception.js        # Logica pantalla recepcion
│   │   ├── display.js          # Logica pantalla de espera
│   │   ├── dependency.js       # Logica panel dependencias
│   │   ├── login.js            # Logica seleccion de perfil
│   │   └── configuracion.js    # Logica gestion de usuarios
│   └── img/                    # Imagenes del proyecto
├── db/
│   ├── schema.sql              # Esquema de base de datos
│   └── digiturno.db            # Base de datos SQLite (auto-generada)
├── login.php                   # SELECCION DE PERFIL (ingreso al sistema)
├── index.php                   # RECEPCION: Registro de visitantes
├── pantalla.php                # PANTALLA DE ESPERA: Turnos en vivo
├── dependencia.php             # DEPENDENCIA: Gestion de atencion
├── configuracion.php           # CONFIGURACION: Gestion de usuarios (admin)
├── init_db.php                 # Inicializar base de datos
└── README.md                   # Este archivo
```

## Requisitos

- **Servidor local**: XAMPP, WAMP, **AppServ** o Laragon (con PHP 7.4+ habilitado)
  - **Importante**: AppServ SI sirve. El sistema usa **SQLite** (incluido en PHP), no MySQL.
  - Si ves "Error de conexión con el servidor", asegúrate de que la extensión SQLite está
    habilitada en `php.ini` (`extension=php_sqlite3.dll`) y ejecuta primero `init_db.php`.
    El sistema crea y actualiza la base de datos automáticamente al abrir las pantallas.
- **SQLite**: Incluido por defecto en PHP (no requiere instalacion adicional)
- **Navegador web**: Chrome, Firefox, Edge

> No necesitas XAMPP si ya usas AppServ. Solo verifica que la extensión SQLite esté activa.

## Instalacion

### Paso 1: Copiar el proyecto
Copiar la carpeta `digiturno-unad` dentro del directorio de publicacion del servidor:
- **XAMPP**: `C:\xampp\htdocs\digiturno-unad\`
- **WAMP**: `C:\wamp64\www\digiturno-unad\`
- **AppServ**: `C:\AppServ\www\digiturno-unad\`

### Paso 2: Iniciar el servidor
Iniciar Apache desde el panel de control de XAMPP/WAMP/AppServ.

### Paso 3: Inicializar la base de datos
Abrir en el navegador:
```
http://localhost/digiturno-unad/init_db.php
```
Esto creara la base de datos SQLite con las dependencias de la UNAD preconfiguradas.

### Paso 4: Acceder al sistema
Entrar a la seleccion de perfiles:
```
http://localhost/digiturno-unad/login.php
```

| Perfil | Credenciales | Modulos disponibles |
|--------|--------------|---------------------|
| **Recepcion** | Acceso directo (sin usuario) | Recepcion (registro) + Pantalla TV |
| **Dependencias** | Usuario/contraseña asignados por el admin | Solo su panel de dependencia |
| **Administrador** | admin / admin123 (por defecto) | Todos los modulos + Configuracion |

> Cambie la contrasena del administrador en **Configuracion** la primera vez que ingrese.

Las pantallas directas (si ya hay una sesion activa en el navegador):
| Pantalla | URL | Uso |
|----------|-----|-----|
| **Recepcion** | `http://localhost/digiturno-unad/index.php` | Registro de visitantes (Vigilante) |
| **Pantalla Espera** | `http://localhost/digiturno-unad/pantalla.php` | Turnos en vivo (Sala de espera) |
| **Dependencia** | `http://localhost/digiturno-unad/dependencia.php` | Gestion de atencion (Cada dependencia) |
| **Configuracion** | `http://localhost/digiturno-unad/configuracion.php` | Gestion de usuarios (Solo admin) |

## Perfiles de Acceso

El sistema tiene **3 perfiles** seleccionables desde `login.php` (estilo Netflix):

1. **Recepcion**: entra directo, sin credenciales. Ve el registro de visitantes y puede
   abrir la **Pantalla** (TV). No ve Dependencias ni Configuracion.
2. **Dependencias**: pide usuario y contrasena asignados por el administrador. Solo ve
   los turnos de **su dependencia** (la configurada en su usuario). No tiene opciones de
   configuracion, gestion de usuarios ni edicion de su propio perfil.
   - No hay recuperacion de contrasena desde el login: debe solicitarla
     **presencialmente en el CEAD** al administrador.
3. **Administrador**: acceso a todos los modulos (Recepcion, Pantalla, Dependencias) y a
   **Configuracion**, donde crea, edita, desactiva y elimina los usuarios de las
   dependencias, y puede cambiar su propia contrasena.

> Credencial por defecto del administrador: **admin / admin123**. Cambiela al primer ingreso.

La gestion de los funcionarios de dependencia (nombre, usuario, contrasena, dependencia
asignada y estado activo/inactivo) se realiza en **Configuracion** exclusivamente por el
administrador.

## Flujo del Sistema

```
1. REGISTRO (Recepcion)
   Vigilante registra visitante → Se genera numero de turno
   ↓
2. ESPERA (Sala de espera)
   Visitante ve su turno en la pantalla
   ↓
3. LLAMADO (Dependencia)
   La dependencia llama al turno desde su panel
   ↓
4. ATENCION (Dependencia)
   Visitante es atendido
   ↓
5. EGRESO (Dependencia)
   Se registra la salida del visitante
```

## Dependencias Preconfiguradas

| Codigo | Dependencia |
|--------|-------------|
| RYC | Registro y Control |
| VISAE | Consejeria academica y orientacion |
| BIU | Bienestar integral unadista |
| ESC | Acompanamiento por Escuelas Academicas y programas (requiere escuela) |
| STCV | Soporte tecnologico y campus virtual |
| BIUN | Servicios de biblioteca (BiUNAD) |
| SAI | Sistema de atencion integral (SAI) |

Al seleccionar **Acompanamiento por Escuelas Academicas y programas (ESC)** se solicita la escuela especifica:

| Codigo | Escuela |
|--------|---------|
| ECACEN | Escuela de Ciencias Administrativas, Contables, Economicas y de Negocios |
| ECAPMA | Escuela de Ciencias Agricolas, Pecuarias y del Medio Ambiente |
| ECBTI | Escuela de Ciencias Basicas, Tecnologia e Ingenieria |
| ECISA | Escuela de Ciencias de la Salud |
| ECEDU | Escuela de Ciencias de la Educacion |
| ECSAH | Escuela de Ciencias Sociales, Artes y Humanidades |
| ECJP | Escuela de Ciencias Juridicas y Politicas |

## Base de Datos

El esquema SQL se encuentra en `db/schema.sql`. Las tablas principales son:

- **dependencias**: Unidades organizacionales de la UNAD
- **turnos**: Registro de visitantes y su estado
- **actividad_log**: Registro de auditoria de acciones
- **configuracion**: Parametros del sistema

### Numeracion de Turnos
- Formato: `V0001`, `V0002`, etc. (prefijo V + 4 digitos)
- Se reinicia automaticamente cada dia

## Caracteristicas

- **100% Offline**: Funciona sin conexion a internet
- **Perfiles de acceso**: Seleccion de rol estilo Netflix (Recepcion / Dependencias / Administrador)
- **Control de acceso**: El funcionario de dependencia solo ve y opera los turnos de su dependencia
- **SQLite**: Sin necesidad de configurar MySQL/PostgreSQL
- **Tiempo real**: La pantalla de espera y el panel de dependencias se actualizan cada 0.5 segundos
- **Multi-pantalla**: Recepcion, Sala de espera, Dependencias simultaneamente
- **Responsive**: Se adapta a diferentes tamanos de pantalla
- **Paleta UNAD**: Colores institucionales azul, amarillo y verde

## Reglas de Registro

- **Nombre y Apellido**: Se normalizan automaticamente a **MAYUSCULAS** y solo aceptan
  letras (incluidas tildes y ñ) y espacios. No se permiten numeros ni simbolos.
- **Cedula no repetible**: Cada cedula solo puede registrarse una vez en el sistema.
  Si se intenta registrar una cedula ya existente, el sistema lo rechaza con un aviso claro.
- **Auto-actualizacion**: Al registrar un turno, la tabla "Turnos Registrados Hoy" de la
  recepcion se actualiza automaticamente medio segundo despues.

## Pantalla de Espera

- Muestra la cola de proximos turnos en formato de **dos columnas: Turno | Dependencia**.
- Para la dependencia **Acompanamiento por Escuelas (ESC)** se muestra la **escuela** del
  visitante en lugar del nombre de la dependencia.
- Se refresca cada **0.5 segundos** para reflejar los turnos en tiempo real.

## Panel de Dependencia

- Las pestañas "Pendientes" y "En Cola" se unificaron en una **sola lista de pendientes**.
- Los turnos se ordenan del **mas antiguo al mas reciente** (primero en llegar, primero en atenderse).
- Cada turno muestra la accion correspondiente segun su estado: **Llamar** (en cola,
  unico boton inicial). Al pinchar "Llamar" desaparece y aparecen solo dos botones:
  **Finalizar** y **No Asistio** (no hay boton "Atender"; el "No Asistio" solo aparece
  despues de llamar).
- Se refresca cada **0.5 segundos**.
