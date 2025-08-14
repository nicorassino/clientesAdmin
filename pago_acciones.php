<?php
session_start();
require 'includes/db.php';

// --- SEGURIDAD Y PRE-REQUISITOS ---

// Verificar que el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
    } else {
        header('Location: login.php');
    }
    exit();
}

// --- FUNCIONES AUXILIARES ---

function set_notification($message, $type = 'success') {
    $_SESSION['status'] = $message;
    $_SESSION['status_type'] = $type;
}

function redirect_to_client($id_cliente) {
    header("Location: cliente_pagos.php?id_cliente=$id_cliente");
    exit();
}

// --- CONTROLADOR DE ACCIONES ---

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    // (Las acciones 'crear', 'actualizar_monto', 'borrar', 'toggle_recordatorio', 'toggle_facturado' no cambian)
    // ...
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
        
        set_notification('Pago creado correctamente.');
        redirect_to_client($id_cliente);
        break;

    case 'actualizar_monto':
        $id_pago = $_POST['id_pago'];
        $id_cliente = $_POST['id_cliente'];
        $monto_pagado = $_POST['monto_pagado'];

        $sql = "UPDATE pagos SET monto_pagado = ? WHERE id = ?";
        $stmt= $pdo->prepare($sql);
        $stmt->execute([$monto_pagado, $id_pago]);
        
        set_notification('Monto pagado actualizado.');
        redirect_to_client($id_cliente);
        break;

         case 'completar_pago':
        $id_pago = $_GET['id_pago'];
        $id_cliente = $_GET['id_cliente'];

        // 1. Obtener el 'monto_a_pagar' para este pago específico.
        $stmt_select = $pdo->prepare("SELECT monto_a_pagar FROM pagos WHERE id = ?");
        $stmt_select->execute([$id_pago]);
        $monto_completo = $stmt_select->fetchColumn();

        if ($monto_completo !== false) { // Verifica si se encontró el pago
            // 2. Actualizar el 'monto_pagado' con el valor obtenido.
            $stmt_update = $pdo->prepare("UPDATE pagos SET monto_pagado = ? WHERE id = ?");
            $stmt_update->execute([$monto_completo, $id_pago]);
            set_notification('El pago se ha completado con el monto total.');
        } else {
            set_notification('Error: No se pudo encontrar el pago para completar.', 'danger');
        }
        
        redirect_to_client($id_cliente);
        break;

    case 'borrar':
        $id_pago = $_GET['id_pago'];
        $id_cliente = $_GET['id_cliente'];

        $sql = "DELETE FROM pagos WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_pago]);
        
        set_notification('El pago ha sido eliminado.', 'danger');
        redirect_to_client($id_cliente);
        break;

    case 'toggle_recordatorio':
        $id_pago = $_GET['id_pago'];
        $id_cliente = $_GET['id_cliente'];

        $sql = "UPDATE pagos SET recordatorio_enviado = NOT recordatorio_enviado WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_pago]);
        
        set_notification('Estado de "Recordatorio Enviado" cambiado.');
        redirect_to_client($id_cliente);
        break;

    case 'toggle_facturado':
        $id_pago = $_GET['id_pago'];
        $id_cliente = $_GET['id_cliente'];

        $sql = "UPDATE pagos SET facturado = NOT facturado WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_pago]);
        
        set_notification('Estado de "Facturado" cambiado.');
        redirect_to_client($id_cliente);
        break;
    // --- FIN DE ACCIONES SIN CAMBIOS ---

    case 'recordatorio':
        $id_pago = $_GET['id_pago'];

        // 1. Obtener datos del pago y cliente para saber el contexto (cliente y año del recordatorio)
        $stmt = $pdo->prepare("SELECT p.*, c.nombre, c.email, c.id as id_cliente FROM pagos p JOIN clientes c ON p.id_cliente = c.id WHERE p.id = ?");
        $stmt->execute([$id_pago]);
        $pago_info = $stmt->fetch();

        if (!$pago_info) die('Pago no encontrado');

        $cliente_email = $pago_info['email'];
        $cliente_nombre = $pago_info['nombre'];
        $id_cliente = $pago_info['id_cliente'];
        $anio_recordatorio = $pago_info['anio']; // El año base para el recordatorio

        // 2. *** NUEVA LÓGICA DE CONSULTA ***
        // Preparamos la consulta para obtener:
        //    a) Pagos con deuda de años ANTERIORES al año del recordatorio.
        //    b) TODOS los pagos del año del recordatorio.
        // Y los ordenamos cronológicamente.
        $meses_orden = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        
        $sql_recordatorio = "
            (SELECT * FROM pagos WHERE id_cliente = ? AND anio < ? AND monto_a_pagar > monto_pagado)
            UNION
            (SELECT * FROM pagos WHERE id_cliente = ? AND anio = ?)
            ORDER BY anio ASC, FIELD(mes, '".implode("','", $meses_orden)."') ASC
        ";

        $stmt_recordatorio = $pdo->prepare($sql_recordatorio);
        $stmt_recordatorio->execute([
            $id_cliente, 
            $anio_recordatorio, 
            $id_cliente, 
            $anio_recordatorio
        ]);
        $pagos_para_recordatorio = $stmt_recordatorio->fetchAll();

        // 3. Construir el cuerpo del email (esta parte no cambia, solo usa la nueva lista de pagos)
        $total_adeudado = 0;
        $tabla_pagos_html = "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%; font-family: sans-serif;'>
            <thead style='background-color: #f2f2f2;'>
                <tr><th>N° Orden</th><th>Mes/Año</th><th>Aumento</th><th>Monto a Pagar</th><th>Pagado</th><th>Adeuda</th></tr>
            </thead>
            <tbody>";
        
        foreach ($pagos_para_recordatorio as $p) {
            $adeuda_mes = $p['monto_a_pagar'] - $p['monto_pagado'];
            $total_adeudado += $adeuda_mes;
            $tabla_pagos_html .= "<tr>
                <td style='text-align: center;'>{$p['id']}</td>
                <td>{$p['mes']} {$p['anio']}</td>
                <td style='text-align: right;'>{$p['porcentaje_aumento']}%</td>
                <td style='text-align: right;'>$".number_format($p['monto_a_pagar'], 2, ',', '.')."</td>
                <td style='text-align: right;'>$".number_format($p['monto_pagado'], 2, ',', '.')."</td>
                <td style='text-align: right; font-weight: bold; color: ".($adeuda_mes > 0 ? 'red' : 'green').";'>$".number_format($adeuda_mes, 2, ',', '.')."</td>
            </tr>";
        }
        $tabla_pagos_html .= "</tbody></table>";
        
        // **TU INFORMACIÓN BANCARIA - MODIFICA AQUÍ**
        $info_bancaria = "
            <div style='margin-top: 20px; padding: 15px; border: 1px solid #ccc; background-color: #f9f9f9;'>
                <h3 style='margin-top: 0;'>Datos para Depósito o Transferencia</h3>
                <p>
                    <strong>Banco:</strong> [NOMBRE DE TU BANCO]<br>
                    <strong>Tipo de Cuenta:</strong> [Caja de Ahorro / Cta. Cte.] en Pesos<br>
                    <strong>Nro de Cuenta:</strong> [NRO DE CUENTA]<br>
                    <strong>CBU:</strong> [TU CBU]<br>
                    <strong>Alias:</strong> [TU ALIAS]<br>
                    <strong>Titular:</strong> [NOMBRE DEL TITULAR]<br>
                    <strong>CUIT/CUIL:</strong> [TU CUIT/CUIL]
                </p>
                <p><em>Por favor, enviar el comprobante de pago a este mismo email una vez realizada la operación.</em></p>
            </div>";

        $asunto = "Recordatorio de Pago - " . $cliente_nombre;
        $cuerpo_email = "
            <html><body style='font-family: sans-serif; color: #333;'>
                <h2>Recordatorio de Pago</h2>
                <p><strong>Fecha de Emisión:</strong> ".date('d/m/Y')."</p>
                <p>Estimado/a {$cliente_nombre},</p>
                <p>Le enviamos un resumen de su estado de cuenta, incluyendo saldos pendientes de períodos anteriores.</p>
                {$tabla_pagos_html}
                <h3 style='text-align: right; font-size: 1.2em;'>Total adeudado a la fecha: <span style='color: red; font-weight: bold;'>$".number_format($total_adeudado, 2, ',', '.')."</span></h3>
                <hr>
                {$info_bancaria}
                <p>Muchas gracias por su atención.</p>
            </body></html>";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: ClientesAdmin <no-reply@tu-dominio.com>' . "\r\n"; 

        if (mail($cliente_email, $asunto, $cuerpo_email, $headers)) {
            $stmt_update = $pdo->prepare("UPDATE pagos SET recordatorio_enviado = 1 WHERE id = ?");
            $stmt_update->execute([$id_pago]);
            $mail_status = "<div style='padding: 15px; background-color: #d4edda; border-color: #c3e6cb; color: #155724; border-radius: 5px;'>Recordatorio enviado a $cliente_email con éxito. Se marcó como enviado en la base de datos.</div>";
        } else {
            $mail_status = "<div style='padding: 15px; background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; border-radius: 5px;'>Error al enviar el email. Verifica la configuración de tu servidor de correo (sendmail).</div>";
        }

        // 4. Mostrar la vista previa
        echo "<html><head><title>Vista Previa del Recordatorio</title><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='container mt-4'>";
        echo "<h1>Vista previa del recordatorio</h1>";
        echo $mail_status;
        echo "<a href='cliente_pagos.php?id_cliente={$id_cliente}' class='btn btn-primary my-3'>Volver a los pagos del cliente</a><hr>";
        echo $cuerpo_email;
        echo "</body></html>";
        break;

    case 'facturar':
        $id_pago = $_GET['id_pago'];
        
        $stmt = $pdo->prepare("
            SELECT p.id_cliente, p.mes, p.anio, p.monto_pagado, c.cuit, c.servicios 
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

            $stmt_update = $pdo->prepare("UPDATE pagos SET facturado = 1 WHERE id = ?");
            $stmt_update->execute([$id_pago]);

            set_notification("Datos para factura generados y pago marcado como 'Facturado'.");
            redirect_to_client($datos_factura['id_cliente']);
        } else {
            set_notification('Error al obtener los datos para la factura.', 'danger');
            header('Location: index.php');
            exit();
        }
        break;

    default:
        header('Location: index.php');
        break;
}
exit();
?>