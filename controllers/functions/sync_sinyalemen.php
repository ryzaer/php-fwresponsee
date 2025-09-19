<?php function sync_sinyalemen(...$args){
	// sidik jari atas
	$finger1 = <<<SQL
CONCAT_WS(
    ' ',
    REPLACE(TRIM(db1.fp_1A),' ',''),
    REPLACE(TRIM(db1.fp_2A),' ',''),
    REPLACE(TRIM(db1.fp_3A),' ',''),
    REPLACE(TRIM(db1.fp_4A),' ',''),
    REPLACE(TRIM(db1.fp_5A),' ',''),
    REPLACE(TRIM(db1.fp_6A),' ','')
)
SQL;
	// sidik jari bawah
    $finger2 = <<<SQL
CONCAT_WS(
    ' ',
    REPLACE(TRIM(db1.fp_1b),' ',''),
    REPLACE(TRIM(db1.fp_2b),' ',''),
    REPLACE(TRIM(db1.fp_3b),' ',''),
    REPLACE(TRIM(db1.fp_4b),' ',''),
    REPLACE(TRIM(db1.fp_5b),' ','')
)
SQL;
	// SINYALEMEN
	$sinyalemen = <<<SQL
CONCAT(
	'[', db1.tinggi_badan,',', db1.berat_badan, ',', db1.id_warna_kulit, 
	',', db1.id_bentuk_tubuh, ',', db1.id_bentuk_kepala, ',', db1.id_warna_rambut,
	',', db1.id_jenis_rambut, ',', db1.id_bentuk_muka, ',', db1.id_dahi, 
	',', db1.id_warna_mata, ',', db1.id_kelainan_mata, ',', db1.id_hidung,
	',', db1.id_bibir, ',', db1.id_gigi, ',', db1.id_dagu, ',', db1.id_telinga,
	',', IFNULL(CONCAT('"', TRIM(db1.tattoo), '"'), ''), 
	',', IFNULL(CONCAT('"', TRIM(db1.cacat), '"'), ''),
	']'
)
SQL;
	// result akhir
	$inafis = <<<SQL
CONCAT('[',
(
	CASE
    WHEN  $sinyalemen = '[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,"",""]'
    THEN '[]'
    ELSE $sinyalemen
	END
)
,',"',
(
    CASE
    WHEN $finger1 = '     '
    THEN ''
    ELSE $finger1
    END
),'","',
(
    CASE
    WHEN $finger2 = '    '
    THEN ''
    ELSE $finger2
    END
),'"]') 
SQL;
return <<<SQL
(
	CASE
	WHEN $inafis = '[[],"",""]'
	THEN ''
	ELSE $inafis
	END
)
SQL;
}