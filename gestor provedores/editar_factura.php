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

// Configuración de conexión LocatelDB
$host = 'db'; 
$dbname = 'LocatelDB';
$user = 'sa';
$pass = 'LocatelPass2026!';

try {
    $dsn = "sqlsrv:Server=$host;Database=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 🔍 SE TRAEN LAS EMPRESAS CON LAS COLUMNAS EXACTAS DE TU TABLA (Solo las activas)
    $sociedades = $conn->query("SELECT codigo_empresa, nombre_empresa FROM mis_empresas WHERE estatus = 'Activo' ORDER BY nombre_empresa ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Cargar proveedores siempre activos para el select
    $provs = $conn->query("SELECT id, nombre FROM proveedores WHERE estatus = 'Activo' ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM facturas WHERE id = ?");
        $stmt->execute([$id]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$f) die("Factura no encontrada.");
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $id_factura = $_POST['id_factura'];
        $id_prov = $_POST['codigo_proveedor'];
        $nro_factura_clean = trim($_POST['nro_factura']);
        
        $stmt_ref = $conn->prepare("SELECT * FROM facturas WHERE id = ?");
        $stmt_ref->execute([$id_factura]);
        $f = $stmt_ref->fetch(PDO::FETCH_ASSOC);

        $stmt_name = $conn->prepare("SELECT nombre FROM proveedores WHERE id = ?");
        $stmt_name->execute([$id_prov]);
        $nombre_proveedor = $stmt_name->fetchColumn();

        // 1. Mapeo de datos (Guardando el valor seleccionado de sociedad_destino)
        $data = [
            ':id'    => $id_factura,
            ':prov'  => $id_prov,
            ':nom_p' => $nombre_proveedor,
            ':soc'   => $_POST['sociedad_destino'], 
            ':nro'   => $nro_factura_clean,
            ':f_emi' => !empty($_POST['fecha_emision']) ? $_POST['fecha_emision'] : null,
            ':f_rec' => !empty($_POST['fecha_recibida']) ? $_POST['fecha_recibida'] : null,
            ':cond'  => $_POST['condicion_pago'],
            ':f_ven' => !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
            ':bs'    => $_POST['monto_bs'],
            ':tasa'  => $_POST['tasa_cambio'],
            ':usd'   => $_POST['monto_usd'],
            ':est'   => $_POST['estado'],
            ':obs'   => $_POST['observaciones']
        ];

        $folder = "uploads/";
        $sql_files = "";
        
        for ($i = 1; $i <= 3; $i++) {
            $key = "comp$i";
            if (isset($_POST['delete_' . $key])) {
                if (!empty($f[$key]) && file_exists($folder . $f[$key])) {
                    unlink($folder . $f[$key]);
                }
                $sql_files .= ", $key = NULL";
            } 
            elseif (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                // CORRECCIÓN DE EXTENSIÓN: Sanitizar y obtener extensión en minúsculas estrictas
                $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
                
                // Generamos un nombre consistente que incluya el número de factura y el índice del archivo
                $newName = "FAC_" . $nro_factura_clean . "_" . $i . "_" . time() . "." . $ext;
                
                if (move_uploaded_file($_FILES[$key]['tmp_name'], $folder . $newName)) {
                    if (!empty($f[$key]) && file_exists($folder . $f[$key])) {
                        unlink($folder . $f[$key]);
                    }
                    $sql_files .= ", $key = :$key";
                    $data[":$key"] = $newName;
                }
            }
        }

        $sql = "UPDATE facturas SET 
                    codigo_proveedor=:prov, 
                    nombre=:nom_p, 
                    sociedad_destino=:soc, 
                    nro_factura=:nro, 
                    fecha_emision=:f_emi, 
                    fecha_recibida=:f_rec, 
                    condicion_pago=:cond, 
                    fecha_vencimiento=:f_ven, 
                    monto_bs=:bs, 
                    tasa_cambio=:tasa, 
                    monto_usd=:usd, 
                    estatus=:est, 
                    observaciones=:obs 
                    $sql_files 
                WHERE id=:id";
        
        $conn->prepare($sql)->execute($data);
        header("Location: vistafacturas.php?edit=success");
        exit();
    }
} catch(PDOException $e) { die("Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Factura | Locatel</title>
    <link rel="stylesheet" href="creacion_nueva_fac.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .input-readonly { background-color: #f4f4f4 !important; cursor: not-allowed; border: 1px solid #ddd; }
        .current-file-box { background: #e8f5e9; padding: 10px; border-radius: 8px; margin-bottom: 10px; border: 1px solid #c8e6c9; }
        .file-info { display: flex; align-items: center; justify-content: space-between; }
        .file-info a { color: #2e7d32; font-size: 13px; font-weight: bold; text-decoration: none; }
        .delete-opt { margin-top: 8px; display: block; color: #d32f2f; font-size: 11px; cursor: pointer; }
        .delete-opt input { margin-right: 5px; }
        .drop-zone { position: relative; border: 2px dashed #00953b; padding: 15px; border-radius: 8px; text-align: center; }
        .drop-zone input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
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
            <div class="breadcrumb">
                <a href="vistafacturas.php">Inicio</a> / <span>Editar Factura #<?php echo htmlspecialchars($f['nro_factura'] ?? ''); ?></span>
            </div>
        </header>

        <form id="invoice-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_factura" value="<?php echo $f['id'] ?? ''; ?>">
            
            <section class="content-header">
                <h1>Modificar Factura</h1>
                <div class="action-buttons">
                    <button type="button" class="btn-cancel" onclick="location.href='vistafacturas.php'">Volver</button>
                    <button type="submit" class="btn-save">Guardar Cambios</button>
                </div>
            </section>

            <div class="form-section">
                <h3><i class="fas fa-info-circle"></i> Datos de Factura</h3>
                <div class="grid-inputs">
                    
                    <div class="field">
                        <label>Empresa Destino (Sociedad) *</label>
                        <select name="sociedad_destino" required style="border-left: 4px solid #00953b;">
                            <option value="">Seleccione...</option>
                            <?php foreach($sociedades as $soc): ?>
                                <option value="<?php echo htmlspecialchars($soc['nombre_empresa']); ?>" 
                                    <?php echo (isset($f['sociedad_destino']) && trim($f['sociedad_destino']) === trim($soc['nombre_empresa'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($soc['codigo_empresa'] ?? 'S/C'); ?> - <?php echo htmlspecialchars($soc['nombre_empresa']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label>Proveedor *</label>
                        <select name="codigo_proveedor" required>
                            <?php foreach($provs as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo (isset($f['codigo_proveedor']) && $p['id'] == $f['codigo_proveedor']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label>Nro. Factura</label>
                        <input type="text" name="nro_factura" value="<?php echo htmlspecialchars($f['nro_factura'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label>Fecha Emisión</label>
                        <input type="date" name="fecha_emision" value="<?php echo htmlspecialchars($f['fecha_emision'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>Fecha Recibida *</label>
                        <input type="date" id="fecha-recibida" name="fecha_recibida" value="<?php echo htmlspecialchars($f['fecha_recibida'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label>Condición Pago</label>
                        <select id="plazo-pago" name="condicion_pago">
                            <option value="0" <?php echo (isset($f['condicion_pago']) && $f['condicion_pago']==0)?'selected':''; ?>>Contado</option>
                            <option value="7" <?php echo (isset($f['condicion_pago']) && $f['condicion_pago']==7)?'selected':''; ?>>7 días</option>
                            <option value="15" <?php echo (isset($f['condicion_pago']) && $f['condicion_pago']==15)?'selected':''; ?>>15 días</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Vencimiento</label>
                        <input type="date" id="fecha-vencimiento" name="fecha_vencimiento" value="<?php echo htmlspecialchars($f['fecha_vencimiento'] ?? ''); ?>" readonly class="input-readonly">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-money-bill-wave"></i> Montos y Estatus</h3>
                <div class="grid-inputs">
                    <div class="field">
                        <label>Monto Bs.</label>
                        <input type="number" step="0.01" id="monto-bs" name="monto_bs" value="<?php echo htmlspecialchars($f['monto_bs'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>Tasa BCV</label>
                        <input type="number" step="0.01" id="tasa-cambio" name="tasa_cambio" value="<?php echo htmlspecialchars($f['tasa_cambio'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>Monto USD ($)</label>
                        <input type="number" step="0.01" id="monto-usd" name="monto_usd" value="<?php echo htmlspecialchars($f['monto_usd'] ?? ''); ?>" readonly class="input-readonly">
                    </div>
                    <div class="field">
                        <label>Estatus</label>
                        <select name="estado" style="border-left: 5px solid #ff671d;">
                            <option value="Pendiente" <?php echo (isset($f['estatus']) && $f['estatus'] == 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="Pagado" <?php echo (isset($f['estatus']) && $f['estatus'] == 'Pagado') ? 'selected' : ''; ?>>Pagado</option>
                            <option value="Rechazado" <?php echo (isset($f['estatus']) && $f['estatus'] == 'Rechazado') ? 'selected' : ''; ?>>Rechazado</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-paperclip"></i> Gestión de Archivos</h3>
                <div class="grid-comprobantes">
                    <?php 
                    $labels = ['comp1' => 'Factura PDF', 'comp2' => 'Comprobante', 'comp3' => 'Anexos'];
                    foreach($labels as $key => $lbl): 
                    ?>
                        <div class="upload-box">
                            <label><?php echo $lbl; ?></label>
                            <?php if(!empty($f[$key])): ?>
                                <div class="current-file-box">
                                    <div class="file-info">
                                        <a href="uploads/<?php echo htmlspecialchars($f[$key]); ?>" target="_blank"><i class="fas fa-eye"></i> Ver Actual</a>
                                    </div>
                                    <label class="delete-opt">
                                        <input type="checkbox" name="delete_<?php echo $key; ?>" value="1"> 
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </label>
                                </div>
                            <?php endif; ?>
                            <div class="drop-zone">
                                <i class="fas fa-upload" style="color: #00953b;"></i>
                                <span id="label-<?php echo $key; ?>" style="font-size: 11px; display:block;">Subir nuevo</span>
                                <input type="file" name="<?php echo $key; ?>" onchange="document.getElementById('label-<?php echo $key; ?>').innerText = this.files[0].name">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-comment-dots"></i> Observaciones</h3>
                <textarea name="observaciones" rows="3" style="width:100%; border:1px solid #ddd; border-radius:8px; padding:10px;"><?php echo htmlspecialchars($f['observaciones'] ?? ''); ?></textarea>
            </div>
        </form>
    </main>

    <script>
        const fRec = document.getElementById('fecha-recibida');
        const plazo = document.getElementById('plazo-pago');
        const fVen = document.getElementById('fecha-vencimiento');
        const mBs = document.getElementById('monto-bs');
        const tasa = document.getElementById('tasa-cambio');
        const mUsd = document.getElementById('monto-usd');

        function calcularMontos() {
            let res = (parseFloat(mBs.value) || 0) / (parseFloat(tasa.value) || 1);
            mUsd.value = res.toFixed(2);
        }

        function calcularVencimiento() {
            if(fRec.value) {
                let d = new Date(fRec.value);
                d.setUTCDate(d.getUTCDate() + parseInt(plazo.value));
                fVen.value = d.toISOString().split('T')[0];
            }
        }

        // Eventos individuales para evitar saltos inesperados de fechas al cargar
        [mBs, tasa].forEach(el => el.addEventListener('input', calcularMontos));
        [fRec, plazo].forEach(el => el.addEventListener('change', calcularVencimiento));
        
        // Ejecución limpia inicial al cargar datos de la BD
        window.onload = function() {
            calcularMontos();
        };
    </script>
</body>
</html>