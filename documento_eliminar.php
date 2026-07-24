<?php
/**
 * Controlador de Eliminación de Documento
 * Ruta: /documento_eliminar.php
 */

session_start();

// Proteger la página: verificar si la sesión del usuario está activa
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Validar que la petición sea de tipo POST por seguridad
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: documentos.php");
    exit;
}

// Incluir conexión a base de datos
require_once __DIR__ . '/config/db.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id > 0) {
    try {
        // 1. Obtener la ruta del archivo para borrarlo físicamente
        $stmt = $pdo->prepare("SELECT archivo_path FROM documentos WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $doc = $stmt->fetch();

        if ($doc) {
            // 2. Borrar el archivo PDF del disco si existe
            if (!empty($doc['archivo_path']) && file_exists(__DIR__ . '/' . $doc['archivo_path'])) {
                unlink(__DIR__ . '/' . $doc['archivo_path']);
            }

            // 3. Borrar el registro de la base de datos
            $stmt_delete = $pdo->prepare("DELETE FROM documentos WHERE id = :id");
            $stmt_delete->execute(['id' => $id]);
        }
    } catch (PDOException $e) {
        error_log("Error al eliminar documento: " . $e->getMessage());
    }
}

// Redirigir de vuelta al listado de documentos
header("Location: documentos.php");
exit;
