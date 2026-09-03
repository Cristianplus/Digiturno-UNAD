<?php
/**
 * DIGITURNO UNAD - API: Cerrar sesion
 */

require_once __DIR__ . '/auth.php';

cerrarSesion();
header('Location: ../login.php');
exit;