<?php
require 'includes/header.php';
require 'includes/db.php';

if (!isset($_GET['id_cliente'])) {
    header('Location: index.php');
    exit();
}

$id_cliente = $_GET['id_cliente'];

// Obtener datos del cliente
$stmt_cliente = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt_cliente->execute([$id_cliente]);
$cliente = $stmt_cliente->fetch();

if (!$cliente) die('Cliente no encontrado');

// --- Valores por defecto para el formulario ---
$meses_orden = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$mes_actual_nombre = $meses_orden[date('n') - 1];

// Obtener el monto del ÚLTIMO pago creado
$stmt_ultimo_monto = $pdo->prepare("SELECT monto_a_pagar FROM pagos WHERE id_cliente = ? ORDER BY id DESC LIMIT 1");
$stmt_ultimo_monto->execute([$id_cliente]);
$ultimo_monto_sugerido = $stmt_ultimo_monto->fetchColumn() ?: '';

// Obtener pagos ordenados por ID DESC (el más nuevo primero)
$stmt_pagos = $pdo->prepare("SELECT * FROM pagos WHERE id_cliente = ? ORDER BY id DESC");
$stmt_pagos->execute([$id_cliente]);
$pagos = $stmt_pagos->fetchAll();

// Lógica para deuda acumulativa
$stmt_pagos_cronologico = $pdo->prepare("SELECT * FROM pagos WHERE id_cliente = ? ORDER BY anio ASC, FIELD(mes, '" . implode("','", $meses_orden) . "') ASC");
$stmt_pagos_cronologico->execute([$id_cliente]);
$pagos_cronologicos = $stmt_pagos_cronologico->fetchAll();

$deuda_acumulada_map = [];
$deuda_total = 0;
foreach($pagos_cronologicos as $pago_cron) {
    $deuda_mes = $pago_cron['monto_a_pagar'] - $pago_cron['monto_pagado'];
    $deuda_total += $deuda_mes;
    $deuda_acumulada_map[$pago_cron['id']] = $deuda_total;
}

?>

<h1>Pagos de: <?php echo htmlspecialchars($cliente['nombre']); ?></h1>
<p>
    <strong>Razón Social:</strong> <?php echo htmlspecialchars($cliente['razon_social']); ?> | 
    <strong>CUIT:</strong> <?php echo htmlspecialchars($cliente['cuit']); ?> | 
    <strong>Email:</strong> <?php echo htmlspecialchars($cliente['email']); ?>
</p>

<!-- Notificaciones -->
<?php if (isset($_SESSION['status'])): ?>
    <div class="alert alert-<?php echo $_SESSION['status_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['status']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['status'], $_SESSION['status_type']); ?>
<?php endif; ?>


<!-- Formulario para Nuevo Pago -->
<div class="card mb-4">
    <div class="card-header">
        Generar Nuevo Pago
    </div>
    <div class="card-body">
        <form action="pago_acciones.php" method="post" class="row g-3 align-items-end">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="id_cliente" value="<?php echo $id_cliente; ?>">

            <div class="col-md-2">
                <label for="mes" class="form-label">Mes</label>
                <select id="mes" name="mes" class="form-select" required>
                    <?php foreach($meses_orden as $mes_nombre): ?>
                    <option value="<?php echo $mes_nombre; ?>" <?php echo ($mes_nombre == $mes_actual_nombre) ? 'selected' : ''; ?>><?php echo $mes_nombre; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="anio" class="form-label">Año</label>
                <input type="number" class="form-control" id="anio" name="anio" value="<?php echo date('Y'); ?>" required>
            </div>
            <div class="col-md-3">
                <label for="tipo_pago" class="form-label">Tipo de Pago</label>
                <select id="tipo_pago" name="tipo_pago" class="form-select" required>
                    <option value="Abono Mensual">Abono Mensual</option>
                    <option value="Desarrollo">Desarrollo</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="monto_a_pagar" class="form-label">Monto a Pagar</label>
                <input type="number" step="0.01" class="form-control" id="monto_a_pagar" name="monto_a_pagar" placeholder="Monto" value="<?php echo $ultimo_monto_sugerido; ?>" required>
            </div>
            <div class="col-md-2">
                <label for="porcentaje_aumento" class="form-label">Aumento %</label>
                <input type="number" step="0.01" class="form-control" id="porcentaje_aumento" name="porcentaje_aumento" placeholder="%">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary">Crear</button>
            </div>
        </form>
    </div>
</div>


<!-- Tabla de Pagos -->
<div class="table-responsive">
    <table class="table table-bordered">
        <thead class="table">
            <tr>
                <th>N° Orden</th>
                <th>Mes/Año</th>
                <th>Tipo</th>
                <th>Monto a Pagar</th>
                <th>Monto Pagado</th>
                <th>Adeuda Mes</th>
                <th>Adeuda Acum.</th>
                <th>Record.</th>
                <th>Fact.</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pagos as $pago): 
                $deuda_mes = $pago['monto_a_pagar'] - $pago['monto_pagado'];
            ?>
            <tr>
                <td><?php echo $pago['id']; ?></td>
                <td><?php echo htmlspecialchars($pago['mes']) . ' ' . $pago['anio']; ?></td>
                <td><?php echo htmlspecialchars($pago['tipo_pago']); ?></td>
                <td>$<?php echo number_format($pago['monto_a_pagar'], 2, ',', '.'); ?></td>
                
                <!-- CELDA DE MONTO PAGADO CON BOTONES DE ACCIÓN -->
                <td>
                    <div class="d-flex align-items-center">
                        <!-- Formulario para actualizar monto manualmente -->
                        <form action="pago_acciones.php" method="post" id="form_pago_<?php echo $pago['id']; ?>" class="flex-grow-1">
                            <input type="hidden" name="accion" value="actualizar_monto">
                            <input type="hidden" name="id_pago" value="<?php echo $pago['id']; ?>">
                            <input type="hidden" name="id_cliente" value="<?php echo $id_cliente; ?>">
                            <input type="number" step="0.01" name="monto_pagado" class="form-control form-control-sm" value="<?php echo $pago['monto_pagado']; ?>">
                        </form>
                        <!-- Botón para enviar el formulario manual -->
                        <button type="submit" form="form_pago_<?php echo $pago['id']; ?>" class="btn btn-sm btn-outline-primary ms-1" title="Actualizar Monto Manual"><i class="bi bi-arrow-clockwise"></i></button>
                        <!-- NUEVO BOTÓN para completar el pago automáticamente -->
                        <a href="pago_acciones.php?accion=completar_pago&id_pago=<?php echo $pago['id']; ?>&id_cliente=<?php echo $id_cliente; ?>" class="btn btn-sm btn-outline-success ms-1" title="Completar Pago (copia el Monto a Pagar)"><i class="bi bi-check-all"></i></a>
                    </div>
                </td>
                
                <td class="<?php echo ($deuda_mes > 0) ? 'text-danger fw-bold' : 'text-success'; ?>">
                    $<?php echo number_format($deuda_mes, 2, ',', '.'); ?>
                </td>
                <td class="<?php echo (isset($deuda_acumulada_map[$pago['id']]) && $deuda_acumulada_map[$pago['id']] > 0) ? 'text-danger fw-bold' : 'text-success'; ?>">
                    $<?php echo isset($deuda_acumulada_map[$pago['id']]) ? number_format($deuda_acumulada_map[$pago['id']], 2, ',', '.') : 'N/A'; ?>
                </td>

                <td>
                    <a href="#" onclick="confirmarAccion('pago_acciones.php?accion=toggle_recordatorio&id_pago=<?php echo $pago['id']; ?>&id_cliente=<?php echo $id_cliente; ?>', '¿Deseas cambiar el estado de Recordatorio Enviado?')" style="text-decoration: none;">
                        <?php if ($pago['recordatorio_enviado']): ?>
                            <i class="bi bi-check-circle-fill text-success" title="Enviado (Click para destildar)"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle-fill text-muted" title="Pendiente (Click para tildar)"></i>
                        <?php endif; ?>
                    </a>
                </td>
                <td>
                    <a href="#" onclick="confirmarAccion('pago_acciones.php?accion=toggle_facturado&id_pago=<?php echo $pago['id']; ?>&id_cliente=<?php echo $id_cliente; ?>', '¿Deseas cambiar el estado de Facturado?')" style="text-decoration: none;">
                        <?php if ($pago['facturado']): ?>
                            <i class="bi bi-check-circle-fill text-success" title="Facturado (Click para destildar)"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle-fill text-muted" title="Pendiente (Click para tildar)"></i>
                        <?php endif; ?>
                    </a>
                </td>
                <td>
                     <a href="pago_acciones.php?accion=recordatorio&id_pago=<?php echo $pago['id']; ?>" class="btn btn-sm btn-info" title="Enviar Recordatorio"><i class="bi bi-envelope"></i></a>
                     <a href="pago_acciones.php?accion=facturar&id_pago=<?php echo $pago['id']; ?>" class="btn btn-sm btn-primary" title="Generar Factura"><i class="bi bi-receipt"></i></a>
                     <a href="#" onclick="confirmarAccion('pago_acciones.php?accion=borrar&id_pago=<?php echo $pago['id']; ?>&id_cliente=<?php echo $id_cliente; ?>', '¿Estás seguro de que deseas eliminar este pago? Esta acción no se puede deshacer.')" class="btn btn-sm btn-danger" title="Borrar Pago"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pagos)): ?>
            <tr><td colspan="10" class="text-center">No hay pagos registrados para este cliente.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const aumentoInput = document.getElementById('porcentaje_aumento');
    const montoPagarInput = document.getElementById('monto_a_pagar');
    const montoBaseOriginal = parseFloat(montoPagarInput.value) || 0;

    aumentoInput.addEventListener('input', function() {
        const porcentaje = parseFloat(aumentoInput.value) || 0;
        if (montoBaseOriginal > 0 && porcentaje >= 0) {
            const nuevoMonto = montoBaseOriginal * (1 + (porcentaje / 100));
            montoPagarInput.value = nuevoMonto.toFixed(2);
        } else if (montoBaseOriginal > 0 && aumentoInput.value === '') {
            montoPagarInput.value = montoBaseOriginal.toFixed(2);
        }
    });
});
</script>

<?php require 'includes/footer.php'; ?>