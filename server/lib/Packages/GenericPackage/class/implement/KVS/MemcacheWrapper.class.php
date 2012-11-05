<?php

/**
 * Memcache操作のラッパークラス(Singletonパターン実装)
 * @author saimushi
 */
class MemcacheWrapper {

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
			if (true === CYOPConfigure::MEMCACHED_PFLAG) {
				$MemcacheInstance-> pconnect(CYOPConfigure::MEMCACHED_HOST,CYOPConfigure::MEMCACHED_PORT);
			} else {
				$MemcacheInstance-> connect(CYOPConfigure::MEMCACHED_HOST,CYOPConfigure::MEMCACHED_PORT);
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
		logging(array('mem'=>__METHOD__, 'key'=>$argKey, 'response'=>$responseBool), 'memcache');
		return $response;
	}

	/**
	 */
	public static function set($argKey, $argVal, $argCompressedFlag = FALSE, $argExpire = 0){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->set($argKey, $argVal, $argCompressedFlag, $argExpire = 0);
		// logging
		logging(array('mem'=>__METHOD__, 'key'=>$argKey, 'val'=>$argVal, 'compressed'=>$argCompressedFlag, 'expire'=>$argExpire, 'response'=>$response), 'memcache');
		return $response;
	}

	/**
	 */
	public static function increment($argKey,$argCnt=1){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->increment($argKey,$argCnt);
		// logging
		logging(array('mem'=>__METHOD__, 'key'=>$argKey, 'cnt'=>$argCnt, 'response'=>$response), 'memcache');
		return $response;
	}

	/**
	 */
	public static function decrement($argKey,$argCnt=1){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->decrement($argKey,$argCnt);
		// logging
		logging(array('mem'=>__METHOD__, 'key'=>$argKey, 'cnt'=>$argCnt, 'response'=>$response), 'memcache');
		return $response;
	}

	/**
	 */
	public static function add($argKey, $argVal, $argCompressedFlag = FALSE, $argExpire = 0){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->add($argKey, $argVal, $argCompressedFlag, $argExpire);
		// logging
		logging(array('mem'=>__METHOD__, 'key'=>$argKey, 'val'=>$argVal, 'compressed'=>$argCompressedFlag, 'expire'=>$argExpire, 'response'=>$response), 'memcache');
		return $response;
	}

	/**
	 */
	public static function delete($argKey, $argExpire = 0){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->delete($argKey, $argExpire = 0);
		// logging
		logging(array('mem'=>__METHOD__, 'key'=>$argKey, 'expire'=>$argExpire, 'response'=>$response), 'memcache');
		return $response;
	}

	/**
	 */
	public static function replace($argKey, $argVal, $argCompressedFlag = FALSE, $argExpire = 0){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->replace($argKey, $argVal, $argCompressedFlag, $argExpire = 0);
		// logging
		logging(array('mem'=>__METHOD__, 'key'=>$argKey, 'val'=>$argVal, 'compressed'=>$argCompressedFlag, 'expire'=>$argExpire, 'response'=>$response), 'memcache');
		return $response;
	}

	/**
	 */
	public static function flush(){
		self::__initMemcache();
		$response = self::$__MemcacheInstance->flush();
		// logging
		logging(array('mem'=>__METHOD__, 'response'=>$response), 'memcache');
		return $response;
	}
}

?>