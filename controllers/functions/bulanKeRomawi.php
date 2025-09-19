<?php function bulanKeRomawi($bulan) {
    $bulan = is_numeric($bulan) ? (int) $bulan-1 : -1;
    $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 
            'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    return $romawi[$bulan] ?? '';
}