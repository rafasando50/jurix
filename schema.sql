-- Script de Creación de la Base de Datos y Tabla de Usuarios
-- Sistema de Gestión Documental y de Poderes Jurídicos

-- Crear tabla de usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `rol` VARCHAR(20) NOT NULL DEFAULT 'usuario',
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar usuarios iniciales
-- 1. Super Admin (Sistemas)
-- Contraseña: pumas123
INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`, `activo`) 
VALUES (
    'Sistemas', 
    'sistemas@einsursupply.com', 
    '$2y$10$.SO5HpeJ2kKxneHYsZl/I.F4zV46kq52.LxnFD.5Kgvf74vdw5VG.', 
    'superadmin', 
    1
) ON DUPLICATE KEY UPDATE `rol` = 'superadmin', `activo` = 1;


-- Crear tabla de empresas
CREATE TABLE IF NOT EXISTS `empresas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(255) NOT NULL UNIQUE,
    `rfc` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar empresa por defecto
INSERT INTO `empresas` (`nombre`) VALUES ('N/A') ON DUPLICATE KEY UPDATE `nombre` = 'N/A';

-- Crear tabla de documentos
CREATE TABLE IF NOT EXISTS `documentos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `numero_instrumento` VARCHAR(100) NOT NULL,
    `libro` VARCHAR(100) NOT NULL,
    `fecha_expedicion` DATE NOT NULL,
    `notaria` VARCHAR(255) NOT NULL,
    `ciudad_notaria` VARCHAR(255) NOT NULL,
    `estado_notaria` VARCHAR(255) NOT NULL DEFAULT '',
    `notario` VARCHAR(255) NOT NULL,
    `tipo` ENUM('acta', 'poder', 'revocacion') NOT NULL,
    `subtipo` ENUM('constitutiva', 'asamblea_ordinaria', 'asamblea_extraordinaria', 'poder_amplio', 'poder_especifico', 'poder_actas_administrativas', 'ninguno') NOT NULL DEFAULT 'ninguno',
    `concepto` TEXT NOT NULL,
    `vigencia` DATE DEFAULT NULL,
    `archivo_path` VARCHAR(255) DEFAULT NULL,
    `revoca_documento_id` INT DEFAULT NULL,
    `empresa_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de personas
CREATE TABLE IF NOT EXISTS `personas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de relaciones documento-personas
CREATE TABLE IF NOT EXISTS `documento_personas` (
    `documento_id` INT NOT NULL,
    `persona_id` INT NOT NULL,
    PRIMARY KEY (`documento_id`, `persona_id`),
    FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

