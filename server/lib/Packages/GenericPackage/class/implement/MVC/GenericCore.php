<?php

/**
 * MVCモデルをHBOPとして提供するクラス
 */
class GenericCore {

	/**
	 * 
	 */
	public static function main(){
		// リクエストクエリーを分解
		
		// リクエストURIからコントローラを特定
		// コントローラをnew
		$cntrlr = new $controlerClass();
		$cntrlr->index();

	}

	public static function batch(){}
}

?>