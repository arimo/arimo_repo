<?php

/**
 * このclassは未完成です！！実装を手伝ってくれる有志求む！！
 * b2c_sc3@cybird.co.jp subject「CYOPコミッター募集」宛に連絡下さい。
 */

/**
 * データアクセスを一挙に管理するオブジェクト
 * @author morita
 */
class DAO extends Singleton {

	/**
	 * コンストラクタ
	 */
	protected function __construct(){
		// Singletonに移譲する
		parent::__construct(__CLASS__,func_get_args());
	}

	/**
	 * 割り当て
	 * @param string $argKey
	 * @param mixid $argVal
	 */
	public function set($argKey,$argVal){
	}

	/**
	 * 取得
	 * @param string $argKey
	 */
	public function get($argKey){
	}
}

?>