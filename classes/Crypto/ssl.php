<?php 
namespace Crypto;

class ssl {

	private static $stmt;
	protected $hash;

	static function hash($algo) {
		if (!self::$stmt) self::$stmt = new self();
		self::$stmt->hash = $algo;
		return self::$stmt;
	}
	static function encrypt($string, $crypt = 6621) {
		if (!self::$stmt) self::$stmt = new self();
		$algo = self::$stmt->hash ? "en_".self::$stmt->hash : 'encrypt';
		self::$stmt->hash = null; // reset hash
		return self::$stmt->process($algo,$string,$crypt);
	}

	static function decrypt($string, $crypt = 6621) {
		if (!self::$stmt) self::$stmt = new self();
		$algo = self::$stmt->hash ? "de_".self::$stmt->hash : 'decrypt';
		self::$stmt->hash = null; // reset hash
		return self::$stmt->process($algo,$string,$crypt);
	}

	private function process($mode, $string, $salt = 6621) {
		$algoList = [
			'haval3' => ['haval256,3','haval128,3'],
			'haval4' => ['haval256,4','haval128,4'],
			'haval5' => ['haval256,5','haval128,5'],
			'ripemd' => ['ripemd256','ripemd128'],
			'snefru' => ['snefru','tiger128,3'],
			'gost'   => ['gost','tiger128,4']
		];

		$algo = ['sha256','md5']; // default AES-256
		if (strpos($mode, '_') !== false) {
			[$prefix, $shortAlgo] = explode('_', $mode, 2);
			$algo = isset($algoList[$shortAlgo])? $algoList[$shortAlgo] : $algo;
			$mode = ($prefix === 'en') ? 'encrypt' : (($prefix === 'de') ? 'decrypt' : $mode);
		}

		$key = hash($algo[0], $salt, true); // 32 byte
		$iv  = hash($algo[1], $salt, true); // 16 byte

		if ($mode === 'encrypt') {
			$encrypted = openssl_encrypt($string, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
			return base64_encode($encrypted);
		} elseif ($mode === 'decrypt') {
			$decoded = base64_decode($string, true);
			return openssl_decrypt($decoded, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
		}

		return null;
	}
}
