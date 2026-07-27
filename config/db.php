<?php
/**
 * Configuración de la Conexión a la Base de Datos (PDO)
 * Sistema de Gestión Documental y de Poderes Jurídicos
 */

// Auto-detectar si es desarrollo local o servidor de producción
$is_local = (php_sapi_name() === 'cli' || 
             (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) ||
             (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost'));

define('IS_PRODUCTION', !$is_local);

// Parámetros de conexión de la base de datos (Modificar con tus datos de Banahosting)
define('DB_HOST', 'localhost');
define('DB_NAME', 'ykihkdau_jurix');
define('DB_USER', 'ykihkdau_jurix_user');
define('DB_PASS', 'MI8e)4X%t*u)');
define('DB_CHARSET', 'utf8mb4');


try {
    // Intentar conexión MySQL primero
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanzar excepciones en errores
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch asociativo por defecto
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Desactivar emulación para evitar inyecciones SQL
    ];
    
    // Crear la instancia de PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    define('DB_TYPE', 'mysql');
    
    // Migración automática del rol y cuentas iniciales para MySQL
    try {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN rol VARCHAR(20) NOT NULL DEFAULT 'usuario'");
    } catch (PDOException $ex) {
        // Ignorar si ya existe
    }

    try {
        $pdo->exec("ALTER TABLE documentos ADD COLUMN revoca_documento_id INT DEFAULT NULL");
    } catch (PDOException $ex) {
        // Ignorar si ya existe
    }
    
    // Asegurar Super Admin Sistemas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
    $stmt->execute(['email' => 'sistemas@einsursupply.com']);
    if ($stmt->fetchColumn() == 0) {
        $hashed_pass = password_hash('pumas123', PASSWORD_DEFAULT);
        $stmt_ins = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, activo, rol) VALUES ('Sistemas', 'sistemas@einsursupply.com', :password, 1, 'superadmin')");
        $stmt_ins->execute(['password' => $hashed_pass]);
    } else {
        $pdo->exec("UPDATE usuarios SET rol = 'superadmin', activo = 1 WHERE email = 'sistemas@einsursupply.com'");
    }
    
    
} catch (PDOException $e) {
    // Si estamos en producción, obligatoriamente necesitamos la base de datos MySQL activa
    if (IS_PRODUCTION) {
        error_log("Error de conexión a la BD en producción: " . $e->getMessage());
        die("Lo sentimos, ha ocurrido un problema con la conexión a la base de datos. Por favor, intente más tarde.");
    }

    // Si no estamos en producción (desarrollo local), intentamos usar SQLite local
    $sqlite_file = __DIR__ . '/../database.sqlite';
    
    try {
        $dsn = "sqlite:" . $sqlite_file;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        
        $pdo = new PDO($dsn, null, null, $options);
        define('DB_TYPE', 'sqlite');
        
        // Habilitar llaves foráneas en SQLite para soportar ON DELETE CASCADE
        $pdo->exec("PRAGMA foreign_keys = ON;");
        
        // Inicializar tabla de usuarios si no existe
        $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            rol TEXT NOT NULL DEFAULT 'usuario',
            activo INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        try {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN rol TEXT DEFAULT 'usuario'");
        } catch (PDOException $ex) {
            // Ignorar si ya existe
        }
        
        // Inicializar tabla de documentos si no existe
        $pdo->exec("CREATE TABLE IF NOT EXISTS documentos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            numero_instrumento TEXT NOT NULL,
            libro TEXT NOT NULL,
            fecha_expedicion TEXT NOT NULL,
            notaria TEXT NOT NULL,
            ciudad_notaria TEXT NOT NULL,
            notario TEXT NOT NULL,
            tipo TEXT NOT NULL,
            subtipo TEXT NOT NULL DEFAULT 'ninguno',
            concepto TEXT NOT NULL,
            personas_acreditadas TEXT DEFAULT NULL,
            vigencia TEXT DEFAULT NULL,
            archivo_path TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Intentar agregar la columna personas_acreditadas por si la tabla ya existía
        try {
            $pdo->exec("ALTER TABLE documentos ADD COLUMN personas_acreditadas TEXT DEFAULT NULL");
        } catch (PDOException $e) {
            // Ignorar si la columna ya existe o si falla
        }

        try {
            $pdo->exec("ALTER TABLE documentos ADD COLUMN revoca_documento_id INTEGER DEFAULT NULL");
        } catch (PDOException $e) {
            // Ignorar si la columna ya existe o si falla
        }

        // Inicializar tablas para la normalización relacional
        $pdo->exec("CREATE TABLE IF NOT EXISTS personas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL UNIQUE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS documento_personas (
            documento_id INTEGER NOT NULL,
            persona_id INTEGER NOT NULL,
            PRIMARY KEY (documento_id, persona_id),
            FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE CASCADE,
            FOREIGN KEY (persona_id) REFERENCES personas (id) ON DELETE CASCADE
        )");

        // Migración automática de datos legacy de personas_acreditadas
        try {
            // 1. Obtener documentos que tengan datos en la columna legacy
            $stmt = $pdo->query("SELECT id, personas_acreditadas FROM documentos WHERE personas_acreditadas IS NOT NULL AND personas_acreditadas != ''");
            $legacy_docs = $stmt->fetchAll();
            
            if (!empty($legacy_docs)) {
                $stmt_ins_persona = $pdo->prepare("INSERT OR IGNORE INTO personas (nombre) VALUES (:nombre)");
                $stmt_sel_persona = $pdo->prepare("SELECT id FROM personas WHERE nombre = :nombre");
                $stmt_ins_relation = $pdo->prepare("INSERT OR IGNORE INTO documento_personas (documento_id, persona_id) VALUES (:documento_id, :persona_id)");
                
                foreach ($legacy_docs as $ldoc) {
                    $names = array_filter(array_map('trim', explode(',', $ldoc['personas_acreditadas'])));
                    foreach ($names as $name) {
                        if (empty($name)) continue;
                        $stmt_ins_persona->execute(['nombre' => $name]);
                        $stmt_sel_persona->execute(['nombre' => $name]);
                        $pid = $stmt_sel_persona->fetchColumn();
                        if ($pid) {
                            $stmt_ins_relation->execute([
                                'documento_id' => $ldoc['id'],
                                'persona_id' => $pid
                            ]);
                        }
                    }
                }
                
                // Limpiar la columna legacy para que no se vuelva a migrar
                $pdo->exec("UPDATE documentos SET personas_acreditadas = NULL");
            }
        } catch (PDOException $mig_err) {
            // Ignorar si la columna no existía o falla
        }
        
        // Verificar si existe el superadmin de Sistemas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
        $stmt->execute(['email' => 'sistemas@einsursupply.com']);
        $count_sis = $stmt->fetchColumn();
        
        if ($count_sis == 0) {
            $hashed_pass = password_hash('pumas123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, activo, rol) VALUES (:nombre, :email, :password, :activo, :rol)");
            $stmt->execute([
                'nombre' => 'Sistemas',
                'email' => 'sistemas@einsursupply.com',
                'password' => $hashed_pass,
                'activo' => 1,
                'rol' => 'superadmin'
            ]);
        } else {
            $pdo->exec("UPDATE usuarios SET rol = 'superadmin', activo = 1 WHERE email = 'sistemas@einsursupply.com'");
        }

        
    } catch (PDOException $sqlite_error) {
        // Si SQLite también falla o no está disponible, usamos un Mock PDO que simule las consultas básicas
        define('DB_TYPE', 'mock');
        
        if (!class_exists('MockPDO')) {
            class MockPDO {
                public function prepare($sql) {
                    return new MockPDOStatement();
                }
                public function exec($sql) {
                    return 0;
                }
            }
            
            class MockPDOStatement {
                private $params = [];
                
                public function execute($params = []) {
                    $this->params = $params;
                    return true;
                }
                
                public function fetch() {
                    // Retornar el usuario administrador mockeado
                    return [
                        'id' => 1,
                        'nombre' => 'Administrador de Prueba (Mock Mode)',
                        'email' => 'admin@sistema.com',
                        // Contraseña encriptada para 'admin123'
                        'password' => '$2y$10$CYn.YgIb0dup7WTL.YO1WuE/X8N9Kp9te5z2ZSTLw.1xpefMjWope',
                        'activo' => 1
                    ];
                }
                
                public function fetchColumn() {
                    return 1;
                }
            }
        }
        
        $pdo = new MockPDO();
    }
}


