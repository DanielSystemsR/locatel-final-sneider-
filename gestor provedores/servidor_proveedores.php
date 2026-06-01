<?php
session_start(); 

error_reporting(E_ALL);
ini_set('display_errors', 0); 
header('Content-Type: application/json');

$host = 'db'; 
$dbname = 'LocatelDB';
$user = 'sa';
$pass = 'LocatelPass2026!';

try {
    $conn = new PDO("sqlsrv:Server=$host;Database=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

   $es_admin     = (isset($_SESSION['user_rol']) && strtolower(trim($_SESSION['user_rol'])) === 'admin');
    $puede_editar = (isset($_SESSION['p_edit']) && $_SESSION['p_edit'] == 1);
    $puede_estatus = (isset($_SESSION['p_est']) && $_SESSION['p_est'] == 1); // Nueva variable
    // Parametros de DataTables
    $busqueda = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
    $start    = isset($_POST['start']) ? (int)$_POST['start'] : 0;
    $length   = isset($_POST['length']) ? (int)$_POST['length'] : 25;

    // 1. Conteo Total
    $totalRecords = $conn->query("SELECT COUNT(*) FROM proveedores")->fetchColumn();

    // 2. Consulta (Agregamos el ID al final del ORDER BY para estabilidad)
    $sql = "SELECT id, codigo_sap, nombre, rif, telefono, correo, estatus 
            FROM proveedores 
            WHERE nombre LIKE ? OR rif LIKE ? OR codigo_sap LIKE ? 
            ORDER BY id DESC 
            OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";

    $stmt = $conn->prepare($sql);
    $term = "%$busqueda%";
    $stmt->execute([$term, $term, $term]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Conteo Filtrado
    $sqlCount = "SELECT COUNT(*) FROM proveedores WHERE nombre LIKE ? OR rif LIKE ? OR codigo_sap LIKE ?";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute([$term, $term, $term]);
    $recordsFiltered = $stmtCount->fetchColumn();

    // 4. Formateo de Datos
    $data = [];
    foreach ($resultados as $row) {
        $estatus_actual = $row['estatus'] ?? 'Inactivo';
        $es_activo = (strtolower($estatus_actual) == 'activo');
        
        // Estilo del texto del estatus
        $color_texto = $es_activo ? '#76bc43' : '#ffa400';
        $badge_estatus = "<span style='color:$color_texto; font-weight:bold;'>" . strtoupper(htmlspecialchars($estatus_actual)) . "</span>";

       // --- BOTONES DE ACCIÓN ---
        // El 'Ver' siempre está disponible
        $acciones = "<i class='fas fa-eye' title='Ver Detalle' style='cursor:pointer; color:#00953b;' onclick='window.location.href=\"ver_proveedor.php?id={$row['id']}\"'></i>";

        // 1. Lógica para EDITAR
        if ($es_admin || $puede_editar) {
            $acciones .= " <i class='fas fa-edit' title='Editar' style='cursor:pointer; color:#76bc43;' onclick='window.location.href=\"editar_proveedor.php?id={$row['id']}\"'></i>";
        }

        // 2. Lógica para CAMBIAR ESTATUS (Toggle)
        if ($es_admin || $puede_estatus) {
            $icono_toggle = $es_activo ? 'fa-toggle-on' : 'fa-toggle-off';
            $color_toggle = $es_activo ? '#76bc43' : '#ccc';
            $titulo_toggle = $es_activo ? 'Desactivar Proveedor' : 'Activar Proveedor';

            $acciones .= " <i class='fas $icono_toggle' title='$titulo_toggle' style='cursor:pointer; color:$color_toggle; font-size:1.2rem;' onclick='cambiarEstatus({$row['id']}, \"$estatus_actual\")'></i>";
        }
        
        $data[] = [
            "<strong>" . htmlspecialchars($row['codigo_sap'] ?? 'S/C') . "</strong>",
            htmlspecialchars($row['nombre']),
            htmlspecialchars($row['rif']),
            htmlspecialchars($row['telefono'] ?? 'S/N'),
            htmlspecialchars($row['correo'] ?? 'S/C'),
            $badge_estatus,
            "<div class='actions' style='display:flex; gap:12px; justify-content:center; align-items:center;'>$acciones</div>"
        ];
    }

    echo json_encode([
        "draw" => intval($_POST['draw'] ?? 1),
        "recordsTotal" => (int)$totalRecords,
        "recordsFiltered" => (int)$recordsFiltered,
        "data" => $data
    ]);

} catch(PDOException $e) {
    echo json_encode([
        "error" => "Error en LocatelDB: " . $e->getMessage(),
        "data" => []
    ]);
}
?>