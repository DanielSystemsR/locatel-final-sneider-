<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de conexión
$host = 'db'; $dbname = 'LocatelDB'; $user = 'sa'; $pass = 'LocatelPass2026!';

try {
    $dsn = "sqlsrv:Server=$host;Database=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. CARGAR DATOS ACTUALES
    if (isset($_GET['id'])) {
        $stmt = $conn->prepare("SELECT * FROM proveedores WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) die("Proveedor no encontrado.");
        
        // SEPARAR EL RIF ACTUAL (Ej: "J-31757880-5" -> Tipo: "J", Número: "317578805")
        $rif_completo = trim($p['rif'] ?? '');
        $rif_tipo_actual = !empty($rif_completo) ? substr($rif_completo, 0, 1) : 'J';

        // Extraemos el número y le borramos todos los guiones que vengan de la BD
        $rif_num_actual = !empty($rif_completo) ? substr($rif_completo, 1) : '';
        $rif_num_actual = str_replace('-', '', $rif_num_actual);
        
    } else {
        header("Location: perfilproveedores.php");
        exit();
    }

    // 2. PROCESAR FORMULARIO
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $id_interno = $_POST['id_interno'];
        $sap_value = trim($_POST['codigo_sap'] ?? '');
        $carpeta_destino = "uploads/proveedores/";

        // CONCATENAR EL TIPO DE RIF CON EL NÚMERO ANTES DE GUARDAR
        $rif_final = trim($_POST['rif_tipo'] ?? '') . trim($_POST['rif_numero'] ?? '');

        $mapeo_archivos = [
            'comp1' => 'rif_adjunto', 
            'comp2' => 'acta_adjunta', 
            'comp3' => 'certificacion_bancaria_adjunta'
        ];

        $sql_archivos = "";
        $params_archivos = [];

        foreach ($mapeo_archivos as $input_name => $columna_bd) {
            if (isset($_POST['delete_' . $input_name])) {
                if (!empty($p[$columna_bd]) && file_exists($carpeta_destino . $p[$columna_bd])) {
                    unlink($carpeta_destino . $p[$columna_bd]);
                }
                $sql_archivos .= ", $columna_bd = NULL";
            } 
            elseif (!empty($_FILES[$input_name]['name'])) {
                $ext = pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION);
                $nuevo_nombre = "PROV_" . $sap_value . "_" . $input_name . "_" . time() . "." . $ext;
                
                if (!is_dir($carpeta_destino)) { mkdir($carpeta_destino, 0777, true); }
                
                if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $carpeta_destino . $nuevo_nombre)) {
                    if (!empty($p[$columna_bd]) && file_exists($carpeta_destino . $p[$columna_bd])) {
                        unlink($carpeta_destino . $p[$columna_bd]);
                    }
                    $sql_archivos .= ", $columna_bd = :$columna_bd";
                    $params_archivos[":$columna_bd"] = $nuevo_nombre;
                }
            }
        }

        // UPDATE con banco_titular y banco_titular_2
        $sql = "UPDATE proveedores SET 
                    codigo_proveedor = :sap,
                    codigo_sap = :sap, 
                    nombre = :nom, 
                    rif = :rif, 
                    pais = :pais,
                    ciudad = :ciu,
                    codigo_postal = :cp,
                    direccion = :dir, 
                    direccion2 = :dir2,
                    telefono = :tlf, 
                    correo = :email, 
                    estatus = :est,
                    banco_nombre = :bnc, 
                    banco_cuenta = :cnt, 
                    banco_tipo = :btit,
                    banco_nombre_2 = :bnc2, 
                    banco_cuenta_2 = :cnt2, 
                    banco_tipo_2 = :btit2,
                    rif_expedicion = :rexp, 
                    rif_vencimiento = :rven
                    $sql_archivos
                WHERE id = :id";

        $stmtUpdate = $conn->prepare($sql);
        $params = [
            ':sap'   => $sap_value,
            ':nom'   => $_POST['nombre'],
            ':rif'   => $rif_final, // Se guarda unido
            ':pais'  => $_POST['pais'],
            ':ciu'   => $_POST['ciudad'],
            ':cp'    => $_POST['codigo_postal'],
            ':dir'   => $_POST['direccion'],
            ':dir2'  => $_POST['direccion2'],
            ':tlf'   => $_POST['telefono'],
            ':email' => $_POST['correo'],
            ':est'   => $_POST['estatus'],
            ':bnc'   => $_POST['banco_nombre'],
            ':cnt'   => $_POST['banco_cuenta'],
            ':btit'  => $_POST['banco_tipo'],
            ':bnc2'  => !empty($_POST['banco_nombre_2']) ? $_POST['banco_nombre_2'] : null,
            ':cnt2'  => !empty($_POST['banco_cuenta_2']) ? $_POST['banco_cuenta_2'] : null,
            ':btit2' => !empty($_POST['banco_tipo_2']) ? $_POST['banco_tipo_2'] : null,
            ':rexp'  => !empty($_POST['rif_expedicion']) ? $_POST['rif_expedicion'] : null,
            ':rven'  => !empty($_POST['rif_vencimiento']) ? $_POST['rif_vencimiento'] : null,
            ':id'    => $id_interno
        ];

        $stmtUpdate->execute(array_merge($params, $params_archivos));
        header("Location: perfilproveedores.php?updated=1");
        exit();
    }
} catch(PDOException $e) {
    die("<div style='color:red; padding:20px;'><h3>❌ Error</h3>" . $e->getMessage() . "</div>");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Proveedor | Locatel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-image: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('images/fondo.png');background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;; margin: 0; padding: 20px; }
        .form-container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 1000px; margin: 20px auto; }
        .form-section-title { margin-top: 30px; padding-bottom: 10px; border-bottom: 2px solid #76bc43; color: #00953b; font-size: 18px; display: flex; align-items: center; gap: 10px; font-weight: bold; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 15px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; }
        .field { display: flex; flex-direction: column; gap: 5px; }
        .field label { font-size: 11px; font-weight: bold; color: #666; text-transform: uppercase; }
        .field input, .field select, .field textarea { padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 13px; }
        .full-width { grid-column: span 3; }
        .banco-card { background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee; }
        .upload-card { background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 8px; }
        .btn-save { background: #00953b; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-cancel { background: #6c757d; color: white; padding: 12px 25px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .file-controls { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; background: #e8f5e9; padding: 5px 10px; border-radius: 4px; font-size: 12px; }
        
        /* Contenedor especial para juntar el Select y el Input del RIF */
        .rif-container { display: flex; gap: 5px; }
        .rif-container select { width: 30%; }
        .rif-container input { width: 70%; }
    </style>
</head>
<body>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <a href="perfilproveedores.php" style="color: #00953b; font-weight: bold; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
                <div style="background: #e3f2fd; color: #0d47a1; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: bold;">
                    ID Interno: <?php echo $p['id']; ?> | SAP: <?php echo htmlspecialchars($p['codigo_sap']); ?>
                </div>
            </div>

            <input type="hidden" name="id_interno" value="<?php echo $p['id']; ?>">

            <div class="form-section-title"><i class="fas fa-id-card"></i> Identificación y Contacto</div>
            <div class="grid-3">
                <div class="field">
                    <label>Código SAP</label>
                    <input type="text" name="codigo_sap" value="<?php echo htmlspecialchars($p['codigo_sap']); ?>" required>
                </div>
                
                <div class="field">
                    <label>RIF</label>
                    <div class="rif-container">
                        <select name="rif_tipo" required>
                            <option value="V" <?php echo ($rif_tipo_actual == 'V') ? 'selected' : ''; ?>>V</option>
                            <option value="J" <?php echo ($rif_tipo_actual == 'J') ? 'selected' : ''; ?>>J</option>
                            <option value="G" <?php echo ($rif_tipo_actual == 'G') ? 'selected' : ''; ?>>G</option>
                            <option value="E" <?php echo ($rif_tipo_actual == 'E') ? 'selected' : ''; ?>>E</option>
                            <option value="P" <?php echo ($rif_tipo_actual == 'P') ? 'selected' : ''; ?>>P</option>
                        </select>
                        <input type="text" 
                               name="rif_numero" 
                               placeholder="Ej: 123456789" 
                               maxlength="10" 
                               oninput="this.value = this.value.replace(/[^0-9]/g, '');" 
                               value="<?php echo htmlspecialchars($rif_num_actual); ?>" 
                               required>
                    </div>
                </div>

                <div class="field">
                    <label>Razón Social</label>
                    <input type="text" name="nombre" oninput="this.value = this.value.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ.,& ]/g, '');" value="<?php echo htmlspecialchars($p['nombre']); ?>" required>
                </div>
                <div class="field">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" maxlength="15" oninput="this.value = this.value.replace(/[^0-9-]/g, '');" value="<?php echo htmlspecialchars($p['telefono']); ?>">
                </div>
                <div class="field">
                    <label>Correo</label>
                    <input type="email" name="correo" value="<?php echo htmlspecialchars($p['correo']); ?>">
                </div>
                <div class="field">
                    <label>Estatus</label>
                    <select name="estatus">
                        <option value="Activo" <?php echo ($p['estatus'] == 'Activo')?'selected':''; ?>>ACTIVO</option>
                        <option value="Inactivo" <?php echo ($p['estatus'] == 'Inactivo')?'selected':''; ?>>INACTIVO</option>
                    </select>
                </div>
            </div>

            <div class="form-section-title"><i class="fas fa-map-marker-alt"></i> Localización</div>
            <div class="grid-3">
                <div class="field">
                    <label>País</label>
                    <select name="pais">
                        <option value="Venezuela" <?php echo ($p['pais'] == 'Venezuela')?'selected':''; ?>>Venezuela</option>
                        <option value="Colombia" <?php echo ($p['pais'] == 'Colombia')?'selected':''; ?>>Colombia</option>
                        <option value="Panama" <?php echo ($p['pais'] == 'Panama')?'selected':''; ?>>Panamá</option>
                    </select>
                </div>
                <div class="field">
                    <label>Ciudad</label>
                    <input type="text" name="ciudad" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '');" value="<?php echo htmlspecialchars($p['ciudad']); ?>">
                </div>
                <div class="field">
                    <label>Código Postal</label>
                    <input type="text" name="codigo_postal" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '');" value="<?php echo htmlspecialchars($p['codigo_postal']); ?>">
                </div>
                <div class="full-width field"><label>Dirección Fiscal Principal</label><textarea name="direccion" rows="2"><?php echo htmlspecialchars($p['direccion']); ?></textarea></div>
                <div class="full-width field">
                    <label>Dirección 2 / Sucursal</label>
                    <textarea name="direccion2" rows="2"><?php echo htmlspecialchars($p['direccion2'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-section-title"><i class="fas fa-university"></i> Cuentas Bancarias</div>
            <div class="grid-2">
                <div class="banco-card">
                    <label style="color:#00953b; font-weight:bold;">Cuenta #1 (Principal)</label>
                    <div class="field" style="margin-top:10px;">
                        <label>Banco</label>
                        <input type="text" name="banco_nombre" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '');" value="<?php echo htmlspecialchars($p['banco_nombre']?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>Titular de la cuenta</label>
                        <input type="text" name="banco_tipo" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '');" value="<?php echo htmlspecialchars($p['banco_tipo'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>Nro de Cuenta</label>
                        <input type="text" name="banco_cuenta" maxlength="20" oninput="this.value = this.value.replace(/[^0-9]/g, '');" value="<?php echo htmlspecialchars($p['banco_cuenta']?? ''); ?>">
                    </div>
                </div>
                <div class="banco-card">
                    <label style="color:#76bc43; font-weight:bold;">Cuenta #2 (Secundaria)</label>
                    <div class="field" style="margin-top:10px;">
                        <label>Banco</label>
                        <input type="text" name="banco_nombre_2" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '');" value="<?php echo htmlspecialchars($p['banco_nombre_2'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>Titular de la cuenta</label>
                        <input type="text" name="banco_tipo_2" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '');" value="<?php echo htmlspecialchars($p['banco_tipo_2'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>Nro de Cuenta</label>
                        <input type="text" name="banco_cuenta_2" maxlength="20" oninput="this.value = this.value.replace(/[^0-9]/g, '');" value="<?php echo htmlspecialchars($p['banco_cuenta_2'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section-title"><i class="fas fa-file-pdf"></i> Documentación Actualizada</div>
            <div class="grid-3">
                <?php 
                $adjuntos = [
                    'comp1' => ['label' => 'RIF Actualizado', 'col' => 'rif_adjunto'],
                    'comp2' => ['label' => 'Acta Constitutiva', 'col' => 'acta_adjunta'],
                    'comp3' => ['label' => 'Certificación Bancaria', 'col' => 'certificacion_bancaria_adjunta']
                ];

                foreach($adjuntos as $input => $info): 
                    $file = $p[$info['col']] ?? null; 
                ?>
                <div class="upload-card">
                    <label style="font-size: 11px; font-weight: bold;"><?php echo $info['label']; ?></label>
                    
                    <?php if(!empty($file)): ?>
                        <div class="file-controls">
                            <a href="uploads/proveedores/<?php echo htmlspecialchars($file); ?>" target="_blank" style="color: #00953b; text-decoration:none;">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                            <label style="margin-left:auto; color:red; cursor:pointer;">
                                <input type="checkbox" name="delete_<?php echo $input; ?>" value="1"> 
                                <i class="fas fa-trash"></i>
                            </label>
                        </div>
                    <?php else: ?>
                        <div style="font-size: 9px; color: #666; margin-bottom: 5px;">Sin archivo previo</div>
                    <?php endif; ?>

                    <input type="file" name="<?php echo $input; ?>" style="font-size: 10px; width: 100%;">
                </div>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> ACTUALIZAR PROVEEDOR</button>
                <a href="perfilproveedores.php" class="btn-cancel">CANCELAR</a>
            </div>
        </div>
    </form>
</body>
</html>