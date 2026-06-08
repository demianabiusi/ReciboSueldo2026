<?php

require_once __DIR__ . '/fpdf19/fpdf.php';
require_once __DIR__ . '/grafico_torta.php';

class ReciboSueldoGenerator extends FPDF {

    const COLOR_HEADER_BG = 230;
    const COLOR_TEXT = 50;

    /**
     * Datos del recibo actual (se resetea por cada pagina).
     */
    private $data = array();

    /**
     * Datos de todos los recibos a generar.
     */
    private $recibos = array();

    // -----------------------------------------------------------------
    //  Helpers de encoding
    // -----------------------------------------------------------------

    function _escape($s) {
        $s = utf8_decode($s);
        return parent::_escape($s);
    }

    private function fmt($n) {
        return number_format((float)$n, 2, ',', '.');
    }

    // -----------------------------------------------------------------
    //  Carga de datos
    // -----------------------------------------------------------------

    /**
     * Agrega un recibo al lote. Cada recibo genera una pagina.
     *
     * Estructura de $recibo:
     * [
     *   'empresa' => [
     *       'razon_social' => 'EMPRESA S.A.',
     *       'provincia'    => 'Provincia...',
     *       'cuit'         => '30-00000000-0',
     *   ],
     *   'empleado' => [
     *       'quincena'      => 1,
     *       'mes'           => 6,
     *       'anio'          => 2026,
     *       'nombre'        => 'APELLIDO, NOMBRE',
     *       'legajo'        => '12345',
     *       'sueldo_bruto'  => 500000.00,
     *       'antiguedad'    => '5 anos',
     *       'fecha_ingreso' => '01/01/2020',
     *       'categoria'     => 'Administrativo',
     *       'cuil'          => '20-12345678-9',
     *       'lugar_pago'    => 'Sede Central',
     *       'fp_aportes'    => 'Mensual',
     *   ],
     *   'contribuciones_empleador' => [
     *       ['concepto' => 'ART',                        'unidad' => '3%',  'base' => '$ 500.000', 'monto' => 15000.00],
     *       ['concepto' => 'Contribucion Jubilacion',    'unidad' => '18%', 'base' => '',           'monto' => 90000.00],
     *       ...
     *   ],
     *   'haberes' => [
     *       ['concepto' => 'Sueldo Basico', 'unidad' => '30', 'base' => '$ 500.000', 'monto' => 500000.00],
     *       ...
     *   ],
     *   'descuentos' => [
     *       ['concepto' => 'Aporte Jubilacion', 'unidad' => '11%', 'base' => '$ 500.000', 'monto' => 55000.00],
     *       ...
     *   ],
     *   'composicion' => [
     *       'remunerativo'     => 450000.00,
     *       'no_remunerativo'  => 50000.00,
     *       'descuentos'       => 75000.00,
     *   ],
     *   'torta' => [   // opcional: datos para el grafico (si no se pasa, se autogenera desde detalle_costos)
     *       ['label' => 'Sueldo Neto',       'porcentaje' => 16, 'color' => [70, 130, 180]],
     *       ...
     *   ],
     *   'detalle_costos' => [  // opcional: secciones del bloque izquierdo del pie; cada item es:
     *       ['titulo' => 'Costo Sindical',   'empleador' => 15000, 'trabajador' => 7500],
     *       ['titulo' => 'Seguridad Social', 'empleador' => 90000, 'trabajador' => 55000],
     *       ['titulo' => 'ART',              'empleador' => 15000],
     *       ...
     *   ],
     * ]
     */
    public function agregarRecibo($recibo) {
        $this->recibos[] = $recibo;
    }

    /**
     * Carga un unico recibo (atajo).
     */
    public function setRecibo($recibo) {
        $this->recibos = array($recibo);
    }

    // -----------------------------------------------------------------
    //  Generacion del PDF
    // -----------------------------------------------------------------

    /**
     * Genera el PDF y lo envia al navegador o lo retorna como string.
     *
     * @param string $modo  'I' = inline (default), 'D' = download, 'S' = return string
     * @return string|null  Solo si $modo === 'S'
     */
    public function generar($modo = 'I') {
        if (empty($this->recibos)) {
            throw new \RuntimeException('No hay recibos cargados. Use agregarRecibo() o setRecibo() primero.');
        }

        $this->SetMargins(10, 10, 10);

        foreach ($this->recibos as $idx => $recibo) {
            $this->data = $this->normalizarRecibo($recibo);

            if ($idx === 0) {
                $this->AddPage();
            } else {
                $this->AddPage();
            }

            $this->dibujarEncabezado();
            $this->dibujarCostoEmpleador();
            $this->dibujarSueldoBruto();
            $this->dibujarComposicion();
            $this->dibujarPie();
        }

        return $this->Output($modo);
    }

    // -----------------------------------------------------------------
    //  Normalizacion de datos (completa valores faltantes)
    // -----------------------------------------------------------------

    private function normalizarRecibo($r) {
        $d = array_merge(array(
            'empresa'                    => array(),
            'empleado'                   => array(),
            'contribuciones_empleador'   => array(),
            'haberes'                    => array(),
            'descuentos'                 => array(),
            'composicion'                => array(),
            'torta'                      => array(),
            'detalle_costos'             => array(),
        ), $r);

        $d['empresa'] = array_merge(array(
            'razon_social' => '',
            'provincia'    => '',
            'cuit'         => '',
        ), (array)$d['empresa']);

        $d['empleado'] = array_merge(array(
            'quincena'      => '',
            'mes'           => '',
            'anio'          => '',
            'nombre'        => '',
            'legajo'        => '',
            'sueldo_bruto'  => 0,
            'antiguedad'    => '',
            'fecha_ingreso' => '',
            'categoria'     => '',
            'cuil'          => '',
            'lugar_pago'    => '',
            'fp_aportes'    => '',
        ), (array)$d['empleado']);

        $d['composicion'] = array_merge(array(
            'remunerativo'    => 0,
            'no_remunerativo' => 0,
            'descuentos'      => 0,
        ), (array)$d['composicion']);

        $d['detalle_costos'] = isset($d['detalle_costos']) ? $d['detalle_costos'] : array();

        return $d;
    }

    // =================================================================
    //  BLOQUES DE DIBUJO (idéntico formato que recibo2026.php)
    // =================================================================

    private function dibujarEncabezado() {
        $emp = $this->data['empresa'];
        $empld = $this->data['empleado'];

        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(self::COLOR_TEXT);

        // Bloque EMPRESA (sin bordes internos)
        $this->Cell(190, 4, ' EMPRESA', 'LTR', 1, 'L');
        $this->Cell(190, 4, ' ' . $emp['razon_social'] . ($emp['provincia'] ? ' - ' . $emp['provincia'] : ''), 'LR', 1, 'L');
        $this->Cell(190, 4, ' C.U.I.T. EMPRESA : ' . $emp['cuit'], 'LRB', 1, 'L');

        // Fila 3: headers de datos personales
        $this->SetFont('Arial', 'B', 6);
        $this->SetFillColor(self::COLOR_HEADER_BG, self::COLOR_HEADER_BG, self::COLOR_HEADER_BG);
        $this->Cell(6, 5, 'Q.', 1, 0, 'C', true);
        $this->Cell(12, 5, 'MES', 1, 0, 'C', true);
        $this->Cell(12, 5, 'AÑO', 1, 0, 'C', true);
        $this->Cell(65, 5, 'APELLIDO Y NOMBRE', 1, 0, 'C', true);
        $this->Cell(25, 5, 'Nº LEGAJO', 1, 0, 'C', true);
        $this->Cell(35, 5, 'SUELDO BRUTO', 1, 0, 'C', true);
        $this->Cell(35, 5, 'ANTIGÜEDAD', 1, 1, 'C', true);

        // Fila 4: datos
        $this->SetFont('Arial', '', 7);
        $this->Cell(6, 5, $empld['quincena'], 1, 0, 'C');
        $this->Cell(12, 5, $empld['mes'], 1, 0, 'C');
        $this->Cell(12, 5, $empld['anio'], 1, 0, 'C');
        $this->Cell(65, 5, ' ' . $empld['nombre'], 1, 0, 'L');
        $this->Cell(25, 5, $empld['legajo'], 1, 0, 'C');
        $this->Cell(35, 5, '$ ' . $this->fmt($empld['sueldo_bruto']), 1, 0, 'R');
        $this->Cell(35, 5, $empld['antiguedad'], 1, 1, 'C');

        // Fila 5: segundas cabeceras
        $this->SetFont('Arial', 'B', 6);
        $this->SetFillColor(self::COLOR_HEADER_BG, self::COLOR_HEADER_BG, self::COLOR_HEADER_BG);
        $this->Cell(50, 5, 'FECHA INGRESO', 1, 0, 'C', true);
        $this->Cell(50, 5, 'CATEGORIA LABORAL', 1, 0, 'C', true);
        $this->Cell(40, 5, 'C.U.I.L.', 1, 0, 'C', true);
        $this->Cell(25, 5, 'LUGAR DE PAGO', 1, 0, 'C', true);
        $this->Cell(25, 5, 'F.PAGO APORTES', 1, 1, 'C', true);

        // Fila 6: datos
        $this->SetFont('Arial', '', 7);
        $this->Cell(50, 5, $empld['fecha_ingreso'], 1, 0, 'C');
        $this->Cell(50, 5, $empld['categoria'], 1, 0, 'C');
        $this->Cell(40, 5, $empld['cuil'], 1, 0, 'C');
        $this->Cell(25, 5, $empld['lugar_pago'], 1, 0, 'C');
        $this->Cell(25, 5, $empld['fp_aportes'], 1, 1, 'C');
    }
    

    private function seccionTituloGris($titulo, $monto) {
        $this->SetFillColor(self::COLOR_HEADER_BG, self::COLOR_HEADER_BG, self::COLOR_HEADER_BG);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(122, 6, '  ' . $titulo, 'LTB', 0, 'R', true);
        $this->Cell(68, 6, '$ ' . $this->fmt($monto), 'TRB', 1, 'R', true);
    }

    private function tablaHeaders() {
        $this->SetFillColor(self::COLOR_HEADER_BG, self::COLOR_HEADER_BG, self::COLOR_HEADER_BG);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell(52, 4, 'CONCEPTO', 1, 0, 'C', true);
        $this->Cell(20, 4, 'UNIDAD', 1, 0, 'C', true);
        $this->Cell(50, 4, 'BASE', 1, 0, 'C', true);
        $this->Cell(68, 4, 'MONTO', 1, 1, 'C', true);
    }

    private function filaTabla($concepto, $unidad = '', $base = '', $monto = 0) {
        $this->SetFont('Arial', '', 7);
        $this->Cell(52, 4, ' ' . $concepto, 'LR', 0, 'L');
        $this->Cell(20, 4, $unidad, 'LR', 0, 'C');
        $this->Cell(50, 4, $base, 'LR', 0, 'R');
        $this->Cell(68, 4, '$ ' . $this->fmt($monto), 'LR', 1, 'R');
    }

    private function cerrarTabla() {
        $this->Cell(190, 0, '', 'T', 1);
    }

    // -----------------------------------------------------------------
    //  Bloques principales
    // -----------------------------------------------------------------

    private function dibujarCostoEmpleador() {
        $contribuciones = $this->data['contribuciones_empleador'];
        $subtotal = array_sum(array_column($contribuciones, 'monto'));

        $this->seccionTituloGris('COSTO TOTAL EMPLEADOR', $subtotal);
        $this->tablaHeaders();
        foreach ($contribuciones as $c) {
            $this->filaTabla($c['concepto'], isset($c['unidad']) ? $c['unidad'] : '', isset($c['base']) ? $c['base'] : '', isset($c['monto']) ? $c['monto'] : 0);
        }
        $this->cerrarTabla();
        $this->Cell(190, 2, '', 1, 1);
        $this->seccionTituloGris('SUB TOTAL CONTRIBUCIONES EMPLEADOR', $subtotal);
    }

    private function dibujarSueldoBruto() {
        $empld = $this->data['empleado'];
        $haberes = $this->data['haberes'];
        $descuentos = $this->data['descuentos'];
        $totalHaberes = array_sum(array_column($haberes, 'monto'));

        $this->seccionTituloGris('SUELDO BRUTO', $totalHaberes);
        $this->tablaHeaders();

        foreach ($haberes as $h) {
            $this->filaTabla($h['concepto'], isset($h['unidad']) ? $h['unidad'] : '', isset($h['base']) ? $h['base'] : '', isset($h['monto']) ? $h['monto'] : 0);
        }

        if (!empty($descuentos)) {
            $this->Cell(52, 2, '', 'LR', 0);
            $this->Cell(20, 2, '', 'LR', 0);
            $this->Cell(50, 2, '', 'LR', 0);
            $this->Cell(68, 2, '', 'LR', 1);
            foreach ($descuentos as $d) {
                $this->filaTabla($d['concepto'], isset($d['unidad']) ? $d['unidad'] : '', isset($d['base']) ? $d['base'] : '', isset($d['monto']) ? $d['monto'] : 0);
            }
            $this->Cell(52, 2, '', 'LR', 0);
            $this->Cell(20, 2, '', 'LR', 0);
            $this->Cell(50, 2, '', 'LR', 0);
            $this->Cell(68, 2, '', 'LR', 1);
        }

        $this->cerrarTabla();
    }

    private function dibujarComposicion() {
        $comp = $this->data['composicion'];

        // Fila de composicion salarial
        $this->SetFillColor(self::COLOR_HEADER_BG, self::COLOR_HEADER_BG, self::COLOR_HEADER_BG);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(45, 5, ' COMPOSICION SALARIAL:', 'LTB', 0, 'L', true);
        $this->Cell(45, 5, ' Remunerativo: $ ' . $this->fmt($comp['remunerativo']), 'TB', 0, 'L', true);
        $this->Cell(45, 5, ' No Remunerativo: $ ' . $this->fmt($comp['no_remunerativo']), 'TB', 0, 'L', true);
        $this->Cell(20, 5, ' Descuentos:', 'TB', 0, 'L', true);
        $this->Cell(35, 5, '$ ' . $this->fmt($comp['descuentos']), 'TRB', 1, 'R', true);

        // Sueldo neto
        $neto = (float)(isset($comp['remunerativo']) ? $comp['remunerativo'] : 0) + (float)(isset($comp['no_remunerativo']) ? $comp['no_remunerativo'] : 0) - (float)(isset($comp['descuentos']) ? $comp['descuentos'] : 0);
        $this->seccionTituloGris('SUELDO NETO $', $neto);
    }
    

    private function dibujarPie() {
        $secciones = $this->data['detalle_costos'];

        // Auto-generar torta desde detalle_costos si no se paso explicitamente
        $torta = $this->data['torta'];
        if (empty($torta) && !empty($secciones)) {
            $coloresDefecto = [
                [70, 130, 180], [180, 80, 80], [140, 180, 80], [100, 190, 140],
                [90, 180, 210], [230, 140, 70], [180, 130, 200], [200, 170, 100],
            ];
            $totalEmp = 0;
            foreach ($secciones as $s) {
                if (isset($s['empleador']) && $s['empleador'] > 0) {
                    $totalEmp += (float)$s['empleador'];
                }
            }
            $ci = 0;
            foreach ($secciones as $s) {
                $emp = isset($s['empleador']) ? (float)$s['empleador'] : 0;
                if ($emp > 0) {
                    $pct = $totalEmp > 0 ? round(($emp / $totalEmp) * 100) : 0;
                    $torta[] = [
                        'label' => $s['titulo'],
                        'porcentaje' => $pct,
                        'color' => $coloresDefecto[$ci % count($coloresDefecto)],
                    ];
                    $ci++;
                }
            }
        }

        if (empty($torta) && empty($secciones)) {
            return;
        }

        $yInicio = $this->GetY();
        $xInicio = $this->GetX();
        $anchoTotal = 190;
        $anchoDer = 57;  // 30% del ancho
        $anchoIzq = $anchoTotal - $anchoDer;
        $xDer = $xInicio + $anchoIzq;

        // --- Bloque izquierdo: detalle de costos (70%) ---
        $this->SetFont('Arial', 'B', 8);
        $txtDetalle = 'Detalle de la composicion salarial';
        $this->Cell($anchoIzq, 4, $txtDetalle, 0, 1, 'C');
        $wTitulo = $this->GetStringWidth($txtDetalle);
        $centroIzq = $xInicio + $anchoIzq / 2;
        $this->Line($centroIzq - $wTitulo / 2, $this->GetY(), $centroIzq + $wTitulo / 2, $this->GetY());
        $this->Ln(2);

        $wLabel = 34;
        $wMonto = 21;
        $wGap = 12;
        $hLinea = 3;

        if (!empty($secciones)) {
            for ($i = 0; $i < count($secciones); $i += 2) {
                $a = $secciones[$i];
                $b = isset($secciones[$i + 1]) ? $secciones[$i + 1] : null;

                $linesA = array();
                if (isset($a['empleador'])) {
                    $linesA[] = array('label' => 'Empleador', 'monto' => (float)$a['empleador']);
                }
                if (isset($a['trabajador'])) {
                    $linesA[] = array('label' => 'Trabajador', 'monto' => (float)$a['trabajador']);
                }

                $linesB = array();
                if ($b !== null) {
                    if (isset($b['empleador'])) {
                        $linesB[] = array('label' => 'Empleador', 'monto' => (float)$b['empleador']);
                    }
                    if (isset($b['trabajador'])) {
                        $linesB[] = array('label' => 'Trabajador', 'monto' => (float)$b['trabajador']);
                    }
                }

                $maxSub = max(count($linesA), count($linesB));
                if ($maxSub === 0) {
                    $maxSub = 1;
                }

                // Total line (bold)
                $this->SetFont('Arial', 'B', 7);
                $this->Cell($wLabel, $hLinea, 'Total ' . $a['titulo'], 0, 0);
                $this->Cell($wMonto, $hLinea, '$ ' . $this->fmt(array_sum(array_column($linesA, 'monto'))), 0, 0, 'R');

                if ($b !== null) {
                    $this->Cell($wGap, $hLinea, '', 0, 0);
                    $this->Cell($wLabel, $hLinea, 'Total ' . $b['titulo'], 0, 0);
                    $this->Cell($wMonto, $hLinea, '$ ' . $this->fmt(array_sum(array_column($linesB, 'monto'))), 0, 1, 'R');
                } else {
                    $this->Cell($wGap + $wLabel + $wMonto, $hLinea, '', 0, 1);
                }

                // Sub-lines (normal)
                $this->SetFont('Arial', '', 7);
                for ($line = 0; $line < $maxSub; $line++) {
                    if ($line < count($linesA)) {
                        $this->Cell($wLabel, $hLinea, $linesA[$line]['label'], 0, 0);
                        $this->Cell($wMonto, $hLinea, '$ ' . $this->fmt($linesA[$line]['monto']), 0, 0, 'R');
                    } else {
                        $this->Cell($wLabel + $wMonto, $hLinea, '', 0, 0);
                    }

                    if ($b !== null) {
                        $this->Cell($wGap, $hLinea, '', 0, 0);
                        if ($line < count($linesB)) {
                            $this->Cell($wLabel, $hLinea, $linesB[$line]['label'], 0, 0);
                            $this->Cell($wMonto, $hLinea, '$ ' . $this->fmt($linesB[$line]['monto']), 0, 0, 'R');
                        } else {
                            $this->Cell($wLabel + $wMonto, $hLinea, '', 0, 0);
                        }
                    }
                    $this->Ln();
                }

                $this->Ln(3);
            }
        }

        // --- Bloque derecho: grafico de torta (30%) ---
        $alturaMin = 0;
        if (!empty($torta)) {
            $alturaMin = 52;
        }

        // Calcular altura del marco
        $altura = max($this->GetY() - $yInicio, $alturaMin);

        if (!empty($torta)) {
            $alturaCaja = $altura - 1;
            $yTortaCaja = $yInicio;

            // Borde gris tenue redondeado
            $this->SetDrawColor(210, 210, 210);
            $this->RoundedRect($xDer, $yTortaCaja, $anchoDer, $alturaCaja, 2, 'D');

            // Titulo de la torta
            $this->SetTextColor(self::COLOR_TEXT);
            $this->SetFont('Arial', 'B', 9);
            $this->SetXY($xDer, $yTortaCaja + 2);
            $this->Cell($anchoDer, 4, 'Costo total empleador', 0, 1, 'C');

            // Grafico de torta centrado en la caja
            $tamTorta = 42;
            $xTorta = $xDer + ($anchoDer - $tamTorta) / 2;
            $yTorta = $yTortaCaja + 6;
            $im = generar_grafico_torta($torta, '');
            ob_start();
            imagepng($im);
            $pngData = ob_get_clean();
            imagedestroy($im);
            $this->ImagePngMem($pngData, $xTorta, $yTorta, $tamTorta, $tamTorta);
        }

        // Marco exterior
        $this->SetDrawColor(self::COLOR_TEXT);
        $this->Rect($xInicio, $yInicio, $anchoTotal, $altura);

        // Nota pegada al borde inferior izquierdo
        $this->SetXY($xInicio, $yInicio + $altura - 3);
        $this->SetFont('Arial', 'I', 6);
        $this->Cell($anchoIzq, 3, 'Nota: Seguridad social del empleador incluye SIPA, Fondo Nacional de Empleo y Asignaciones Familiares', 0, 0);

        // Avanzar Y por debajo del marco
        $this->SetY($yInicio + $altura + 3);
    }

    // -----------------------------------------------------------------
    //  ImagePngMem: embeber PNG desde memoria (sin archivo intermedio)
    // -----------------------------------------------------------------

    function ImagePngMem($data, $x = null, $y = null, $w = 0, $h = 0) {
        $f = fopen('php://temp', 'rb+');
        fwrite($f, $data);
        rewind($f);
        $info = $this->_parsepngstream($f, 'memimage');
        fclose($f);

        $key = 'mem_' . count($this->images);
        $info['i'] = count($this->images) + 1;
        $this->images[$key] = $info;

        if ($w == 0 && $h == 0) {
            $w = -96; $h = -96;
        }
        if ($w < 0) $w = -$info['w'] * 72 / $w / $this->k;
        if ($h < 0) $h = -$info['h'] * 72 / $h / $this->k;
        if ($w == 0) $w = $h * $info['w'] / $info['h'];
        if ($h == 0) $h = $w * $info['h'] / $info['w'];

        if ($y === null) {
            if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
                $x2 = $this->x;
                $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
                $this->x = $x2;
            }
            $y = $this->y;
            $this->y += $h;
        }

        if ($x === null) $x = $this->x;
        $this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',
            $w * $this->k, $h * $this->k,
            $x * $this->k, ($this->h - ($y + $h)) * $this->k,
            $info['i']));
    }

    private function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k,
            $x3 * $this->k, ($h - $y3) * $this->k));
    }

    function RoundedRect($x, $y, $w, $h, $r, $style = 'D') {
        if ($style == 'F') {
            $op = 'f';
        } elseif ($style == 'FD' || $style == 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }
        $k = $this->k;
        $hp = $this->h;
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        if ($r)
            $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        if ($r)
            $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        if ($r)
            $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        if ($r)
            $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }
}
