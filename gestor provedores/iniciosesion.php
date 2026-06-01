<?php
session_start();

$host = 'db'; 
$dbname = 'LocatelDB'; 
$user = 'sa'; 
$pass = 'LocatelPass2026!';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn = new PDO("sqlsrv:Server=$host;Database=$dbname", $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $email_post = trim($_POST['email']);
        $password_post = $_POST['password'];

        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email_post]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC); 

        if ($user_data) {
            // 1. Verificación de estado
            if (isset($user_data['estado']) && (int)$user_data['estado'] === 0) {
                $error = "Usuario bloqueado. Contacte a soporte técnico.";
            } 
            // 2. Verificación de contraseña
            elseif ($password_post === $user_data['password']) {
                session_regenerate_id(true);

                // --- ASIGNACIÓN DE SESIÓN ---
                $_SESSION['user_id']   = $user_data['id'];
                $_SESSION['user_name'] = $user_data['nombre'];
                $_SESSION['user_rol']  = strtolower($user_data['rol']); 

                // Mapeo de permisos desde la base de datos
                $_SESSION['f_ver']    = (int)($user_data['f_ver_historial'] ?? 0);
                $_SESSION['f_crear']  = (int)($user_data['f_crear'] ?? 0);
                $_SESSION['f_edit']   = (int)($user_data['f_editar_eliminar'] ?? 0);
                // CORRECCIÓN AQUÍ: El nombre de la columna en la BD y el de la sesión
                $_SESSION['p_crear']  = (int)($user_data['p_crear'] ?? 0); 
                $_SESSION['p_edit']  = (int)($user_data['p_editar_estatus'] ?? 0); 
                $_SESSION['p_est']   = (int)($user_data['p_estatus'] ?? 0); // Si agregaste el nuevo permiso de estatus
                                
                // Acceso a módulos
                $_SESSION['acc_facturas']    = (int)($user_data['acc_facturas'] ?? 0);
                $_SESSION['acc_proveedores'] = (int)($user_data['acc_proveedores'] ?? 0);

                header("Location: vistafacturas.php");
                exit();
            } else {
                $error = "Credenciales inválidas.";
            }
        } else {
            $error = "Credenciales inválidas.";
        }
    } catch(PDOException $e) {
        $error = "Error de conexión con la base de datos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión | Locatel</title>
    <link rel="stylesheet" href="iniciosesion.css">
    <style>
        .debug-msg {
            background: #fff5f5; color: #c53030; padding: 12px;
            border: 1px solid #feb2b2; border-radius: 5px;
            margin-bottom: 15px; font-size: 14px; text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="logo-placeholder">
        <img src="images/Logo_Locatel (1).png" alt="Logo Locatel" class="logo-img-real">
    </div>

    <h2>Sistema de Facturación</h2>
    
    <?php if($error): ?>
        <div class="debug-msg">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form action="iniciosesion.php" method="POST">
        <div class="input-group">
            <label for="email">Correo Institucional</label>
            <input type="email" id="email" name="email" placeholder="usuario@locatel.com.ve" required>
        </div>
        
        <div class="input-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="********" required>
        </div>
        
        <button type="submit" class="login-btn">Entrar al Sistema</button>
    </form>
</div>
</body>
</html>