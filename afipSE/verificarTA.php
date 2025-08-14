<?php

// FUNCION PARA VERIFICAR Y/O GENERAR TA.xml
function verificar_o_generar_TA($archivo_ta = 'TA.xml', $script_generador = 'obtenerToken.php') {
     if (!file_exists($archivo_ta)) {
        echo "🔄 TA.xml no encontrado. Generando nuevo..." . PHP_EOL;
        $script_generador = __DIR__ . '/' . $script_generador;
        exec("php $script_generador", $output, $exit_code);
        echo "Salida del script:\n" . implode(PHP_EOL, $output) . PHP_EOL;
        echo "Código de salida: $exit_code" . PHP_EOL;
        return $exit_code === 0;
    }

    $ta = simplexml_load_file($archivo_ta);
    if (!$ta) {
        echo "❌ Error al cargar TA.xml. Generando nuevo..." . PHP_EOL;
        exec("php $script_generador", $output, $exit_code);
        echo implode(PHP_EOL, $output) . PHP_EOL;
        return $exit_code === 0;
    }

    $expiration = strtotime((string) $ta->header->expirationTime);
    $now = time();

    if ($now >= $expiration) {
        echo "⏰ TA.xml vencido. Generando nuevo..." . PHP_EOL;
        exec("php $script_generador", $output, $exit_code);
        echo implode(PHP_EOL, $output) . PHP_EOL;
        return $exit_code === 0;
    }

    echo "✅ TA.xml vigente hasta: " . $ta->header->expirationTime . PHP_EOL;
    return true;
}


