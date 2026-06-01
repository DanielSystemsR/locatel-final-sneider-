<?php
ob_start(); 
session_start();

// 1. CONTROL DE SESIÓN Y ROLES
if (!isset($_SESSION['user_id'])) {
    header("Location: iniciosesion.php");
    exit();
}

// Definir si es administrador
$es_admin = (isset($_SESSION['user_rol']) && strtolower(trim($_SESSION['user_rol'])) === 'admin');

// SEGURIDAD: Verificar sesión y rol de administrador
if (!isset($_SESSION['user_id']) || strtolower(trim($_SESSION['user_rol'])) !== 'admin') {
    header("Location: vistafacturas.php?error=acceso_denegado");
    exit();
}

// 2. CONEXIÓN A BASE DE DATOS
$host = 'db'; $dbname = 'LocatelDB'; $user = 'sa'; $pass = 'LocatelPass2026!';

try {
    $dsn = "sqlsrv:Server=$host;Database=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $mensaje = "";

    // 3. LÓGICA DE INSERCIÓN CON VALIDACIÓN DE DUPLICADOS
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_empresa'])) {
        $nombre = trim($_POST['nombre_empresa']);
        $direccion = trim($_POST['direccion']);
        $correo = trim($_POST['correo']); 
        $codigo = intval($_POST['codigo_empresa']); 
        
        // --- PROCESAMIENTO DEL RIF ---
        $rif_tipo = $_POST['rif_tipo'] ?? 'J';
        $rif_numero = trim($_POST['rif_numero'] ?? '');
        // Unimos la letra seleccionada y el número (ejemplo: J-12345678-9)
        $rif = !empty($rif_numero) ? $rif_tipo . '-' . $rif_numero : '';

        // --- VALIDACIONES DE SEGURIDAD (BACKEND) ---
        if (empty($nombre) || empty($rif_numero) || $codigo <= 0) {
            $mensaje = "<div class='alert error'>Código de empresa, Nombre y RIF son obligatorios.</div>";
        } 
        // Validamos que el número cumpla estrictamente: entre 7 y 9 dígitos, un guion obligatorio, y un número o letra al final
        else if (!preg_match('/^[0-9]{7,9}-[0-9A-Za-z]{1}$/', $rif_numero)) {
            $mensaje = "<div class='alert error'>⚠️ El formato del número de RIF es inválido. Debe contener solo números, un guion y el dígito verificador final (Ej: 12345678-9).</div>";
        } 
        else {
            // --- PASO CLAVE 1: VALIDACIÓN DE DUPLICIDAD DEL CÓDIGO ---
            $stmtCheckCodigo = $conn->prepare("SELECT COUNT(*) FROM mis_empresas WHERE codigo_empresa = ?");
            $stmtCheckCodigo->execute([$codigo]);
            $existeCodigo = $stmtCheckCodigo->fetchColumn();

            // --- PASO CLAVE 2: VALIDACIÓN DE DUPLICIDAD DEL RIF COMPLETE ---
            $stmtCheckRif = $conn->prepare("SELECT COUNT(*) FROM mis_empresas WHERE rif = ?");
            $stmtCheckRif->execute([$rif]);
            $existeRif = $stmtCheckRif->fetchColumn();

            if ($existeCodigo > 0) {
                $mensaje = "<div class='alert error'>⚠️ El Código de Empresa #{$codigo} ya se encuentra registrado. Introduzca un código único.</div>";
            } else if ($existeRif > 0) {
                $mensaje = "<div class='alert error'>⚠️ El RIF {$rif} ya se encuentra registrado para otra empresa. Verifique los datos.</div>";
            } else {
                // Si pasa todos los filtros, procedemos a guardar
                $sql = "INSERT INTO mis_empresas (nombre_empresa, rif, direccion, correo, codigo_empresa, estatus) 
                        VALUES (?, ?, ?, ?, ?, 'Activo')";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([$nombre, $rif, $direccion, $correo, $codigo]);
                
                header("Location: mis_empresas.php?success=1");
                exit();
            }
        }
    }
} catch (PDOException $e) {
    $mensaje = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Empresa | Locatel</title>
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .input-group { display: flex; flex-direction: column; gap: 8px; }
        .input-group label { font-size: 12px; font-weight: bold; color: #666; }
        .input-group input, .input-group select {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
        }

        .input-group input:focus, .input-group select:focus { border-color: var(--green-locatel); }

        .rif-container {
            display: flex;
            gap: 10px;
        }
        .rif-container select {
            width: 80px;
            flex-shrink: 0;
        }
        .rif-container input {
            flex-grow: 1;
        }

        .btn-submit {
            grid-column: span 2;
            background-color: var(--green-locatel);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            text-transform: uppercase;
            margin-top: 10px;
            transition: 0.3s;
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
            <div style="color: #000000; font-size: 14px;">Creación Empresas</div>
            <div style="font-weight: bold;">
                IUP SANTIAGO MARIÑO <i class="fas fa-user-circle" style="color: var(--green-locatel); margin-left: 10px;"></i>
            </div>
        </header>

        <main class="content">
            <div class="title-section">
                <h1>Nueva Empresa</h1>
                <div class="orange-line"></div>
            </div>

            <?= $mensaje ?>

            <div class="form-card">
                <div class="form-header">
                    <i class="fas fa-plus-circle"></i> Datos de la Razón Social
                </div>
                <form method="POST">
                    <div class="form-grid">
                        <div class="input-group">
                            <label>Nombre de la Empresa</label>
                            <input type="text" name="nombre_empresa" required value="<?php echo isset($_POST['nombre_empresa']) ? htmlspecialchars($_POST['nombre_empresa']) : ''; ?>">
                        </div>
                        
                        <div class="input-group">
                            <label>RIF (Registro de Información Fiscal)</label>
                            <div class="rif-container">
                                <?php $tipo_seleccionado = $_POST['rif_tipo'] ?? 'J'; ?>
                                <select name="rif_tipo">
                                    <option value="V" <?php echo $tipo_seleccionado === 'V' ? 'selected' : ''; ?> title="Persona Natural Venezolana: Para ciudadanos venezolanos.">V</option>
                                    <option value="E" <?php echo $tipo_seleccionado === 'E' ? 'selected' : ''; ?> title="Persona Natural Extranjera: Para individuos de otra nationality residentes en el país.">E</option>
                                    <option value="J" <?php echo $tipo_seleccionado === 'J' ? 'selected' : ''; ?> title="Persona Jurídica: Para empresas, sociedades, asociaciones y emprendimientos formales.">J</option>
                                    <option value="G" <?php echo $tipo_seleccionado === 'G' ? 'selected' : ''; ?> title="Gobierno / Entidades Públicas: Para instituciones, ministerios y organismos del Estado.">G</option>
                                </select>
                                <input type="text" 
                                       name="rif_numero" 
                                       required 
                                       placeholder="12345678-9" 
                                       pattern="^[0-9]{7,9}-[0-9A-Za-z]{1}$" 
                                       title="Introduzca un formato válido: de 7 a 9 números, un guion y un dígito verificador. (Ejemplo: 12345678-9)"
                                       oninput="this.value = this.value.replace(/[^0-9-kK]/g, '');"
                                       value="<?php echo isset($_POST['rif_numero']) ? htmlspecialchars($_POST['rif_numero']) : ''; ?>">
                            </div>
                        </div>

                        <div class="input-group">
                            <label>Código de Empresa (Numérico)</label>
                            <input type="number" name="codigo_empresa" required value="<?php echo isset($_POST['codigo_empresa']) ? htmlspecialchars($_POST['codigo_empresa']) : ''; ?>">
                        </div>
                        <div class="input-group">
                            <label>Correo Electrónico</label>
                            <input type="email" name="correo" placeholder="ejemplo@locatel.com.ve" value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
                        </div>
                        <div class="input-group" style="grid-column: span 2;">
                            <label>Dirección Fiscal</label>
                            <input type="text" name="direccion" value="<?php echo isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : ''; ?>">
                        </div>
                        <button type="submit" name="registrar_empresa" class="btn-submit">
                            <i class="fas fa-save"></i> Guardar Nueva Empresa
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