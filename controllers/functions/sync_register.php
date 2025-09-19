<?php function sync_register($id, $user = null){
	$self = \Router::instance();
	$user = $user ? " AND `id_user` = :user" : '';
	$parm = $user ? ['id' => $id, 'user' => $user] : ['id' => $id];
	if(!$self || empty($id)) return [];
	$pdo = $self->dbConnect();
	$rslt = [];
	foreach (range((int)date('Y'),2016, -1) as $y) {
		$stmt = $pdo->prepare("SELECT id_reg as id,tgl_terbit as tanggal, (SELECT keperluan FROM tbskck_keperluan WHERE id=id_kep LIMIT 1) as keperluan FROM `tbskck_reg_$y` WHERE `id_biodata` = :id$user ORDER BY `id_reg` DESC");
		$stmt->execute($parm);
		foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $k => $v){
			$rslt[] = $v;
		}
	}
	return $rslt;
}