<?php

$corefilename = strtoupper(substr(basename(__FILE__), 0, strpos(basename(__FILE__), '.')));

// XXX config.xmlを別パスに設置したい場合は以下の定数を定義すればOK(絶対パスでの定義)
//define($corefilename . '_CONFIG_XML_PATH', dirname(__FILE__).'/' . $corefilename . '.config.xml');
// XXX 強制終了時に閉鎖処理を入れたい場合は以下の定数に関数名を指定する事！引数は渡せないので工夫する事！
//define($corefilename . '_ERROR_FINALIS', 'finalize');

// XXX 各種フラグファイルを別パスに設置したい場合は以下の定数を定義すればOK(絶対パスでの定義)
// 自動ジェネレート(高速化用静的ファイル変換)のセット
//define($corefilename . '_AUTO_GENERAT_ENABLED', dirname(dirname(__FILE__)).'/.auto_generat');
// ローカル環境フラグのセット
//define($corefilename . '_STAGE_LOCAL_ENABLED', dirname(dirname(__FILE__)).'/.local');
// 開発環境フラグのセット
//define($corefilename . '_STAGE_DEV_ENABLED', dirname(dirname(__FILE__)).'/.dev');
// テスト環境(テスト用凍結環境)フラグのセット
//define($corefilename . '_STAGE_TEST_ENABLED', dirname(dirname(__FILE__)).'/.test');
// ステージング環境フラグのセット
//define($corefilename . '_STAGE_STAGING_ENABLED', dirname(dirname(__FILE__)).'/.staging');
// デバッグモードのセット
//define($corefilename . '_DEBUG_MODE_ENABLED', dirname(dirname(__FILE__)).'/.debug');
// エラーレポートのセット
//define($corefilename . '_ERROR_REPORT_ENABLED', dirname(dirname(__FILE__)).'/.error_report');

// XXX
//define($corefilename . '_USE_DI_FLAG', TRUE);

/*------------------------------ 根幹関数定義 ココから ------------------------------*/

/**
 * クラス使用時、ロードされてないと実行される
 * @param string $className
 */
function _autoloadFramework($className){
	//
	if(!class_exists($className, FALSE)){
		loadModule($className);
	}
}
// オートローダの登録
spl_autoload_register('_autoloadFramework');

/**
 * HAPPYBORN OOP/AOP向けフレームワークの本体
 * @param string モジュール本体を探すヒント文字列(URI) or .区切り文字 (混在はNG /がある場合、URIとして優先して処理される）
 * @param mixed DI動作を許可するかどうかのフラグメント NULL/TRUE/FALSE NULL=デフォルト設定準拠 TRUE強制DI FALSE=強制非DI
 * @return return instance名を返す
 */
function loadModule($argHint,$argUseDIMode = NULL){

	$corefilename = strtoupper(substr(basename(__FILE__), 0, strpos(basename(__FILE__), '.')));

	// パッケージ定義の初期化
	$pkConfXMLs = _initFramework();

	// defaultパッケージの使用は最後 or 明示の時だけに絞る
	$defaultPackageFlag = FALSE;

	// defaultパッケージの明示指定があるかどうか
	$matches = NULL;
	if(preg_match('/^default\.(.+)/',$argHint,$matches)){
		$defaultPackageFlag = TRUE;
		$packageName = $matches[1];
		$pkConfXML = $pkConfXMLs[0];
	}else{
		// Hintパスからパッケージを当てる
		$packageName = NULL;
		if(!is_file($argHint)){
			for($pkConfXMLCnt = 0; count($pkConfXMLs) > $pkConfXMLCnt; $pkConfXMLCnt++){
				$pkConfXML = $pkConfXMLs[$pkConfXMLCnt];
				// Hintのパス情報そのままの定義があればそれを使う
				if(isset($pkConfXML->{$argHint})){
					$packageName = $argHint;
					// instance指定がある場合は1ファイルに複数class定義があるんだなーとなんとなく察してあげる
					if(isset($pkConfXML->{$packageName}->instance)){
						$instanceName = $pkConfXML->{$packageName}->instance;
					}
					break;
				}else{
					// XML定義から回す
					foreach(get_object_vars($pkConfXML) as $key => $ChildNode){
						if(isset($ChildNode->pattern) && preg_match('/'.$ChildNode->pattern.'/',$argHint)){
							$packageName = $key;
							if(isset($ChildNode->instance)){
								$instanceName = $ChildNode->instance;
							}
							break 2;
						}else{
							continue;
						}
					}
					// Hintのパス情報からたどる
					$pathHints = explode('/',$argHint);
					if(count($pathHints) == 0){
						$pathHints = explode('.',$argHint);
					}
					$pathHintMaxCnt = count($pathHints);
					// hintの長い状態から上を徐々に削って短くし、完全一致する場所を探す
					for($pathHintCnt = 0; $pathHintMaxCnt > $pathHintCnt; $pathHintCnt++){
						$packageName = implode('.',$pathHints);
						if(isset($pkConfXML->{$packageName})){
							// instance指定がある場合は1ファイルに複数class定義があるんだなーとなんとなく察してあげる
							if(isset($pkConfXML->{$packageName}->instance)){
								$instanceName = $pkConfXML->{$packageName}->instance;
							}
							break 2;
						}else{
							$packageName = NULL;
							unset($pathHints[$pathHintCnt]);
						}
					}
				}
			}
		}
		if(NULL === $packageName){
			// ここまできてなかったら仮でdefaultパッケージ設定のパスをセットする
			$defaultPackageFlag = TRUE;
			$packageName = $argHint;
			$pkConfXML = $pkConfXMLs[0];
		}
	}

	$useDIFlag = FALSE;
	// DIの有無チェック
	// XXX 直接指定が最優先 共通指定があっても、直接指定が無効なら処理しない！
	if(TRUE === $argUseDIMode){
		$useDIFlag = TRUE;
	}
	elseif(NULL === $argUseDIMode && defined($corefilename . '_USE_DI_FLAG') && TRUE == constant($corefilename . '_USE_DI_FLAG')){
		$useDIFlag = TRUE;
	}
	// DIが有効ならメモリーに展開
	if(TRUE === $useDIFlag){
		// XXX DIの実装は未完成！！！使用しないで！！！
		/* ココから
		 static $loaded = array();
		 if(!isset($loaded[''])){
			$loaded[''] = TRUE;
			$file = file_get_contents($packagePaths);
			// クラス名をネームスペースっぽく処理してやる。
			}
			ココまで */
	}else{
		if(TRUE === $defaultPackageFlag){
			// defaultパッケージを捜査
			_loadDefaultModule($pkConfXML,$packageName);
			$instanceName = NULL;
		}else{
			// 明示的な指定がある場合の捜査
			for($packagePathCnt = 0, $errorCnt = 0; count($pkConfXML->{$packageName}->link) > $packagePathCnt; $packagePathCnt++){
				$fileget = FALSE;
				$addmethod = FALSE;
				$rename = FALSE;
				// メソッドを追加する処理
				if(0 < @strlen($pkConfXML->{$packageName}->link[$packagePathCnt]->attributes()->addmethod)){
					$fileget = TRUE;
					$addmethod = TRUE;
				}
				// クラス名をリネームする処理
				if(0 < @strlen($pkConfXML->{$packageName}->link[$packagePathCnt]->attributes()->renameto) && 0 < @strlen($pkConfXML->{$packageName}->link[$packagePathCnt]->attributes()->renamefrom)){
					$fileget = TRUE;
					$rename = TRUE;
				}
				if(TRUE === $fileget){
					$classdef = @file_get_contents($pkConfXML->{$packageName}->link[$packagePathCnt], TRUE);
					if(strlen($classdef) == 0){
						$subPackageName = $pkConfXML->{$packageName}->link[$packagePathCnt];
						if(preg_match('/^default\.(.+)/', $subPackageName, $matches)){
							$subPackageName = $matches[1];
						}
						// loadModuleの再帰処理による自動解決を試みる
						$classdef = _loadDefaultModule($pkConfXML, $subPackageName, TRUE);
					}
				}else{
					if(FALSE === @include_once($pkConfXML->{$packageName}->link[$packagePathCnt])){
						$subPackageName = $pkConfXML->{$packageName}->link[$packagePathCnt];
						if(preg_match('/^default\.(.+)/', $subPackageName, $matches)){
							$subPackageName = $matches[1];
						}
						// loadModuleの再帰処理による自動解決を試みる
						_loadDefaultModule($pkConfXML, $subPackageName);
					}
				}
				// methodの動的追加を実行
				if(TRUE === $addmethod){
					if(!isset($classBuffer)){
						ob_start();
						echo $classdef;
						$classBuffer = ob_get_clean();
					}
					// 追加するメソッド定義を探す
					$addmethoddef = $pkConfXML->{$packageName}->link[$packagePathCnt]->attributes()->addmethod;
					if(FALSE !== strpos($addmethoddef, ',')){
						$addmethoddefs = explode(',', $addmethoddef);
						for($addmethoddefIndex=0; count($addmethoddefs) > $addmethoddefIndex; $addmethoddefIndex++){
							if(isset($pkConfXML->{$packageName}->{trim($addmethoddefs[$addmethoddefIndex])}) && isset($pkConfXML->{$packageName}->{trim($addmethoddefs[$addmethoddefIndex])}->attributes()->targetclass) && strlen($pkConfXML->{$packageName}->{trim($addmethoddefs[$addmethoddefIndex])}->attributes()->targetclass) > 0){
								$targetClassName = $pkConfXML->{$packageName}->{trim($addmethoddefs[$addmethoddefIndex])}->attributes()->targetclass;
								$addmethod = (string)$pkConfXML->{$packageName}->{trim($addmethoddefs[$addmethoddefIndex])};
								$classBuffer = preg_replace('/(class|abstract|interface)\s+?'.trim($targetClassName).'(.*)?\{/', '$1 '. trim($targetClassName) . '\2 { '.$addmethod, $classBuffer);
							}else{
								_systemError('class method add notfound node \''.$packageName.'.'.trim($addmethoddefs[$addmethoddefIndex]).' or undefined attribute \'targetclass\'');
							}
						}
					}else{
						if(isset($pkConfXML->{$packageName}->{$addmethoddef}) && isset($pkConfXML->{$packageName}->{trim($addmethoddef)}->attributes()->targetclass) && strlen($pkConfXML->{$packageName}->{trim($addmethoddef)}->attributes()->targetclass) > 0){
							$targetClassName = $pkConfXML->{$packageName}->{trim($addmethoddef)}->attributes()->targetclass;
							$addmethod = (string)$pkConfXML->{$packageName}->{trim($addmethoddef)};
							$classBuffer = preg_replace('/(class|abstract|interface)\s+?'.trim($targetClassName).'(.*)?\{/', '$1 '. trim($targetClassName) . '\2 { '.$addmethod, $classBuffer);
						}else{
							_systemError('class method add notfound node \''.$packageName.'.'.trim($addmethoddef).' or undefined attribute \'targetclass\'');
						}
					}
				}
				// クラス名リネームの実行
				// XXX 処理の順番に注意！！先にaddmethodを処理。renameした後だとクラス名が変わっていてaddにしくじるので
				if(TRUE === $rename){
					if(!isset($classBuffer)){
						ob_start();
						echo $classdef;
						$classBuffer = ob_get_clean();
					}
					// リネーム
					$renametoClassName = $pkConfXML->{$packageName}->link[$packagePathCnt]->attributes()->renameto;
					$renamefromClassName = $pkConfXML->{$packageName}->link[$packagePathCnt]->attributes()->renamefrom;
					if(FALSE !== strpos($renametoClassName, ',')){
						$renametoClassName = explode(',', $renametoClassName);
						$renamefromClassName = explode(',', $renamefromClassName);
						if(!(is_array($renametoClassName) && is_array($renamefromClassName) && count($renametoClassName) == count($renamefromClassName))){
							_systemError('class rename error! renameto-from count missmatch renameto-count='.count($renametoClassName).' renamefrom-count='.count($renamefromClassName));
						}
						for($renameIndex=0; count($renamefromClassName)>$renameIndex; $renameIndex++){
							$classBuffer = preg_replace('/(class|abstract|interface)\s+?'.trim($renamefromClassName[$renameIndex]).'(\s|\{|\r|\n)/', '\1 '. trim($renametoClassName[$renameIndex]), $classBuffer);
						}
					}else{
						$classBuffer = preg_replace('/(class|abstract|interface)\s+?'.trim($renamefromClassName).'/', '\1 '. $renametoClassName, $classBuffer);
					}
				}
				// 定義の動的変更を実行
				if(isset($classBuffer)){
					// PHPの開始タグがあるとコケるので消す
					$classBuffer = preg_replace('/^<\?(php){0,1}(\s|\t)*?(\r\n|\r|\n)/', '', $classBuffer);
					eval($classBuffer);
					//echo $classBuffer;
					unset($classdef);
					unset($classBuffer);
				}
				// クラス名をマッピングする処理
				if(0 < @strlen($pkConfXML->{$packageName}->link[$packagePathCnt]->attributes()->mapto) && 0 < @strlen($pkConfXML->{$packageName}->link[$packagePathCnt]->attributes()->mapfrom)){
					$maptoClassName = $pkConfXML->{$packageName}->link[$packagePathCnt]->attributes()->mapto;
					$mapfromClassName = $pkConfXML->{$packageName}->link[$packagePathCnt]->attributes()->mapfrom;
					if(FALSE !== strpos($maptoClassName, ',')){
						$maptoClassName = explode(',', $maptoClassName);
						$mapfromClassName = explode(',', $mapfromClassName);
						if(!(is_array($maptoClassName) && is_array($mapfromClassName) && count($maptoClassName) == count($mapfromClassName))){
							_systemError('class map error! mapto-from count missmatch mapto-count='.count($maptoClassName).' mapfrom-count='.count($mapfromClassName));
						}
						$mapClass = array();
						for($mapIndex=0;count($maptoClassName)>$mapIndex; $mapIndex++){
							$mapClass[] = 'class '.$maptoClassName[$mapIndex].' extends '.$mapfromClassName[$mapIndex].'{}';
						}
						$mapClass = implode('', $mapClass);
					}else{
						$mapClass = 'class '.$maptoClassName.' extends '.$mapfromClassName.'{}';
					}
					eval($mapClass);
					unset($mapClass);
				}
			}
			if(isset($pkConfXML->{$packageName}->instance)){
				$instanceName = $pkConfXML->{$packageName}->instance;
				if(!class_exists($instanceName, FALSE)){
					_systemError('not found class ' . $instanceName . ' on ' . $pkConfXML->{$packageName}->link[$packagePathCnt] . '!!');
				}
			}else{
				$instanceName = NULL;
			}
		}
	}
	return (string) $instanceName;
}

function _loadDefaultModule($argPkConfXML, $argPackageName, $argFileGetContentsEnabled = FALSE){
	$matches = NULL;
	// abstractとinterfaceは見分ける
	if(preg_match('/^abstract\.(.+)/',$argPackageName,$matches)){
		$argPackageName = $matches[1];
		// abstract定義のパスを走査
		$loaded = FALSE;
		for($packagePathCnt = 0, $errorCnt = 0; count($argPkConfXML->default->abstract) > $packagePathCnt; $packagePathCnt++){
			if(TRUE === $argFileGetContentsEnabled){
				$file = @file_get_contents($argPkConfXML->default->abstract[$packagePathCnt].'/'.$argPackageName.$argPkConfXML->default->abstract[$packagePathCnt]->attributes()->suffix, TRUE);
				if(strlen($file) > 0){
					$loaded = TRUE;
					break;
				}
				$file = @file_get_contents($argPkConfXML->default->abstract[$packagePathCnt].'/'.$argPackageName, TRUE);
				if(strlen($file) > 0){
					$loaded = TRUE;
					break;
				}
			}else{
				if(FALSE !== @include_once($argPkConfXML->default->abstract[$packagePathCnt].'/'.$argPackageName.$argPkConfXML->default->abstract[$packagePathCnt]->attributes()->suffix)){
					$loaded = TRUE;
					break;
				}
				if(FALSE !== @include_once($argPkConfXML->default->abstract[$packagePathCnt].'/'.$argPackageName)){
					$loaded = TRUE;
					break;
				}
			}
		}
		if(FALSE === $loaded){
			_systemError('not found abstract ' . $argPackageName . '!!');
		}
	}else{
		$matches = NULL;
		if(preg_match('/^interface\.(.+)/',$argPackageName,$matches)){
			$argPackageName = $matches[1];
			// interface定義のパスを走査
			$loaded = FALSE;
			for($packagePathCnt = 0, $errorCnt = 0; count($argPkConfXML->default->interface) > $packagePathCnt; $packagePathCnt++){
				if(TRUE === $argFileGetContentsEnabled){
					$file = @file_get_contents($argPkConfXML->default->interface[$packagePathCnt].'/'.$argPackageName.$argPkConfXML->default->interface[$packagePathCnt]->attributes()->suffix, TRUE);
					if(strlen($file) > 0){
						$loaded = TRUE;
						break;
					}
					$file = @file_get_contents($argPkConfXML->default->interface[$packagePathCnt].'/'.$argPackageName, TRUE);
					if(strlen($file) > 0){
						$loaded = TRUE;
						break;
					}
				}else{
					if(FALSE !== @include_once($argPkConfXML->default->interface[$packagePathCnt].'/'.$argPackageName.$argPkConfXML->default->interface[$packagePathCnt]->attributes()->suffix)){
						$loaded = TRUE;
						break;
					}
					if(FALSE !== @include_once($argPkConfXML->default->interface[$packagePathCnt].'/'.$argPackageName)){
						$loaded = TRUE;
						break;
					}
				}
			}
			if(FALSE === $loaded){
				_systemError('not found interface ' . $argPackageName . '!!');
			}
		}else{
			// default定義パスを全走査
			$matches = NULL;
			if(preg_match('/^implement\.(.+)/',$argPackageName,$matches)){
				$argPackageName = $matches[1];
			}
			$loaded = FALSE;
			// まずはimplementにあるかどうか
			for($packagePathCnt = 0, $errorCnt = 0; count($argPkConfXML->default->implement) > $packagePathCnt; $packagePathCnt++){
				if(TRUE === $argFileGetContentsEnabled){
					$file = @file_get_contents($argPkConfXML->default->implement[$packagePathCnt].'/'.$argPackageName.$argPkConfXML->default->implement[$packagePathCnt]->attributes()->suffix, TRUE);
					if(strlen($file) > 0){
						$loaded = TRUE;
						break;
					}
					$file = @file_get_contents($argPkConfXML->default->implement[$packagePathCnt].'/'.$argPackageName, TRUE);
					if(strlen($file) > 0){
						$loaded = TRUE;
						break;
					}
				}else{
					if(FALSE !== @include_once($argPkConfXML->default->implement[$packagePathCnt].'/'.$argPackageName.$argPkConfXML->default->implement[$packagePathCnt]->attributes()->suffix)){
						$loaded = TRUE;
						break;
					}
					if(FALSE !== @include_once($argPkConfXML->default->implement[$packagePathCnt].'/'.$argPackageName)){
						$loaded = TRUE;
						break;
					}
				}
			}
			if(FALSE === $loaded){
				// なければlink直下を探す
				for($packagePathCnt = 0, $errorCnt = 0; count($argPkConfXML->default->link) > $packagePathCnt; $packagePathCnt++){
					if(TRUE === $argFileGetContentsEnabled){
						$file = @file_get_contents($argPkConfXML->default->link[$packagePathCnt].'/'.$argPackageName.$argPkConfXML->default->link[$packagePathCnt]->attributes()->suffix, TRUE);
						if(strlen($file) > 0){
							$loaded = TRUE;
							break;
						}
						$file = @file_get_contents($argPkConfXML->default->link[$packagePathCnt].'/'.$argPackageName, TRUE);
						if(strlen($file) > 0){
							$loaded = TRUE;
							break;
						}
					}else{
						if(FALSE !== @include_once($argPkConfXML->default->link[$packagePathCnt].'/'.$argPackageName.$argPkConfXML->default->link[$packagePathCnt]->attributes()->suffix)){
							$loaded = TRUE;
							break;
						}
						if(FALSE !== @include_once($argPkConfXML->default->link[$packagePathCnt].'/'.$argPackageName)){
							$loaded = TRUE;
							break;
						}
					}
				}
				if(FALSE === $loaded){
					// なければabstract直下を探す
					for($packagePathCnt = 0, $errorCnt = 0; count($argPkConfXML->default->abstract) > $packagePathCnt; $packagePathCnt++){
						if(TRUE === $argFileGetContentsEnabled){
							$file = @file_get_contents($argPkConfXML->default->abstract[$packagePathCnt].'/'.$argPackageName.$argPkConfXML->default->abstract[$packagePathCnt]->attributes()->suffix, TRUE);
							if(strlen($file) > 0){
								$loaded = TRUE;
								break;
							}
							$file = @file_get_contents($argPkConfXML->default->abstract[$packagePathCnt].'/'.$argPackageName, TRUE);
							if(strlen($file) > 0){
								$loaded = TRUE;
								break;
							}
						}else{
							if(FALSE !== @include_once($argPkConfXML->default->abstract[$packagePathCnt].'/'.$argPackageName.$argPkConfXML->default->abstract[$packagePathCnt]->attributes()->suffix)){
								$loaded = TRUE;
								break;
							}
							if(FALSE !== @include_once($argPkConfXML->default->abstract[$packagePathCnt].'/'.$argPackageName)){
								$loaded = TRUE;
								break;
							}
						}
					}
					if(FALSE === $loaded){
						// なければinterface直下を探す
						for($packagePathCnt = 0, $errorCnt = 0; count($argPkConfXML->default->interface) > $packagePathCnt; $packagePathCnt++){
							if(TRUE === $argFileGetContentsEnabled){
								$file = @file_get_contents($argPkConfXML->interface->link.'/'.$argPackageName.$argPkConfXML->default->interface->attributes()->suffix, TRUE);
								if(strlen($file) > 0){
									$loaded = TRUE;
									break;
								}
								$file = @file_get_contents($argPkConfXML->interface->link.'/'.$argPackageName, TRUE);
								if(strlen($file) > 0){
									$loaded = TRUE;
									break;
								}
							}else{
								if(FALSE !== @include_once($argPkConfXML->interface->link.'/'.$argPackageName.$argPkConfXML->default->interface->attributes()->suffix)){
									$loaded = TRUE;
									break;
								}
								if(FALSE !== @include_once($argPkConfXML->interface->link.'/'.$argPackageName)){
									$loaded = TRUE;
									break;
								}
							}
						}
						if(FALSE === $loaded){
							// それでもなければインクルードパスを信じてみる
							if(TRUE === $argFileGetContentsEnabled){
								$file = @file_get_contents($argPackageName, TRUE);
								if(strlen($file) == 0){
									$file = @file_get_contents($argPackageName.'.php', TRUE);
									if(strlen($file) == 0){
										_systemError('not found package ' . $argPackageName . '!!');
									}
								}
							}else{
								if(FALSE === @include_once($argPackageName)){
									if(FALSE === @include_once($argPackageName.'.php')){
										_systemError('not found package ' . $argPackageName . '!!');
									}
								}
							}
						}
					}
				}
			}
		}
	}
	if(isset($file)){
		return $file;
	}
}

/**
 * フレームワークの初期化処理(内部関数)
 */
function _initFramework(){
	static $pkgConfXML = NULL;
	if(NULL === $pkgConfXML){
		// packageXMLを読み込む
		$pkgConfXMLPath = dirname(__FILE__).'/package.xml';
		if(file_exists($pkgConfXMLPath)){
			$pkgConfXML[] = simplexml_load_file($pkgConfXMLPath, NULL, LIBXML_NOCDATA);
			// defaulのauto節を処理する
			if(count($pkgConfXML[count($pkgConfXML)-1]->default->auto) > 0){
				foreach($pkgConfXML[count($pkgConfXML)-1]->default->auto->children() as $autoLoadModule){
					loadModule($autoLoadModule);
				}
			}
		}
		$pkgConfXMLPath = dirname(__FILE__).'/' . strtoupper(substr(basename(__FILE__), 0, strpos(basename(__FILE__), '.'))) . '.package.xml';
		if(file_exists($pkgConfXMLPath)){
			$pkgConfXML[] = simplexml_load_file($pkgConfXMLPath, NULL, LIBXML_NOCDATA);
			// defaulのauto節を処理する
			if(count($pkgConfXML[count($pkgConfXML)-1]->default->auto) > 0){
				foreach($pkgConfXML[count($pkgConfXML)-1]->default->auto->children() as $autoLoadModule){
					loadModule($autoLoadModule);
				}
			}
		}
		$configPaths = constants('.*PACKAGE_CONFIG_XML_PATH.*',TRUE);
		if(count($configPaths) > 0){
			$pathCnt = 0;
			foreach($configPaths as $key => $pkgConfXMLPath){
				if(file_exists($pkgConfXMLPath)){
					$pkgConfXML[] = simplexml_load_file($pkgConfXMLPath, NULL, LIBXML_NOCDATA);
					// defaulのauto節を処理する
					if(count($pkgConfXML[count($pkgConfXML)-1]->default->auto) > 0){
						foreach($pkgConfXML[count($pkgConfXML)-1]->default->auto->children() as $autoLoadModule){
							loadModule($autoLoadModule);
						}
					}
				}
			}
		}
		if(NULL === $pkgConfXML){
			_systemError('not found package.xml ' . $pkgConfXMLPath. ' !!');
		}
	}
	return $pkgConfXML;
}

/**
 * 外部からのフレームワークの明示的初期化処理
 */
eval('function init' . $corefilename . '(){ return _initFramework(); }');

/**
 * フレームワーク内のエラー処理
 */
function _systemError($argMsg){
	$corefilename = strtoupper(substr(basename(__FILE__), 0, strpos(basename(__FILE__), '.')));
	// ココを通るのは相当なイレギュラー
	if(defined($corefilename . '_ERROR_FINALIS')){
		eval(constant($corefilename . '_ERROR_FINALIS').'();');
	}else{
		header('HTTP/1.0 500 Internal Server Error');
		echo '<h1>Internal Server Error</h1>'.PHP_EOL;
		echo '<br/>'.PHP_EOL;
		echo 'Please check exception\'s log'.PHP_EOL;
	}
	echo $argMsg.PATH_SEPARATOR.var_export(debug_backtrace(),TRUE);
	logging($argMsg.PATH_SEPARATOR.var_export(debug_backtrace(),TRUE),'exception');
	exit();
}

/**
 * 外部からのフレームワークの明示的エラー処理
 */
eval('function error' . $corefilename . '($argMsg){ _systemError($argMsg); }');

/**
 * フレームワークの終了処理
 * ob_startを仕掛けたプログラムの終了時にコールされる
 * @param バッファストリング
 */
function _callbackAndFinalize($argBuffer){
	// return ってすると出力されるのよ。
	return $argBuffer;
}

/**
 * 単純なsessionIDのストア
 */
function _sessionIDStroe($argAction,$argSID = NULL){
	static $sessionID = NULL;
	if('get' === strtolower($argAction)){
		return $sessionID;
	}elseif('set' === strtolower($argAction)){
		$sessionID = $argSID;
	}
}

/**
 * sessionIDのアクセサ
 */
function setSessionID($argSessionID){
	_sessionIDStroe('set',$argSessionID);
}

/**
 * sessionIDのアクセサ
 */
function getSessionID(){
	return _sessionIDStroe('get');
}

/**
 * 単純なUIDのストア
 */
function _uniqueuserIDStroe($argAction,$argUID = NULL){
	static $UID = NULL;
	if('get' === strtolower($argAction)){
		return $UID;
	}elseif('set' === strtolower($argAction)){
		$UID = $argUID;
	}
}

/**
 * sessionIDのアクセサ
 */
function setUID($argUID){
	_uniqueuserIDStroe('set',$argUID);
}

/**
 * sessionIDのアクセサ
 */
function getUID(){
	return _uniqueuserIDStroe('get');
}

/**
 * pathを一気に上まで駆け上がる！
 */
function path($argFilePath, $argDepth = 1){
	for($pathUpcnt=0; $argDepth > $pathUpcnt; $pathUpcnt++){
		$argFilePath = dirname($argFilePath);
	}
	return $argFilePath;
}

/**
 * logging
 */
function logging($arglog, $argLogName = NULL, $argConsolEchoFlag = FALSE){

	static $pdate = NULL;
	static $phour = NULL;
	static $loggingLineNum = 1;

	$logpath = dirname(dirname(__FILE__)).'/logs/';
	if(class_exists("Config", FALSE) && NULL !== constant('Config::LOG_PATH')){
		$logpath = Config::LOG_PATH;
	}

	if(NULL === $argLogName){
		$argLogName = 'process';
	}

	if(NULL === $pdate){
		$dateins = new DateTime();
		$pdate = $dateins->format('Y-m-d H:i:s') . ' [UDate:'. microtime(TRUE).']';
		$phour = $dateins->format('H');
	}
	if(is_array($arglog) || is_object($arglog)){
		$arglog = var_export($arglog,TRUE);
	}
	if(isset($_SERVER['REQUEST_URI'])){
		$arglog = '[URI:'.$_SERVER['REQUEST_URI'].']'.$arglog;
	}
	$logstr = $pdate.'[logging'.$loggingLineNum.'][SID:'.getSessionID().'][UID:'.getUID().']'.$arglog;

	// 改行コードは\rだけにして、一行で表現出来るようにする
	$logstr = str_replace("\r",'[EOL]',$logstr);
	$logstr = str_replace("\n",'[EOL]',$logstr);
	if('process' !== $argLogName){
		// process_logは常に出す
		if(!is_file($logpath.'process_log')){
			@touch($logpath.'process_log');
			@chmod($logpath.'process_log', 0666);
		}
		@file_put_contents($logpath.'process_'.$phour.'.log', $logstr.PHP_EOL, FILE_APPEND);
	}
	if(!is_file($logpath.$argLogName.'_log')){
		@touch($logpath.$argLogName.'_log');
		@chmod($logpath.$argLogName.'_log', 0666);
	}
	//if('exception' == $argLogName || 'batch' == $argLogName){
	@file_put_contents($logpath.$argLogName.'_'.$phour.'.log', $logstr.PHP_EOL, FILE_APPEND);
	//}

	// DEBUG_FLAGが有効だったらhttpHeaderにログを出しちゃう
	if(defined('DEBUG_FLAG') && TRUE == DEBUG_FLAG && isset($_SERVER['REQUEST_URI'])){
		@header('debug'.$loggingLineNum.': ['.$argLogName.']'.$logstr, FALSE);
	}

	// XXX consolは画面に出力
	if(TRUE === $argConsolEchoFlag && defined('DEBUG_FLAG') && TRUE == DEBUG_FLAG && !isset($_SERVER['REQUEST_URI'])){
		echo $logstr.PHP_EOL;
	}

	$loggingLineNum++;

}

/**
 * debug
 */
function debug($arglog){
	logging($arglog, 'debug',TRUE);
}

/**
 * 定数を正規表現で検索可能にする
 */
function constants($argKey, $argSearchFlag = FALSE){
	if(FALSE !== $argSearchFlag){
		$datas = array();
		foreach(get_defined_constants() as $constKey => $val){
			if(preg_match('/'.$argKey.'/',$constKey)){
				$datas[$constKey] = $val;
			}
		}
		if(count($datas)>0){
			return $datas;
		}
	}elseif(TRUE === defined($argKey)){
		return constant($argKey);
	}
	return NULL;
}

/**
 * 実行環境チェック
 */
function isTest($argStagingEnabled=FALSE){
	if(TRUE == LOCAL_FLAG || TRUE == DEV_FLAG || TRUE == TEST_FLAG) {
		if(FALSE === $argStagingEnabled){
			return TRUE;
		}elseif(TRUE == STAGING_FLAG){
			return TRUE;
		}
	}
	return FALSE;
}

/*------------------------------ 根幹関数定義 ココから ------------------------------*/



/*------------------------------ 以下手続き型処理 ココから ------------------------------*/

// output buffaringを開始する
ob_start('_callbackAndFinalize');

// エラーレポートの設定
$errorReportEnabled = dirname(dirname(__FILE__)).'/.error_report';
if(defined($corefilename . '_ERROR_REPORT_ENABLED')){
	$errorReportEnabled = constant($corefilename . '_ERROR_REPORT_ENABLED');
}
if(file_exists($errorReportEnabled)){
	// 本番環境意外はすべてエラーを出す！！！
	ini_set('error_reporting',E_ALL);
	ini_set('display_errors',1);
}

/**
 * configの読み込みとconfigureクラスの定義を実行する
 */
function loadConfig($argConfigPath){

	$corefilename = strtoupper(substr(basename(__FILE__), 0, strpos(basename(__FILE__), '.')));
	$autoGeneratEnabled = dirname(dirname(__FILE__)).'/.auto_generat';
	if(defined($corefilename . '_AUTO_GENERAT_ENABLED')){
		$autoGeneratEnabled = constant($corefilename . '_AUTO_GENERAT_ENABLED');
	}
	// 自動ジェネレートフラグのセット
	$autoGeneratFlag = file_exists($autoGeneratEnabled);
	if(true === $autoGeneratFlag){
		if(is_file($argConfigPath)){
			$configFileName = basename($argConfigPath);
			$generatedConfigFileName = dirname(dirname(__FILE__)).'/generations/'.$configFileName.'.generated.php';
			if(file_exists($generatedConfigFileName) && filemtime($generatedConfigFileName) >= filemtime($argConfigPath)){
				// 静的ファイル化されたコンフィグクラスファイルを読み込んで終了
				require_once $generatedConfigFileName;
				return TRUE;
			}
		}
	}

	$stageLocalEnabled = dirname(dirname(__FILE__)).'/.local';
	if(defined($corefilename . '_STAGE_LOCAL_ENABLED')){
		$stageLocalEnabled = constant($corefilename . '_STAGE_LOCAL_ENABLED');
	}
	$stageDevEnabled = dirname(dirname(__FILE__)).'/.dev';
	if(defined($corefilename . '_STAGE_DEV_ENABLED')){
		$stageDevEnabled = constant($corefilename . '_STAGE_DEV_ENABLED');
	}
	$stageTestEnabled = dirname(dirname(__FILE__)).'/.test';
	if(defined($corefilename . '_STAGE_TEST_ENABLED')){
		$stageTestEnabled = constant($corefilename . '_STAGE_TEST_ENABLED');
	}
	$stageStagingEnabled = dirname(dirname(__FILE__)).'/.staging';
	if(defined($corefilename . '_STAGE_STAGING_ENABLED')){
		$stageStagingEnabled = constant($corefilename . '_STAGE_STAGING_ENABLED');
	}
	$debugModeEnabled = dirname(dirname(__FILE__)).'/.debug';
	if(defined($corefilename . '_DEBUG_MODE_ENABLED')){
		$debugModeEnabled = constant($corefilename . '_DEBUG_MODE_ENABLED');
	}
	$errorReportEnabled = dirname(dirname(__FILE__)).'/.error_report';
	if(defined($corefilename . '_ERROR_REPORT_ENABLED')){
		$errorReportEnabled = constant($corefilename . '_ERROR_REPORT_ENABLED');
	}
	// ローカル環境フラグのセット
	$localFlag = file_exists($stageLocalEnabled);
	// 開発環境フラグのセット
	$devFlag = file_exists($stageDevEnabled);
	// テスト環境(テスト用凍結環境)フラグのセット
	$testFlag = file_exists($stageTestEnabled);
	// ステージング環境フラグのセット
	$stagingFlag = file_exists($stageStagingEnabled);
	// デバッグモードフラグのセット
	$debugFlag = file_exists($debugModeEnabled);
	// エラーレポートフラグのセット
	$errorReportFlag = file_exists($errorReportEnabled);

	if(!is_file($argConfigPath)){
		return FALSE;
	}

	// configureの初期化
	$configs = array();
	$configure = simplexml_load_file($argConfigPath, NULL, LIBXML_NOCDATA);

	// 環境フラグをセット
	if(!class_exists("Config", FALSE)){
		$configure->addChild('AUTO_GENERAT_ENABLED', $autoGeneratFlag);
		$configure->addChild('LOCAL_FLAG', $localFlag);
		$configure->addChild('DEV_FLAG', $devFlag);
		$configure->addChild('TEST_FLAG', $testFlag);
		$configure->addChild('STAGING_FLAG', $stagingFlag);
		$configure->addChild('DEBUG_FLAG', $debugFlag);
		$configure->addChild('ERROR_REPORT_FLAG', $errorReportFlag);
	}

	foreach(get_object_vars($configure) as $key => $val){
		if('comment' != $key){
			if(count($configure->{$key}->children()) > 0){
				if(!isset($configs[$key.'Config'])){
					$configs[$key.'Config'] = '';
				}
				foreach(get_object_vars($val) as $key2 => $val2){
					$evalFlag = FALSE;
					if(count($val2) > 1){
						$skip = TRUE;
						for($attrCnt=0;count($val2)>$attrCnt;$attrCnt++){
							if(isset($configure->{$key}->{$key2}[$attrCnt]->attributes()->stage)){
								$stage = $configure->{$key}->{$key2}[$attrCnt]->attributes()->stage;
								if('local' == $stage && TRUE === $localFlag){
									$skip = FALSE;
									break;
								}elseif('dev' == $stage && TRUE === $devFlag){
									$skip = FALSE;
									break;
								}elseif('test' == $stage && TRUE === $testFlag){
									$skip = FALSE;
									break;
								}elseif('staging' == $stage && TRUE === $stagingFlag){
									$skip = FALSE;
									break;
								}
							}else{
								$defAttrCnt = $attrCnt;
							}
						}
						if(TRUE === $skip){
							$attrCnt = $defAttrCnt;
						}
						$val2 = $val2[$attrCnt];
						if(isset($configure->{$key}->{$key2}[$attrCnt]->attributes()->code)){
							$evalFlag = TRUE;
						}
					}elseif(isset($configure->{$key}->{$key2}->attributes()->code)){
						$evalFlag = TRUE;
					}
					$val2 = trim($val2);
					$matches = NULL;
					if(preg_match_all('/\%(.+)\%/',$val2,$matches) > 0){
						for($matchCnt=0; count($matches[0]) > $matchCnt; $matchCnt++){
							$matchKey = $matches[0][$matchCnt];
							$matchStr = $matches[1][$matchCnt];
							$val2 = substr_replace($val2,$val->{$matchStr},strpos($val2,$matchKey),strlen($matchKey));
						}
					}
					if(TRUE === $evalFlag){
						@eval('$val2 = '.$val2.';');
						$configure->{$key}->{$key2} = $val2;
						$configs[$key.'Config'] .= "\t".'const '.$key2.' = \''.$val2.'\';'.PHP_EOL;
					}else{
						if(strlen($val2) == 0){
							$configs[$key.'Config'] .= "\t".'const '.$key2.' = \'\';'.PHP_EOL;
						}elseif('TRUE' == strtoupper($val2) || 'FALSE' == strtoupper($val2) || 'NULL' == strtoupper($val2) || is_numeric($val2)){
							$configs[$key.'Config'] .= "\t".'const '.$key2.' = '.$val2.';'.PHP_EOL;
						}else{
							$configs[$key.'Config'] .= "\t".'const '.$key2.' = \''.addslashes($val2).'\';'.PHP_EOL;
						}
					}
				}
			}else{
				$evalFlag = FALSE;
				if(count($val) > 1){
					$skip = TRUE;
					for($attrCnt=0;count($val)>$attrCnt;$attrCnt++){
						if(isset($configure->{$key}[$attrCnt]->attributes()->stage)){
							$stage = $configure->{$key}[$attrCnt]->attributes()->stage;
							if('local' == $stage && TRUE === $localFlag){
								$skip = FALSE;
								break;
							}elseif('dev' == $stage && TRUE === $devFlag){
								$skip = FALSE;
								break;
							}elseif('test' == $stage && TRUE === $testFlag){
								$skip = FALSE;
								break;
							}elseif('staging' == $stage && TRUE === $stagingFlag){
								$skip = FALSE;
								break;
							}
						}else{
							$defAttr = $attrCnt;
						}
					}
					if(TRUE === $skip){
						$attrCnt = $defAttr;
					}
					$val = $val[$attrCnt];
					if(isset($configure->{$key}[$attrCnt]->attributes()->code)){
						$evalFlag = TRUE;
					}
				}elseif(isset($configure->{$key}->attributes()->code)){
					$evalFlag = TRUE;
				}
				$val = trim($val);
				$matches = NULL;
				if(preg_match_all('/\%(.+)\%/',$val,$matches) > 0){
					for($matchCnt=0; count($matches[0]) > $matchCnt; $matchCnt++){
						$matchKey = $matches[0][$matchCnt];
						$matchStr = $matches[1][$matchCnt];
						$val = substr_replace($val,$configure->{$matchStr},strpos($val,$matchKey),strlen($matchKey));
					}
				}

				if(TRUE === $evalFlag){
					eval('$val = '.$val.';');
					//define($key,$val);
					$configs['Config'] .= "\t".'const '.$key.' = \''.$val.'\';'.PHP_EOL;
				}else{
					if(strlen($val) == 0){
						//define($key,'');
						$configs['Config'] .= "\t".'const '.$key.' = \'\';'.PHP_EOL;
					}elseif('TRUE' == strtoupper($val) || 'FALSE' == strtoupper($val) || 'NULL' == strtoupper($val) || is_numeric($val)){
						//define($key,$val);
						$configs['Config'] .= "\t".'const '.$key.' = '.$val.';'.PHP_EOL;
					}else{
						// XXX ココ危険！！！addslashesしないと行けないシチュエーションが出てくるかも
						//define($key,$val);
						$configs['Config'] .= "\t".'const '.$key.' = \''.addslashes($val).'\';'.PHP_EOL;
					}
				}
			}
		}
	}

	static $baseConfigureClassDefine = NULL;
	if(NULL === $baseConfigureClassDefine){



		/*------------------------------ 根幹クラス定義 ココから ------------------------------*/

		$baseConfigureClassDefine = <<<_CLASSDEF_
class %class% {

	%consts%

	private static function _search(\$argVal,\$argKey,\$argHints){
		if(preg_match('/'.\$argHints['hint'].'/',\$argKey)){
			\$argHints['data'][\$argKey] = \$argVal;
		}
	}

	public static function constant(\$argKey,\$argSearchFlag = FALSE){
		static \$myConsts = NULL;
		if(FALSE !== \$argSearchFlag){
			if(NULL === \$myConsts){
				\$ref = new ReflectionClass(__CLASS__);
				\$myConsts = \$ref->getConstants();
			}
			\$tmpArr = array('hint'=>\$argKey,'data'=>array());
			array_walk(\$myConsts,__CLASS__.'::_search',&\$tmpArr);
			if(count(\$tmpArr['data'])>0){
				return \$tmpArr['data'];
			}
		}elseif(TRUE === defined('self::'.\$argKey)){
			return constant('self::'.\$argKey);
		}
		return NULL;
	}
}
_CLASSDEF_;

		/*------------------------------ 根幹クラス定義 ココまで ------------------------------*/



	}

	// configureクラスを宣言する
	$configGeneratClassDefine = NULL;
	foreach($configs as $key => $val){
		$configClassDefine = str_replace('%class%',ucwords($key),$baseConfigureClassDefine);
		$configClassDefine = str_replace('%consts%',substr($val,1),$configClassDefine);
		if(true === $autoGeneratFlag){
			$configGeneratClassDefine .= $configClassDefine;
		}else{
			eval($configClassDefine);
		}
	}

	// ジェネレート処理
	if(true === $autoGeneratFlag){
		$configFileName = basename($argConfigPath);
		$generatedConfigFileName = dirname(dirname(__FILE__)).'/generations/'.$configFileName.'.generated.php';
		// タブ文字削除
		$configGeneratClassDefine = str_replace("\t", "", $configGeneratClassDefine);
		// 改行文字削除
		$configGeneratClassDefine = str_replace(array("\r","\n"), "", $configGeneratClassDefine);
		file_put_contents($generatedConfigFileName, '<?php' . PHP_EOL . $configGeneratClassDefine . PHP_EOL . '?>');
		@chmod($generatedConfigFileName,0777);
		// 静的ファイル化されたコンフィグクラスファイルを読み込んで終了
		require_once $generatedConfigFileName;
	}

	return TRUE;

}

// 共通configurationの読み込み
loadConfig(dirname(__FILE__).'/config.xml');
loadConfig(dirname(__FILE__).'/' . $corefilename . '.config.xml');

if(defined($corefilename . '_CONFIG_XML_PATH')){
	loadConfig(constant($corefilename . '_CONFIG_XML_PATH'));
}

// パス関連の定数をset_include_pathする
foreach(get_defined_constants() as $constKey => $val){
	if(!preg_match('/.+_INCLUDE_PATH$/',$constKey) && !preg_match('/^PHP_.+_PATH$/',$constKey) && !preg_match('/.+USE.*_PATH$/',$constKey) && preg_match('/.+_PATH$/',$constKey)){
		set_include_path(get_include_path().PATH_SEPARATOR.$val);
	}
}

if(TRUE === class_exists("Config", FALSE)){
	$paths = Config::constant(".+_PATH$",TRUE);
	foreach($paths as $key => $val){
		set_include_path(get_include_path().PATH_SEPARATOR.$val);
	}
}

/*------------------------------ 以下手続き型処理 ココまで ------------------------------*/

?>