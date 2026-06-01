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
include 'registro.php'; // Conexión $conn

// Endpoint para validar número de factura vía AJAX (devuelve JSON)
if (isset($_GET['action']) && $_GET['action'] === 'check_nro') {
    $nro_ajax = trim($_GET['nro'] ?? '');
    if ($nro_ajax === '') {
        header('Content-Type: application/json');
        echo json_encode(['exists' => false]);
        exit();
    }
    try {
        $stmt_ajax = $conn->prepare("SELECT COUNT(*) FROM facturas WHERE nro_factura = ?");
        $stmt_ajax->execute([$nro_ajax]);
        $exists = $stmt_ajax->fetchColumn() > 0;
        header('Content-Type: application/json');
        echo json_encode(['exists' => (bool)$exists]);
        exit();
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// --- FUNCIÓN PARA OBTENER TASA BCV DESDE LA API KONVIERTE ---
$tasa_bcv_hoy = ""; 

function obtenerTasaBCV() {
    $url = "https://konvierte.vercel.app/api/rates"; 

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_status == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['rates']['usd']['price'])) {
            return $data['rates']['usd']['price'];
        }
    }
    return ""; 
}

$tasa_bcv_hoy = obtenerTasaBCV();

// --- LÓGICA DE PROCESAMIENTO ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $id_proveedor_form = $_POST['proveedor_id'] ?? null;
        $nro_factura = trim($_POST['nro_factura'] ?? '');

        // Validar que el número de factura contenga solo dígitos
        if ($nro_factura !== '' && !ctype_digit($nro_factura)) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<body></body>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({ icon: 'error', title: 'Número inválido', text: 'El número de factura debe contener únicamente dígitos.', confirmButtonColor: '#00953b' })
                    .then(() => { window.history.back(); });
                });
            </script>";
            exit();
        }

        // 🔥 VALIDACIÓN: Evitar número de factura repetido (único global)
        if (!empty($nro_factura)) {
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM facturas WHERE nro_factura = ?");
            $stmt_check->execute([$nro_factura]);
            $existe_factura = $stmt_check->fetchColumn();

            if ($existe_factura > 0) {
                // Si ya existe la factura para este proveedor, frena el flujo y avisa al usuario
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<body></body>"; // Permite que SweetAlert se monte correctamente en el DOM
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Factura Duplicada',
                            text: 'El número de factura ya se encuentra registrado en el sistema.',
                            confirmButtonColor: '#00953b'
                        }).then(() => {
                            window.history.back();
                        });
                    });
                </script>";
                exit();
            }
        }
        
        // Obtener nombre del proveedor para guardarlo (desnormalización útil para reportes rápidos)
        $stmt_name = $conn->prepare("SELECT nombre FROM proveedores WHERE id = ?");
        $stmt_name->execute([$id_proveedor_form]);
        $nombre_proveedor = $stmt_name->fetchColumn();

        // Manejo de archivos
        $directorio_destino = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR;
        if (!is_dir($directorio_destino)) {
            mkdir($directorio_destino, 0777, true);
        }

        $archivos = ['c1' => null, 'c2' => null, 'c3' => null];
        foreach (['comp1', 'comp2', 'comp3'] as $indice => $campo) {
            if (!empty($_FILES[$campo]['name']) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION);
                // Limpiar nombre de archivo
                $nombre_base = preg_replace("/[^a-zA-Z0-9]/", "_", pathinfo($_FILES[$campo]['name'], PATHINFO_FILENAME));
                $nuevo_nombre = time() . "_" . ($indice + 1) . "_" . $nombre_base . "." . $ext;
                
                if (move_uploaded_file($_FILES[$campo]['tmp_name'], $directorio_destino . $nuevo_nombre)) {
                    $archivos['c' . ($indice + 1)] = $nuevo_nombre;
                }
            }
        }

        // Preparar datos con limpieza de tipos
        $monto_bs = (float)str_replace(',', '', $_POST['monto_bs'] ?? 0);
        $tasa = (float)($_POST['tasa_cambio'] ?? $tasa_bcv_hoy);
        if ($tasa <= 0) $tasa = 1; // Seguridad
        $monto_usd = $monto_bs / $tasa;

        $data_insert = [
            ':cod_p' => $id_proveedor_form,
            ':nom_p' => $nombre_proveedor,
            ':soc'   => $_POST['sociedad_destino'] ?? null,
            ':nro'   => $nro_factura, // 🔥 Vinculado con la variable limpia y validada
            ':f_emi' => !empty($_POST['fecha_emision']) ? $_POST['fecha_emision'] : null,
            ':f_rec' => !empty($_POST['fecha_recibida']) ? $_POST['fecha_recibida'] : null,
            ':cond'  => (isset($_POST['condicion_pago']) && is_numeric($_POST['condicion_pago'])) ? max(0, (int)$_POST['condicion_pago']) : 0,
            ':f_ven' => !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
            ':m_bs'  => $monto_bs,
            ':tasa'  => $tasa,
            ':m_usd' => $monto_usd,
            ':est'   => $_POST['estado'] ?? 'Pendiente',
            ':obs'   => $_POST['observaciones'] ?? '',
            ':c1'    => $archivos['c1'],
            ':c2'    => $archivos['c2'],
            ':c3'    => $archivos['c3']
        ];

        $sql = "INSERT INTO facturas (
                    codigo_proveedor, nombre, sociedad_destino, nro_factura, fecha_emision, 
                    fecha_recibida, condicion_pago, fecha_vencimiento,
                    monto_bs, tasa_cambio, monto_usd, estatus, observaciones,
                    comp1, comp2, comp3
                ) VALUES (
                    :cod_p, :nom_p, :soc, :nro, :f_emi, :f_rec, :cond, :f_ven, 
                    :m_bs, :tasa, :m_usd, :est, :obs, :c1, :c2, :c3
                )";

        $stmt = $conn->prepare($sql);
        $stmt->execute($data_insert);

        header("Location: vistafacturas.php?status=success");
        exit();

    } catch (PDOException $e) {
        die("Error en base de datos: " . $e->getMessage());
    }
}
// --- LÓGICA DE CARGA DE PROVEEDORES ---
$proveedores_db = [];
$mis_empresas = [];

if (isset($conn) && $conn !== null) {
    try {
        // 1. Cargar Proveedores
        $queryProv = $conn->query("SELECT id, nombre FROM proveedores WHERE estatus = 'Activo' ORDER BY nombre ASC");
        if ($queryProv) {
            $proveedores_db = $queryProv->fetchAll(PDO::FETCH_ASSOC);
        }
        // 2. Cargar Mis Empresas
        $queryEmp = $conn->query("SELECT codigo_empresa, nombre_empresa FROM mis_empresas WHERE estatus = 'Activo' ORDER BY nombre_empresa ASC");
        if ($queryEmp) {
            while ($row = $queryEmp->fetch(PDO::FETCH_ASSOC)) {
                $mis_empresas[$row['codigo_empresa']] = $row['nombre_empresa'];
            }
        }

    } catch (PDOException $e) {
        error_log("Error al cargar datos: " . $e->getMessage());
    }
} else {
    echo "<div style='color:red; padding:10px; background:#ffebeb;'>⚠️ Error de conexión a la base de datos.</div>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Factura | Locatel</title>
    <link rel="stylesheet" href="creacion_nueva_fac.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .select2-container--default .select2-selection--single {
            border: 1px solid #ddd;
            height: 42px;
            display: flex;
            align-items: center;
        }
        .select2-container { width: 100% !important; margin-top: 5px; }
    </style>
</head>
<body>

    <aside class="sidebar">
    <div class="sidebar-logo">
        <img src="images/Logo_Locatel (1).png" alt="Logo" class="mini-logo">
    </div>
    <nav class="sidebar-nav">
        <!-- Detectamos si estamos en facturas -->
        <?php if ($es_admin || (isset($_SESSION['acc_facturas']) && $_SESSION['acc_facturas'] == 1)): ?>
            <a href="vistafacturas.php" class="active-sub">
                <i class="fas fa-home"></i> FACTURACIÓN
            </a>
        <?php endif; ?>

        <!-- Proveedores no lleva la clase activa aquí -->
        <?php if ($es_admin || (isset($_SESSION['acc_proveedores']) && $_SESSION['acc_proveedores'] == 1)): ?>
            <a href="perfilproveedores.php">
                <i class="fas fa-truck"></i> PROVEEDORES
            </a>
        <?php endif; ?>
        <!-- Nuevo Módulo: MIS EMPRESAS -->
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
            <div class="breadcrumb"><a href="vistafacturas.php">Facturas</a> / <span>Nueva factura</span></div>
            <div class="user-info">
                <span class="user-email">admin@locatel.com.ve</span>
                <i class="fas fa-user-circle user-icon"></i>
            </div>
        </header>

        <form id="invoice-form" action="" method="POST" enctype="multipart/form-data">
            
            <section class="content-header">
                <h1>Nueva factura de proveedor</h1>
                <div class="action-buttons">
                    <button type="button" class="btn-cancel" onclick="window.history.back()">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar factura</button>
                </div>
            </section>

            <div class="form-section">
                <h3><i class="fas fa-info-circle"></i> Información General</h3>
                <div class="grid-inputs"> 
                    
                   <div class="field">
                        <label>Empresa Destino (Sociedad) *</label>
                        <select id="sociedad_destino" name="sociedad_destino" required>
                            <option value="">Seleccione la empresa...</option>
                            <?php foreach($mis_empresas as $codigo => $nombre): ?>
                                <option value="<?= htmlspecialchars($nombre) ?>">
                                    <?= htmlspecialchars($codigo) ?> - <?= htmlspecialchars($nombre) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                                        <div class="field">
                        <label>Proveedor *</label>
                        <select id="proveedor_id" name="proveedor_id" required> <!-- ID CORRECTO -->
                        <option value="">Selecciona un proveedor...</option>
                        <?php foreach($proveedores_db as $prov): ?>
                            <option value="<?= $prov['id'] ?>">
                                <?= htmlspecialchars($prov['id']) ?> - <?= htmlspecialchars($prov['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    </div>

                    <div class="field">
                        <label>Número de Factura</label>
                        <input type="text" id="num_factura" name="nro_factura" placeholder="Ej: 001" required
                               pattern="^[0-9]+$" title="Solo dígitos (ej: 001)" oninput="this.value=this.value.replace(/[^0-9]/g,'');">
                    </div>

                    <div class="field">
                        <label>Fecha de Emisión</label>
                        <input type="date" id="fecha-emision" name="fecha_emision">
                    </div>

                    <div class="field">
                        <label>Fecha de Recibida *</label>
                        <input type="date" id="fecha-recibida" name="fecha_recibida" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="field">
                        <label>Condición de Pago</label>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <input type="number" id="plazo-pago" name="condicion_pago" min="0" step="1" placeholder="0" style="width:120px;">
                            <small id="plazo-help" style="color:#666;">Contado</small>
                        </div>
                    </div>

                    <div class="field">
                        <label>Fecha de Vencimiento</label>
                        <input type="date" id="fecha-vencimiento" name="fecha_vencimiento" readonly class="input-readonly">
                    </div>

                    <div class="field">
                        <label>Estado del Pago</label>
                        <select name="estado" style="border-left: 4px solid #ff671d;">
                            <option value="Pendiente" selected>🕒 Pendiente</option>
                            <option value="Pagado">✅ Pagado</option>
                            <option value="Rechazado">❌ Rechazado</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-money-bill-wave"></i> Detalles Económicos</h3>
                <div class="grid-inputs">
                    <div class="field">
                        <label>Monto en Bs.</label>
                        <input type="number" id="monto-bs" name="monto_bs" step="0.01" placeholder="0.00" required>
                    </div>
                    <div class="field">
                        <label>Tasa de Cambio (BCV)</label>
                        <input type="number" id="tasa-cambio" name="tasa_cambio" step="0.0001" 
                               value="<?= $tasa_bcv_hoy ?>" required>
                    </div>
                    <div class="field">
                        <label>Equivalente en Dólares ($)</label>
                        <input type="number" id="monto-usd" name="monto_usd" step="0.01" readonly class="input-readonly">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-paperclip"></i> Gestión de Comprobantes</h3>
                <div class="grid-comprobantes">
                    <?php for($i=1; $i<=3; $i++): 
                        $label = ($i==1) ? "Factura" : (($i==2) ? "Pago" : "Otros");
                    ?>
                    <div class="upload-box">
                        <label>Comprobante <?= $i ?> (<?= $label ?>)</label>
                        <div class="drop-zone" id="zone-comp<?= $i ?>">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span class="upload-text" id="txt-comp<?= $i ?>">Subir archivo</span>
                            <input type="file" name="comp<?= $i ?>" id="comp<?= $i ?>" class="file-input" 
                                   onchange="actualizarEstado(this, 'txt-comp<?= $i ?>', 'zone-comp<?= $i ?>', 'pre-comp<?= $i ?>')">
                            <div id="pre-comp<?= $i ?>" class="preview-zone"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-comment-alt"></i> Información Adicional</h3>
                <div class="field">
                    <label>Observaciones</label>
                    <textarea id="notas" name="observaciones" rows="4" placeholder="Detalles adicionales..."></textarea>
                </div>
            </div>
        </form>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            // Inicializar buscadores inteligentes
            $('#proveedor_id').select2({ placeholder: "Buscar proveedor..." });
            $('#sociedad_destino').select2({ placeholder: "Seleccionar sociedad..." });
            
            // Cálculos iniciales
            calcularDolares();
            calcularVencimiento();
            // Comprobación inicial de número de factura al perder foco y al enviar
            $('#num_factura').on('blur', function() {
                const nro = $(this).val().trim();
                if (!nro) return;
                $.getJSON('creacion_nueva_fac.php', { action: 'check_nro', nro: nro }, function(resp) {
                    if (resp && resp.exists) {
                        Swal.fire({ icon: 'warning', title: 'Número existente', text: 'Ya existe una factura con ese número.' });
                    }
                });
            });

            $('#invoice-form').on('submit', function(e) {
                e.preventDefault();
                const nro = $('#num_factura').val().trim();
                if (!nro) {
                    this.submit();
                    return;
                }
                $.getJSON('creacion_nueva_fac.php', { action: 'check_nro', nro: nro }, function(resp) {
                    if (resp && resp.exists) {
                        Swal.fire({ icon: 'error', title: 'Factura Duplicada', text: 'El número de factura ya está registrado.' });
                    } else {
                        $('#invoice-form')[0].submit();
                    }
                });
            });
        });

        const inputRecibida = document.getElementById('fecha-recibida');
        const selectPlazo = document.getElementById('plazo-pago');
        const inputVencimiento = document.getElementById('fecha-vencimiento');
        const inputBs = document.getElementById('monto-bs');
        const inputTasa = document.getElementById('tasa-cambio');
        const inputUsd = document.getElementById('monto-usd');

        function calcularVencimiento() {
            if (inputRecibida.value) {
                const fechaBase = new Date(inputRecibida.value);
                        const dias = parseInt(selectPlazo.value) || 0;
                        fechaBase.setUTCDate(fechaBase.getUTCDate() + dias);
                inputVencimiento.value = fechaBase.toISOString().split('T')[0];
            }
        }

        function calcularDolares() {
            const bs = parseFloat(inputBs.value) || 0;
            const tasa = parseFloat(inputTasa.value) || 0;
            if (tasa > 0) {
                inputUsd.value = (bs / tasa).toFixed(2);
            } else {
                inputUsd.value = "0.00";
            }
        }

        inputRecibida.addEventListener('change', calcularVencimiento);
        selectPlazo.addEventListener('input', function(e) {
            // Forzar sólo dígitos (enteros no negativos)
            const val = this.value.toString();
            const cleaned = val.replace(/\D/g, '');
            if (cleaned === '') {
                this.value = '';
                document.getElementById('plazo-help').textContent = 'Contado';
            } else {
                this.value = cleaned;
                document.getElementById('plazo-help').textContent = cleaned + ' días';
            }
            calcularVencimiento();
        });
        inputBs.addEventListener('input', calcularDolares);
        inputTasa.addEventListener('input', calcularDolares);
        
        function actualizarEstado(input, textId, zoneId, previewId) {
            const texto = document.getElementById(textId);
            const zona = document.getElementById(zoneId);
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                texto.innerHTML = `<b style="color: #28a745;"><i class="fas fa-check"></i> Cargado</b>`;
                zona.style.border = "2px solid #28a745";
                zona.style.background = "#f9fff9";
                preview.innerHTML = `<p style="font-size: 11px; margin-top:5px; color:#666;">${input.files[0].name}</p>`;
            }
        }
    </script>
</body>
</html>