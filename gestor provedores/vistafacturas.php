<?php
session_start();

require_once 'auth_check.php'; 

// 1. CONTROL DE SESIÓN
if (!isset($_SESSION['user_id'])) {
    header("Location: iniciosesion.php");
    exit();
}

// 2. DEFINICIÓN DE VARIABLES DE PERMISO (Esto corrige el Warning)
$es_admin = (isset($_SESSION['user_rol']) && strtolower(trim($_SESSION['user_rol'])) === 'admin');

// 3. PROTECCIÓN DEL MÓDULO
if (!$es_admin && (!isset($_SESSION['acc_facturas']) || $_SESSION['acc_facturas'] == 0)) {
    header("Location: perfilproveedores.php?error=sin_acceso");
    exit();
}

// 4. LÓGICA DE CIERRE DE SESIÓN
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

$facturas = [];

try {
    $dsn = "sqlsrv:Server=$host;Database=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

   // --- LÓGICA DE FILTRADO Y BÚSQUEDA ---
    $fecha_inicio = $_GET['fecha-inicio'] ?? '';
    $fecha_fin    = $_GET['fecha-fin'] ?? '';
    $tipo_fecha   = $_GET['tipo-fecha'] ?? 'f.fecha_registro';
    $limit        = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $search       = $_GET['search'] ?? ''; 
    
    // 🔥 1. Capturar el nuevo filtro de orden (Por defecto Ascendente)
    $orden        = $_GET['orden'] ?? 'ASC';

    $columnas_validas = ['f.fecha_registro', 'f.fecha_vencimiento', 'f.fecha_recibida'];
    if (!in_array($tipo_fecha, $columnas_validas)) { 
        $tipo_fecha = 'f.fecha_registro'; 
    }

    // 🔥 2. Validar el orden de forma estricta (Evita inyección SQL)
    if ($orden !== 'ASC' && $orden !== 'DESC') {
        $orden = 'ASC';
    }

    $sql = "SELECT TOP $limit f.*, p.nombre AS nombre_proveedor 
            FROM facturas f 
            LEFT JOIN proveedores p ON f.codigo_proveedor = p.id 
            WHERE 1=1";

    $params = [];

    if (!empty($search)) {
        $sql .= " AND (f.nro_factura LIKE :search1 OR p.nombre LIKE :search2)";
        $params[':search1'] = "%$search%";
        $params[':search2'] = "%$search%";
    }

    if (!empty($fecha_inicio) && !empty($fecha_fin)) {
        $sql .= " AND $tipo_fecha >= :inicio AND $tipo_fecha <= :fin";
        $params[':inicio'] = $fecha_inicio . " 00:00:00";
        $params[':fin']    = $fecha_fin . " 23:59:59";
    }

    // 🔥 3. Aplicar la variable $orden en la consulta
    $sql .= " ORDER BY $tipo_fecha $orden";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 🔥 Exportar la lista actual de facturas a Excel con formato bonito
    if (isset($_GET['export']) && $_GET['export'] == '1') {
        header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"facturas_exportadas.xls\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo "\xEF\xBB\xBF";

        echo "<html><head><meta charset=\"UTF-8\"/><style>";
        echo "body{font-family: Arial, sans-serif;}";
        echo "table{border-collapse:collapse;width:100%;}";
        echo "th{background:#00953b;color:#fff;padding:10px 8px;border:1px solid #ccc;}";
        echo "td{padding:8px 8px;border:1px solid #ccc;color:#333;}";
        echo "tr:nth-child(even){background:#f7f7f7;}";
        echo "tr:hover{background:#eaf7ea;}";
        echo "</style></head><body>";
        echo "<table><thead><tr>";
        echo "<th>Nro. Factura</th>";
        echo "<th>Código SAP</th>";
        echo "<th>Proveedor</th>";
        echo "<th>Fecha Recibida</th>";
        echo "<th>Vencimiento</th>";
        echo "<th>Monto Bs.</th>";
        echo "<th>Monto $</th>";
        echo "<th>Estatus</th>";
        echo "</tr></thead><tbody>";

        foreach ($facturas as $f) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($f['nro_factura']) . "</td>";
            echo "<td>" . htmlspecialchars($f['codigo_proveedor']) . "</td>";
            echo "<td>" . htmlspecialchars($f['nombre_proveedor']) . "</td>";
            echo "<td>" . (!empty($f['fecha_recibida']) ? date('d/m/Y', strtotime($f['fecha_recibida'])) : '---') . "</td>";
            echo "<td>" . (!empty($f['fecha_vencimiento']) ? date('d/m/Y', strtotime($f['fecha_vencimiento'])) : '---') . "</td>";
            echo "<td style=\"mso-number-format:'\#\,\#\#0\,00'\">" . number_format($f['monto_bs'], 2, ',', '.') . "</td>";
            echo "<td style=\"mso-number-format:'0\.00'\">" . number_format($f['monto_usd'], 2, '.', ',') . "</td>";
            echo "<td>" . htmlspecialchars($f['estatus'] ?? 'Pendiente') . "</td>";
            echo "</tr>";
        }

        echo "</tbody></table></body></html>";
        exit();
    }

} catch (PDOException $e) {
    die("Error de sistema: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Facturas - Locatel</title>
    <link rel="stylesheet" href="vista_factura.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .btn-logout-top {
            color: #d32f2f; text-decoration: none; font-weight: bold; margin-left: 15px;
            padding: 5px 12px; border: 1px solid #d32f2f; border-radius: 4px; font-size: 13px;
            transition: 0.3s; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-logout-top:hover { background: #d32f2f; color: white; }
        .filter-group { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; margin-bottom: 20px; }
        .filter-container select, .filter-container input { padding: 8px; border-radius: 5px; border: 1px solid #ccc; outline: none; }
        .status-pill { padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
        
        /* 🔥 CORRECCIÓN DEL DISEÑO DE ACCIONES EN LA TABLA 🔥 */
        .actions-cell {
            white-space: nowrap; /* Evita que la celda colapse hacia abajo */
            width: 1%; /* Fuerza a la celda a usar solo el espacio mínimo que sus hijos demanden */
        }
        .actions-wrapper { 
            display: inline-flex; 
            gap: 14px; 
            align-items: center; 
            justify-content: center;
            flex-wrap: nowrap; /* Evita por completo que los iconos salten de línea */
        }
        .actions-wrapper i, .actions-wrapper a { 
            cursor: pointer; 
            transition: 0.2s; 
            text-decoration: none; 
            font-size: 15px; 
            display: inline-block;
        }
        .actions-wrapper .fa-eye { color: #00953b; }
        .actions-wrapper .fa-edit { color: #00953b; }
        
        /* Colores de los botones de descarga */
        .actions-wrapper .btn-download-pdf { color: #76bc43; } /* Verde secundario Locatel */
        .actions-wrapper .btn-download-comp { color: #0d47a1; } /* Azul corporativo */
        .actions-wrapper .btn-download-anx { color: #ff671d; }  /* Naranja */
        
        /* Efecto Hover sutil para feedback visual */
        .actions-wrapper i:hover, .actions-wrapper a:hover {
            transform: scale(1.15);
            opacity: 0.85;
        }
        .btn-export {
            background: #00953b;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 12px;
            transition: 0.2s;
        }
        .btn-export:hover {
            background: #007a2c;
        }
    </style>
</head>
<body>

 <aside class="sidebar">
    <div class="sidebar-logo">
        <img src="images/Logo_Locatel (1).png" alt="Logo" class="mini-logo">
    </div>
    <nav class="sidebar-nav">
        <?php if ($es_admin || (isset($_SESSION['acc_facturas']) && $_SESSION['acc_facturas'] == 1)): ?>
            <a href="vistafacturas.php" class="active-sub">
                <i class="fas fa-home"></i> FACTURACIÓN
            </a>
        <?php endif; ?>

        <?php if ($es_admin || (isset($_SESSION['acc_proveedores']) && $_SESSION['acc_proveedores'] == 1)): ?>
            <a href="perfilproveedores.php">
                <i class="fas fa-truck"></i> PROVEEDORES
            </a>
        <?php endif; ?>

        <?php if ($es_admin || (isset($_SESSION['acc_empresas']) && $_SESSION['acc_empresas'] == 1)): ?>
        <a href="mis_empresas.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'mis_empresas.php') ? 'active-sub' : ''; ?>">
            <i class="fas fa-building"></i> MIS EMPRESAS
        </a>
    <?php endif; ?>
        
        <?php if ($es_admin): ?>
            <a href="gestion_usuarios.php" style="margin-top: 10px; border-top: 1px solid #eee; padding-top: 15px; color: #ffffff;">
                <i class="fas fa-users-cog"></i> GESTIÓN USUARIOS
            </a>
        <?php endif; ?>
    </nav>
</aside>

    <main class="main-content">
        <header class="top-navbar">
            <div class="breadcrumb"><span>Facturas de proveedores</span></div>

            <div class="search-box">
                <div class="input-wrapper">
                    <input type="text" form="filterForm" name="search" id="searchInput" 
                           placeholder="Buscar nro. factura o proveedor..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <i class="fas fa-search" onclick="document.getElementById('filterForm').submit()"></i>
                </div>
            </div>

            <div class="user-info">
                <span class="user-email" style="font-weight: bold; color: #444;">
                    <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?>
                </span>
                <i class="fas fa-user-circle user-icon" style="font-size: 24px; margin-left: 10px; color: #00953b;"></i>
                <a href="vistafacturas.php?logout=true" class="btn-logout-top">
                    <i class="fas fa-power-off"></i> Salir
                </a>
            </div>
        </header>

        <section class="content-header">
            <h1>Listado de Facturas</h1>
            <?php if ($es_admin || (isset($_SESSION['f_crear']) && $_SESSION['f_crear'] == 1)): ?>
                <a href="creacion_nueva_fac.php" class="btn-save">
                    <i class="fas fa-plus-circle"></i> NUEVA FACTURA
                </a>
            <?php endif; ?>
        </section>

        <div class="filter-container">
            <h2>Panel de Filtros</h2>
            <form method="GET" id="filterForm" action="vistafacturas.php" class="filter-group">
                <div class="input-field">
                    
                    <label>Criterio de fecha:</label>
                    <select name="tipo-fecha" onchange="this.form.submit();">
                        <option value="f.fecha_registro" <?php echo $tipo_fecha == 'f.fecha_registro' ? 'selected' : ''; ?>>Fecha de Registro</option>
                        <option value="f.fecha_vencimiento" <?php echo $tipo_fecha == 'f.fecha_vencimiento' ? 'selected' : ''; ?>>Fecha de Vencimiento</option>
                        <option value="f.fecha_recibida" <?php echo $tipo_fecha == 'f.fecha_recibida' ? 'selected' : ''; ?>>Fecha de Recepción</option>
                    </select>
                </div>
                            <div class="input-field">
                    <label>Orden:</label>
                    <select name="orden" onchange="this.form.submit();">
                        <option value="ASC" <?php echo $orden == 'ASC' ? 'selected' : ''; ?>>Menor a Mayor </option>
                        <option value="DESC" <?php echo $orden == 'DESC' ? 'selected' : ''; ?>>Mayor a Menor </option>
                    </select>
                </div>

                <div class="input-field">
                    <label>Desde:</label>
                    <input type="date" name="fecha-inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                </div>

                <div class="input-field">
                    <label>Hasta:</label>
                    <input type="date" name="fecha-fin" value="<?php echo htmlspecialchars($fecha_fin); ?>">
                </div>

                <div class="input-field">
                    <label>Mostrar:</label>
                    <select name="limit" onchange="this.form.submit();">
                        <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10 registros</option>
                        <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25 registros</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 registros</option>
                    </select>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                    <button type="submit" class="btn-filter">Aplicar Filtros</button>
                    <button type="submit" name="export" value="1" class="btn-export">Exportar a Excel</button>
                    <?php if (!empty($fecha_inicio) || !empty($search) || !empty($limit) || !empty($tipo_fecha) || !empty($orden)): ?>
                        <a href="vistafacturas.php" style="text-decoration:none; padding:10px; background:#666; color:white; border-radius:5px; font-size:12px;">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table class="facturas-table">
                <thead>
                    <tr>
                        <th>Nro. Factura</th>
                        <th>Código SAP</th>
                        <th>Proveedor</th>
                        <th>Fecha Recibida</th>
                        <th>Vencimiento</th>
                        <th>Monto Bs.</th>
                        <th>Monto $</th>
                        <th>Estatus</th> 
                        <th style="text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($facturas) > 0): ?>
                        <?php foreach ($facturas as $f): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($f['nro_factura']); ?></strong></td>
                                <td><?php echo htmlspecialchars($f['codigo_proveedor'] ?? '---'); ?></td>
                                <td><?php echo htmlspecialchars($f['nombre_proveedor'] ?? 'S/N'); ?></td>
                                <td><?php echo !empty($f['fecha_recibida']) ? date('d/m/Y', strtotime($f['fecha_recibida'])) : '---'; ?></td>
                                <td><?php echo !empty($f['fecha_vencimiento']) ? date('d/m/Y', strtotime($f['fecha_vencimiento'])) : '---'; ?></td>
                                <td><?php echo number_format($f['monto_bs'], 2, ',', '.'); ?> Bs.</td>
                                <td class="usd-cell"><?php echo number_format($f['monto_usd'], 2, '.', ','); ?> $</td>
                                <td>
                                    <?php 
                                        $estado = $f['estatus'] ?? 'Pendiente'; 
                                        switch($estado) {
                                            case 'Pagado': $color = "#00953b"; $bg = "#e8f5e9"; $icon = "fa-check-circle"; break;
                                            case 'Rechazado': $color = "#d32f2f"; $bg = "#ffebee"; $icon = "fa-times-circle"; break;
                                            default: $color = "#ff671d"; $bg = "#fff3e0"; $icon = "fa-clock"; break;
                                        }
                                    ?>
                                    <span class="status-pill" style="color:<?php echo $color; ?>; background-color:<?php echo $bg; ?>; border:1px solid <?php echo $color; ?>;">
                                        <i class="fas <?php echo $icon; ?>"></i> <?php echo $estado; ?>
                                    </span>
                                </td>
                                
                                <td class="actions-cell">
    <div class="actions-wrapper">
        
        <?php if ($es_admin || (isset($_SESSION['f_ver']) && $_SESSION['f_ver'] == 1)): ?>
            <i class="fas fa-eye" title="Ver" onclick="window.location.href='ver_factura.php?id=<?php echo $f['id']; ?>'"></i>
        <?php endif; ?>

        <?php if ($es_admin || (isset($_SESSION['f_edit']) && $_SESSION['f_edit'] == 1)): ?>
            <i class="fas fa-edit" title="Editar" onclick="window.location.href='editar_factura.php?id=<?php echo $f['id']; ?>'"></i>
        <?php endif; ?>

        <?php if ($es_admin || (isset($_SESSION['f_ver']) && $_SESSION['f_ver'] == 1)): ?>
            
        <?php if (!empty($f['comp1'])): ?>
        <a href="/uploads/<?php echo htmlspecialchars($f['comp1']); ?>" 
           download="<?php echo htmlspecialchars($f['comp1']); ?>" 
           class="btn-action btn-download-pdf" 
           title="Descargar Factura PDF"
           style="color: #00953b; margin-left: 5px;">
            <i class="fas fa-file-pdf"></i>
        </a>
    <?php endif; ?>

    <?php if (!empty($f['comp2'])): ?>
        <a href="/uploads/<?php echo htmlspecialchars($f['comp2']); ?>" 
           download="<?php echo htmlspecialchars($f['comp2']); ?>" 
           class="btn-action btn-download-comp" 
           title="Descargar Comprobante"
           style="color: #00953b; margin-left: 5px;">
            <i class="fas fa-file-invoice"></i>
        </a>
    <?php endif; ?>

    <?php if (!empty($f['comp3'])): ?>
        <a href="/uploads/<?php echo htmlspecialchars($f['comp3']); ?>" 
           download="<?php echo htmlspecialchars($f['comp3']); ?>" 
           class="btn-action btn-download-anexo" 
           title="Descargar Anexos"
           style="color: #00953b; margin-left: 5px;">
            <i class="fas fa-paperclip"></i>
        </a>
    <?php endif; ?>

        <?php endif; ?>
    </div>
</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" style="text-align: center; padding: 20px;">No se encontraron facturas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>