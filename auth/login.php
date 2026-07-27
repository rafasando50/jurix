<?php
/**
 * Lógica de Autenticación - Procesamiento de Formulario de Inicio de Sesión
 * Ruta: /auth/login.php
 */

session_start();

// Validar que la petición sea de tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

// Incluir la conexión a la base de datos
require_once __DIR__ . '/../config/db.php';

// Obtener y sanitizar datos del formulario
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validar que los campos no estén vacíos
if (empty($email) || empty($password)) {
    $_SESSION['login_error'] = "Por favor, complete todos los campos.";
    header("Location: ../index.php");
    exit;
}

// Validar formato del correo electrónico
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['login_error'] = "El formato de correo electrónico no es válido.";
    header("Location: ../index.php");
    exit;
}

try {
    // Consulta preparada para buscar al usuario por su correo
    // Estrictamente parametrizado para evitar inyecciones SQL
    $stmt = $pdo->prepare("SELECT id, nombre, email, password, activo, rol FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        // Verificar si la cuenta está activa
        if ((int)$user['activo'] !== 1) {
            $_SESSION['login_error'] = "Esta cuenta se encuentra desactivada. Contacte al administrador.";
            header("Location: ../index.php");
            exit;
        }

        // Verificar la contraseña usando password_verify
        if (password_verify($password, $user['password'])) {
            // Regenerar el ID de sesión para prevenir Session Fixation (Ataque de Fijación de Sesión)
            session_regenerate_id(true);

            // Almacenar datos del usuario en la sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_rol'] = $user['rol'];

            // Redirigir al panel principal (dashboard.php)
            header("Location: ../dashboard.php");
            exit;
        }
    }

    // Si el usuario no existe o la contraseña no coincide, lanzamos el mismo mensaje por seguridad (ofuscación)
    $_SESSION['login_error'] = "El correo electrónico o la contraseña son incorrectos.";
    header("Location: ../index.php");
    exit;

} catch (PDOException $e) {
    // Registrar error internamente en el servidor
    error_log("Error en el login: " . $e->getMessage());
    
    $_SESSION['login_error'] = "Ocurrió un error en el servidor al intentar iniciar sesión. Inténtelo más tarde.";
    header("Location: ../index.php");
    exit;
}
