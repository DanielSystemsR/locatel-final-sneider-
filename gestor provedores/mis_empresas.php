<?php
session_start();

// 1. CONTROL DE SESIÓN
if (!isset($_SESSION['user_id'])) {
    header("Location: iniciosesion.php");
    exit();
}

$es_admin = (isset($_SESSION['user_rol']) && strtolower(trim($_SESSION['user_rol'])) === 'admin');

// 2. PROTECCIÓN DEL MÓDULO (Acceso si es admin o tiene permiso de empresas)
if (!$es_admin && (!isset($_SESSION['acc_empresas']) || $_SESSION['acc_empresas'] == 0)) {
    header("Location: perfilproveedores.php?error=sin_acceso");
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: iniciosesion.php");
    exit();
}

// Configuración de la base de datos
$host = 'db'; 
$dbname = 'LocatelDB';
$user = 'sa'; 
$pass = 'LocatelPass2026!';

$empresas = [];

try {
    $dsn = "sqlsrv:Server=$host;Database=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- LÓGICA DE BÚSQUEDA ---
    $search = $_GET['search'] ?? ''; 
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

    $sql = "SELECT TOP $limit * FROM mis_empresas WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (nombre_empresa LIKE :search1 OR rif LIKE :search2 OR correo LIKE :search3)";
        $params[':search1'] = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
    }

    $sql .= " ORDER BY fecha_registro DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params); 
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de sistema: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Empresas - Locatel</title>
    <link rel="stylesheet" href="vista_factura.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .btn-logout-top {
            color: #d32f2f; text-decoration: none; font-weight: bold; margin-left: 15px;
            padding: 5px 12px; border: 1px solid #d32f2f; border-radius: 4px; font-size: 13px;
            transition: 0.3s; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-logout-top:hover { background: #d32f2f; color: white; }
        .status-pill { padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
        
        /* Estilos para las acciones */
        .actions i { cursor: pointer; margin: 0 8px; transition: 0.2s; font-size: 16px; }
        .actions .fa-eye { color: #00953b; } /* Verde Locatel para Ver */
        .actions .fa-edit { color: #00953b; } /* Verde Locatel para Editar */
        .actions i:hover { transform: scale(1.2); }
    </style>
</head>
<body>

 <aside class="sidebar">
    <div class="sidebar-logo">
        <img src="images/Logo_Locatel (1).png" alt="Logo" class="mini-logo">
    </div>
    <nav class="sidebar-nav">
        <?php if ($es_admin || (isset($_SESSION['acc_facturas']) && $_SESSION['acc_facturas'] == 1)): ?>
            <a href="vistafacturas.php"><i class="fas fa-home"></i> FACTURACIÓN</a>
        <?php endif; ?>

        <?php if ($es_admin || (isset($_SESSION['acc_proveedores']) && $_SESSION['acc_proveedores'] == 1)): ?>
            <a href="perfilproveedores.php"><i class="fas fa-truck"></i> PROVEEDORES</a>
        <?php endif; ?>

        <a href="mis_empresas.php" class="active-sub"><i class="fas fa-building"></i> MIS EMPRESAS</a>
        
        <?php if ($es_admin): ?>
            <a href="gestion_usuarios.php" style="margin-top: 10px; border-top: 1px solid #eee; padding-top: 15px; color: #ffffff;">
                <i class="fas fa-users-cog"></i> GESTIÓN USUARIOS
            </a>
        <?php endif; ?>
    </nav>
</aside>

    <main class="main-content">
        <header class="top-navbar">
            <div class="breadcrumb"><span>Administración / Mis Empresas</span></div>

            <div class="search-box">
                <form method="GET" id="filterForm" class="input-wrapper">
                    <input type="text" name="search" placeholder="Buscar por Nombre, RIF o Correo..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <i class="fas fa-search" onclick="document.getElementById('filterForm').submit()"></i>
                </form>
            </div>

            <div class="user-info">
                <span class="user-email" style="font-weight: bold; color: #444;">
                    <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?>
                </span>
                <i class="fas fa-user-circle user-icon" style="font-size: 24px; margin-left: 10px; color: #00953b;"></i>
                <a href="mis_empresas.php?logout=true" class="btn-logout-top">
                    <i class="fas fa-power-off"></i> Salir
                </a>
            </div>
        </header>

        <section class="content-header">
            <h1>Gestión de Empresas Propias</h1>
            <?php if ($es_admin || (isset($_SESSION['acc_empresas_crear']) && $_SESSION['acc_empresas_crear'] == 1)): ?>
                <a href="creacion_empresa.php" class="btn-save">
                    <i class="fas fa-plus-circle"></i> NUEVA EMPRESA
                </a>
            <?php endif; ?>
        </section>

        <div class="table-container">
            <table class="facturas-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre de Empresa</th>
                        <th>RIF</th>
                        <th>Correo de Contacto</th>
                        <th>Estatus</th> 
                        <th style="text-align: center;">Acciones</th> 
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($empresas) > 0): ?>
                        <?php foreach ($empresas as $e): ?>
                            <tr>
                                <td style="font-weight: bold; color: #00953b;">#<?php echo htmlspecialchars($e['codigo_empresa']); ?></td>
                                <td><?php echo htmlspecialchars($e['nombre_empresa']); ?></td>
                                <td><?php echo htmlspecialchars($e['rif']); ?></td>
                                <td><?php echo htmlspecialchars($e['correo'] ?? 'Sin correo'); ?></td>
                                <td>
                                    <?php 
                                        $estado = $e['estatus'] ?? 'Activo'; 
                                        $color = ($estado == 'Activo') ? "#00953b" : "#ff671d";
                                        $bg = ($estado == 'Activo') ? "#e8f5e9" : "#fff3e0";
                                    ?>
                                    <span class="status-pill" style="color:<?php echo $color; ?>; background-color:<?php echo $bg; ?>; border:1px solid <?php echo $color; ?>;">
                                        <i class="fas fa-check-circle"></i> <?php echo $estado; ?>
                                    </span>
                                </td>
                                <td class="actions" style="text-align: center;">
                                    
                                    
                                    <!-- Acción Editar -->
                                    <i class="fas fa-edit" title="Editar Empresa" onclick="window.location.href='editar_empresa.php?id=<?php echo $e['id']; ?>'"></i>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 40px;">No hay empresas registradas con ese criterio.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>