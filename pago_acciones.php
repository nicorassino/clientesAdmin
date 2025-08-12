<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    // Para llamadas fetch, devolvemos un error JSON. Para otras, redirigimos.
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
    } else {
        header('Location: login.php');
    }
    exit();
}

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
    case 'get_ultimo_monto':
        // Usado por JS para autocompletar
        header('Content-Type: application/json');
        $id_cliente = $_GET['id_cliente'];
        $stmt = $pdo->prepare("SELECT monto_a_pagar FROM pagos WHERE id_cliente = ? AND tipo_pago = 'Abono Mensual' ORDER BY anio DESC, FIELD(mes, 'Diciembre', 'Noviembre', 'Octubre', 'Septiembre', 'Agosto', 'Julio', 'Junio', 'Mayo', 'Abril', 'Marzo', 'Febrero', 'Enero') DESC LIMIT 1");
        $stmt->execute([$id_cliente]);
        $ultimo_pago = $stmt->fetch();
        echo json_encode(['monto' => $ultimo_pago['monto_a_pagar'] ?? null]);
        break;

    case 'crear':
        $id_cliente = $_POST['id_cliente'];
        $mes = $_POST['mes'];
        $anio = $_POST['anio'];
        $tipo_pago = $_POST['tipo_pago'];
        $monto_a_pagar = $_POST['monto_a_pagar'];
        $porcentaje_aumento = $_POST['porcentaje_aumento'] ?: 0;
        
        $sql = "INSERT INTO pagos (id_cliente, mes, anio, tipo_pago, monto_a_pagar, porcentaje_aumento) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt= $pdo->prepare($sql);
        $stmt->execute([$id_cliente, $mes, $anio, $tipo_pago, $monto_a_pagar, $porcentaje_aumento]);

        header("Location: cliente_pagos.php?id_cliente=$id_cliente");
        break;

    case 'actualizar_monto':
        $id_pago = $_POST['id_pago'];
        $id_cliente = $_POST['id_cliente'];
        $monto_pagado = $_POST['monto_pagado'];

        $sql = "UPDATE pagos SET monto_pagado = ? WHERE id = ?";
        $stmt= $pdo->prepare($sql);
        $stmt->execute([$monto_pagado, $id_pago]);
        
        header("Location: cliente_pagos.php?id_cliente=$id_cliente");
        break;

    case 'recordatorio':
        $id_pago = $_GET['id_pago'];

        // Obtener datos del pago y cliente
        $stmt = $pdo->prepare("SELECT p.*, c.nombre, c.email FROM pagos p JOIN clientes c ON p.id_cliente = c.id WHERE p.id = ?");
        $stmt->execute([$id_pago]);
        $pago_info = $stmt->fetch();

        if (!$pago_info) die('Pago no encontrado');

        $cliente_email = $pago_info['email'];
        $cliente_nombre = $pago_info['nombre'];
        $anio_actual = $pago_info['anio'];

        // Obtener todos los pagos del año para el reporte
        $meses_orden = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $stmt_pagos_anio = $pdo->prepare("SELECT * FROM pagos WHERE id_cliente = ? AND anio = ? ORDER BY FIELD(mes, '".implode("','", $meses_orden)."') ASC");
        $stmt_pagos_anio->execute([$pago_info['id_cliente'], $anio_actual]);
        $pagos_del_anio = $stmt_pagos_anio->fetchAll();

        // Construir el cuerpo del email
        $total_adeudado = 0;
        $tabla_pagos_html = "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>
            <thead>
                <tr style='background-color: #f2f2f2;'>
                    <th>Nro Orden</th><th>Mes/Año</th><th>Aumento</th><th>Monto a Pagar</th><th>Pagado</th><th>Adeuda</th>
                </tr>
            </thead>
            <tbody>";
        
        foreach ($pagos_del_anio as $p) {
            $adeuda_mes = $p['monto_a_pagar'] - $p['monto_pagado'];
            $total_adeudado += $adeuda_mes;
            $tabla_pagos_html .= "<tr>
                <td>{$p['id']}</td>
                <td>{$p['mes']} {$p['anio']}</td>
                <td>{$p['porcentaje_aumento']}%</td>
                <td>$".number_format($p['monto_a_pagar'], 2, ',', '.')."</td>
                <td>$".number_format($p['monto_pagado'], 2, ',', '.')."</td>
                <td>$".number_format($adeuda_mes, 2, ',', '.')."</td>
            </tr>";
        }
        $tabla_pagos_html .= "</tbody></table>";
        
        // **TU INFORMACIÓN BANCARIA - MODIFICA AQUÍ**
        $info_bancaria = "
            <p><strong>Datos para Depósito o Transferencia:</strong></p>
            <p>
                Banco: [NOMBRE DE TU BANCO]<br>
                Tipo de Cuenta: [Caja de Ahorro / Cta. Cte.] en Pesos<br>
                Nro de Cuenta: [NRO DE CUENTA]<br>
                CBU: [TU CBU]<br>
                Alias: [TU ALIAS]<br>
                Titular: [NOMBRE DEL TITULAR]<br>
                CUIT: [TU CUIT]
            </p>
            <p>Por favor, enviar el comprobante de pago a este mismo email.</p>";

        $asunto = "Recordatorio de Pago - " . $cliente_nombre;
        $cuerpo_email = "
            <html><body>
                <h2>Recordatorio de Pago</h2>
                <p><strong>Fecha de Emisión:</strong> ".date('d/m/Y')."</p>
                <p>Estimado/a {$cliente_nombre},</p>
                <p>Le enviamos un resumen del estado de sus pagos correspondientes al año {$anio_actual}.</p>
                {$tabla_pagos_html}
                <h3 style='text-align: right;'>Total adeudado a la fecha: $".number_format($total_adeudado, 2, ',', '.')."</h3>
                <hr>
                {$info_bancaria}
                <p>Muchas gracias.</p>
            </body></html>";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: ClientesAdmin <no-reply@tu-dominio.com>' . "\r\n"; // Cambia el remitente

        // ¡ATENCIÓN! La función mail() de PHP es muy básica.
        // Para producción se recomienda usar una librería como PHPMailer.
        if (mail($cliente_email, $asunto, $cuerpo_email, $headers)) {
            // Marcar como enviado
            $stmt_update = $pdo->prepare("UPDATE pagos SET recordatorio_enviado = 1 WHERE id = ?");
            $stmt_update->execute([$id_pago]);
            $_SESSION['mail_status'] = "Recordatorio enviado a $cliente_email con éxito. Se marcó como enviado.";
            $_SESSION['mail_status_type'] = 'success';
        } else {
            $_SESSION['mail_status'] = "Error al enviar el email. Verifica la configuración del servidor.";
            $_SESSION['mail_status_type'] = 'danger';
        }

        // Mostrar el recordatorio generado para verificar
        echo "<h1>Vista previa del recordatorio</h1><a href='cliente_pagos.php?id_cliente={$pago_info['id_cliente']}'>Volver a los pagos</a><hr>" . $cuerpo_email;
        // No redirigir inmediatamente para que se pueda ver la vista previa.
        break;

    case 'facturar':
        $id_pago = $_GET['id_pago'];
        
        $stmt = $pdo->prepare("
            SELECT p.mes, p.anio, p.monto_pagado, c.cuit, c.servicios 
            FROM pagos p 
            JOIN clientes c ON p.id_cliente = c.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$id_pago]);
        $datos_factura = $stmt->fetch();

        if ($datos_factura) {
            $_SESSION['factura_cuit'] = $datos_factura['cuit'];
            $_SESSION['factura_servicio'] = $datos_factura['servicios'];
            $_SESSION['factura_mes'] = $datos_factura['mes'];
            $_SESSION['factura_anio'] = $datos_factura['anio'];
            $_SESSION['factura_monto'] = $datos_factura['monto_pagado'];

            // Marcar como facturado
            $stmt_update = $pdo->prepare("UPDATE pagos SET facturado = 1 WHERE id = ?");
            $stmt_update->execute([$id_pago]);

            $_SESSION['factura_status'] = "Datos para factura generados y pago marcado como 'Facturado'.";

            // Obtener id_cliente para redirigir
            $stmt_cliente = $pdo->prepare("SELECT id_cliente FROM pagos WHERE id = ?");
            $stmt_cliente->execute([$id_pago]);
            $id_cliente = $stmt_cliente->fetchColumn();
            
            header("Location: cliente_pagos.php?id_cliente=$id_cliente");
        } else {
            die('Error al obtener datos para la factura.');
        }
        break;

    default:
        header('Location: index.php');
        break;
}
exit();
?>