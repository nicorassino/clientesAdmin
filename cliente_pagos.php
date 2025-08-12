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

// Obtener pagos ordenados por año y luego por mes (orden cronológico inverso)
$meses_orden = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$stmt_pagos = $pdo->prepare("SELECT * FROM pagos WHERE id_cliente = ? ORDER BY anio DESC, FIELD(mes, '" . implode("','", array_reverse($meses_orden)) . "') DESC");
$stmt_pagos->execute([$id_cliente]);
$pagos = $stmt_pagos->fetchAll();

// Lógica para deuda acumulativa
// 1. Obtener todos los pagos en orden cronológico
$stmt_pagos_cronologico = $pdo->prepare("SELECT * FROM pagos WHERE id_cliente = ? ORDER BY anio ASC, FIELD(mes, '" . implode("','", $meses_orden) . "') ASC");
$stmt_pagos_cronologico->execute([$id_cliente]);
$pagos_cronologicos = $stmt_pagos_cronologico->fetchAll();

$deuda_acumulada_map = [];
$deuda_total = 0;
foreach($pagos_cronologicos as $pago) {
    $deuda_mes = $pago['monto_a_pagar'] - $pago['monto_pagado'];
    $deuda_total += $deuda_mes;
    $deuda_acumulada_map[$pago['id']] = $deuda_total;
}

?>

<h1>Pagos de: <?php echo htmlspecialchars($cliente['nombre']); ?></h1>
<p>
    <strong>Razón Social:</strong> <?php echo htmlspecialchars($cliente['razon_social']); ?> | 
    <strong>CUIT:</strong> <?php echo htmlspecialchars($cliente['cuit']); ?> | 
    <strong>Email:</strong> <?php echo htmlspecialchars($cliente['email']); ?>
</p>

<?php if (isset($_SESSION['mail_status'])): ?>
    <div class="alert alert-<?php echo $_SESSION['mail_status_type']; ?>"><?php echo $_SESSION['mail_status']; ?></div>
    <?php unset($_SESSION['mail_status'], $_SESSION['mail_status_type']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['factura_status'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['factura_status']; ?></div>
    <?php unset($_SESSION['factura_status']); ?>
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
                    <option value="<?php echo $mes_nombre; ?>"><?php echo $mes_nombre; ?></option>
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
                <input type="number" step="0.01" class="form-control" id="monto_a_pagar" name="monto_a_pagar" placeholder="Auto / Manual">
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
                
                <!-- Formulario para actualizar monto pagado -->
                <td>
                    <form action="pago_acciones.php" method="post" class="d-flex">
                        <input type="hidden" name="accion" value="actualizar_monto">
                        <input type="hidden" name="id_pago" value="<?php echo $pago['id']; ?>">
                        <input type="hidden" name="id_cliente" value="<?php echo $id_cliente; ?>">
                        <input type="number" step="0.01" name="monto_pagado" class="form-control form-control-sm" value="<?php echo $pago['monto_pagado']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary ms-1"><i class="bi bi-arrow-clockwise"></i></button>
                    </form>
                </td>
                
                <td class="<?php echo ($deuda_mes > 0) ? 'text-danger fw-bold' : 'text-success'; ?>">
                    $<?php echo number_format($deuda_mes, 2, ',', '.'); ?>
                </td>
                <td class="<?php echo ($deuda_acumulada_map[$pago['id']] > 0) ? 'text-danger fw-bold' : 'text-success'; ?>">
                    $<?php echo number_format($deuda_acumulada_map[$pago['id']], 2, ',', '.'); ?>
                </td>

                <td>
                    <?php if ($pago['recordatorio_enviado']): ?>
                        <i class="bi bi-check-circle-fill text-success" title="Enviado"></i>
                    <?php else: ?>
                        <i class="bi bi-x-circle-fill text-muted" title="Pendiente"></i>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($pago['facturado']): ?>
                        <i class="bi bi-check-circle-fill text-success" title="Facturado"></i>
                    <?php else: ?>
                        <i class="bi bi-x-circle-fill text-muted" title="Pendiente"></i>
                    <?php endif; ?>
                </td>
                <td>
                     <a href="pago_acciones.php?accion=recordatorio&id_pago=<?php echo $pago['id']; ?>" class="btn btn-sm btn-info" title="Enviar Recordatorio"><i class="bi bi-envelope"></i></a>
                     <a href="pago_acciones.php?accion=facturar&id_pago=<?php echo $pago['id']; ?>" class="btn btn-sm btn-primary" title="Generar Factura"><i class="bi bi-receipt"></i></a>
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
// Script para autocompletar monto o aplicar aumento
document.addEventListener('DOMContentLoaded', function() {
    const tipoPagoSelect = document.getElementById('tipo_pago');
    const montoPagarInput = document.getElementById('monto_a_pagar');
    const aumentoInput = document.getElementById('porcentaje_aumento');

    async function obtenerMontoAnterior() {
        if (tipoPagoSelect.value === 'Abono Mensual') {
            const idCliente = <?php echo $id_cliente; ?>;
            const response = await fetch(`pago_acciones.php?accion=get_ultimo_monto&id_cliente=${idCliente}`);
            const data = await response.json();
            if (data.monto) {
                montoPagarInput.value = data.monto;
                montoPagarInput.placeholder = `Sugerido: ${data.monto}`;
            }
        } else {
             montoPagarInput.value = '';
             montoPagarInput.placeholder = 'Monto Manual';
        }
    }
    
    tipoPagoSelect.addEventListener('change', obtenerMontoAnterior);

    aumentoInput.addEventListener('input', function() {
        const montoBase = parseFloat(montoPagarInput.value) || 0;
        const porcentaje = parseFloat(aumentoInput.value) || 0;
        if (montoBase > 0 && porcentaje > 0) {
            const nuevoMonto = montoBase * (1 + (porcentaje / 100));
            montoPagarInput.value = nuevoMonto.toFixed(2);
        }
    });

    // Cargar monto al inicio si es abono
    obtenerMontoAnterior();
});
</script>

<?php require 'includes/footer.php'; ?>