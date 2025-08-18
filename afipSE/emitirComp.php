<?php
require_once 'verificarTA.php';

if (!verificar_o_generar_TA()) {
    exit("❌ No se pudo generar TA.xml");
}

// --- DATOS DE ENTRADA (desde GET o donde los recibas) ---
$CUIT       = '20355729428'; // Tu CUIT
$PTO_VTA    = 4;
$TIPO_CMP   = $_GET['tipoComp'] ?? 11;    // 11=Factura C, 15=Recibo C, 12=Nota de Crédito C
$DOC_NRO    = $_GET['cuit'] ?? 'No especificado';
$IMPORTE    = (float)($_GET['importe'] ?? 0.00);

// --- CÁLCULO DE FECHAS SEGÚN LA LÓGICA SOLICITADA ---
$hoy = new DateTime();
$diaActual = (int)$hoy->format('d');
$mesActual = $hoy->format('m');
$anioActual = $hoy->format('Y');

// 1. Fecha de Servicio: primer y último día del mes actual.
$fechaServicioDesde = new DateTime("first day of this month");
$fechaServicioHasta = new DateTime("last day of this month");

// 2. Fecha de Vencimiento de Pago: día 15 o hoy.
$fechaVencimientoPago = new DateTime(); // Empieza como hoy
if ($diaActual <= 15) {
    // Si estamos en el día 15 o antes, el vencimiento es el 15.
    $fechaVencimientoPago->setDate($anioActual, $mesActual, 15);
}
// Si es después del 15, $fechaVencimientoPago ya tiene la fecha de hoy, así que no hacemos nada.


// Formateo de fechas para AFIP (formato YYYYMMDD)
$FECHA_CMP_AFIP  = $hoy->format('Ymd');
$FECHA_SERV_DESDE_AFIP = $fechaServicioDesde->format('Ymd');
$FECHA_SERV_HASTA_AFIP = $fechaServicioHasta->format('Ymd');
$FECHA_VTO_PAGO_AFIP = $fechaVencimientoPago->format('Ymd');


// --- DATOS DEL RECEPTOR Y DE ITEMS ---
// Estos datos deben venir de tu sistema o formulario.
// El nombre del receptor ahora es la Razón Social.
$datos_receptor = [
    "razon_social" => $_GET['receptor_razon_social'] ?? "Consumidor Final", // <--- USAR ESTE CAMPO
    "domicilio" => $_GET['receptor_domicilio'] ?? "S/D",
    "condicion_iva" => "IVA Sujeto Exento", // O la que corresponda
];
$datos_items = [
    ["desc" => $_GET['item_desc'] ?? "Servicio de Soporte", "cant" => 1, "precio" => $IMPORTE],
];


// --- LÓGICA DE CONEXIÓN Y OBTENCIÓN DE ÚLTIMO COMPROBANTE (sin cambios) ---
$ta = simplexml_load_file('TA.xml');
$token = (string) $ta->credentials->token;
$sign = (string) $ta->credentials->sign;
$wsdl = __DIR__ . '/wsdl/WSFEv1.wsdl';

try {
    $client = new SoapClient($wsdl, [
        'soap_version' => SOAP_1_2,
        'location' => "https://servicios1.afip.gov.ar/wsfev1/service.asmx",
        'trace' => 1,
        'exceptions' => true,
    ]);
} catch (Exception $e) {
    die("Error al crear SoapClient: " . $e->getMessage());
}

try {
    $ultimo = $client->FECompUltimoAutorizado(['Auth' => ['Token' => $token, 'Sign' => $sign, 'Cuit' => $CUIT], 'PtoVta' => $PTO_VTA, 'CbteTipo' => $TIPO_CMP]);
} catch (SoapFault $e) {
    die("Error SOAP en FECompUltimoAutorizado: " . $e->getMessage());
}

$nro_siguiente = $ultimo->FECompUltimoAutorizadoResult->CbteNro + 1;


// --- ARMADO DE LA SOLICITUD PARA AFIP CON LAS NUEVAS FECHAS ---
$detalles = [
    'Concepto'     => 2, // Servicios
    'DocTipo'      => 80, // CUIT
    'DocNro'       => $DOC_NRO,
    'CbteDesde'    => $nro_siguiente,
    'CbteHasta'    => $nro_siguiente,
    'CbteFch'      => $FECHA_CMP_AFIP,
    'FchServDesde' => $FECHA_SERV_DESDE_AFIP, // <--- Fecha de servicio DESDE
    'FchServHasta' => $FECHA_SERV_HASTA_AFIP, // <--- Fecha de servicio HASTA
    'FchVtoPago'   => $FECHA_VTO_PAGO_AFIP,   // <--- Fecha de Vencimiento de Pago
    'ImpTotal'     => $IMPORTE,
    'ImpTotConc'   => 0.00,
    'ImpNeto'      => $IMPORTE,
    'ImpOpEx'      => 0.00,
    'ImpIVA'       => 0.00,
    'ImpTrib'      => 0.00,
    'MonId'        => 'PES',
    'MonCotiz'     => 1,
];

// --- LÓGICA DE EMISIÓN (sin cambios) ---
try {
    $respuesta = $client->FECAESolicitar([
        'Auth' => ['Token' => $token, 'Sign' => $sign, 'Cuit' => $CUIT],
        'FeCAEReq' => [
            'FeCabReq' => ['CantReg' => 1, 'PtoVta' => $PTO_VTA, 'CbteTipo' => $TIPO_CMP],
            'FeDetReq' => ['FECAEDetRequest' => $detalles]
        ]
    ]);

    $detalleRespuesta = $respuesta->FECAESolicitarResult->FeDetResp->FECAEDetResponse;

    if ($detalleRespuesta->Resultado !== 'A') {
        $msg = "Error de AFIP: ";
        if (isset($detalleRespuesta->Observaciones->Obs)) {
            $msg .= $detalleRespuesta->Observaciones->Obs->Code . " - " . $detalleRespuesta->Observaciones->Obs->Msg;
        }
        die($msg);
    }
    
    // --- ÉXITO: CONSTRUIR EL ARRAY $factura_data PARA EL PDF ---
    $tipo_str = "Factura C";
    if ($TIPO_CMP == 15) $tipo_str = "Recibo C";
    if ($TIPO_CMP == 12) $tipo_str = "Nota de Crédito C";

    $factura_data = [
        "tipo" => $tipo_str,
        "letra" => "C",
        "punto_venta" => (string)$PTO_VTA,
        "nro_comprobante" => (string)$detalleRespuesta->CbteDesde,
        "fecha_emision" => $detalleRespuesta->CbteFch,     // Formato YYYYMMDD
        "fecha_vto_pago" => $detalles['FchVtoPago'],       // Formato YYYYMMDD
        "periodo_desde" => $detalles['FchServDesde'],     // Formato YYYYMMDD
        "periodo_hasta" => $detalles['FchServHasta'],     // Formato YYYYMMDD
        "cae" => $detalleRespuesta->CAE,
        "vto_cae" => $detalleRespuesta->CAEFchVto,         // Formato YYYYMMDD
        "emisor" => [
            "nombre" => "RASSINO NICOLAS IGNACIO",
            "cuit" => $CUIT,
            "domicilio" => "Los Ceibos 84 - Mendiolaza, Córdoba",
            "condicion_iva" => "Responsable Monotributo",
        ],
        "receptor" => [
            "nombre" => $datos_receptor['razon_social'], // <--- USANDO LA RAZÓN SOCIAL
            "cuit" => $DOC_NRO,
            "domicilio" => $datos_receptor['domicilio'],
            "condicion_iva" => $datos_receptor['condicion_iva'],
        ],
        "items" => $datos_items,
    ];

    // --- GENERAR HTML Y JS PARA AUTO-ENVIAR EL FORMULARIO (sin cambios) ---
    $factura_json = htmlspecialchars(json_encode($factura_data), ENT_QUOTES, 'UTF-8');
    
    echo <<<HTML
    <!DOCTYPE html>
    <html>
    <head><title>Generando Comprobante...</title></head>
    <body>
        <p>Por favor espere, estamos generando su comprobante...</p>
        <form id="form_pdf" action="facturaCPDF.php" method="post">
            <input type="hidden" name="factura_data" value="{$factura_json}">
        </form>
        <script type="text/javascript">
            document.getElementById('form_pdf').submit();
        </script>
    </body>
    </html>
HTML;

} catch (SoapFault $e) {
    echo "❌ Error al emitir comprobante: " . $e->getMessage() . PHP_EOL;
    exit;
}
?>