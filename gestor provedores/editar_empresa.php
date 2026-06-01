<?php
ob_start();
session_start();

// 1. CONTROL DE SESIÓN Y PERMISOS
if (!isset($_SESSION['user_id'])) {
    header("Location: iniciosesion.php");
    exit();
}

$es_admin = (isset($_SESSION['user_rol']) && strtolower(trim($_SESSION['user_rol'])) === 'admin');

if (!$es_admin && (!isset($_SESSION['acc_empresas']) || $_SESSION['acc_empresas'] == 0)) {
    header("Location: mis_empresas.php?error=sin_permiso");
    exit();
}

// Configuración de la base de datos
$host = 'db'; 
$dbname = 'LocatelDB';
$user = 'sa'; 
$pass = 'LocatelPass2026!';

$mensaje = "";
$empresa = null;
$rif_tipo = 'J';
$rif_numero = '';

try {
    $dsn = "sqlsrv:Server=$host;Database=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // CARGAR LOS DATOS ACTUALES DE LA EMPRESA
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM mis_empresas WHERE id = ?");
        $stmt->execute([$id]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$empresa) { 
            die("Empresa no encontrada."); 
        }

        // --- PROCESAR RIF GUARDADO PARA EL FORMULARIO ---
        // Si el RIF tiene el formato correcto (Letra-Número), lo separamos
        if (!empty($empresa['rif']) && strpos($empresa['rif'], '-') !== false) {
            $partes_rif = explode('-', $empresa['rif'], 2);
            $rif_tipo = $partes_rif[0];
            $rif_numero = $partes_rif[1];
        } else {
            $rif_numero = $empresa['rif'];
        }
    }

    // LÓGICA DE ACTUALIZACIÓN CON FILTROS DE DUPLICADOS
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_empresa'])) {
        $id_update = $_POST['id'];
        $nombre = trim($_POST['nombre_empresa']);
        $direccion = trim($_POST['direccion']);
        $correo = trim($_POST['correo']);
        $codigo = intval($_POST['codigo_empresa']);
        $estatus = $_POST['estatus'];
        
        // --- PROCESAMIENTO DEL RIF EN EL POST ---
        $rif_tipo = $_POST['rif_tipo'] ?? 'J';
        $rif_numero = trim($_POST['rif_numero'] ?? '');
        $rif = !empty($rif_numero) ? $rif_tipo . '-' . $rif_numero : '';

        // --- VALIDACIONES DE SEGURIDAD (BACKEND) ---
        if (empty($nombre) || empty($rif_numero) || $codigo <= 0) {
            $mensaje = "<div class='alert error'>⚠️ Código de empresa, Nombre y RIF son obligatorios.</div>";
        } 
        // Validamos formato del número del RIF
        else if (!preg_match('/^[0-9]{7,9}-[0-9A-Za-z]{1}$/', $rif_numero)) {
            $mensaje = "<div class='alert error'>⚠️ El formato del número de RIF es inválido. Debe contener solo números, un guion y el dígito verificador final (Ej: 12345678-9).</div>";
        } 
        else {
            // --- PASO CLAVE 1: VALIDACIÓN DE DUPLICIDAD DEL CÓDIGO (Excluyendo la empresa actual) ---
            $stmtCheckCodigo = $conn->prepare("SELECT COUNT(*) FROM mis_empresas WHERE codigo_empresa = ? AND id <> ?");
            $stmtCheckCodigo->execute([$codigo, $id_update]);
            $existeCodigo = $stmtCheckCodigo->fetchColumn();

            // --- PASO CLAVE 2: VALIDACIÓN DE DUPLICIDAD DEL RIF (Excluyendo la empresa actual) ---
            $stmtCheckRif = $conn->prepare("SELECT COUNT(*) FROM mis_empresas WHERE rif = ? AND id <> ?");
            $stmtCheckRif->execute([$rif, $id_update]);
            $existeRif = $stmtCheckRif->fetchColumn();

            if ($existeCodigo > 0) {
                $mensaje = "<div class='alert error'>⚠️ El Código de Empresa #{$codigo} ya se encuentra registrado en otra entidad.</div>";
            } else if ($existeRif > 0) {
                $mensaje = "<div class='alert error'>⚠️ El RIF {$rif} ya está registrado para otra empresa. Verifique los datos.</div>";
            } else {
                // Procedemos a actualizar si todo está en orden
                $sql = "UPDATE mis_empresas SET nombre_empresa = ?, rif = ?, direccion = ?, correo = ?, codigo_empresa = ?, estatus = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$nombre, $rif, $direccion, $correo, $codigo, $estatus, $id_update]);
                
                $mensaje = "<div class='alert success'><i class='fas fa-check-circle'></i> Empresa actualizada correctamente. <a href='mis_empresas.php' style='font-weight:bold; color:#2e7d32; text-decoration:underline;'>Volver al listado</a></div>";
                
                // Sincronizamos las variables de vista para que reflejen el cambio inmediatamente en el formulario
                $empresa['nombre_empresa'] = $nombre;
                $empresa['rif'] = $rif;
                $empresa['direccion'] = $direccion;
                $empresa['correo'] = $correo;
                $empresa['codigo_empresa'] = $codigo;
                $empresa['estatus'] = $estatus;
            }
        }
    }
} catch (PDOException $e) {
    die("Error de sistema: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Empresa - Locatel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --green-locatel: #00953b; 
            --orange-locatel: #ffa400; 
            --bg-main: #f4f7f6;
            --white: #ffffff;
        }
        body { 
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: var(--bg-main); 
            display: flex;
            height: 100vh;
        }
        
        .sidebar {
            width: 240px;
            background-color: var(--green-locatel);
            color: white;
            flex-shrink: 0;
        }
        .sidebar-logo { padding: 20px; text-align: center; }
        .sidebar-logo img { width: 150px; filter: brightness(0) invert(1); }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: white;
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
            gap: 12px;
        }
        .active-sub {
            background-color: var(--white) !important;
            color: var(--green-locatel) !important;
            border-left: 5px solid var(--orange-locatel);
        }

        .main-container { flex-grow: 1; display: flex; flex-direction: column; }
        
        .top-navbar {
            background: var(--white);
            height: 65px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 35px;
            border-bottom: 2px solid #e0e0e0;
        }

        .content { padding: 40px; }
        
        .title-section h1 {
            color: var(--green-locatel);
            font-size: 28px;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0;
        }
        .orange-line {
            width: 50px;
            height: 5px;
            background-color: var(--orange-locatel);
            margin-top: 8px;
            border-radius: 3px;
        }

        .form-card { 
            background: var(--white); 
            margin-top: 30px;
            border-radius: 8px; 
            border-left: 5px solid var(--orange-locatel);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
            padding: 30px;
        }
        .form-header {
            color: var(--green-locatel);
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; }
        
        .input-group { display: flex; flex-direction: column; gap: 8px; }
        .input-group label { font-size: 12px; font-weight: bold; color: #666; }
        .input-group input, .input-group select { 
            padding: 12px; border: 1px solid #ccc; border-radius: 5px; outline: none; font-size: 14px; box-sizing: border-box;
        }
        .input-group input:focus, .input-group select:focus { border-color: var(--green-locatel); }
        
        .rif-container { display: flex; gap: 10px; }
        .rif-container select { width: 80px; flex-shrink: 0; }
        .rif-container input { flex-grow: 1; }
        
        .btn-submit { 
            grid-column: span 2;
            background-color: var(--green-locatel); color: white; border: none; padding: 15px; 
            border-radius: 5px; font-weight: bold; font-size: 14px; cursor: pointer; text-transform: uppercase;
            margin-top: 10px; transition: 0.3s;
        }
        .btn-submit:hover { background-color: #007a30; transform: translateY(-2px); }
        
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="images/Logo_Locatel (1).png" alt="Logo" class="mini-logo">
    </div>
    <nav class="sidebar-nav">
        <?php $pagina_actual = basename($_SERVER['PHP_SELF']); ?>

        <?php if ($es_admin || (isset($_SESSION['acc_facturas']) && $_SESSION['acc_facturas'] == 1)): ?>
            <a href="vistafacturas.php" class="<?php echo ($pagina_actual == 'vistafacturas.php') ? 'active-sub' : ''; ?>">
                <i class="fas fa-file-invoice"></i> FACTURACIÓN
            </a>
        <?php endif; ?>

        <?php if ($es_admin || (isset($_SESSION['acc_proveedores']) && $_SESSION['acc_proveedores'] == 1)): ?>
            <a href="perfilproveedores.php" class="<?php echo ($pagina_actual == 'perfilproveedores.php') ? 'active-sub' : ''; ?>">
                <i class="fas fa-truck"></i> PROVEEDORES
            </a>
        <?php endif; ?>

        <?php if ($es_admin || (isset($_SESSION['acc_empresas']) && $_SESSION['acc_empresas'] == 1)): ?>
            <a href="mis_empresas.php" class="<?php echo ($pagina_actual == 'mis_empresas.php' || $pagina_actual == 'creacion_empresa.php') ? 'active-sub' : ''; ?>">
                <i class="fas fa-building"></i> MIS EMPRESAS
            </a>
        <?php endif; ?>
        
        <?php if ($es_admin): ?>
            <a href="gestion_usuarios.php" class="<?php echo ($pagina_actual == 'gestion_usuarios.php') ? 'active-sub' : ''; ?>" style="margin-top: 10px; border-top: 1px solid #eee; padding-top: 15px; color: #ffffff;">
                <i class="fas fa-users-cog"></i> GESTIÓN USUARIOS
            </a>
        <?php endif; ?>
    </nav>
</aside>

<div class="main-container">
    <header class="top-navbar">
        <div style="color: #000000; font-size: 14px;">Modificación de Datos Maestros</div>
        <div style="font-weight: bold;">
            IUP SANTIAGO MARIÑO <i class="fas fa-user-circle" style="color: var(--green-locatel); margin-left: 10px;"></i>
        </div>
    </header>

    <main class="content">
        <div class="title-section">
            <h1>Editar Empresa Propietaria</h1>
            <div class="orange-line"></div>
        </div>

        <?php echo $mensaje; ?>

        <div class="form-card">
            <div class="form-header">
                <i class="fas fa-edit"></i> Actualizar Datos de la Razón Social
            </div>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($empresa['id']); ?>">

                <div class="form-grid">
                    <div class="input-group">
                        <label>Nombre de la Empresa</label>
                        <input type="text" name="nombre_empresa" required value="<?php echo htmlspecialchars($empresa['nombre_empresa']); ?>">
                    </div>
                    
                    <div class="input-group">
                        <label>RIF (Registro de Información Fiscal)</label>
                        <div class="rif-container">
                            <select name="rif_tipo">
                                <option value="V" <?php echo $rif_tipo === 'V' ? 'selected' : ''; ?>>V</option>
                                <option value="E" <?php echo $rif_tipo === 'E' ? 'selected' : ''; ?>>E</option>
                                <option value="J" <?php echo $rif_tipo === 'J' ? 'selected' : ''; ?>>J</option>
                                <option value="G" <?php echo $rif_tipo === 'G' ? 'selected' : ''; ?>>G</option>
                            </select>
                            <input type="text" 
                                   name="rif_numero" 
                                   required 
                                   placeholder="12345678-9" 
                                   pattern="^[0-9]{7,9}-[0-9A-Za-z]{1}$" 
                                   title="Introduzca un formato válido: de 7 a 9 números, un guion y un dígito verificador. (Ejemplo: 12345678-9)"
                                   oninput="this.value = this.value.replace(/[^0-9-kK]/g, '');"
                                   value="<?php echo htmlspecialchars($rif_numero); ?>">
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Código de Empresa (Numérico)</label>
                        <input type="number" name="codigo_empresa" required value="<?php echo htmlspecialchars($empresa['codigo_empresa']); ?>">
                    </div>
                    
                    <div class="input-group">
                        <label>Estatus de la Entidad</label>
                        <select name="estatus">
                            <option value="Activo" <?php echo ($empresa['estatus'] === 'Activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="Inactivo" <?php echo ($empresa['estatus'] === 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Correo Electrónico</label>
                        <input type="email" name="correo" placeholder="ejemplo@locatel.com.ve" value="<?php echo htmlspecialchars($empresa['correo'] ?? ''); ?>">
                    </div>
                    
                    <div class="input-group">
                        <label>Dirección Fiscal</label>
                        <input type="text" name="direccion" value="<?php echo htmlspecialchars($empresa['direccion'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" name="actualizar_empresa" class="btn-submit">
                        <i class="fas fa-save"></i> Guardar Cambios Realizados
                    </button>
                </div>
            </form>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="mis_empresas.php" style="color: var(--green-locatel); text-decoration: none; font-weight: bold;">
                <i class="fas fa-arrow-left"></i> Volver al listado
            </a>
        </div>
    </main>
</div>

</body>
</html>