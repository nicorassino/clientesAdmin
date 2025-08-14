<?php
require_once 'verificarTA.php';

if (!verificar_o_generar_TA()) {
    exit("❌ No se pudo generar TA.xml");
}

$CUIT       = '20355729428';
$PTO_VTA    = 4;
$TIPO_CMP   = 12;               // 15 = Recibo C, 12 = Nota de Crédito C
$CONCEPTO   = 2;                // Servicios
$DOC_TIPO   = 96;               // DNI
$DOC_NRO    = 13964667;
$IMPORTE    = 10.00;
$MONEDA_ID  = 'PES';
$COTIZACION = 1.000;
$FECHA      = date('Ymd');

// Cargar Ticket de Acceso
$ta    = simplexml_load_file('TA.xml');
$token = (string) $ta->credentials->token;
$sign  = (string) $ta->credentials->sign;

$wsdl = __DIR__ . '/wsdl/WSFEv1.wsdl';

try {
    $client = new SoapClient($wsdl, [
        'soap_version' => SOAP_1_2,
        'location' => "https://wswhomo.afip.gov.ar/wsfev1/service.asmx",
        //'location' => "https://servicios1.afip.gov.ar/wsfev1/service.asmx", // Producción
        'trace' => 1,
        'exceptions' => true,
        'stream_context' => stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ])
    ]);
    echo "SOAP Client creado con éxito\n";
} catch (Exception $e) {
    echo "Error al crear SoapClient: " . $e->getMessage() . "\n";
    exit;
}

// Obtener último comprobante autorizado del tipo actual
try {
    $ultimo = $client->FECompUltimoAutorizado([
        'Auth' => [
            'Token' => $token,
            'Sign'  => $sign,
            'Cuit'  => $CUIT
        ],
        'PtoVta'   => $PTO_VTA,
        'CbteTipo' => $TIPO_CMP,
    ]);
} catch (SoapFault $e) {
    echo "Error SOAP en FECompUltimoAutorizado: " . $e->getMessage() . PHP_EOL;
    echo "Request:\n" . $client->__getLastRequest() . PHP_EOL;
    echo "Response:\n" . $client->__getLastResponse() . PHP_EOL;
    exit;
}

$nro_siguiente = $ultimo->FECompUltimoAutorizadoResult->CbteNro + 1;

// Datos base comunes
$detalles = [
    'Concepto'     => $CONCEPTO,
    'DocTipo'      => $DOC_TIPO,
    'DocNro'       => $DOC_NRO,
    'CbteDesde'    => $nro_siguiente,
    'CbteHasta'    => $nro_siguiente,
    'CbteFch'      => $FECHA,
    'FchServDesde' => $FECHA,
    'FchServHasta' => $FECHA,
    'FchVtoPago'   => $FECHA,
    'ImpTotal'     => $IMPORTE,
    'ImpTotConc'   => 0.00,
    'ImpNeto'      => $IMPORTE,
    'ImpOpEx'      => 0.00,
    'ImpIVA'       => 0.00,
    'ImpTrib'      => 0.00,
    'MonId'        => $MONEDA_ID,
    'MonCotiz'     => $COTIZACION
];

// Si es una nota de crédito, agregar comprobante asociado
if ($TIPO_CMP === 12) {
    $detalles['CbtesAsoc'] = [
        'CbteAsoc' => [
            [
                'Tipo'   => 15,                       // Tipo de comprobante a anular (Recibo C)
                'PtoVta' => $PTO_VTA,
                'Nro'    => $nro_siguiente - 1        // Se asume que es el anterior
            ]
        ]
    ];
    // Motivo (campo opcional, útil para registrar por qué se emite)
    $detalles['Motivo'] = 'Anulación de recibo C por error de facturación';
}

// Armar solicitud completa
$datos = [
    'FeCAEReq' => [
        'FeCabReq' => [
            'CantReg'  => 1,
            'PtoVta'   => $PTO_VTA,
            'CbteTipo' => $TIPO_CMP,
        ],
        'FeDetReq' => [
            'FECAEDetRequest' => $detalles
        ]
    ]
];

// Enviar solicitud
try {
    $respuesta = $client->FECAESolicitar([
        'Auth' => [
            'Token' => $token,
            'Sign'  => $sign,
            'Cuit'  => $CUIT
        ],
        'FeCAEReq' => $datos['FeCAEReq']
    ]);

    $detalle = $respuesta->FECAESolicitarResult->FeDetResp->FECAEDetResponse;

    echo "\n✅ CAE otorgado: " . $detalle->CAE . "\n";
    echo "📅 Vencimiento CAE: " . $detalle->CAEFchVto . "\n";

} catch (SoapFault $e) {
    echo "❌ Error al emitir comprobante: " . $e->getMessage() . PHP_EOL;
    echo "Request:\n" . $client->__getLastRequest() . PHP_EOL;
    echo "Response:\n" . $client->__getLastResponse() . PHP_EOL;
    exit;
}
