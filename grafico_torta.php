<?php

/**
 * Genera un grafico de torta (pie chart) en memoria usando GD.
 *
 * @param array $segmentos  Cada elemento: ['label' => string, 'porcentaje' => float, 'color' => [R, G, B]]
 * @param string $titulo    Titulo de la leyenda (opcional)
 * @param int $ancho        Ancho de la imagen en px
 * @param int $alto         Alto de la imagen en px
 * @return resource         GD image resource
 */
function generar_grafico_torta($segmentos = array(), $titulo = 'Costo total', $ancho = 420, $alto = 420) {
    $imagen = imagecreatetruecolor($ancho, $alto);

    $blanco      = imagecolorallocate($imagen, 255, 255, 255);
    $gris_texto  = imagecolorallocate($imagen, 80, 80, 80);

    imagefill($imagen, 0, 0, $blanco);

    // Colores por defecto si no se especifican
    $coloresDefecto = [
        [70, 130, 180],  // azul
        [180, 80, 80],   // rojo
        [140, 180, 80],  // verde
        [100, 190, 140], // verde claro
        [90, 180, 210],  // celeste
        [230, 140, 70],  // naranja
        [180, 130, 200], // violeta
        [200, 170, 100], // dorado
    ];

    // Si no se pasan segmentos, usar los de ejemplo del recibo2026
    if (empty($segmentos)) {
        $segmentos = array(
            array('label' => 'Sueldo Neto',       'porcentaje' => 16, 'color' => $coloresDefecto[0]),
            array('label' => 'Seguridad Social',  'porcentaje' => 18, 'color' => $coloresDefecto[1]),
            array('label' => 'Costo Sindical',    'porcentaje' => 17, 'color' => $coloresDefecto[2]),
            array('label' => 'Obra Social',       'porcentaje' => 17, 'color' => $coloresDefecto[3]),
            array('label' => 'PAMI',              'porcentaje' => 15, 'color' => $coloresDefecto[4]),
            array('label' => 'ART',               'porcentaje' => 17, 'color' => $coloresDefecto[5]),
        );
    }

    // Asignar colores por defecto a segmentos sin color
    $colorIdx = 0;
    foreach ($segmentos as $i => &$seg) {
        if (!isset($seg['color'])) {
            $seg['color'] = $coloresDefecto[$colorIdx % count($coloresDefecto)];
            $colorIdx++;
        }
        $seg['color_gd'] = imagecolorallocate($imagen, $seg['color'][0], $seg['color'][1], $seg['color'][2]);
    }
    unset($seg);

    // Centro y diametro de la torta
    $cx = 210;
    $cy = 260;
    $diametro = 300;
    $explode = 8; // separacion de las porciones desde el centro

    // Dibujar porciones
    $angulo_inicio = 0;
    foreach ($segmentos as $info) {
        $grados = ($info['porcentaje'] / 100) * 360;
        $angulo_fin = $angulo_inicio + $grados;

        // Offset para separar la porcion del centro
        $angulo_medio = deg2rad($angulo_inicio + ($grados / 2));
        $ox = $cx + (cos($angulo_medio) * $explode);
        $oy = $cy + (sin($angulo_medio) * $explode);

        imagefilledarc($imagen, (int)$ox, (int)$oy, $diametro, $diametro,
            $angulo_inicio, $angulo_fin, $info['color_gd'], IMG_ARC_PIE);

        // Texto del porcentaje dentro de la porcion
        $txt_x = $ox + (cos($angulo_medio) * ($diametro / 2.8));
        $txt_y = $oy + (sin($angulo_medio) * ($diametro / 2.8));
        imagestring($imagen, 5, (int)$txt_x - 14, (int)$txt_y - 8, $info['porcentaje'] . '%', $blanco);

        $angulo_inicio = $angulo_fin;
    }

    // Leyenda (2 filas de 3, arriba de la torta)
    $cols = 3;
    $colW = (int)($ancho / $cols);
    $yFila1 = 38;
    $yFila2 = 58;
    $i = 0;

    imagestring($imagen, 2, 10, 2, $titulo, $gris_texto);

    foreach ($segmentos as $info) {
        $col = $i % $cols;
        $fila = (int)($i / $cols);
        $xItem = 10 + ($col * $colW);
        $yItem = ($fila === 0) ? $yFila1 : $yFila2;
        imagefilledrectangle($imagen, $xItem, $yItem, $xItem + 10, $yItem + 10, $info['color_gd']);
        imagestring($imagen, 2, $xItem + 14, $yItem, $info['label'], $gris_texto);
        $i++;
    }

    return $imagen;
}

// --- Standalone execution (backward compatible) ---
if (basename(__FILE__) === basename(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '')) {
    $imagen = generar_grafico_torta();
    imagepng($imagen, 'grafico_torta.png');
    imagedestroy($imagen);
    echo "Grafico generado con exito en 'grafico_torta.png'!";
}
