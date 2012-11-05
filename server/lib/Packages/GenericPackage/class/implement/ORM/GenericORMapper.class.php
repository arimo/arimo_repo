<?php

/**
 * モデルクラスの親クラス
 */
class GenericORMapper {

	static private $_models;

	/**
	 * コンストラクタ
	 */
	private function __construct(){
	}

	/**
	 * モデルクラスの取得
	 */
	public static function getModel($argDBO, $argModelName, $argExtractionCondition=NULL, $argBinds=NULL, $argMySQLSeqQuery=NULL, $argPostgresSeqQuery=NULL, $argOracleSeqQuery=NULL){
		//
		$tableName = $argModelName;
		$modelName = ucfirst($tableName);
		if((strlen($modelName) -5) === strpos(strtolower($modelName), "model")){
			$tableName = substr($tableName, 0, strlen($tableName)-5);
		}else{
			$modelName = $modelName."Model";
		}
		if(!isset(self::$_models[$tableName])){
			// モデルクラスの自動生成
			$baseModelClassDefine = "class " . $modelName . " extends GenericModelBase { %vars% public function __construct(\$argDBO, \$argExtractionCondition=NULL, \$argBinds=NULL){ %describes% parent::__construct(\$argDBO, \$argExtractionCondition, \$argBinds); } }";
			// テーブル定義を取得
			$describes = $argDBO->getTableDescribes($tableName);
			$describeDef = "\$this->describes = array(); ";
			$varDef = NULL;
			$pkeysVarDef = "public \$pkeys = array(";
			$pkeyCnt = 0;
			foreach($describes as $colName => $describe){
				// 小文字で揃える(Oracle向けの対応)
				$colName = strtolower($colName);
				$escape = "";
				if("int" !== $describe["type"] && "bool" !== $describe["type"]){
					$escape = "\"";
				}
				if(isset($describe["type"]) && "bool" === $describe["type"] && isset($describe["default"])){
					if(TRUE === $describe["default"]){
						$describe["default"] = "TRUE";
					}
					elseif(FALSE === $describe["default"]){
						$describe["default"] = "FALSE";
					}
				}
				if(NULL === $describe["default"]){
					$describe["default"] = "NULL";
				}
				if(TRUE === $describe["null"]){
					$describe["null"] = "TRUE";
				}
				elseif(FALSE === $describe["null"]){
					$describe["null"] = "FALSE";
				}
				if(TRUE === $describe["pkey"]){
					$describe["pkey"] = "TRUE";
				}
				elseif(FALSE === $describe["pkey"]){
					$describe["pkey"] = "FALSE";
				}
				if(TRUE === $describe["autoincrement"]){
					$describe["autoincrement"] = "TRUE";
				}
				elseif(FALSE === $describe["autoincrement"]){
					$describe["autoincrement"] = "FALSE";
				}
				$describeDef .= "\$this->describes[\"" . $colName . "\"] = array(); ";
				$describeDef .= "\$this->describes[\"" . $colName . "\"][\"type\"] = \"" . $describe["type"] . "\"; ";
				if(FALSE !== $describe["default"]){
					if("NULL" !== $describe["default"]){
						$describeDef .= "\$this->describes[\"" . $colName . "\"][\"default\"] = " . $escape . $describe["default"] . $escape . "; ";
					}
					else{
						$describeDef .= "\$this->describes[\"" . $colName . "\"][\"default\"] = " . $describe["default"] . "; ";
					}
				}
				$describeDef .= "\$this->describes[\"" . $colName . "\"][\"null\"] = " . $describe["null"] . "; ";
				$describeDef .= "\$this->describes[\"" . $colName . "\"][\"pkey\"] = " . $describe["pkey"] . "; ";
				$describeDef .= "\$this->describes[\"" . $colName . "\"][\"length\"] = \"" . $describe["length"] . "\"; ";
				$describeDef .= "\$this->describes[\"" . $colName . "\"][\"autoincrement\"] = " . $describe["autoincrement"] . "; ";
				$varDef .= "public \$" . $colName;
				if(isset($describe["default"]) && strlen($describe["default"])){
					$varDef .= " = " . $escape . $describe["default"] . $escape;
				}
				elseif(isset($describe["null"]) && "TRUE" === $describe["null"]){
					$varDef .= " = NULL";
				}
				$varDef .= "; ";
				if(0 === $pkeyCnt && isset($describe["pkey"]) && "TRUE" === $describe["pkey"]){
					$varDef .= "public \$pkeyName = \"" . $colName . "\"; ";
					$pkeyCnt++;
				}
				if(isset($describe["pkey"]) && "TRUE" === $describe["pkey"]){
					$pkeysVarDef .= "\"" . $colName . "\", ";
				}
			}
			$pkeysVarDef .= "); ";
			$varDef .= $pkeysVarDef;
			$varDef .= "public \$tableName = \"" . $tableName . "\"; ";
			$varDef .= "public \$sequenceSelectQueryForMySQL = \"" . $argMySQLSeqQuery . "\"; ";
			$varDef .= "public \$sequenceSelectQueryForPostgre = \"" . $argPostgresSeqQuery . "\"; ";
			$varDef .= "public \$sequenceSelectQueryForOracle = \"" . $argOracleSeqQuery . "\"; ";
			$baseModelClassDefine = str_replace("%vars%", $varDef, $baseModelClassDefine);
			$baseModelClassDefine = str_replace("%describes%", $describeDef, $baseModelClassDefine);
			// モデルクラス定義からクラス生成
			eval($baseModelClassDefine);
			self::$_models[$tableName] = $modelName;
		}
		return new self::$_models[$tableName]($argDBO, $argExtractionCondition, $argBinds);
	}
}

?>