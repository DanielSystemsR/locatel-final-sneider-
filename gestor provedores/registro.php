<?php
ob_start();
// Datos de conexión
$host = 'db'; 
$dbname = 'LocatelDB';
$user = 'sa';
$pass = 'LocatelPass2026!';

try {
    // CAMBIO CLAVE: Usar sqlsrv en lugar de mysql
    $conn = new PDO("sqlsrv:Server=$host;Database=$dbname", $user, $pass);
    
    // Configurar para que lance excepciones en caso de error
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        // 1. Obtener el nombre del proveedor
        $id_prov = $_POST['proveedor_id'] ?? null;
        $stmt_name = $conn->prepare("SELECT nombre FROM proveedores WHERE id = ?");
        $stmt_name->execute([$id_prov]);
        $nombre_proveedor = $stmt_name->fetchColumn();

        // 2. Gestión de Archivos
        $folder = "uploads/";
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        $c1 = null; $c2 = null; $c3 = null;

        for ($i = 1; $i <= 3; $i++) {
            $key = "comp$i";
            if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);
                $nro_limpio = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['nro_factura'] ?? 'fact');
                $newName = "FAC_" . $nro_limpio . "_$i" . "_" . time() . "." . $ext;
                
                if (move_uploaded_file($_FILES[$key]['tmp_name'], $folder . $newName)) {
                    if ($i == 1) $c1 = $newName;
                    if ($i == 2) $c2 = $newName;
                    if ($i == 3) $c3 = $newName;
                }
            }
        }

        // 3. Preparar array de datos (AHORA INCLUYE SOCIEDAD)
        $data = [
            ':prov'           => $id_prov,
            ':nom_p'          => $nombre_proveedor,
            ':soc'            => $_POST['sociedad_destino'] ?? null, // <-- AGREGADO
            ':nro'            => $_POST['nro_factura'] ?? null,
            ':f_emision'      => !empty($_POST['fecha_emision']) ? $_POST['fecha_emision'] : null,
            ':f_recibida'     => !empty($_POST['fecha_recibida']) ? $_POST['fecha_recibida'] : null,
            ':condicion'      => $_POST['condicion_pago'] ?? null,
            ':f_vencimiento'  => !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
            ':bs'             => number_format((float)($_POST['monto_bs'] ?? 0), 2, '.', ''),
            ':tasa'           => number_format((float)($_POST['tasa_cambio'] ?? 0), 2, '.', ''),
            ':usd'            => number_format((float)($_POST['monto_usd'] ?? 0), 2, '.', ''),
            ':est'            => $_POST['estado'] ?? 'Pendiente',
            ':obs'            => $_POST['observaciones'] ?? '',
            ':c1'             => $c1,
            ':c2'             => $c2,
            ':c3'             => $c3
        ];

        // 4. SQL Final (AHORA INCLUYE LA COLUMNA sociedad_destino)
        $sql = "INSERT INTO facturas (
                    codigo_proveedor, nombre, sociedad_destino, nro_factura, fecha_emision, 
                    fecha_recibida, condicion_pago, fecha_vencimiento, 
                    monto_bs, tasa_cambio, monto_usd, estatus, observaciones, 
                    comp1, comp2, comp3
                ) VALUES (
                    :prov, :nom_p, :soc, :nro, :f_emision, 
                    :f_recibida, :condicion, :f_vencimiento, 
                    :bs, :tasa, :usd, :est, :obs, 
                    :c1, :c2, :c3
                )";

        $stmt = $conn->prepare($sql);
        $stmt->execute($data);

        if (ob_get_length()) ob_end_clean();
        header("Location: vistafacturas.php?status=success");
        exit();
    }

} catch(PDOException $e) {
    if (ob_get_length()) ob_end_clean();
    echo "<div style='background:#fee; color:#c00; padding:20px; border:2px solid #c00; border-radius:8px; font-family:sans-serif; margin: 20px;'>";
    echo "<h3>❌ Error al guardar en LocatelDB</h3>";
    echo "<p><b>Detalle técnico:</b> " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>