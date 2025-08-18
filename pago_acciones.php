<?php
session_start();
// --- INCLUDES Y LIBRERÍAS ---
require 'includes/db.php';
require 'config.php'; // Nuestro archivo de configuración con las credenciales SMTP
require 'vendor/autoload.php'; // Autoloader de Composer para PHPMailer

// --- Usar las clases de PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    // (Omitiendo los otros 'case' que no han cambiado para brevedad en esta explicación,
    // pero el código de abajo los contiene todos)
    // ...

    case 'recordatorio':
        $id_pago = $_GET['id_pago'];

        // 1. Obtener datos del pago y cliente, incluyendo datos bancarios
        $stmt = $pdo->prepare("SELECT p.*, c.nombre, c.email, c.id as id_cliente, c.datos_bancarios FROM pagos p JOIN clientes c ON p.id_cliente = c.id WHERE p.id = ?");
        $stmt->execute([$id_pago]);
        $pago_info = $stmt->fetch();
        if (!$pago_info) die('Pago no encontrado');

        $cliente_email = $pago_info['email'];
        $cliente_nombre = $pago_info['nombre'];
        $id_cliente = $pago_info['id_cliente'];
        $anio_recordatorio = $pago_info['anio'];
        
        // 2. Obtener lista de pagos para el recordatorio
        $meses_orden = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $sql_recordatorio = "(SELECT * FROM pagos WHERE id_cliente = ? AND anio < ? AND monto_a_pagar > monto_pagado) UNION (SELECT * FROM pagos WHERE id_cliente = ? AND anio = ?) ORDER BY anio ASC, FIELD(mes, '".implode("','", $meses_orden)."') ASC";
        $stmt_recordatorio = $pdo->prepare($sql_recordatorio);
        $stmt_recordatorio->execute([$id_cliente, $anio_recordatorio, $id_cliente, $anio_recordatorio]);
        $pagos_para_recordatorio = $stmt_recordatorio->fetchAll();

        // 3. *** FIX: Re-insertar la construcción completa de la tabla HTML ***
        $total_adeudado = 0;
        $tabla_pagos_html = "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%; font-family: sans-serif;'>
            <thead style='background-color: #f2f2f2;'>
                <tr><th>Mes/Año</th><th>Aumento</th><th>Monto a Pagar</th><th>Pagado</th><th>Adeuda</th></tr>
            </thead>
            <tbody>";
        foreach ($pagos_para_recordatorio as $p) {
            $adeuda_mes = $p['monto_a_pagar'] - $p['monto_pagado'];
            $total_adeudado += $adeuda_mes;
            $tabla_pagos_html .= "<tr>
                <td>{$p['mes']} {$p['anio']}</td>
                <td >{$p['porcentaje_aumento']}%</td>
                <td >$".number_format($p['monto_a_pagar'], 2, ',', '.')."</td>
                <td >$".number_format($p['monto_pagado'], 2, ',', '.')."</td>
                <td style='font-weight: bold; color: ".($adeuda_mes > 0 ? 'red' : 'green').";'>$".number_format($adeuda_mes, 2, ',', '.')."</td>
            </tr>";
        }
        $tabla_pagos_html .= "</tbody></table>";
        
        // 4. Construir bloque de datos bancarios dinámicamente
        $info_bancaria = "";
        if (!empty($pago_info['datos_bancarios'])) {
            // Usamos nl2br para convertir saltos de línea en <br> y mantener el formato
            $datos_formateados = nl2br(htmlspecialchars($pago_info['datos_bancarios']));
            $info_bancaria = "
                <div style='margin-top: 20px; padding: 15px; border: 1px solid #ccc; background-color: #f9f9f9;'>
                    <h3 style='margin-top: 0;'>Datos para Depósito o Transferencia</h3>
                    <p style='white-space: pre-wrap;'>" . $datos_formateados . "</p>
                </div>";
        }

        // 5. *** FIX: Re-insertar la construcción completa del cuerpo del email ***
        $asunto = "Recordatorio de Pago - " . $cliente_nombre;
        $cuerpo_email = "
            <html><body style='font-family: sans-serif; color: #333;'>
                <h2>Recordatorio de Pago</h2>
                <p><strong>Fecha de Emisión:</strong> ".date('d/m/Y')."</p>
                <p><strong>Fecha de Vencimiento:</strong> ".date('15/m/Y')."</p>
                <p>Estimado/a {$cliente_nombre},</p>
                <p>Le enviamos un resumen de su estado de cuenta, incluyendo saldos pendientes de períodos anteriores.</p>
                {$tabla_pagos_html}
                <h3 style='text-align: right; font-size: 1.2em;'>Total adeudado a la fecha: <span style='color: red; font-weight: bold;'>$".number_format($total_adeudado, 2, ',', '.')."</span></h3>
                <hr>
                {$info_bancaria}
                <p>Muchas gracias por su atención.</p>
            </body></html>";

        // 6. Enviar el correo usando PHPMailer
        $mail = new PHPMailer(true);
        $mail_status = '';
        try {
            // Configuración del servidor
            // $mail->SMTPDebug = 2; // Descomentar para depuración avanzada
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = SMTP_PORT;

            // Remitente y Destinatarios
            // *** CAMBIO: El nombre del remitente ahora es "SistemasEscolares" ***
            $mail->setFrom(SMTP_USER, 'SistemasEscolares');
            $mail->addAddress($cliente_email, $cliente_nombre);
            $mail->addReplyTo(SMTP_USER, 'SistemasEscolares'); // <-- Consistencia en el nombre

            // Contenido
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $asunto;
            $mail->Body    = $cuerpo_email;
            $mail->AltBody = 'Para ver este mensaje, por favor use un cliente de email compatible con HTML.';

            $mail->send();
            
            $stmt_update = $pdo->prepare("UPDATE pagos SET recordatorio_enviado = 1 WHERE id = ?");
            $stmt_update->execute([$id_pago]);
            $mail_status = "<div class='alert alert-success'>Recordatorio enviado a $cliente_email con éxito.</div>";

        } catch (Exception $e) {
            $mail_status = "<div class='alert alert-danger'>El mensaje no pudo ser enviado. Error de PHPMailer: {$mail->ErrorInfo}</div>";
        }
        
        // 7. Mostrar la vista previa
        echo "<html><head><title>Vista Previa del Recordatorio</title><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='container mt-4'>";
        echo "<h1>Vista previa del recordatorio</h1>";
        echo $mail_status;
        echo "<a href='cliente_pagos.php?id_cliente={$id_cliente}' class='btn btn-primary my-3'>Volver a los pagos del cliente</a><hr>";
        echo $cuerpo_email;
        echo "</body></html>";
        break;

    // --- Aquí están el resto de las acciones, sin cambios ---
    
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
$monto_pagado = (isset($_POST['monto_pagado']) && is_numeric($_POST['monto_pagado'])) ? $_POST['monto_pagado'] : 0;
        $sql = "UPDATE pagos SET monto_pagado = ? WHERE id = ?";
        $stmt= $pdo->prepare($sql);
        $stmt->execute([$monto_pagado, $id_pago]);
        
        set_notification('Monto pagado actualizado.');
        redirect_to_client($id_cliente);
        break;

    case 'completar_pago':
        $id_pago = $_GET['id_pago'];
        $id_cliente = $_GET['id_cliente'];

        $stmt_select = $pdo->prepare("SELECT monto_a_pagar FROM pagos WHERE id = ?");
        $stmt_select->execute([$id_pago]);
        $monto_completo = $stmt_select->fetchColumn();

        if ($monto_completo !== false) {
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
        
   case 'facturar':
        $id_pago = $_GET['id_pago'];
        
        // 1. Obtener los datos necesarios para la factura desde la base de datos
        $stmt = $pdo->prepare("
            SELECT p.monto_pagado, c.cuit 
            FROM pagos p 
            JOIN clientes c ON p.id_cliente = c.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$id_pago]);
        $datos_factura = $stmt->fetch();

        if ($datos_factura) {
            // 2. Marcar el pago como 'Facturado' en nuestra base de datos
            $stmt_update = $pdo->prepare("UPDATE pagos SET facturado = 1 WHERE id = ?");
            $stmt_update->execute([$id_pago]);
            
            // 3. Preparar las variables para la URL
            $tipoComp = "11"; // Factura C
            $cuit_cliente = $datos_factura['cuit'];
            $importe_pago = $datos_factura['monto_pagado'];

            // 4. Construir la URL de destino con los parámetros GET
            // urlencode() asegura que los datos se pasen correctamente en la URL
            $url_destino = sprintf(
                "afipSE/emitirComp.php?tipoComp=%s&cuit=%s&importe=%s",
                urlencode($tipoComp),
                urlencode($cuit_cliente),
                urlencode($importe_pago)
            );

            // 5. Redirigir al usuario al script de facturación
            header("Location: " . $url_destino);
            exit(); // Terminar la ejecución del script aquí

        } else {
            // Si no se encuentra el pago, redirigir con un error
            set_notification('Error: No se pudo encontrar el pago para facturar.', 'danger');
            // Intentamos redirigir al cliente si tenemos el ID, sino al index
            if (isset($_GET['id_cliente'])) {
                redirect_to_client($_GET['id_cliente']);
            } else {
                header('Location: index.php');
                exit();
            }
        }
        break;

    default:
        header('Location: index.php');
        break;
}
exit();
?>