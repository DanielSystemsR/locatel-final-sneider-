<?php
// Script de revisión: muestra filas que incumplen las restricciones de solo-dígitos
include 'registro.php'; // proporciona $conn

try {
    echo "<h2>Revisión de restricciones</h2>";

    // Proveedores: codigo_sap no numérico o vacío
    $sqlProv = "SELECT id, codigo_sap, nombre FROM proveedores WHERE codigo_sap IS NULL OR LTRIM(RTRIM(codigo_sap)) = '' OR codigo_sap LIKE '%[^0-9]%'";
    $stmt = $conn->query($sqlProv);
    $provRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Proveedores con codigo_sap inválido</h3>";
    if (count($provRows) === 0) {
        echo "<p>Todos los proveedores cumplen la restricción.</p>";
    } else {
        echo "<table border=1 cellpadding=6><tr><th>ID</th><th>codigo_sap</th><th>Nombre</th></tr>";
        foreach ($provRows as $r) {
            echo "<tr><td>".htmlspecialchars($r['id'])."</td><td>".htmlspecialchars($r['codigo_sap'])."</td><td>".htmlspecialchars($r['nombre'])."</td></tr>";
        }
        echo "</table>";
    }

    // Facturas: nro_factura no numérico or empty
    $sqlFac = "SELECT id, nro_factura, codigo_proveedor FROM facturas WHERE nro_factura IS NULL OR LTRIM(RTRIM(nro_factura)) = '' OR nro_factura LIKE '%[^0-9]%'";
    $stmt2 = $conn->query($sqlFac);
    $facRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Facturas con nro_factura inválido</h3>";
    if (count($facRows) === 0) {
        echo "<p>Todas las facturas cumplen la restricción.</p>";
    } else {
        echo "<table border=1 cellpadding=6><tr><th>ID</th><th>nro_factura</th><th>codigo_proveedor</th></tr>";
        foreach ($facRows as $r) {
            echo "<tr><td>".htmlspecialchars($r['id'])."</td><td>".htmlspecialchars($r['nro_factura'])."</td><td>".htmlspecialchars($r['codigo_proveedor'])."</td></tr>";
        }
        echo "</table>";
    }

} catch (PDOException $e) {
    echo "<div style='color:red;'>Error al ejecutar la revisión: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<p><a href=\"perfilproveedores.php\">Volver</a></p>";

?>
