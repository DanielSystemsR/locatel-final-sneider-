<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- AGREGA ESTAS LÍNEAS AQUÍ ---
$host = 'db'; 
$dbname = 'LocatelDB';
$user = 'sa'; 
$pass = 'LocatelPass2026!';
// -------------------------------


try {
    $dsn = "sqlsrv:Server=$host;Database=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configurar para que lance excepciones en caso de error
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (isset($_GET['id'])) {
        $stmt = $conn->prepare("SELECT * FROM proveedores WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) die("Proveedor no encontrado.");
    } else {
        header("Location: perfilproveedores.php");
        exit();
    }
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

function fechaLatina($fecha) {
    if (empty($fecha) || $fecha == '0000-00-00') return "No registrada";
    return date('d/m/Y', strtotime($fecha));
}

// FUNCIÓN DE CARGA DE DOCUMENTOS: Usa la ruta de tu script de edición
function renderDocLink($filename, $label) {
    if (!empty($filename)) {
        // Sincronizado con: uploads/proveedores/
        $path = "uploads/proveedores/" . $filename;
        if (file_exists($path)) {
            return '<a href="'.$path.'" target="_blank" class="doc-link success"><i class="fas fa-file-pdf"></i> Ver '.htmlspecialchars($label).'</a>';
        }
    }
    return '<span class="doc-link empty"><i class="fas fa-times-circle"></i> '.$label.' no cargado</span>';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Expediente: <?php echo htmlspecialchars($p['nombre'] ?? 'Sin Nombre'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <style>
    /* Estilos para la pantalla */
    :root { --locatel-green: #00953b; --locatel-light-green: #76bc43; }
    body { font-family: 'Segoe UI', sans-serif; background-image: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('images/fondo.png');background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;; margin: 0; padding: 20px; }
    .view-container { background: white; padding: 35px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 1000px; margin: auto; }
    .view-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
    .full-width { grid-column: span 3; }
    .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
    .status-badge.active { background: #e8f5e9; color: var(--locatel-green); border: 1px solid var(--locatel-green); }
    .status-badge.inactive { background: #ffebee; color: #d32f2f; border: 1px solid #d32f2f; }
    .data-box { background: #f8f9fa; border: 1px solid #e9ecef; padding: 10px; border-radius: 6px; font-size: 14px; color: #333; min-height: 18px; }
    .view-section-title { border-left: 4px solid var(--locatel-light-green); padding-left: 10px; margin: 30px 0 15px 0; color: var(--locatel-green); font-weight: bold; text-transform: uppercase; font-size: 14px; }
    .bank-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .bank-card-view { background: #fdfdfd; border: 1px solid #eee; padding: 15px; border-radius: 8px; }
    code { font-family: 'Courier New', monospace; background: #eee; padding: 2px 5px; border-radius: 3px; font-size: 13px; color: #444; }
    .docs-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
    .doc-link { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid #eee; }
    .doc-link.success { background: #e8f5e9; color: #2e7d32; }
    .doc-link.empty { background: #f5f5f5; color: #999; }

    /* --- CONFIGURACIÓN CRÍTICA PARA IMPRESIÓN --- */
    @media print {
        @page { size: portrait; margin: 1cm; }
        body { background: white; padding: 0; }
        .no-print, .navigation-bar, .view-actions, .btn-edit, button { display: none !important; }
        .view-container { box-shadow: none; border: none; padding: 0; width: 100%; }
        
        /* Forzamos a que el grid se mantenga en la impresión */
        .view-grid { display: grid !important; grid-template-columns: repeat(3, 1fr) !important; gap: 10px !important; }
        .bank-grid { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 15px !important; }
        .docs-container { display: grid !important; grid-template-columns: repeat(3, 1fr) !important; }
        
        .data-box { background: #fff !important; border: 1px solid #ccc !important; }
        .view-section-title { border-left: 4px solid #00953b !important; -webkit-print-color-adjust: exact; }
    }
</style>
</head>
<body>
    <div class="view-container">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="perfilproveedores.php" style="text-decoration: none; color: #00953b; font-weight: bold;">
                <i class="fas fa-arrow-left"></i> Volver al Listado
            </a>
            <span class="status-badge <?php echo (strtolower($p['estatus'] ?? '') == 'activo') ? 'active' : 'inactive'; ?>">
                ESTATUS: <?php echo strtoupper(htmlspecialchars($p['estatus'] ?? 'Inactivo')); ?>
            </span>
        </div>

        <div class="view-section-title"><i class="fas fa-id-card"></i> Identificación y Contacto</div>
        <div class="view-grid">
            <div class="view-field"><label>Código SAP</label><div class="data-box"><?php echo htmlspecialchars($p['codigo_sap'] ?? '---'); ?></div></div>
            <div class="view-field"><label>RIF / ID Fiscal</label><div class="data-box"><?php echo htmlspecialchars($p['rif'] ?? '---'); ?></div></div>
            <div class="view-field"><label>Correo Electrónico</label><div class="data-box"><?php echo htmlspecialchars($p['correo'] ?? '---'); ?></div></div>
            <div class="view-field full-width"><label>Razón Social / Nombre</label>
                <div class="data-box" style="font-weight: bold; color: var(--locatel-green); font-size: 16px;">
                    <?php echo htmlspecialchars($p['nombre'] ?? '---'); ?>
                </div>
            </div>
        </div>

        <div class="view-section-title"><i class="fas fa-file-signature"></i> Expediente Digital</div>
        <div class="docs-container">
            <?php 
                echo renderDocLink($p['rif_adjunto'] ?? '', 'RIF Actualizado');
                echo renderDocLink($p['acta_adjunto'] ?? '', 'Acta Constitutiva');
                echo renderDocLink($p['cert_adjunto'] ?? '', 'Certificación Bancaria');
            ?>
        </div>

        <div class="view-section-title"><i class="fas fa-map-marked-alt"></i> Datos de Ubicación</div>
        <div class="view-grid">
            <div class="view-field"><label>País</label><div class="data-box"><?php echo htmlspecialchars($p['pais'] ?? '---'); ?></div></div>
            <div class="view-field"><label>Ciudad</label><div class="data-box"><?php echo htmlspecialchars($p['ciudad'] ?? '---'); ?></div></div>
            <div class="view-field"><label>Teléfono</label><div class="data-box"><?php echo htmlspecialchars($p['telefono'] ?? '---'); ?></div></div>
            
            <div class="view-field full-width"><label>Dirección Fiscal Principal</label>
                <div class="data-box"><?php echo nl2br(htmlspecialchars($p['direccion'] ?? '---')); ?></div>
            </div>
            
            <div class="view-field full-width"><label>Dirección 2 / Sucursal</label>
                <div class="data-box"><?php echo nl2br(htmlspecialchars($p['direccion_2'] ?? '---')); ?></div>
            </div>
        </div>

        <div class="view-section-title"><i class="fas fa-university"></i> Información Bancaria</div>
        <div class="bank-grid">
            <div class="bank-card-view" style="border-left: 4px solid var(--locatel-light-green);">
                <small style="color: var(--locatel-light-green); font-weight: bold;">CUENTA PRINCIPAL</small>
                <div style="margin: 8px 0;"><strong><?php echo htmlspecialchars($p['banco_nombre'] ?? 'No registrado'); ?></strong></div>
                <div style="font-size: 13px; margin-bottom: 5px;">Titular: <?php echo htmlspecialchars($p['banco_tipo'] ?? '---'); ?></div>
                <code><?php echo htmlspecialchars($p['banco_cuenta'] ?? '---'); ?></code>
            </div>

            <div class="bank-card-view" style="border-left: 4px solid var(--locatel-green);">
                <small style="color: var(--locatel-green); font-weight: bold;">CUENTA SECUNDARIA</small>
                <div style="margin: 8px 0;"><strong><?php echo htmlspecialchars($p['banco_nombre_2'] ?? 'No registrado'); ?></strong></div>
                <div style="font-size: 13px; margin-bottom: 5px;">Titular: <?php echo htmlspecialchars($p['banco_tipo_2'] ?? '---'); ?></div>
                <code><?php echo htmlspecialchars($p['banco_cuenta_2'] ?? '---'); ?></code>
            </div>
        </div>

        <div class="view-section-title"><i class="fas fa-calendar-alt"></i> Control de Vigencias</div>
        <div class="view-grid">
            <div class="view-field"><label>Fecha de Registro</label><div class="data-box"><?php echo fechaLatina($p['fecha_registro'] ?? ''); ?></div></div>
            <div class="view-field"><label>Vencimiento RIF</label>
                <div class="data-box" style="<?php echo (isset($p['rif_vencimiento']) && strtotime($p['rif_vencimiento']) < time()) ? 'color: #d32f2f; font-weight: bold;' : ''; ?>">
                    <?php echo fechaLatina($p['rif_vencimiento'] ?? ''); ?>
                    <?php if(isset($p['rif_vencimiento']) && strtotime($p['rif_vencimiento']) < time() && !empty($p['rif_vencimiento'])): ?> 
                        <small>(VENCIDO)</small> 
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="margin-top: 40px; display: flex; gap: 15px; justify-content: center;" class="no-print">
            <a href="editar_proveedor.php?id=<?php echo $p['id']; ?>" style="background: var(--locatel-green); color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: bold;">
                <i class="fas fa-edit"></i> Editar Información
            </a>
            <button onclick="window.print()" style="background: #6c757d; color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;">
                <i class="fas fa-print"></i> Imprimir Expediente
            </button>
        </div>

    </div>

    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; padding: 0; }
            .view-container { box-shadow: none; border: none; width: 100%; max-width: 100%; }
        }
    </style>
</body>
</html>