<?php
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;

// --- Validar si se recibieron los datos por POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['factura_data'])) {
    die("Error: Acceso no válido o datos de factura no recibidos.");
}

// Decodificar los datos JSON enviados desde emitirComp.php
$factura = json_decode($_POST['factura_data'], true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: Los datos de la factura están mal formados.");
}


// ----------------- Calcular totales + tabla HTML -----------------
$total = 0.0;
$itemsHtml = "";
foreach ($factura["items"] as $item) {
    $subtotal = $item["cant"] * $item["precio"];
    $total += $subtotal;
    // Formatear números para la vista
    $cantidadF = number_format($item['cant'], 2, ',', '.');
    $precioF = number_format($item['precio'], 2, ',', '.');
    $subtotalF = number_format($subtotal, 2, ',', '.');
    
    $itemsHtml .= "
    <tr>
        <td>" . htmlspecialchars($item['desc']) . "</td>
        <td class='right'>{$cantidadF}</td>
        <td class='right'>{$precioF}</td>
        <td class='right'>{$subtotalF}</td>
    </tr>";
}

// ----------------- Preparar datos para el QR AFIP -----------------
// El tipo de comprobante AFIP (código numérico)
$tipoCmpCodigo = 11; // 11=Factura C, 15=Recibo C, 12=NC C
if (strpos(strtolower($factura['tipo']), 'recibo') !== false) {
    $tipoCmpCodigo = 15;
} elseif (strpos(strtolower($factura['tipo']), 'nota de crédito') !== false) {
    $tipoCmpCodigo = 12;
}

$datos_qr = [
    "ver"        => 1,
    "fecha"      => date('Y-m-d', strtotime($factura["fecha_emision"])), // Formato YYYY-MM-DD
    "cuit"       => (int)$factura["emisor"]["cuit"],
    "ptoVta"     => (int)$factura["punto_venta"],
    "tipoCmp"    => $tipoCmpCodigo,
    "nroCmp"     => (int)$factura["nro_comprobante"],
    "importe"    => (float)number_format($total, 2, '.', ''), // Sin separador de miles
    "moneda"     => "PES",
    "ctz"        => 1,
    "tipoDocRec" => 80, // CUIT
    "nroDocRec"  => (int)$factura["receptor"]["cuit"],
    "tipoCodAut" => "E", // "E" para CAE
    "codAut"     => (int)$factura["cae"],
];
$qrPayload = "https://www.afip.gob.ar/fe/qr/?p=" . base64_encode(json_encode($datos_qr));

$writer = new PngWriter();
$qr = QrCode::create($qrPayload)
    ->setEncoding(new Encoding('UTF-8'))
    ->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
    ->setSize(150)
    ->setMargin(5);

$qrResult = $writer->write($qr);
$qrDataUri = $qrResult->getDataUri();

// ----------------- Preparar datos para el HTML -----------------
$puntoVenta     = str_pad($factura["punto_venta"], 5, "0", STR_PAD_LEFT);
$nroComprobante = str_pad($factura["nro_comprobante"], 8, "0", STR_PAD_LEFT);
$fechaEmisionF  = date('d/m/Y', strtotime($factura["fecha_emision"]));
$vtoCaeF        = date('d/m/Y', strtotime($factura["vto_cae"]));
$periodoDesdeF  = date('d/m/Y', strtotime($factura["periodo_desde"]));
$periodoHastaF  = date('d/m/Y', strtotime($factura["periodo_hasta"]));
$vtoPagoF       = date('d/m/Y', strtotime($factura["fecha_vto_pago"]));

// --- Título dinámico ---
$tipoComprobanteTitulo = "FACTURA";
if (strpos(strtolower($factura['tipo']), 'recibo') !== false) {
    $tipoComprobanteTitulo = "RECIBO";
} elseif (strpos(strtolower($factura['tipo']), 'nota de crédito') !== false) {
    $tipoComprobanteTitulo = "NOTA DE CRÉDITO";
}
$codigoComprobanteTitulo = "COD. " . str_pad($tipoCmpCodigo, 3, "0", STR_PAD_LEFT);


// ----------------- HTML Dinámico -----------------
$html = '
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
    .titulo { text-align: center; font-size: 20px; font-weight: bold; border: 2px solid #000; padding: 5px; margin-bottom: 5px; }
    .subtitulo { text-align: center; font-size: 14px; margin-bottom: 15px; }
    .grid { display: table; width: 100%; border-collapse: collapse; }
    .row { display: table-row; }
    .cell { display: table-cell; border: 1px solid #000; padding: 6px; vertical-align: top; }
    .cell-half { width: 50%; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .items th, .items td { border: 1px solid #000; padding: 6px; }
    .right { text-align: right; }
    .total { font-size: 14px; font-weight: bold; text-align: right; margin-top: 10px; }
    .footer { margin-top: 12px; font-size: 10px; }
    .top-meta { margin: 8px 0 10px 0; }
    .qr-wrap { margin-top: 8px; text-align: right; }
    .footer-fixed { position: fixed; bottom: 20px; left: 0; width: 100%; }
</style>

<div class="titulo">'.$tipoComprobanteTitulo.' '.$factura["letra"].' - '.$codigoComprobanteTitulo.'</div>
<div class="subtitulo">ORIGINAL</div>

<div class="grid top-meta">
  <div class="row">
    <div class="cell cell-half">
      <strong>Fecha de Emisión:</strong> '.$fechaEmisionF.'<br>
      <strong>Período:</strong> '.$periodoDesdeF.' al '.$periodoHastaF.'<br>
      <strong>Vto. Pago:</strong> '.$vtoPagoF.'
    </div>
    <div class="cell cell-half">
      <strong>Pto. Venta:</strong> '.$puntoVenta.' &nbsp;&nbsp;
      <strong>Comp. Nro:</strong> '.$nroComprobante.'
    </div>
  </div>
</div>

<div class="grid">
  <div class="row">
    <div class="cell cell-half">
      <strong>Emisor</strong><br>
      '.htmlspecialchars($factura["emisor"]["nombre"]).'<br>
      CUIT: '.$factura["emisor"]["cuit"].'<br>
      '.htmlspecialchars($factura["emisor"]["domicilio"]).'<br>
      Condición frente al IVA: '.htmlspecialchars($factura["emisor"]["condicion_iva"]).'
    </div>
    <div class="cell cell-half">
      <strong>Cliente</strong><br>
      '.htmlspecialchars($factura["receptor"]["nombre"]).'<br>
      CUIT: '.$factura["receptor"]["cuit"].'<br>
      Condición frente al IVA: '.htmlspecialchars($factura["receptor"]["condicion_iva"]).'
    </div>
  </div>
</div>

<table class="items">
  <thead>
    <tr><th>Descripción</th><th>Cantidad</th><th>Precio Unit.</th><th>Subtotal</th></tr>
  </thead>
  <tbody>'.$itemsHtml.'</tbody>
</table>

<div class="footer-fixed">
  <div class="total">Total: $'.number_format($total,2,',','.').'</div>
  <div class="grid">
    <div class="row">
      <div class="cell cell-half">
        <div class="footer">
          <strong>CAE N°:</strong> '.$factura["cae"].' &nbsp;&nbsp;
          <strong>Vto. CAE:</strong> '.$vtoCaeF.'<br>
          Comprobante Autorizado.
        </div>
      </div>
      <div class="cell cell-half">
        <div class="qr-wrap">
          <img src="'.$qrDataUri.'" alt="QR AFIP" width="120" height="120">
        </div>
      </div>
    </div>
  </div>
</div>
';

// ----------------- Dompdf -----------------
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("factura.pdf", ["Attachment" => false]);
