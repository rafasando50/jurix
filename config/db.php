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
        
        // Inicializar tabla de usuarios si no existe
        $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            activo INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
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
            vigencia TEXT DEFAULT NULL,
            archivo_path TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Verificar si existe el usuario administrador de prueba
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
        $stmt->execute(['email' => 'admin@sistema.com']);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            // Contraseña: admin123
            $hashed_pass = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, activo) VALUES (:nombre, :email, :password, :activo)");
            $stmt->execute([
                'nombre' => 'Administrador de Prueba (SQLite)',
                'email' => 'admin@sistema.com',
                'password' => $hashed_pass,
                'activo' => 1
            ]);
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


