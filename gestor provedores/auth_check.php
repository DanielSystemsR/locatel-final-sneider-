<?php
// auth_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si no hay sesión, mandarlo al login
if (!isset($_SESSION['user_id'])) {
    header("Location: iniciosesion.php");
    exit();
}

// Datos de conexión (Usa los mismos de tu iniciosesion.php)
$host = 'db'; 
$dbname = 'LocatelDB'; 
$user = 'sa'; 
$pass = 'LocatelPass2026!';

try {
    $conn_auth = new PDO("sqlsrv:Server=$host;Database=$dbname", $user, $pass);
    $conn_auth->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Buscamos los datos frescos del usuario
    $stmt = $conn_auth->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($u) {
        // Si lo bloquearon mientras navegaba, lo sacamos de una vez
        if (isset($u['estado']) && (int)$u['estado'] === 0) {
            session_destroy();
            header("Location: iniciosesion.php?error=bloqueado");
            exit();
        }

        // ACTUALIZACIÓN DE PERMISOS EN TIEMPO REAL
        $_SESSION['user_rol'] = strtolower($u['rol']); 
        $_SESSION['acc_facturas'] = (int)($u['acc_facturas'] ?? 0);
        $_SESSION['acc_proveedores'] = (int)($u['acc_proveedores'] ?? 0);
        $_SESSION['acc_empresas'] = (int)($u['acc_empresas'] ?? 0); // Por si agregaste este módulo

        // Permisos específicos (Facturas)
        $_SESSION['f_ver']   = (int)($u['f_ver_historial'] ?? 0);
        $_SESSION['f_crear'] = (int)($u['f_crear'] ?? 0);
        $_SESSION['f_edit']  = (int)($u['f_editar_eliminar'] ?? 0);

        // Permisos específicos (Proveedores)
        $_SESSION['p_crear'] = (int)($u['p_crear'] ?? 0); 
        $_SESSION['p_edit']  = (int)($u['p_editar_estatus'] ?? 0); 
        $_SESSION['p_est']   = (int)($u['p_estatus'] ?? 0);

    } else {
        // Si el usuario ya no existe en la DB
        session_destroy();
        header("Location: iniciosesion.php");
        exit();
    }
} catch(PDOException $e) {
    // Si falla la DB, dejamos que siga con la sesión vieja para no romper el sistema
}
?>