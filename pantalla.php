<?php
/**
 * DIGITURNO UNAD - Pantalla de Espera (TV)
 * Acceso: perfil Recepcion o Administrador.
 */
require_once __DIR__ . '/api/auth.php';
requierePerfil(['recepcion', 'admin']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiTurno UNAD - Pantalla de Espera</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #001a33; }
    </style>
</head>
<body class="display-screen">

    <div class="display-header">
        <h1>Universidad Nacional Abierta y a Distancia - UNAD</h1>
        <p class="subtitle">Sala de Espera - CEAD Acacias | Sistema DigiTurno</p>
        <p class="display-date" id="display-date"></p>
    </div>

    <div class="display-content">
        <!-- Columna Izquierda: Turnos en Atencion -->
        <div class="display-column">
            <div class="display-column-title">Turnos en Atencion</div>

            <div id="turnos-atencion-container">
                <div class="turno-actual sin-turno">
                    <div class="turno-label">Turno en Atencion</div>
                    <div class="turno-numero">Sin turnos</div>
                    <div class="turno-dependencia"></div>
                    <div class="turno-nombre"></div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Proximos Turnos -->
        <div class="display-column">
            <div class="display-column-title">Proximos Turnos</div>
            <div id="proximos-container">
                <div class="empty-state" style="color: rgba(255,255,255,0.5);">
                    <div class="empty-icon">&#128203;</div>
                    <h3 style="color: rgba(255,255,255,0.7);">No hay turnos pendientes</h3>
                    <p style="color: rgba(255,255,255,0.4);">Esperando nuevos registros...</p>
                </div>
            </div>
        </div>
    </div>

    <div class="display-stats">
        <div class="stat-item">
            <div class="stat-value" id="stat-total">0</div>
            <div class="stat-label">Total Hoy</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" id="stat-en-cola">0</div>
            <div class="stat-label">En Cola</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" id="stat-atendidos">0</div>
            <div class="stat-label">Atendidos</div>
        </div>
    </div>

    <script src="assets/js/display.js"></script>
</body>
</html>
