<?php
session_start();
require_once 'auth_check.php'; 

// El resto de tu código...
$es_admin = ($_SESSION['user_rol'] === 'admin');

// 1. PROTECCIÓN DE RUTA Y MÓDULO
if (!isset($_SESSION['user_id'])) {
    header("Location: iniciosesion.php");
    exit();
}

if (isset($_SESSION['acc_proveedores']) && $_SESSION['acc_proveedores'] == 0) {
    header("Location: vistafacturas.php?error=sin_acceso");
    exit();
}

// Variables de permisos
// Variables de permisos (CORREGIDAS)
$puede_crear  = (isset($_SESSION['p_crear']) && $_SESSION['p_crear'] == 1);
$es_admin     = (isset($_SESSION['user_rol']) && strtolower(trim($_SESSION['user_rol'])) === 'admin');

// Usamos p_edit que es el permiso que mapeamos en el login para proveedores
$puede_editar_prov = (isset($_SESSION['p_edit']) && $_SESSION['p_edit'] == 1);

// 2. LÓGICA DE CIERRE DE SESIÓN
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: iniciosesion.php");
    exit();
}

// -------------------------------------------------------------------------
// 3. CONEXIÓN A LA BASE DE DATOS (DEBE IR ANTES DE LA LÓGICA DE ESTATUS)
// -------------------------------------------------------------------------
$host = 'db'; 
$dbname = 'LocatelDB';
$user = 'sa'; 
$pass = 'LocatelPass2026!';

try {
    $dsn = "sqlsrv:Server=$host;Database=$dbname";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /// 4. LÓGICA DE CAMBIO DE ESTATUS
if (isset($_GET['id']) && isset($_GET['current_status'])) {
    // Validar con la nueva variable de proveedores
    if (!$es_admin && !$puede_editar_prov) {
        header("Location: perfilproveedores.php?error=no_permiso");
        exit();
    }
    
    $id_status = $_GET['id'];
    // Normalizamos a minúsculas para comparar mejor
    $status_recibido = trim($_GET['current_status']);
    $nuevo_estatus = (strtolower($status_recibido) === 'activo') ? 'Inactivo' : 'Activo';

    $sql = "UPDATE proveedores SET estatus = :nuevo WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':nuevo' => $nuevo_estatus,
        ':id' => $id_status
    ]);
    
    header("Location: perfilproveedores.php?status_updated=true");
    exit();
}

} catch(PDOException $e) { 
    $error = "Error de sistema: " . $e->getMessage(); 
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de Proveedores - Locatel</title>
    
    <link rel="stylesheet" href="perfilproveedor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <style>
    /* Ocultamos el filtro por defecto de DataTables */
    .dataTables_wrapper .dataTables_filter { display: none; }
    
    /* Estilo para el selector de cantidad de registros */
    .dataTables_length {
        float: left;
        margin-bottom: 20px;
        background: #f4f4f4;
        padding: 8px 15px;
        border-radius: 50px;
        border: 1px solid #ddd;
        font-size: 13px;
        color: #444;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .dataTables_length select {
        border: 2px solid #00953b;
        border-radius: 6px;
        padding: 2px 8px;
        color: #00953b;
        font-weight: bold;
        outline: none;
    }

    /* --- AJUSTE DE LA TABLA --- */
    table.dataTable {
        border-collapse: collapse !important;
        /* IMPORTANTE: Cambiamos fixed por auto para que use el 100% del espacio */
        table-layout: auto !important; 
        width: 100% !important;
        margin-top: 10px;
    }

    /* Alineación mejorada: textos largos a la izquierda, indicadores al centro */
    th, td { 
        padding: 12px 15px;
        text-align: left; 
        vertical-align: middle;
        /* Quitamos el overflow hidden para permitir que el contenido defina el ancho */
        white-space: normal; 
    }

    /* Centramos solo columnas de códigos y acciones */
    th:nth-child(1), td:nth-child(1), /* SAP */
    th:nth-child(6), td:nth-child(6), /* Estatus */
    th:nth-child(7), td:nth-child(7)  /* Acciones */ { 
        text-align: center !important; 
    }

    .btn-logout-top {
        color: #d32f2f; text-decoration: none; font-weight: bold;
        margin-left: 15px; padding: 5px 12px; border: 1px solid #d32f2f;
        border-radius: 4px; font-size: 13px; display: inline-flex;
        align-items: center; gap: 5px;
    }
</style>
</head>
<body>

 <aside class="sidebar">
    <div class="sidebar-logo">
        <img src="images/Logo_Locatel (1).png" alt="Logo" class="mini-logo">
    </div>
    <nav class="sidebar-nav">
        <!-- Facturación no lleva la clase activa aquí -->
        <?php if ($es_admin || (isset($_SESSION['acc_facturas']) && $_SESSION['acc_facturas'] == 1)): ?>
            <a href="vistafacturas.php">
                <i class="fas fa-home"></i> FACTURACIÓN
            </a>
        <?php endif; ?>

        <!-- Aplicamos la clase activa a PROVEEDORES -->
        <?php if ($es_admin || (isset($_SESSION['acc_proveedores']) && $_SESSION['acc_proveedores'] == 1)): ?>
            <a href="perfilproveedores.php" class="active-sub">
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
            <div class="search-box">
                <div class="input-wrapper">
                    <input type="text" id="busqueda-proveedor" autocomplete="off" placeholder="Buscar proveedor...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
            
            <div class="user-info">
                <span class="user-email" style="font-weight: bold; color: #444;">
                    <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?>
                </span>
                <i class="fas fa-user-circle user-icon" style="font-size: 24px; color: #00953b;"></i>
                <a href="perfilproveedores.php?logout=true" class="btn-logout-top"><i class="fas fa-power-off"></i> Salir</a>
            </div>
        </header>

        <section class="content-header">
            <h1>Directorio de Proveedores</h1>
            <?php if ($es_admin || $puede_crear): ?>
                <button class="btn-new" onclick="location.href='creacion_proveedor.php'">
                    <i class="fas fa-plus"></i> REGISTRAR PROVEEDOR
                </button>
            <?php endif; ?>
        </section>

        <div class="table-container">
            <table id="tabla-proveedores" class="table table-striped">
                <thead>
                    <tr>
                        <th>SAP</th> 
                        <th>Nombre / Razón Social</th>
                        <th>RIF / ID</th>
                        <th>Teléfono</th>
                        <th>Correo</th> 
                        <th>Estatus</th>
                        <th>Acciones</th> 
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </main>

    <script>
    $(document).ready(function() {
        var table = $('#tabla-proveedores').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "servidor_proveedores.php",
                "type": "POST"
            },
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
            "order": [], 
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            },
            "dom": '<"top"l>rt<"bottom"ip><"clear">',
            "columnDefs": [
                { "orderable": false, "targets": "_all" }
            ]
        });

        // Buscador personalizado
        let timeout = null;
        $('#busqueda-proveedor').on('keyup', function() {
            clearTimeout(timeout);
            let valor = this.value;
            timeout = setTimeout(function() {
                table.search(valor).draw();
            }, 400); 
        });

        // Alertas de éxito
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status_updated')) {
            Swal.fire('¡Actualizado!', 'El estatus del proveedor ha sido cambiado.', 'success');
        }
    });

    function cambiarEstatus(idProveedor, estatusActual) {
        const accion = (estatusActual === 'Activo') ? 'Desactivar' : 'Activar';
        const color = (estatusActual === 'Activo') ? '#d33' : '#00953b';

        Swal.fire({
            title: `¿${accion} proveedor?`,
            text: `El proveedor pasará a estar ${estatusActual === 'Activo' ? 'Inactivo' : 'Activo'}.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: color,
            cancelButtonColor: '#6e7881',
            confirmButtonText: `Sí, ${accion}`,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `perfilproveedores.php?id=${idProveedor}&current_status=${estatusActual}`;
            }
        });
    }
    </script>
</body>
</html>