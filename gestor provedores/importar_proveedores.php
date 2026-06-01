<?php
// 1. Configuración de conexión
$host   = 'db'; 
$dbname = 'LocatelDB';
$user   = 'sa';
$pass   = 'LocatelPass2026!';

try {
    $dsn = "sqlsrv:Server=$host;Database=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Error crítico: " . $e->getMessage());
}

$archivo = 'proveedores.txt';

if (file_exists($archivo)) {
    $gestor = fopen($archivo, "r");
    
    /**
     * 2. SQL BLINDADO
     * Hemos quitado rif_expedicion, rif_vencimiento y fecha_registro 
     * para que SQL Server no intente convertir strings vacíos en fechas.
     */
    $sql = "INSERT INTO proveedores (
        codigo_proveedor, codigo_sap, nombre, rif, direccion, direccion_2, 
        pais, ciudad, codigo_postal, telefono, correo, estatus, 
        banco_nombre, banco_cuenta, banco_tipo, banco_nombre_2, banco_cuenta_2, banco_tipo_2, 
        rif_adjunto, acta_adjunto, cert_adjunto
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )";

    $stmt = $conn->prepare($sql);

    echo "--- Iniciando Importación (Modo Compatibilidad Total) ---<br>";

    $fila = 0;
    while (($linea = fgets($gestor)) !== false) {
        $linea = trim($linea);
        if (empty($linea)) continue;

        $datos = explode("|", $linea);
        $fila++;

        if (count($datos) >= 14) {
            try {
                // Función para asegurar que siempre se envíe un string (aunque sea vacío)
                $s = function($indice) use ($datos) {
                    return (isset($datos[$indice]) && trim($datos[$indice]) !== '') ? trim($datos[$indice]) : '';
                };

                // Construimos el array de parámetros (21 columnas de texto)
                $params = [
                    $s(8),          // codigo_proveedor
                    '',             // codigo_sap
                    $s(1),          // nombre
                    $s(9),          // rif
                    $s(3),          // direccion
                    $s(4),          // direccion_2
                    $s(0),          // pais
                    $s(5),          // ciudad
                    $s(6),          // codigo_postal
                    $s(10),         // telefono
                    $s(7),          // correo
                    'Activo',       // estatus
                    $s(13),         // banco_nombre
                    $s(14),         // banco_cuenta
                    '',             // banco_tipo
                    '',             // banco_nombre_2
                    '',             // banco_cuenta_2
                    '',             // banco_tipo_2
                    '',             // rif_adjunto
                    '',             // acta_adjunto
                    ''              // cert_adjunto
                ];

                $stmt->execute($params);
                echo "[$fila] ✅ " . $datos[1] . " importado.<br>";

            } catch (PDOException $e) {
                echo "[$fila] ❌ Error en " . ($datos[1] ?? 'Fila') . ": " . $e->getMessage() . "<br>";
            }
        }
    }

    fclose($gestor);
    echo "--- Finalizado ---";
} else {
    echo "No existe el archivo proveedores.txt";
}
?>