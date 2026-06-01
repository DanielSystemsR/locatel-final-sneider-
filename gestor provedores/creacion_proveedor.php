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

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de la base de datos
$host = 'db'; 
$dbname = 'LocatelDB';
$user = 'sa'; 
$pass = 'LocatelPass2026!';

$mensaje = "";

try {
    $dsn = "sqlsrv:Server=$host;Database=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $sap_value = trim($_POST['codigo_sap'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        
        // --- PROCESAMIENTO DEL RIF ---
        $rif_tipo = $_POST['rif_tipo'] ?? 'J';
        $rif_numero = trim($_POST['rif_numero'] ?? '');
        $rif = !empty($rif_numero) ? $rif_tipo . '-' . $rif_numero : '';

        // Cuentas Bancarias
        $banco1   = $_POST['banco_nombre'] ?? '';
        $cuenta1  = $_POST['banco_cuenta'] ?? '';
        $titular1 = $_POST['banco_titular'] ?? ''; 

        $banco2   = !empty($_POST['banco_nombre_2']) ? $_POST['banco_nombre_2'] : null;
        $cuenta2  = !empty($_POST['banco_cuenta_2']) ? $_POST['banco_cuenta_2'] : null;
        $titular2 = !empty($_POST['banco_titular_2']) ? $_POST['banco_titular_2'] : null; 
        
        $direccion_2 = !empty($_POST['direccion_2']) ? $_POST['direccion_2'] : null;

        // --- VALIDACIONES DE DUPLICADOS EN BACKEND ---
        if (empty($sap_value) || empty($rif_numero) || empty($nombre)) {
            $mensaje = "<div class='alert error'>⚠️ El Código SAP, RIF y la Razón Social son campos estrictamente obligatorios.</div>";
        } 
        else if (!preg_match('/^[0-9]{8}-[0-9A-Za-z]{1}$/', $rif_numero)) {
            $mensaje = "<div class='alert error'>⚠️ Formato de número de RIF incorrecto. Debe ser 8 dígitos, un guion y el dígito verificador (Ej: 12345678-9).</div>";
        } 
        else {
            // 1. Verificar si el Código SAP ya existe
            $stmtCheckSap = $conn->prepare("SELECT COUNT(*) FROM proveedores WHERE codigo_sap = ?");
            $stmtCheckSap->execute([$sap_value]);
            $existeSap = $stmtCheckSap->fetchColumn();

            // 2. Verificar si el RIF unificado ya existe
            $stmtCheckRif = $conn->prepare("SELECT COUNT(*) FROM proveedores WHERE rif = ?");
            $stmtCheckRif->execute([$rif]);
            $existeRif = $stmtCheckRif->fetchColumn();

            if ($existeSap > 0) {
                $mensaje = "<div class='alert error'>⚠️ El Código SAP '{$sap_value}' ya está asignado a otro proveedor registrado.</div>";
            } else if ($existeRif > 0) {
                $mensaje = "<div class='alert error'>⚠️ El RIF '{$rif}' ya se encuentra registrado en el sistema.</div>";
            } else {
                // --- PROCESAMIENTO DE ARCHIVOS ADJUNTOS ---
                $upload_dir = 'uploads/proveedores/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

                $docs = ['rif_adj' => null, 'acta_adj' => null, 'cert_adj' => null];
                $map = [
                    'rif_doc' => 'rif_adj',
                    'acta_doc' => 'acta_adj',
                    'cert_bancaria' => 'cert_adj'
                ];

                foreach ($map as $input => $key) {
                    if (!empty($_FILES[$input]['name'])) {
                        $ext = pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION);
                        $file_name = "PROV_" . $sap_value . "_" . $key . "_" . time() . "." . $ext;
                        if (move_uploaded_file($_FILES[$input]['tmp_name'], $upload_dir . $file_name)) {
                            $docs[$key] = $file_name;
                        }
                    }
                }

                // Inserción limpia a la Base de Datos
                $sql = "INSERT INTO proveedores (
                            codigo_proveedor, codigo_sap, nombre, rif, direccion, direccion_2,
                            pais, ciudad, codigo_postal, telefono, correo, 
                            estatus, banco_nombre, banco_cuenta, banco_tipo, 
                            banco_nombre_2, banco_cuenta_2, banco_tipo_2, 
                            rif_expedicion, rif_vencimiento,
                            rif_adjunto, acta_adjunto, cert_adjunto
                        ) VALUES (
                            :cod_prov, :sap, :nom, :rif, :dir, :dir2,
                            :pais, :ciu, :cp, :tlf, :email, 
                            :est, :bnc, :cnt, :tit, 
                            :bnc2, :cnt2, :tit2, 
                            :rexp, :rven,
                            :r_adj, :a_adj, :c_adj
                        )";

                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':cod_prov' => $sap_value,
                    ':sap'      => $sap_value,
                    ':nom'      => $nombre,
                    ':rif'      => $rif,
                    ':dir'      => $_POST['direccion'] ?? '',
                    ':dir2'     => $direccion_2,
                    ':pais'     => $_POST['pais'] ?? '',
                    ':ciu'      => $_POST['ciudad'] ?? '',
                    ':cp'       => $_POST['codigo_postal'] ?? '',
                    ':tlf'      => $_POST['contacto_tel'][0] ?? '',
                    ':email'    => $_POST['contacto_correo'][0] ?? '',
                    ':est'      => "Activo",
                    ':bnc'      => $banco1,
                    ':cnt'      => $cuenta1,
                    ':tit'      => $titular1, 
                    ':bnc2'     => $banco2,
                    ':cnt2'     => $cuenta2,
                    ':tit2'     => $titular2, 
                    ':rexp'     => !empty($_POST['rif_expedicion']) ? $_POST['rif_expedicion'] : null,
                    ':rven'     => !empty($_POST['rif_vencimiento']) ? $_POST['rif_vencimiento'] : null,
                    ':r_adj'    => $docs['rif_adj'],
                    ':a_adj'    => $docs['acta_adj'],
                    ':c_adj'    => $docs['cert_adj']
                ]);

                header("Location: perfilproveedores.php?success=1");
                exit();
            }
        }
    }
} catch(PDOException $e) {
    die("Error de sistema: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro Maestro de Proveedores - Locatel</title>
    <link rel="stylesheet" href="creacion_proveedor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-section-title { margin-top: 30px; padding-bottom: 10px; border-bottom: 2px solid #76bc43; color: #00953b; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .grid-2-bancos { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 15px; }
        .banco-card { background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee; }
        .file-upload-card { border: 2px dashed #ddd; padding: 15px; border-radius: 8px; text-align: center; background: #f9f9f9; cursor: pointer; transition: 0.3s; }
        .file-upload-card:hover { border-color: #00953b; background: #f0fdf4; }
        .file-upload-card i { font-size: 24px; color: #00953b; }
        .file-name-info { font-size: 11px; color: #007bff; margin-top: 5px; display: block; }
        
        /* Ajuste estructural RIF */
        .rif-container { display: flex; gap: 10px; }
        .rif-container select { width: 75px; padding: 10px; border-radius: 4px; border: 1px solid #ccc; }
        .rif-container input { flex-grow: 1; }

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
            <a href="vistafacturas.php" class="<?php echo ($pagina_actual == 'vistafacturas.php' || $pagina_actual == 'editar_factura.php') ? 'active-sub' : ''; ?>">
                <i class="fas fa-file-invoice"></i> FACTURACIÓN
            </a>
        <?php endif; ?>

        <?php if ($es_admin || (isset($_SESSION['acc_proveedores']) && $_SESSION['acc_proveedores'] == 1)): ?>
            <a href="perfilproveedores.php" class="<?php echo ($pagina_actual == 'perfilproveedores.php' || $pagina_actual == 'nuevo_proveedor.php' || $pagina_actual == 'creacion_proveedor.php') ? 'active-sub' : ''; ?>">
                <i class="fas fa-truck"></i> PROVEEDORES
            </a>
        <?php endif; ?>

        <?php if ($es_admin || (isset($_SESSION['acc_empresas']) && $_SESSION['acc_empresas'] == 1)): ?>
            <a href="mis_empresas.php" class="<?php echo ($pagina_actual == 'mis_empresas.php') ? 'active-sub' : ''; ?>">
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

    <main class="main-content">
        <div class="form-container">
            
            <?php echo $mensaje; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                
                <div class="form-section-title"><i class="fas fa-sync"></i> Datos desde SAP / Básicos</div>
                <div class="grid-inputs">
                    <div class="field">
                        <label>Código SAP Proveedor</label>
                        <input type="text" name="codigo_sap" placeholder="Ej: 10002345" required
                               pattern="^[0-9]+$" title="Solo dígitos (ej: 10002345)"
                               oninput="this.value=this.value.replace(/[^0-9]/g,'');"
                               value="<?php echo htmlspecialchars($_POST['codigo_sap'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>RIF / ID Fiscal</label>
                        <div class="rif-container">
                            <select name="rif_tipo">
                                <option value="V" <?php echo (isset($_POST['rif_tipo']) && $_POST['rif_tipo'] === 'V') ? 'selected' : ''; ?>>V</option>
                                <option value="E" <?php echo (isset($_POST['rif_tipo']) && $_POST['rif_tipo'] === 'E') ? 'selected' : ''; ?>>E</option>
                                <option value="J" <?php echo (!isset($_POST['rif_tipo']) || $_POST['rif_tipo'] === 'J') ? 'selected' : ''; ?>>J</option>
                                <option value="G" <?php echo (isset($_POST['rif_tipo']) && $_POST['rif_tipo'] === 'G') ? 'selected' : ''; ?>>G</option>
                            </select>
                            <input type="text" 
                                id="rif_numero" 
                                name="rif_numero" 
                                required 
                                placeholder="12345678-9" 
                                pattern="^[0-9]{8}-[0-9A-Za-z]{1}$" 
                                title="Ingrese 8 dígitos, un guion y su dígito verificador."
                                value="<?php echo htmlspecialchars($_POST['rif_numero'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="field full-width">
                    <label>Nombre / Razón Social (SAP)</label>
                    <input type="text" 
                        name="nombre" 
                        placeholder="Nombre oficial de la empresa" 
                        required 
                        oninput="this.value = this.value.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ.,& ]/g, '');"
                        value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                </div>
                </div>

                <div class="form-section-title"><i class="fas fa-map-marker-alt"></i> Localización y Dirección</div>
                <div class="grid-3">
                    <div class="field">
                        <label>País</label>
                        <select name="pais" required>
                            <option value="Venezuela" selected>Venezuela</option>
                            <option value="Colombia">Colombia</option>
                            <option value="Panama">Panamá</option>
                            <option value="USA">Estados Unidos</option>
                        </select>
                    </div>
                    <div class="field">
    <label>Ciudad</label>
    <input type="text" 
           name="ciudad" 
           placeholder="Ej: Caracas" 
           required 
           oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '');" 
           value="<?php echo htmlspecialchars($_POST['ciudad'] ?? ''); ?>">
</div>
                  <div class="field">
                    <label>Código Postal</label>
                    <input type="text" 
                        name="codigo_postal" 
                        placeholder="1010" 
                        maxlength="10"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '');" 
                        value="<?php echo htmlspecialchars($_POST['codigo_postal'] ?? ''); ?>">
                </div>
                </div>
                <div class="field full-width" style="margin-top:10px;">
                    <label>Dirección Fiscal Principal</label>
                    <textarea name="direccion" rows="2" required><?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?></textarea>
                </div>
                <div class="field full-width" style="margin-top:10px;">
                    <label>Dirección 2 / Sucursal (Opcional)</label>
                    <textarea name="direccion_2" rows="2"><?php echo htmlspecialchars($_POST['direccion_2'] ?? ''); ?></textarea>
                </div>

                <div class="form-section-title"><i class="fas fa-calendar-check"></i> Vigencia del RIF / ID Fiscal</div>
                <div class="grid-3" style="margin-top: 15px;">
                    <div class="field">
                        <label>Fecha de Expedición</label>
                        <input type="date" name="rif_expedicion" required value="<?php echo htmlspecialchars($_POST['rif_expedicion'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>Fecha de Vencimiento</label>
                        <input type="date" name="rif_vencimiento" required value="<?php echo htmlspecialchars($_POST['rif_vencimiento'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-section-title"><i class="fas fa-address-book"></i> Contactos Administrativos</div>
                <div class="grid-3">
                    <div class="field">
    <label>Nombre Contacto</label>
    <input type="text" 
           name="contacto_nombre[]" 
           placeholder="Maria Perez"
           oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '');">
</div>
                    <div class="field">
                        <label>Correo Electrónico</label>
                        <input type="email" name="contacto_correo[]" placeholder="admin@proveedor.com">
                    </div>
                    <div class="field">
                    <label>Teléfono</label>
                    <input type="text" 
                        name="contacto_tel[]" 
                        placeholder="0414-0000000"
                        maxlength="15"
                        oninput="this.value = this.value.replace(/[^0-9-]/g, '');">
                </div>
                </div>

                <div class="form-section-title"><i class="fas fa-university"></i> Datos Bancarios (Doble Cuenta)</div>
                <div class="grid-2-bancos">
                    <div class="banco-card">
                        <label><strong>Cuenta Principal</strong></label>
                        <div class="field">
                            <label>Banco</label>
                            <input type="text" 
                                name="banco_nombre" 
                                placeholder="Ej: Banesco" 
                                oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '');"
                                value="<?php echo htmlspecialchars($_POST['banco_nombre'] ?? ''); ?>">
                        </div>
                        <div class="field">
                            <label>Titular de la cuenta</label>
                            <input type="text" 
                                name="banco_titular" 
                                placeholder="Nombre del titular" 
                                oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '');"
                                value="<?php echo htmlspecialchars($_POST['banco_titular'] ?? ''); ?>">
                        </div>
                        <div class="field">
                        <label>Nro de Cuenta</label>
                        <input type="text" 
                            name="banco_cuenta" 
                            maxlength="20" 
                            placeholder="20 dígitos" 
                            oninput="this.value = this.value.replace(/[^0-9]/g, '');" 
                            value="<?php echo htmlspecialchars($_POST['banco_cuenta'] ?? ''); ?>">
                    </div>
                    </div>

                    <div class="banco-card">
                        <label><strong>Cuenta Secundaria (Opcional)</strong></label>
                        <div class="field">
                            <label>Banco</label>
                            <input type="text" 
                                name="banco_nombre_2" 
                                placeholder="Ej: Mercantil" 
                                oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '');"
                                value="<?php echo htmlspecialchars($_POST['banco_nombre_2'] ?? ''); ?>">
                        </div>
                        <div class="field">
                            <label>Titular de la cuenta</label>
                            <input type="text" 
                                name="banco_titular_2" 
                                placeholder="Nombre del titular" 
                                oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '');"
                                value="<?php echo htmlspecialchars($_POST['banco_titular_2'] ?? ''); ?>">
                        </div>
                        <div class="field">
                        <label>Nro de Cuenta</label>
                        <input type="text" 
                            name="banco_cuenta_2" 
                            maxlength="20" 
                            placeholder="20 dígitos" 
                            oninput="this.value = this.value.replace(/[^0-9]/g, '');" 
                            value="<?php echo htmlspecialchars($_POST['banco_cuenta_2'] ?? ''); ?>">
                    </div>
                    </div>
                </div>

                <div class="form-section-title"><i class="fas fa-file-upload"></i> Documentación Obligatoria</div>
                <div class="grid-3" style="margin-top:15px;">
                    <div class="file-upload-card" onclick="document.getElementById('rif_doc').click()">
                        <i class="fas fa-id-card"></i><br>
                        <label>RIF Actualizado</label><br>
                        <span id="name-rif" class="file-name-info"></span>
                        <input type="file" name="rif_doc" id="rif_doc" accept=".pdf,.jpg" style="display:none" onchange="document.getElementById('name-rif').innerText=this.files[0].name">
                    </div>
                    <div class="file-upload-card" onclick="document.getElementById('acta_doc').click()">
                        <i class="fas fa-gavel"></i><br>
                        <label>Acta Constitutiva</label><br>
                        <span id="name-acta" class="file-name-info"></span>
                        <input type="file" name="acta_doc" id="acta_doc" accept=".pdf" style="display:none" onchange="document.getElementById('name-acta').innerText=this.files[0].name">
                    </div>
                    <div class="file-upload-card" onclick="document.getElementById('cert_bancaria').click()">
                        <i class="fas fa-university"></i><br>
                        <label>Certificación Bancaria</label><br>
                        <span id="name-cert" class="file-name-info"></span>
                        <input type="file" name="cert_bancaria" id="cert_bancaria" accept=".pdf" style="display:none" onchange="document.getElementById('name-cert').innerText=this.files[0].name">
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 40px;">
                    <button type="button" class="btn-cancel" onclick="location.href='perfilproveedores.php'">Cancelar</button>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Finalizar Registro</button>
                </div>
            </form>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const fechaExp = document.getElementsByName('rif_expedicion')[0];
        const fechaVen = document.getElementsByName('rif_vencimiento')[0];
        const hoy = new Date().toISOString().split('T')[0];
        fechaExp.setAttribute('max', hoy);

        fechaExp.addEventListener('change', function() {
            fechaVen.setAttribute('min', this.value);
            if(fechaVen.value && fechaVen.value < this.value) {
                fechaVen.value = '';
                alert('La fecha de vencimiento no puede ser anterior a la de expedición.');
            }
        });
    });
    </script>
</body>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const minMap = { V:8, E:8, J:8, G:8 };
    const maxMap = { V:8, E:8, J:8, G:8 };
    const selectTipo = document.querySelector('select[name="rif_tipo"]');
    const inputRif = document.getElementById('rif_numero');

    function updatePattern(tipo) {
        const min = minMap[tipo] || 7;
        const max = maxMap[tipo] || 9;
        inputRif.setAttribute('pattern', `^[0-9]{${min},${max}}-[0-9A-Za-z]{1}$`);
        inputRif.title = `Formato: ${min} a ${max} dígitos, guion y dígito verificador.`;
    }

    function formatRif() {
        const tipo = (selectTipo.value || 'J').toUpperCase();
        const max = maxMap[tipo] || 9;
        const min = minMap[tipo] || 7;
        // eliminar caracteres no alfanuméricos
        let raw = inputRif.value.replace(/[^0-9A-Za-z]/g, '');
        // extraer solo dígitos para la parte numérica
        let digitsOnly = raw.replace(/[^0-9]/g, '');
        let numeric = digitsOnly.slice(0, max);
        // obtener carácter verificador (siguiente carácter después de la parte numérica en el raw)
        let remainder = raw.slice(numeric.length);
        let check = remainder.charAt(0) || '';

        // Si la parte numérica tiene menos que el mínimo, no mostrar guion ni verificador aún
        if (numeric.length < min && check === '') {
            inputRif.value = numeric;
        } else {
            let formatted = numeric;
            if (check) formatted += '-' + check.toUpperCase();
            inputRif.value = formatted;
        }
        updatePattern(tipo);
    }

    selectTipo.addEventListener('change', function() {
        formatRif();
        inputRif.focus();
    });

    inputRif.addEventListener('input', function() {
        formatRif();
    });

    // Formatear al cargar con valores preexistentes
    updatePattern(selectTipo.value || 'J');
    if (inputRif.value) formatRif();
});
</script>
</html>