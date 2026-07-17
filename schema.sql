-- Script de Creación de la Base de Datos y Tabla de Usuarios
-- Sistema de Gestión Documental y de Poderes Jurídicos

-- Crear tabla de usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar usuario administrador de prueba
-- Credenciales:
-- Correo: admin@sistema.com
-- Contraseña: admin123
INSERT INTO `usuarios` (`nombre`, `email`, `password`, `activo`) 
VALUES (
    'Administrador de Prueba', 
    'admin@sistema.com', 
    '$2y$10$CYn.YgIb0dup7WTL.YO1WuE/X8N9Kp9te5z2ZSTLw.1xpefMjWope', 
    1
) ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);
