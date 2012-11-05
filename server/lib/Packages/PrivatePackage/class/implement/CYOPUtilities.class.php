<?php

/**
 * 関数群
 * @author saimushi
 */
class CYOPUtilities {

	/**
	 * logging(集計用ロギングテーブルにログをインサートする)
	 * @param int		$argUser	ユーザーID
	 * @param string	$argAction	preview|click
	 * @param string	$argNote
	 * @param string	$argPlase	クラス名
	 */
	public static function logging($argUser=NULL, $argAction=NULL, $argNote=NULL, $argPlace=NULL){
		// Logging テーブルにログをインサート
		$sql = 'INSERT INTO logging VALUES(log_id_seq.NEXTVAL, :place, :who, :action, :note, systimestamp)';
		$binds = array();
		$binds['place'] = $argPlace;
		$binds['who'] = $argUser;
		$binds['action'] = $argAction;
		$binds['note'] = $argNote;

		$response = DBO::execute($sql,$binds);

		if(FALSE === $response){
			throw new Exception(__CLASS__.PATH_SEPARATOR.__METHOD__.PATH_SEPARATOR.__LINE__.PATH_SEPARATOR.DBO::getLastErrorMessage());
			return FALSE;
		}
	}


	/**
	 * AES暗号形式でデータを暗号化し、base64encodeする
	 * @param string エンコードする文字列
	 * @param string 暗号キー
	 * @param string IV
	 * @return string base64encodeされｔ暗号データ
	 */
	public static function do64Encrypt($argValue, $argKey = NULL, $argIV = NULL) {
		return base64_encode(self::encryptAES($argValue, $argKey, $argIV));
	}

	/**
	 * base64decodeしてかっらAES暗号形式のデータを複合化する
	 * @param string デコードする文字列
	 * @param string 暗号キー
	 * @param string IV
	 * @return string 複合データ
	 */
	public static function do64Decrypt($argValue, $argKey = NULL, $argIV = NULL) {
		return self::decryptAES(base64_decode($argValue), $argKey, $argIV);
	}

	/**
	 * AES暗号形式でデータを暗号化する
	 * @param 	$argValue 	エンコードする値
	 * @param 	$argKey 	暗号キー
	 * @param 	$argIv 		IV
	 * @return 	$encrypt 	暗号化データ
	 */
	public static function encryptAES($argValue, $argKey = NULL, $argIV = NULL) {

		// パラメータセット
		$arguments = array();
		if(NULL === $argKey){
			$argKey = CYOPSecurityConfigure::SECURITY_KEY32;
		}
		$arguments['key'] = $argKey;

		if(NULL === $argIV){
			$argIV = CYOPSecurityConfigure::SECURITY_IV32;
		}
		$arguments['iv'] = base64_decode($argIV);
		$arguments['algorithm'] = CYOPSecurityConfigure::SECURITY_ARTM;
		$arguments['mode'] = CYOPSecurityConfigure::SECURITY_BLOCK_MODE;
		$arguments['prefix'] = CYOPSecurityConfigure::CIPHERED_BLOCK_PREFIX;
		$arguments['suffix'] = CYOPSecurityConfigure::CIPHERED_BLOCK_SUFFIX;
		$arguments['value'] = $argValue;

		// データを暗号化する
		$encrypt = Cipher :: encrypt($arguments);

		// エラー処理
		if (false === $encrypt || NULL === $encrypt) {
			return false;
		}
		return $encrypt;
	}

	/**
	 * AES暗号形式で暗号化されたデータを複号化する
	 * @param 	$argValue 	デコードする値
	 * @param 	$argKey 	暗号キー
	 * @param 	$argIv 		IV
	 * @return 	$encrypt 	複号化データ
	 */
	public static function decryptAES($argValue, $argKey = NULL, $argIV = NULL) {
		// パラメータセット
		// パラメータセット
		$arguments = array();
		if(NULL === $argKey){
			$argKey = CYOPSecurityConfigure::SECURITY_KEY32;
		}
		$arguments['key'] = $argKey;

		if(NULL === $argIV){
			$argIV = CYOPSecurityConfigure::SECURITY_IV32;
		}
		$arguments['iv'] = base64_decode($argIV);
		$arguments['algorithm'] = CYOPSecurityConfigure::SECURITY_ARTM;
		$arguments['mode'] = CYOPSecurityConfigure::SECURITY_BLOCK_MODE;
		$arguments['prefix'] = CYOPSecurityConfigure::CIPHERED_BLOCK_PREFIX;
		$arguments['suffix'] = CYOPSecurityConfigure::CIPHERED_BLOCK_SUFFIX;
		$arguments['value'] = $argValue;

		// データを暗号化する
		$decrypt = Cipher :: decrypt($arguments);

		// エラー処理
		if (false === $decrypt || NULL === $decrypt) {
			return false;
		}
		return $decrypt;
	}

	/**
	 * unserializeしてやる
	 */
	public static function getSession($argKey){
		return @Memcached::get($argKey);
	}

	/**
	 * serializeしてやる
	 */
	public static function setSession($argKey,$arguments,$argExpire=0){
		// CYOPのセッションIDストアに通知しておく
		setSessionID($argKey);
		return Memcached::set($argKey,$arguments,FALSE,$argExpire);
	}

	/**
	 * CYOPセッションを消す(完全削除)
	 * XXX 存在した場合だけ処理される事に注意
	 */
	public static function removeSession($argKey){
		return @Memcached::delete($argKey);
	}

	/**
	 * メール送信
	 */
	public static function sendMail($argTo, $argSubject, $argBody, $argFrom=NOREPLY_ADDRESS){
		// SendMagic(sm-v252.sf.cybird.ne.jp)
		$mailParams = array('host' => CYOPMailConfigure::HOST, 'port' => CYOPMailConfigure::PORT, 'auth' => false);

		// メーラインスタンスを作成する
		$mail =& Mail::factory('smtp', $mailParams);

		// 送信
		$mailSend = FALSE;

		// SendMagic 配信ID設定
		// XXX
		$sendMagicID = CYOPMailConfigure::SEND_MAGIC_ID_PREFIX.'-'.date('Ymd').'_s0001';

		// SendMagic Envelope-From指定
		$parseEmail = explode('@', $argTo);
		$envelope  = 'z-'.$sendMagicID.'-0-0-';
		$envelope .= sprintf('%03d', strlen($parseEmail[0]));
		$envelope .= $parseEmail[0].$parseEmail[1];
		$envelope .= '@'.CYOPMailConfigure::ENVELOPE_FROM_DOMAIN;

		// 送信ヘッダ
		$headers = array();
		$headers['From'] = $argFrom;
		$headers['To'] = $argTo;
		$headers['Subject'] = $argSubject;
		$headers['Date'] = Utilities::date('D, d M Y H:i:s O');
		$headers['X-SM-Envelope-From'] = $envelope;
		$headers['X-SM-ID'] = $sendMagicID. ' direct';

		$mailSend = $mail->send($argTo, $headers, $argBody);
		return $mailSend;
	}
}

?>