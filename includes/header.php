<?php
/**
 * Layout Header - Encabezado y protección de sesión
 * Ruta: /includes/header.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteger la página: verificar si la sesión del usuario está activa
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión Documental y Poderes</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Estilos Personalizados -->
    <link rel="stylesheet" href="assets/css/style.css?v=1.1">
</head>
<body class="dashboard-body">
    <div class="dashboard-wrapper">
