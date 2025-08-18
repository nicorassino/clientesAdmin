<?php
require 'includes/header.php';
require 'includes/db.php';

$anio_seleccionado = $_GET['anio'] ?? date('Y');

// Obtener todos los clientes según el rol
$sql_clientes = "SELECT id, nombre FROM clientes";
if ($_SESSION['rol'] !== 'admin') {
    $sql_clientes .= " WHERE id_usuario = ?";
    $stmt_clientes = $pdo->prepare($sql_clientes);
    $stmt_clientes->execute([$_SESSION['user_id']]);
} else {
    $stmt_clientes = $pdo->query($sql_clientes);
}
$clientes = $stmt_clientes->fetchAll();

// Obtener todos los pagos del año seleccionado
$stmt_pagos = $pdo->prepare("SELECT id_cliente, mes, monto_a_pagar, monto_pagado FROM pagos WHERE anio = ?");
$stmt_pagos->execute([$anio_seleccionado]);
$pagos = $stmt_pagos->fetchAll();

// Procesar los pagos en una estructura fácil de usar
$estado_pagos = [];
foreach ($pagos as $pago) {
    // Si un cliente tiene múltiples pagos para el mismo mes, los sumamos.
    if (!isset($estado_pagos[$pago['id_cliente']][$pago['mes']])) {
         $estado_pagos[$pago['id_cliente']][$pago['mes']] = ['pagado' => 0, 'a_pagar' => 0];
    }
    $estado_pagos[$pago['id_cliente']][$pago['mes']]['pagado'] += $pago['monto_pagado'];
    $estado_pagos[$pago['id_cliente']][$pago['mes']]['a_pagar'] += $pago['monto_a_pagar'];
}

$meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
?>

<h1>Estado de Pagos Anual</h1>

<form method="get" class="row g-3 align-items-end mb-4">
    <div class="col-auto">
        <label for="anio" class="form-label">Seleccionar Año</label>
        <input type="number" class="form-control" name="anio" id="anio" value="<?php echo $anio_seleccionado; ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Ver Reporte</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-bordered text-center">
        <thead class="table">
            <tr>
                <th class="text-start">Cliente</th>
                <?php foreach ($meses as $mes): ?>
                <th><?php echo substr($mes, 0, 3); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $cliente): ?>
            <tr>
                <td class="text-start"><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                <?php foreach ($meses as $mes): 
                    $celda = '<i class="bi bi-dash-lg text-muted"></i>'; // No hay pago registrado
                    if (isset($estado_pagos[$cliente['id']][$mes])) {
                        $pago_mes = $estado_pagos[$cliente['id']][$mes];
                        if ($pago_mes['pagado'] >= $pago_mes['a_pagar'] && $pago_mes['a_pagar'] > 0) {
                            $celda = '<i class="bi bi-check-circle-fill text-success" title="Pagado Completo"></i>';
                        } elseif ($pago_mes['pagado'] > 0) {
                            $celda = '<i class="bi bi-exclamation-triangle-fill text-warning" title="Pago Parcial"></i>';
                        } else {
                            $celda = '<i class="bi bi-x-circle-fill text-danger" title="Pendiente"></i>';
                        }
                    }
                ?>
                <td><?php echo $celda; ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
             <?php if (empty($clientes)): ?>
            <tr>
                <td colspan="<?php echo count($meses) + 1; ?>" class="text-center">No hay clientes para mostrar.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-3">
    <strong>Leyenda:</strong>
    <span class="mx-2"><i class="bi bi-check-circle-fill text-success"></i> Pagado</span>
    <span class="mx-2"><i class="bi bi-exclamation-triangle-fill text-warning"></i> Parcial</span>
    <span class="mx-2"><i class="bi bi-x-circle-fill text-danger"></i> Pendiente</span>
    <span class="mx-2"><i class="bi bi-dash-lg text-muted"></i> Sin registro</span>
</div>


<?php require 'includes/footer.php'; ?>