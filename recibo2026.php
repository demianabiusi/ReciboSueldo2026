<?php

/**
 * Ejemplo de uso de ReciboSueldoGenerator.
 * Ejecutar: php recibo2026.php
 */

ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);


require_once __DIR__ . '/ReciboSueldoGenerator.php';

$gen = new ReciboSueldoGenerator('P', 'mm', 'A4');

// --- Recibo 1 ---
$gen->agregarRecibo(array(
    'empresa' => array(
        'razon_social' => 'EMPRESA EJEMPLO S.A.',
        'provincia'    => 'Provincia de Buenos Aires',
        'cuit'         => '30-00000000-0',
    ),
    'empleado' => array(
        'quincena'      => 1,
        'mes'           => 6,
        'anio'          => 2026,
        'nombre'        => 'GARCIA, JUAN CARLOS',
        'legajo'        => '12345',
        'sueldo_bruto'  => 500000,
        'antiguedad'    => '5 años',
        'fecha_ingreso' => '01/01/2020',
        'categoria'     => 'Administrativo',
        'cuil'          => '20-12345678-9',
        'lugar_pago'    => 'Sede Central',
        'fp_aportes'    => 'Mensual',
    ),
    'contribuciones_empleador' => array(
        array('concepto' => 'ART',                        'unidad' => '3%',  'base' => '$ 500.000',  'monto' => 15000),
        array('concepto' => 'Contribucion Jubilacion',    'unidad' => '18%', 'base' => '$ 500.000',  'monto' => 90000),
        array('concepto' => 'Contribucion OO.SS.',        'unidad' => '6%',  'base' => '$ 500.000',  'monto' => 30000),
        array('concepto' => 'Seguro de vida fijo',        'unidad' => '',    'base' => '',           'monto' => 2000),
        array('concepto' => 'Costo derivado del CCT:',    'unidad' => '',    'base' => '',           'monto' => 0),
        array('concepto' => 'Concepto 1',                 'unidad' => '1%',  'base' => '',           'monto' => 5000),
        array('concepto' => 'Concepto 2',                 'unidad' => '',    'base' => '',           'monto' => 3000),
    ),
    'haberes' => array(
        array('concepto' => 'Sueldo Basico', 'unidad' => '30', 'base' => '$ 500.000', 'monto' => 500000),
        array('concepto' => 'Antiguedad',    'unidad' => '5%', 'base' => '$ 500.000', 'monto' => 25000),
        array('concepto' => 'Presentismo',   'unidad' => '',   'base' => '',          'monto' => 20000),
    ),
    'descuentos' => array(
        array('concepto' => 'Aporte Jubilacion', 'unidad' => '11%', 'base' => '$ 500.000', 'monto' => 55000),
        array('concepto' => 'Ley 19.032',        'unidad' => '3%',  'base' => '$ 500.000', 'monto' => 15000),
        array('concepto' => 'Obra Social',       'unidad' => '3%',  'base' => '$ 500.000', 'monto' => 15000),
        array('concepto' => 'Otros conceptos',   'unidad' => '1%',  'base' => '',          'monto' => 5000),
    ),
    'composicion' => array(
        'remunerativo'    => 450000,
        'no_remunerativo' => 75000,
        'descuentos'      => 90000,
    ),
    'torta' => array(
        array('label' => 'Sueldo Neto',       'porcentaje' => 55, 'color' => array(70, 130, 180)),
        array('label' => 'Seguridad Social',  'porcentaje' => 18, 'color' => array(180, 80, 80)),
        array('label' => 'Costo Sindical',    'porcentaje' => 7,  'color' => array(140, 180, 80)),
        array('label' => 'Obra Social',       'porcentaje' => 10, 'color' => array(100, 190, 140)),
        array('label' => 'PAMI',              'porcentaje' => 5,  'color' => array(90, 180, 210)),
        array('label' => 'ART',               'porcentaje' => 5,  'color' => array(230, 140, 70)),
    ),
    'detalle_costos' => array(
        array('titulo' => 'Costo Sindical',   'empleador' => 15000, 'trabajador' => 7500),
        array('titulo' => 'INSSJP',           'empleador' => 10000, 'trabajador' => 5000),
        array('titulo' => 'Seguridad Social', 'empleador' => 90000, 'trabajador' => 55000),
        array('titulo' => 'ART',              'empleador' => 15000),
        array('titulo' => 'Obra Social',      'empleador' => 30000, 'trabajador' => 15000),
        array('titulo' => 'SCVO',             'empleador' => 5000),
    ),
));

// --- Recibo 2 (opcional, comenta para probar multi-pagina) ---

$gen->agregarRecibo(array(
    'empresa' => array(
        'razon_social' => 'EMPRESA EJEMPLO S.A.',
        'provincia'    => 'CABA',
        'cuit'         => '30-00000000-0',
    ),
    'empleado' => array(
        'quincena'      => 2,
        'mes'           => 6,
        'anio'          => 2026,
        'nombre'        => 'LOPEZ, MARIA FERNANDA',
        'legajo'        => '67890',
        'sueldo_bruto'  => 350000,
        'antiguedad'    => '2 años',
        'fecha_ingreso' => '15/03/2024',
        'categoria'     => 'Ventas',
        'cuil'          => '27-87654321-0',
        'lugar_pago'    => 'Sucursal Norte',
        'fp_aportes'    => 'Mensual',
    ),
    'contribuciones_empleador' => array(
        array('concepto' => 'ART',                     'unidad' => '3%',  'base' => '$ 350.000', 'monto' => 10500),
        array('concepto' => 'Contribucion Jubilacion', 'unidad' => '18%', 'base' => '$ 350.000', 'monto' => 63000),
        array('concepto' => 'Contribucion OO.SS.',     'unidad' => '6%',  'base' => '$ 350.000', 'monto' => 21000),
        array('concepto' => 'Seguro de vida fijo',     'unidad' => '',    'base' => '',          'monto' => 2000),
    ),
    'haberes' => array(
        array('concepto' => 'Sueldo Basico',      'unidad' => '30', 'base' => '$ 350.000', 'monto' => 350000),
        array('concepto' => 'Comision por ventas', 'unidad' => '',   'base' => '',          'monto' => 45000),
    ),
    'descuentos' => array(
        array('concepto' => 'Aporte Jubilacion', 'unidad' => '11%', 'base' => '$ 350.000', 'monto' => 38500),
        array('concepto' => 'Ley 19.032',        'unidad' => '3%',  'base' => '$ 350.000', 'monto' => 10500),
        array('concepto' => 'Obra Social',       'unidad' => '3%',  'base' => '$ 350.000', 'monto' => 10500),
    ),
    'composicion' => array(
        'remunerativo'    => 350000,
        'no_remunerativo' => 45000,
        'descuentos'      => 59500,
    ),
    'torta' => array(),
    'detalle_costos' => array(
        array('titulo' => 'Costo Sindical',   'empleador' => 8000,  'trabajador' => 4000),
        array('titulo' => 'INSSJP',           'empleador' => 7000,  'trabajador' => 3500),
        array('titulo' => 'Seguridad Social', 'empleador' => 63000, 'trabajador' => 38500),
        array('titulo' => 'ART',              'empleador' => 10500),
        array('titulo' => 'Obra Social',      'empleador' => 21000, 'trabajador' => 10500),
        array('titulo' => 'SCVO',             'empleador' => 3500),
    ),
));


// Generar y enviar al navegador
$gen->generar('I');
