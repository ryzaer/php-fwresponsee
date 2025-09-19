<?php
namespace Crypto;

class sodium {
	private static $stmt;
	private $nonce,$key,$base,$uniq,$salt;
	function __construct($salt=null,$base=false) {		
		$this->base = $base;
		$this->salt = $salt;
		$this->poly = false;
	}

	function create_keys() {
		
		$hash = null;
		if(is_array($this->salt) && isset($this->salt['key']) && isset($this->salt['nonce'])){
			$this->key   = $this->salt['key'] ? $this->salt['key'] : false;
			$this->nonce = $this->salt['nonce'] ? $this->salt['nonce'] : false;
			$this->salt  = null;
		}else{
			$this->salt = $this->salt ? $this->salt : uniqid();
			// hashing algo haval195,5 byte data
			$hash = base64_encode(hash('haval192,5',$this->salt));			
			$this->key   = substr($hash,0,32);
			$this->nonce = substr($hash,32,24);	
		}		
		$this->rand_uid($hash);
	}
	function rand_uid($hash=null) {
		$rand = $this->salt ? $this->salt : uniqid();
		$hash = $hash ? $hash : base64_encode(hash('haval192,5',$rand));
		$uid  = [str_split(hash('adler32',substr($hash,56)),2),str_split(hash('crc32b',substr($hash,56)),2)];			
		$this->uniq = "{$uid[1][3]}{$uid[0][0]}-{$uid[1][2]}{$uid[0][1]}{$uid[0][2]}-{$uid[0][3]}{$uid[1][0]}{$uid[1][3]}{$uid[0][1]}";
	}
	static function encrypt($data,$salt=null,$base=false) {
		if(self::$stmt && self::$stmt->poly === true)
			self::$stmt->close();
		if(!self::$stmt){
			self::$stmt = new self($salt,$base);
			self::$stmt->create_keys();
		}
		$enc = sodium_crypto_secretbox($data,self::$stmt->nonce,self::$stmt->key);
		return self::$stmt->base ? base64_encode($enc) : $enc;	
	}
	static function decrypt($data,$salt=null,$base=false) {
		if(self::$stmt && self::$stmt->poly === true)
			self::$stmt->close();
		if(!self::$stmt || self::$stmt->poly === true){
			self::$stmt = new self($salt,$base);
			self::$stmt->create_keys();
		}		
		$data = self::$stmt->base ? base64_decode($data) : $data;
		return sodium_crypto_secretbox_open($data,self::$stmt->nonce,self::$stmt->key);
	}
	static function poly1305_encrypt($data,$salt_key=null,$base=false) {	
		if(!self::$stmt){
			self::$stmt = new self($salt_key,$base);
			self::$stmt->rand_uid();
		}	
		self::$stmt->poly = true;		
		// Generate a binary secret key. This value must be stored securely.
		$key = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
		// Generate a binary nonce for EACH MESSAGE. This can be public, and must be provided to decrypt the message.
		$nonce = \random_bytes(\SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
		// Text to encrypt.
		$encrypted_data = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($data, self::$stmt->salt, $nonce, $key);
		// concat all into file
		$encrypted_data = $key.$nonce.$encrypted_data;
		return  self::$stmt->base ? base64_encode($encrypted_data) : $encrypted_data ;
		// return  $encrypted_data ;
	}
	static function poly1305_decrypt($data,$salt_key=null,$base=false){
		if(!self::$stmt){
			self::$stmt = new self($salt_key,$base);
			self::$stmt->rand_uid();
		}
		self::$stmt->poly = true;
		$salt_key = is_string($salt_key) ? $salt_key : null;
		$data = self::$stmt->base ? base64_decode($data) : $data;
		$getkey   = substr($data,0,32);
		$getnonce = substr($data,32,24);
		$getdata  = substr($data,56);
		return sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($getdata, self::$stmt->salt, $getnonce, $getkey);		
	}
	static function encode($get=true){
		$output = [];
		if(self::$stmt){
			if(is_bool($get) && $get === false){
				$output['algo'] = self::$stmt->poly ? "poly1305" : "haval192,5";
				$output['salt'] = self::$stmt->salt;
				$output['uid'] = self::$stmt->uniq;
			}
			$output['key'] =  self::$stmt->poly ? null : self::$stmt->key;
			$output['nonce'] = self::$stmt->poly ? null : self::$stmt->nonce;
		}
		return isset($output[$get]) ? $output[$get] : $output;
	}
	static function close(){
		if(self::$stmt)
			self::$stmt = null;
		return null;
	}

}