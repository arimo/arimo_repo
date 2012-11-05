<?php

/**
 * このclassは未完成です！！実装を手伝ってくれる有志求む！！
 * b2c_sc3@cybird.co.jp subject「CYOPコミッター募集」宛に連絡下さい。
 */

/**
 * Singletonパターン実装の抽象クラス
 * @author saimushi
 */
abstract class Singleton{

	/**
	 * My SingletonInstance
	 * @var object
	 */
	protected static $myself = null;
	protected $instanseOf = true;

	/**
	 * コンストラクタ
	 * @param string 小クラス名
	 * @param mixid instance化時の引数群
	 */
	protected function __construct($argInstanceName,$arguments = null){
		$this->__initSinglton(false,$argInstanceName,$arguments);
	}

	/**
	 * staticでアクセス出来る、Singltonのメイン処理
	 * @param string 小クラス名
	 * @param mixid instance化時の引数群
	 */
	protected static function __initSinglton($argStaticCallFlag, $argInstanceName,$arguments = null){
		static $first = false;
		if(false === $argStaticCallFlag){
			if(false === $first){
				$first = true;
				$singletonInstance = self::$myself;
				$singletonInstance = self::__getInstance($argInstanceName,$arguments);
			}
		}else{
			$ins = new $argInstanceName($arguments);
		}
	}

	/**
	 * Singletonによるインスタンス化
	 * @param string 小クラス名
	 * @param mixid instance化時の引数群
	 */
	protected function __getInstance($argInstanceName,$arguments = null){
		static $myself;
		if(!is_object($myself)){
			$myself = new $argInstanceName();
			if(null !== $arguments && count($arguments) > 0){
				$myself->__initialize($arguments);
			}
		}
		return $myself;
	}

	/**
	 * 実際の初期化処理のスケルトン
	 * @param mixid instance化時の引数群
	 */
	public function initialize($arguments){}
}

?>