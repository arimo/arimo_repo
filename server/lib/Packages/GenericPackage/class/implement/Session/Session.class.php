<?php

/**
 * Sessionを練り練りする
 * @author saimushi
 */
class Session {

	private static $_sessionData = NULL;

	private static function _parseSessionKey($argSessionKey = NULL){
		$sessionKey = $argSessionKey;
		if(NULL === $sessionKey){
			if(isset($_COOKIE[sha1($_SERVER['HTTP_USER_AGENT'])])){
				$sessionKey = $_COOKIE[sha1($_SERVER['HTTP_USER_AGENT'])];
			}else{
				return NULL;
			}
		}

		$parseKeys = explode(PATH_SEPARATOR,CYOPUtilities::do64Decrypt($sessionKey));
		$parseKeys[] = $sessionKey;
		return $parseKeys;
	}

	/**
	 */
	public static function get($argSessionKey = NULL){

		if(is_array(self::$_sessionData)){
			return self::$_sessionData;
		}

		$tmp = self::_parseSessionKey($argSessionKey);
		$sessionKey = $tmp[2];

		if(!(isset($tmp[0]) && 0 < strlen($tmp[0]))){
			// XXX エラーハンドリング
			throw new Exception(__CLASS__.PATH_SEPARATOR.__METHOD__.PATH_SEPARATOR.__LINE__);
		}
		$sessionHash = $tmp[0];
		if(!(isset($tmp[1]) && 0 < strlen($tmp[1]))){
			// XXX エラーハンドリング
			throw new Exception(__CLASS__.PATH_SEPARATOR.__METHOD__.PATH_SEPARATOR.__LINE__);
		}
		$sessionTime = $tmp[1];
		if(TRUE === WishscopeConfigure::SESSION_MODE_BD_ENABLES) {
			$query = 'SELECT session_data FROM wst_session WHERE session_hash=? AND session_time=? order by create_date desc limit 1';
			$binds = array($sessionHash, $sessionTime);
			$response = DBO::execute($query, $binds);
			if(!is_object($response)){
				// XXX エラーハンドリング
				throw new Exception(__CLASS__.PATH_SEPARATOR.__METHOD__.PATH_SEPARATOR.__LINE__.PATH_SEPARATOR.Utilities::getBacktraceExceptionLine().PATH_SEPARATOR.DBO::getLastErrorMessage());
			}elseif(0 < $response->RecordCount()){
				$tmp = $response->GetArray();
				$data = unserialize($tmp[0]['session_data']);
			}else{
				return NULL;
			}
		}else{
			$data = Memcached::get($sessionKey);
		}
		if(!(1 < count($data) && isset($data[$sessionHash]) && $data[$sessionHash] == $sessionTime)){
			// XXX エラーハンドリング
			throw new Exception(__CLASS__.PATH_SEPARATOR.__METHOD__.PATH_SEPARATOR.__LINE__);
		}
		return $data;
	}

	/**
	 */
	public static function set($argments, $argCompressedFlag = FALSE, $argExpire = 0){

		if(!is_array($argments)){
			// XXX 一次元以上の配列意外は許可しない！
			throw new Exception(__CLASS__.PATH_SEPARATOR.__METHOD__.PATH_SEPARATOR.__LINE__);
		}

		// 古いセッションキーが含まれていたら消す
		$tmp = self::_parseSessionKey();
		if(isset($tmp[0]) && 0 < strlen($tmp[0]) && isset($argments[$tmp[0]])){
			unset($argments[$tmp[0]]);
		}

		$sessionTime = (string)microtime(TRUE);
		$sessionHash = SHA1(getmypid().$sessionTime);
		$sessionKey = CYOPUtilities::do64Encrypt($sessionHash.PATH_SEPARATOR.$sessionTime).PATH_SEPARATOR.$sessionTime;

		$newExpired = (int)$argExpire;
		if(0 === $newExpired){
			// XXX キメウチですが・・・24時間をクッキーの有効期限とする！
			$newExpired = Utilities::date('U') + 86400;
		}

		$argments[$sessionHash] = $sessionTime;

		if(TRUE === WishscopeConfigure::SESSION_MODE_BD_ENABLES) {
			$query = 'INSERT INTO wst_session (session_hash, session_time, session_data) VALUES (?, ?, ?)';
			$binds = array($sessionHash, $sessionTime, serialize($argments));
			$response = DBO::execute($query, $binds);
			logging(array('mode'=>'DB::'.__METHOD__, 'key'=>$argKey, 'val'=>$argments, 'compressed'=>$argCompressedFlag, 'expire'=>$newExpired, 'response'=>$response), 'db');
			if(!is_object($response)){
				// XXX エラーハンドリング
				throw new Exception(__CLASS__.PATH_SEPARATOR.__METHOD__.PATH_SEPARATOR.__LINE__.PATH_SEPARATOR.Utilities::getBacktraceExceptionLine().PATH_SEPARATOR.PATH_SEPARATOR.DBO::getLastErrorMessage());
			}
		}else{
			logging(array('mode'=>'MEM::'.__METHOD__, 'key'=>$argKey, 'val'=>$argments, 'compressed'=>$argCompressedFlag, 'expire'=>$newExpired, 'response'=>$response), 'memcache');
			Memcached::set($sessionKey, $argments, $argCompressedFlag, $argExpire);
		}

		// データの登録に成功してからクッキーを書き換える
		setcookie(sha1($_SERVER['HTTP_USER_AGENT']), $sessionKey, $newExpired, '/', $_SERVER['SERVER_NAME']);

		self::$_sessionData = $argments;

		return TRUE;
	}
}

?>