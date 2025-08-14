<?php
require_once 'verificarTA.php';
// USO DE LA FUNCIÓN
if (!verificar_o_generar_TA()) {
    exit("❌ No se pudo generar TA.xml");
}


$CUIT      = '30535380593';
$PTO_VTA   = 5;
$TIPO_CMP  = 15; // Recibo C

// Cargar TA.xml
$ta = simplexml_load_file('TA.xml');
$token = (string) $ta->credentials->token;
$sign  = (string) $ta->credentials->sign;

// Cliente SOAP
$client = new SoapClient("wsdl/WSFEv1.wsdl", [
    'soap_version' => SOAP_1_2,
    'location' => "https://servicios1.afip.gov.ar/wsfev1/service.asmx", // Homologación
    // 'location' => "https://servicios1.afip.gov.ar/wsfev1/service.asmx", // Producción
    'trace' => 1,
    'exceptions' => true,
]);

try {
    // Paso 1: Consultar último comprobante
    $ultimo = $client->FECompUltimoAutorizado([
        'Auth' => [
            'Token' => $token,
            'Sign'  => $sign,
            'Cuit'  => $CUIT
        ],
        'PtoVta'    => $PTO_VTA,
        'CbteTipo'  => $TIPO_CMP,
    ]);

    $nro_comprobante = $ultimo->FECompUltimoAutorizadoResult->CbteNro;
    echo "Último comprobante autorizado: $nro_comprobante" . PHP_EOL;

    // Paso 2: Consultar detalle de ese comprobante
    $consulta = $client->FECompConsultar([
        'Auth' => [
            'Token' => $token,
            'Sign'  => $sign,
            'Cuit'  => $CUIT
        ],
        'FeCompConsReq' => [
            'CbteTipo' => $TIPO_CMP,
            'PtoVta'   => $PTO_VTA,
            'CbteNro'  => $nro_comprobante,
        ]
    ]);

    $detalle = $consulta->FECompConsultarResult->ResultGet;

    echo "CAE: " . $detalle->CodAutorizacion . PHP_EOL;
    echo "Fecha Vto CAE: " . $detalle->FchVto . PHP_EOL;
    echo "Fecha Comprobante: " . $detalle->CbteFch . PHP_EOL;

} catch (Exception $e) {
    echo "Error al consultar comprobante: " . $e->getMessage() . PHP_EOL;
}
