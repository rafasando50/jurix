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

-- Crear tabla de documentos
CREATE TABLE IF NOT EXISTS `documentos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `numero_instrumento` VARCHAR(100) NOT NULL,
    `libro` VARCHAR(100) NOT NULL,
    `fecha_expedicion` DATE NOT NULL,
    `notaria` VARCHAR(255) NOT NULL,
    `ciudad_notaria` VARCHAR(255) NOT NULL,
    `notario` VARCHAR(255) NOT NULL,
    `tipo` ENUM('acta', 'poder', 'revocacion') NOT NULL,
    `subtipo` ENUM('constitutiva', 'asamblea_ordinaria', 'asamblea_extraordinaria', 'poder_amplio', 'poder_especifico', 'poder_actas_administrativas', 'ninguno') NOT NULL DEFAULT 'ninguno',
    `concepto` TEXT NOT NULL,
    `vigencia` DATE DEFAULT NULL,
    `archivo_path` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

