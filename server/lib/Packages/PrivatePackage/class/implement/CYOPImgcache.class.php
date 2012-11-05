<?php

/**
 * CYOP専用ImageMemcacheクラス(Singletonパターン実装)
 * @author saimushi
 */
class CYOPImgcache {

	/**
	 * Memcacheインスタンス保持用
	 * @var instance
	 */
	private static $__MemcacheInstance = NULL;

	/**
	 */
	private static function __initMemcache(){
		if(NULL === self::$__MemcacheInstance) {
			// Memcacheクラスをインスタンス化
			$MemcacheInstance = new Memcache();
			if (true === CYOPConfigure::IMG_MEMCACHED_PFLAG) {
				$MemcacheInstance-> pconnect(CYOPConfigure::IMG_MEMCACHED_HOST,CYOPConfigure::IMG_MEMCACHED_PORT);
			} else {
				$MemcacheInstance-> connect(CYOPConfigure::IMG_MEMCACHED_HOST,CYOPConfigure::IMG_MEMCACHED_PORT);
			}
			if(false === $MemcacheInstance){
				// Memcacheインスタンス初期化エラー
				throw new Exception(__CLASS__.':'.__METHOD__.':'.__LILE__);
			}
			self::$__MemcacheInstance = $MemcacheInstance;
		}
	}

	/**
	 */
	public static function get($argKey){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->get($argKey);
		$responseBool = FALSE;
		if(FALSE !== $response){
			$responseBool = TRUE;
		}
		// logging
		logging(array('imgmem'=>__METHOD__, 'key'=>$argKey, 'response'=>$responseBool), 'imgcache');
		return $response;
	}

	/**
	 */
	public static function set($argKey, $argVal, $argCompressedFlag = FALSE, $argExpire = 0){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->set($argKey, $argVal, $argCompressedFlag, $argExpire = 0);
		// logging
		logging(array('imgmem'=>__METHOD__, 'key'=>$argKey, 'vallen'=>strlen($argVal), 'compressed'=>$argCompressedFlag, 'expire'=>$argExpire, 'response'=>$response), 'imgcache');
		return $response;
	}

	/**
	 */
	public static function add($argKey, $argVal, $argCompressedFlag = FALSE, $argExpire = 0){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->add($argKey, $argVal, $argCompressedFlag, $argExpire);
		// logging
		logging(array('imgmem'=>__METHOD__, 'key'=>$argKey, 'vallen'=>strlen($argVal), 'compressed'=>$argCompressedFlag, 'expire'=>$argExpire, 'response'=>$response), 'imgcache');
		return $response;
	}

	/**
	 */
	public static function delete($argKey, $argExpire = 0){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->delete($argKey, $argExpire = 0);
		// logging
		logging(array('imgmem'=>__METHOD__, 'key'=>$argKey, 'expire'=>$argExpire, 'response'=>$response), 'imgcache');
		return $response;
	}

	/**
	 */
	public static function replace($argKey, $argVal, $argCompressedFlag = FALSE, $argExpire = 0){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->replace($argKey, $argVal, $argCompressedFlag, $argExpire = 0);
		// logging
		logging(array('imgmem'=>__METHOD__, 'key'=>$argKey, 'vallen'=>strlen($argVal), 'compressed'=>$argCompressedFlag, 'expire'=>$argExpire, 'response'=>$response), 'imgcache');
		return $response;
	}

	/**
	 */
	public static function flush(){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->flush();
		// logging
		logging(array('imgmem'=>__METHOD__, 'response'=>$response), 'imgcache');
		return $response;
	}
}

?>