<?php
session_start();

// 1. SEGURIDAD: Solo administradores
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    header("Location: vistafacturas.php");
    exit();
}

$host = 'db'; $dbname = 'LocatelDB'; $user = 'sa'; $pass = 'LocatelPass2026!';

try {
    $dsn = "sqlsrv:Server=$host;Database=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // LÓGICA: Crear nuevo usuario
    if (isset($_POST['crear_usuario'])) {
        $nom = $_POST['nombre'];
        $em  = $_POST['email'];
        $pw  = $_POST['password']; 
        $rol = $_POST['rol'];
        
        // Añadimos acc_empresas al final de la lista de columnas y un 0 al final de los VALUES
        $sql = "INSERT INTO usuarios (nombre, email, password, rol, estado, fecha_creacion, 
                acc_facturas, f_ver_historial, f_crear, f_editar_eliminar, 
                acc_proveedores, p_ver_historial, p_crear, p_editar_estatus, p_estatus, acc_empresas) 
                VALUES (?, ?, ?, ?, 1, GETDATE(), 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)";
        
        $conn->prepare($sql)->execute([$nom, $em, $pw, $rol]);
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();
    }

    // LÓGICA: Alternar Estado Usuario (Activo/Inactivo)
    if (isset($_POST['toggle_estado'])) {
        $u_id = $_POST['user_id'];
        $nuevo_estado = $_POST['estado_actual'] == 1 ? 0 : 1;
        $sql = "UPDATE usuarios SET estado = ? WHERE id = ?";
        $conn->prepare($sql)->execute([$nuevo_estado, $u_id]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // LÓGICA: Actualizar permisos detallados
    if (isset($_POST['update_permisos'])) {
        $u_id = $_POST['user_id'];
        
        // Módulo Facturas
        $acc_f   = isset($_POST['acc_f']) ? 1 : 0;
        $f_hist  = isset($_POST['f_hist']) ? 1 : 0;
        $f_crear = isset($_POST['f_crear']) ? 1 : 0;
        $f_edit  = isset($_POST['f_edit']) ? 1 : 0;

        // Módulo Proveedores
        $acc_p   = isset($_POST['acc_p']) ? 1 : 0;
        $p_hist  = isset($_POST['p_hist']) ? 1 : 0;
        $p_crear = isset($_POST['p_crear']) ? 1 : 0;
        $p_edit  = isset($_POST['p_edit']) ? 1 : 0;
        $p_est   = isset($_POST['p_est']) ? 1 : 0; // Nueva columna estatus
        $acc_e = isset($_POST['acc_e']) ? 1 : 0;

        $sql = "UPDATE usuarios SET 
                acc_facturas = ?, f_ver_historial = ?, f_crear = ?, f_editar_eliminar = ?, 
                acc_proveedores = ?, p_ver_historial = ?, p_crear = ?, p_editar_estatus = ?, p_estatus = ?,
                acc_empresas = ? 
                WHERE id = ?";

        $conn->prepare($sql)->execute([
            $acc_f, $f_hist, $f_crear, $f_edit,
            $acc_p, $p_hist, $p_crear, $p_edit, $p_est,
            $acc_e, // Nuevo permiso
            $u_id
        ]);
        
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();
    }

    $usuarios = $conn->query("SELECT * FROM usuarios ORDER BY fecha_creacion DESC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { die("Error: " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios | Locatel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --green: #00953b; --orange: #ff671d; --bg: #f4f7f6; --red: #e74c3c; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1250px; margin: auto; }
        .header-form { background: #fff; padding: 20px 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 40px; }
        .grid-header { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 15px; align-items: end; }
        .input-box { display: flex; flex-direction: column; gap: 5px; }
        .input-box label { font-size: 12px; font-weight: 600; color: #888; }
        .input-box input, .input-box select { padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .btn-green { background: var(--green); color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .user-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 25px; }
        .user-card { background: #fff; border-radius: 15px; border: 1px solid #eee; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.03); transition: 0.3s; }
        .user-card.inactive { opacity: 0.6; filter: grayscale(0.5); }
        .card-header { padding: 15px 20px; border-bottom: 1px solid #f9f9f9; display: flex; justify-content: space-between; align-items: center; }
        .card-header h4 { margin: 0; color: var(--green); font-size: 18px; text-transform: uppercase; }
        .card-body { padding: 20px; }
        .module-title { font-size: 12px; font-weight: 900; text-transform: uppercase; margin-bottom: 10px; display: block; border-bottom: 2px solid #f0f0f0; padding-bottom: 5px; }
        .row-switch { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 14px; color: #666; }
        .switch { position: relative; width: 40px; height: 20px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 20px; }
        .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider.green-slider { background-color: var(--green); }
        input:checked + .slider.orange-slider { background-color: var(--orange); }
        input:checked + .slider:before { transform: translateX(20px); }
        .btn-dark { width: 100%; background: #333; color: #fff; border: none; padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer; margin-top: 15px; }
        .btn-status { width: 100%; background: none; border: none; padding: 8px; font-size: 12px; font-weight: bold; cursor: pointer; margin-top: 10px; border-radius: 5px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .deactivate { color: var(--red); }
        .activate { color: var(--green); }
        .btn-back { text-decoration: none; background: #555; color: white; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: bold; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .btn-back:hover { background: #222; }
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        input:checked + .slider.blue-slider { 
    background-color: #007bff; 
}
    </style>
</head>
<body>

<div class="container">
    <div class="header-form">
        <div class="header-top">
            <h3 style="margin:0;"><i class="fas fa-user-plus"></i> Registrar Nuevo Usuario</h3>
            <a href="vistafacturas.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver al Inicio</a>
        </div>
        <form method="POST" class="grid-header">
            <div class="input-box"><label>Nombre</label><input type="text" name="nombre" required></div>
            <div class="input-box"><label>Correo</label><input type="email" name="email" required></div>
            <div class="input-box"><label>Contraseña</label><input type="password" name="password" required></div>
            <div class="input-box"><label>Rol</label><select name="rol"><option value="Usuario">Usuario</option><option value="Admin">Admin</option></select></div>
            <button type="submit" name="crear_usuario" class="btn-green">Guardar Usuario</button>
        </form>
    </div>

    <div class="user-grid">
        <?php foreach ($usuarios as $u): 
            $es_activo = ($u['estado'] ?? 1) == 1;
        ?>
        <div class="user-card <?= !$es_activo ? 'inactive' : '' ?>">
            <div class="card-header">
                <div>
                     <span style="font-size: 18px; font-weight: bold; text-transform: uppercase; color: <?= $es_activo ? 'var(--green)' : 'var(--red)' ?>">
                        <?= $es_activo ? 'Activo' : 'Desactivado' ?>
                    </span>
                    <h4><?= htmlspecialchars($u['nombre']) ?></h4>
                    <i class="fas fa-envelope"></i> <strong>Usuario:</strong> 
        <span style="color: #333;"><?= htmlspecialchars($u['email']) ?></span>
            <p><i class="fas fa-key"></i> <strong>Contraseña:</strong>  
          <span style="color: #333; font-family: monospace;"><?= htmlspecialchars($u['password']) ?></span></p>
            
                   
                </div>
                <span style="font-size:10px; background:#eee; padding:3px 8px; border-radius:10px;"><?= $u['rol'] ?></span>
            </div>
            
            <div class="card-body">
    <form method="POST">
        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">

        <!-- Módulo Facturas -->
        <span class="module-title" style="color:var(--green)">Módulo Facturas</span>
        <div class="row-switch"><span>Acceso</span><label class="switch"><input type="checkbox" name="acc_f" class="master-f" data-id="<?= $u['id'] ?>" <?= ($u['acc_facturas']) ? 'checked' : '' ?>><span class="slider green-slider"></span></label></div>
        <div class="row-switch"><span>Ver</span><label class="switch"><input type="checkbox" name="f_hist" id="v_f_<?= $u['id'] ?>" <?= ($u['f_ver_historial']) ? 'checked' : '' ?>><span class="slider green-slider"></span></label></div>
        <div class="row-switch"><span>Crear</span><label class="switch"><input type="checkbox" name="f_crear" <?= ($u['f_crear']) ? 'checked' : '' ?>><span class="slider green-slider"></span></label></div>
        <div class="row-switch"><span>Editar/Eliminar</span><label class="switch"><input type="checkbox" name="f_edit" <?= ($u['f_editar_eliminar']) ? 'checked' : '' ?>><span class="slider green-slider"></span></label></div>

        <!-- Módulo Proveedores -->
        <span class="module-title" style="color:var(--orange); margin-top:15px;">Módulo Proveedores</span>
        <div class="row-switch"><span>Acceso</span><label class="switch"><input type="checkbox" name="acc_p" class="master-p" data-id="<?= $u['id'] ?>" <?= ($u['acc_proveedores']) ? 'checked' : '' ?>><span class="slider orange-slider"></span></label></div>
        <div class="row-switch"><span>Ver</span><label class="switch"><input type="checkbox" name="p_hist" id="v_p_<?= $u['id'] ?>" <?= ($u['p_ver_historial']) ? 'checked' : '' ?>><span class="slider orange-slider"></span></label></div>
        <div class="row-switch"><span>Crear</span><label class="switch"><input type="checkbox" name="p_crear" <?= ($u['p_crear']) ? 'checked' : '' ?>><span class="slider orange-slider"></span></label></div>
        <div class="row-switch"><span>Editar</span><label class="switch"><input type="checkbox" name="p_edit" <?= ($u['p_editar_estatus']) ? 'checked' : '' ?>><span class="slider orange-slider"></span></label></div>
        <div class="row-switch"><span>Cambiar Estatus</span><label class="switch"><input type="checkbox" name="p_est" <?= ($u['p_estatus'] ?? 0) ? 'checked' : '' ?>><span class="slider orange-slider"></span></label></div>

        <!-- NUEVO: Módulo Mis Empresas -->
        

        <button type="submit" name="update_permisos" class="btn-dark">Actualizar Permisos</button>
    </form>

    <form method="POST">
        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
        <input type="hidden" name="estado_actual" value="<?= $u['estado'] ?>">
        <button type="submit" name="toggle_estado" class="btn-status <?= $es_activo ? 'deactivate' : 'activate' ?>">
            <i class="fas <?= $es_activo ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
            <?= $es_activo ? 'Desactivar' : 'Activar' ?>
        </button>
    </form>
</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('master-f')) {
        const histF = document.getElementById('v_f_' + e.target.dataset.id);
        if (histF && !e.target.checked) histF.checked = false;
    }
    if (e.target.classList.contains('master-p')) {
        const histP = document.getElementById('v_p_' + e.target.dataset.id);
        if (histP && !e.target.checked) histP.checked = false;
    }
});
</script>

</body>
</html>