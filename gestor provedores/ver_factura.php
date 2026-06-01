<?php
$host = 'db'; $dbname = 'LocatelDB'; $user = 'sa'; $pass = 'LocatelPass2026!';


try {
    $dsn = "sqlsrv:Server=$host;Database=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        
        $sql = "SELECT f.*, p.nombre as nombre_proveedor 
                FROM facturas f 
                LEFT JOIN proveedores p ON f.codigo_proveedor = p.id 
                WHERE f.id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $f = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$f) {
            die("<div style='padding:20px; color:red;'>⚠️ Factura no encontrada.</div>");
        }
    } else {
        header("Location: vistafacturas.php");
        exit();
    }
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

function formatearFecha($fecha) {
    if (empty($fecha) || $fecha == '0000-00-00') return "No registrada";
    return date('d/m/Y', strtotime($fecha));
}

$estatus_clase = '';
$estatus_icon = '';
switch($f['estatus']) {
    case 'Pagado': 
        $estatus_clase = 'badge-success'; 
        $estatus_icon = 'fa-check-circle';
        break;
    case 'Rechazado': 
        $estatus_clase = 'badge-danger'; 
        $estatus_icon = 'fa-times-circle';
        break;
    default: 
        $estatus_clase = 'badge-warning'; 
        $estatus_icon = 'fa-clock';
        break;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Factura #<?php echo $f['nro_factura']; ?></title>
    <link rel="stylesheet" href="ver_factura.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    /* --- 1. BASE Y CUERPO --- */
    body { 
        font-family: 'Segoe UI', Tahoma, sans-serif; 
        margin: 0; 
        background-color: #f4f7f6; 
    }
    .content-header {
        background-color: #00953b; 
        padding: 20px 30px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .content-header h1 {
        color: white !important;
        margin: 0;
        font-size: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-container {
        background: white;
        margin: 20px auto;
        padding: 35px;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.1);
        max-width: 1100px;
        width: 90%;
    }

    .form-section h3 {
        color: #00953b !important;
        border-bottom: 2px solid #76bc43;
        padding-bottom: 8px;
        margin-bottom: 20px;
        font-size: 18px;
    }

    /* --- 2. GRID Y CAMPOS (CORRECCIÓN DE COLOR AQUÍ) --- */
    .grid-inputs {
        display: flex;
        flex-wrap: wrap;
        gap: 25px;
    }

    .field { flex: 1; min-width: 220px; }
    
    .field label {
        display: block;
        font-weight: bold;
        margin-bottom: 8px;
        color: #555;
        font-size: 13px;
    }

    /* Forzamos el color del texto para que NO salga blanco */
    .input-readonly {
        width: 100%;
        padding: 12px;
        background-color: #f9f9f9 !important;
        border: 1px solid #ddd !important;
        border-radius: 6px;
        font-size: 14px;
        color: #333 !important; /* Texto gris oscuro visible */
        box-sizing: border-box;
        display: block;
    }

    /* --- 3. SECCIÓN DE COMPROBANTES --- */
    .grid-comprobantes {
        display: flex;
        gap: 20px;
        margin-top: 10px;
    }

    .comprobante-item { text-align: center; flex: 1; }

    .comprobante-label {
        color: #00953b;
        font-weight: bold;
        font-size: 12px;
        margin-bottom: 8px;
        display: block;
    }

    .box-na {
        border: 2px dashed #ccc;
        border-radius: 8px;
        padding: 15px;
        color: #999;
        font-weight: bold;
        background: #fafafa;
    }

    /* --- 4. BOTÓN PDF --- */
    .btn-print {
        background-color: #00953b !important;
        color: white !important;
        padding: 10px 22px;
        border-radius: 8px;
        border: none;
        font-weight: bold;
        cursor: pointer;
    }

    /* --- 5. IMPRESIÓN (PDF) --- */
    @media print {
        @page { size: portrait; margin: 0.8cm; }

        * { 
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important; 
        }

        .top-navbar, .btn-print, .breadcrumb, .back-link { display: none !important; }

        .form-container {
            box-shadow: none !important;
            margin: 0 auto !important;
            width: 100% !important;
            padding: 10px !important;
        }

        /* Mostrar comprobantes y asegurar color de texto */
        .grid-comprobantes { display: flex !important; gap: 15px !important; }
        
        .input-readonly { 
            color: #333 !important; 
            padding: 8px !important; 
            font-size: 11px !important; 
            background-color: #f9f9f9 !important;
        }

        .grid-inputs {
            display: flex !important;
            flex-wrap: nowrap !important;
            gap: 10px !important;
        }
    }
</style>
</head>
<body>

    <div class="main-content" style="margin-left: 0; width: 100%;">
        
        <header class="top-navbar">
            <div class="breadcrumb">
                <a href="vistafacturas.php" style="text-decoration: none; color: #00953b; font-weight: bold;">
                    <i class="fas fa-arrow-left"></i> Volver al Listado
                </a>
            </div>
            <div class="user-info" style="display: flex; gap: 15px; align-items: center;">
                <button onclick="window.print();" class="btn-print">
                    <i class="fas fa-file-pdf"></i> Generar PDF
                </button>

                <div class="status-badge <?php echo $estatus_clase; ?>">
                    <i class="fas <?php echo $estatus_icon; ?>"></i> <?php echo strtoupper($f['estatus']); ?>
                </div>
            </div>
        </header>

        <section class="content-header" style="padding: 20px 30px;">
            <div class="title-section">
                <i class="fas fa-file-invoice-dollar title-icon" style="color: #ffffff; font-size: 24px;"></i>
                <h1 style="display: inline; margin-left: 10px; color: #ffffff;">Expediente Factura: <?php echo $f['nro_factura']; ?></h1>
            </div>
        </section>

        <div class="form-container" style="padding: 20px 30px; background: white; margin: 20px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
            
            <div class="form-section">
                <h3><i class="fas fa-university"></i> Información de Entidades</h3>
                <div class="grid-inputs" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">
                    <div class="field">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Sociedad / Empresa Destino</label>
                        <input type="text" value="<?php echo $f['sociedad_destino'] ?? 'No especificada'; ?>" class="input-readonly" readonly style="font-weight: bold; border-left: 4px solid #00953b;">
                    </div>
                    <div class="field">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Proveedor / Razón Social</label>
                        <input type="text" value="<?php echo $f['nombre_proveedor'] ?? 'No especificado'; ?>" class="input-readonly" readonly>
                    </div>
                    <div class="field">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Número de Factura</label>
                        <input type="text" value="<?php echo $f['nro_factura']; ?>" class="input-readonly" readonly>
                    </div>
                </div>
            </div>

            <div class="form-section" style="margin-top: 30px;">
                <h3><i class="fas fa-calendar-alt"></i> Fechas y Condiciones</h3>
                <div class="grid-inputs" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px;">
                    <div class="field">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Fecha de Emisión</label>
                        <input type="text" value="<?php echo formatearFecha($f['fecha_emision']); ?>" class="input-readonly" readonly>
                    </div>
                    <div class="field">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Fecha de Recepción</label>
                        <input type="text" value="<?php echo formatearFecha($f['fecha_recibida']); ?>" class="input-readonly" readonly>
                    </div>
                    <div class="field">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Plazo de Crédito</label>
                        <input type="text" value="<?php echo $f['condicion_pago']; ?> días" class="input-readonly" readonly>
                    </div>
                    <div class="field">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Vencimiento Estimado</label>
                        <input type="text" value="<?php echo formatearFecha($f['fecha_vencimiento']); ?>" class="input-readonly" readonly style="background-color: #fffde7;">
                    </div>
                </div>
            </div>

            <div class="form-section" style="margin-top: 30px;">
                <h3><i class="fas fa-coins"></i> Análisis de Montos</h3>
                <div class="grid-inputs" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">
                    <div class="field">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Monto Total (Bs.)</label>
                        <input type="text" value="<?php echo number_format($f['monto_bs'] ?? 0, 2, ',', '.'); ?> Bs." class="input-readonly" readonly>
                    </div>
                    <div class="field">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Tasa BCV Aplicada</label>
                        <input type="text" value="<?php echo number_format($f['tasa_cambio'] ?? 0, 2, ',', '.'); ?>" class="input-readonly" readonly>
                    </div>
                    <div class="field">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Equivalente en Divisa (USD)</label>
                        <input type="text" value="$ <?php echo number_format($f['monto_usd'] ?? 0, 2, ',', '.'); ?>" class="input-readonly" readonly style="color: #00953b; font-weight: bold; border-left: 4px solid #00953b;">
                    </div>
                </div>
            </div>

            <div class="form-section" style="margin-top: 30px;">
                <h3><i class="fas fa-comments"></i> Observaciones</h3>
                <div class="field" style="margin-top: 10px;">
                    <textarea class="input-readonly" style="width: 100%; height: 80px; resize: none; padding:10px;" readonly><?php echo htmlspecialchars($f['observaciones']); ?></textarea>
                </div>
            </div>

            <div class="form-section grid-comprobantes" style="margin-top: 30px;">
                <h3><i class="fas fa-paperclip"></i> Archivos Digitalizados (Solo Vista Web)</h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 15px;">
                    <?php 
                    $labels = ['comp1' => 'Factura PDF', 'comp2' => 'Comprobante Ret.', 'comp3' => 'Anexos/Otros'];
                    for($i=1; $i<=3; $i++): 
                        $file_key = "comp$i"; 
                    ?>
                        <div class="upload-box" style="text-align: center;">
                            <label style="display: block; margin-bottom: 10px; font-weight: bold; color: #00953b;"><?php echo $labels[$file_key]; ?></label>
                            <?php if(!empty($f[$file_key])): ?>
                                <a href="uploads/<?php echo $f[$file_key]; ?>" target="_blank" style="display: block; padding: 10px; border: 2px solid #76bc43; color: #00953b; text-decoration: none; border-radius: 12px; background: #f9fff4;">
                                    <i class="fas fa-file-pdf"></i> VER
                                </a>
                            <?php else: ?>
                                <div style="padding: 10px; border: 2px dashed #ccc; color: #999; border-radius: 12px;">N/A</div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

        </div>
    </div>

</body>
</html>