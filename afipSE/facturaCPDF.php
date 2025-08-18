<?php
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// QR: Endroid
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;

// ----------------- Datos de ejemplo (reemplazá con los tuyos) -----------------
$factura = [
    "tipo" => "Factura C",
    "letra" => "C",
    "punto_venta" => "4",            // para mostrar lo paddeamos; para QR lo pasamos a int
    "nro_comprobante" => "508",      // idem
    "fecha_emision" => "2025-08-04", // AFIP espera YYYY-MM-DD en el QR
    "fecha_vto_pago" => "2025-08-11",
    "periodo_desde" => "2025-08-01",
    "periodo_hasta" => "2025-08-31",
    "cae" => "75318900218724",
    "vto_cae" => "2025-08-14",
    "emisor" => [
        "nombre" => "RASSINO NICOLAS IGNACIO",
        "cuit" => "20355729428",
        "domicilio" => "Los Ceibos 84 - Mendiolaza, Córdoba",
        "condicion_iva" => "Responsable Monotributo",
    ],
    "receptor" => [
        "nombre" => "INSTITUTO CATOLICO SUPERIOR",
        "cuit" => "30677610782",
        "domicilio" => "Avenida Velez Sarfield 539 - Barrio Centro Sur, Córdoba",
        "condicion_iva" => "IVA Sujeto Exento",
    ],
    "items" => [
        ["desc" => "soporte sistema terciario", "cant" => 1, "precio" => 144383.28],
    ],
];

// ----------------- Calcular totales + tabla -----------------
$total = 0.0;
$itemsHtml = "";
foreach ($factura["items"] as $item) {
    $subtotal = $item["cant"] * $item["precio"];
    $total += $subtotal;
    $itemsHtml .= "
    <tr>
        <td>{$item['desc']}</td>
        <td class='right'>".number_format($item['cant'],2,',','.')."</td>
        <td class='right'>".number_format($item['precio'],2,',','.')."</td>
        <td class='right'>".number_format($subtotal,2,',','.')."</td>
    </tr>";
}

// ----------------- QR AFIP (local, sin internet) -----------------
$importeQr = number_format($total, 2, '.', ''); // punto decimal
$datos_qr = [
    "ver"        => 1,
    "fecha"      => $factura["fecha_emision"],          // YYYY-MM-DD
    "cuit"       => (int)$factura["emisor"]["cuit"],
    "ptoVta"     => (int)$factura["punto_venta"],
    "tipoCmp"    => 11,                                  // 11 = Factura C
    "nroCmp"     => (int)$factura["nro_comprobante"],
    "importe"    => (float)$importeQr,
    "moneda"     => "PES",
    "ctz"        => 1,
    "tipoDocRec" => 80,                                  // 80 = CUIT
    "nroDocRec"  => (int)$factura["receptor"]["cuit"],
    "tipoCodAut" => "E",
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

// ----------------- HTML -----------------
$puntoVenta    = str_pad($factura["punto_venta"], 5, "0", STR_PAD_LEFT);
$nroComprobante= str_pad($factura["nro_comprobante"], 8, "0", STR_PAD_LEFT);

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
    .footer-fixed {
    position: fixed;
    bottom: 20px; /* margen inferior */
    left: 0;
    width: 100%;
}

</style>

<div class="titulo">FACTURA '.$factura["letra"].' - COD. 011</div>
<div class="subtitulo">ORIGINAL</div>

<div class="grid top-meta">
  <div class="row">
    <div class="cell cell-half">
      <strong>Fecha de Emisión:</strong> '.$factura["fecha_emision"].'<br>
      <strong>Período:</strong> '.$factura["periodo_desde"].' al '.$factura["periodo_hasta"].'<br>
      <strong>Vto. Pago:</strong> '.$factura["fecha_vto_pago"].'
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
      '.$factura["emisor"]["nombre"].'<br>
      CUIT: '.$factura["emisor"]["cuit"].'<br>
      '.$factura["emisor"]["domicilio"].'<br>
      Condición frente al IVA: '.$factura["emisor"]["condicion_iva"].'
    </div>
    <div class="cell cell-half">
      <strong>Cliente</strong><br>
      '.$factura["receptor"]["nombre"].'<br>
      CUIT: '.$factura["receptor"]["cuit"].'<br>
      '.$factura["receptor"]["domicilio"].'<br>
      Condición frente al IVA: '.$factura["receptor"]["condicion_iva"].'
    </div>
  </div>
</div>

<table class="items">
  <thead>
    <tr>
      <th>Descripción</th>
      <th>Cantidad</th>
      <th>Precio Unit.</th>
      <th>Subtotal</th>
    </tr>
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
          <strong>Vto. CAE:</strong> '.$factura["vto_cae"].'<br>
          Comprobante Autorizado. Esta agencia no se responsabiliza por los datos ingresados en el detalle de la operación.
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
$options->set('isRemoteEnabled', true); // no dependemos de remoto, pero lo dejamos
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("factura.pdf", ["Attachment" => false]);
