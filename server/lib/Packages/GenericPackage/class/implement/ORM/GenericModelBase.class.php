<?php

/**
 * モデルクラスの親クラス
 */
class GenericModelBase {

	/**
	 * 操作対象DBオブジェクト
	 * @var instance
	 */
	private $_DBO;

	/**
	 * 操作時に式を使うフィールドの一覧
	 * @var string
	 */
	private $_exec;

	/**
	 * 対象テーブル名
	 * @var string
	 */
	public $tableName = NULL;

	/**
	 * 対象テーブルのシーケンス取得クエリ-MySQL用
	 * @var string
	 */
	public $sequenceSelectQueryForMySQL = NULL;

	/**
	 * 対象テーブルのシーケンス取得クエリ-Postgres用
	 * @var string
	 */
	public $sequenceSelectQueryForPostgre = NULL;

	/**
	 * 対象テーブルのシーケンス取得クエリ-Oracle用
	 * @var string
	 */
	public $sequenceSelectQueryForOracle = NULL;

	/**
	 * 対象レコードのプライマリーキー
	 * @var mixid(string or numeric)
	 */
	public $pkey = NULL;

	/**
	 * 対象レコードの複合プライマリーキー一覧
	 * @var array
	 */
	public $pkeys = NULL;

	/**
	 * 対象レコードのプライマリーキーのキー名
	 * @var string
	 */
	public $pkeyName = NULL;

	/**
	 * フィール定義情報一覧
	 */
	public $describes = array();

	/**
	 * レコードの読み込みを行ったかどうか
	 */
	public $loaded = FALSE;

	/**
	 * コンストラクタ
	 */
	public function __construct($argDBO, $argExtractionCondition=NULL, $argBinds=NULL){
		$this->_DBO = $argDBO;
		$this->load($argExtractionCondition, $argBinds);
	}

	/**
	 * レコードの読み込み
	 */
	public function load($argExtractionCondition=NULL, $argBinds=NULL){
		// 抽出条件が指定されている場合はそれに準じる
		if(NULL !== $argExtractionCondition){
			if(TRUE === is_array($argExtractionCondition)){
				// 配列なら抽出結果をセットされいる
				$this->init($argExtractionCondition);
			}
			else{
				if(FALSE !== strpos($argExtractionCondition,"SELECT") && strpos($argExtractionCondition,"SELECT") <= 1){
					// SELECT文での指定
					$response = $this->_DBO->execute($argExtractionCondition . " ");
				}
				elseif(strlen($argExtractionCondition) > 0){
					// フィールド指定句を生成
					foreach($this->describes as $key => $val){
						if("date" === $val["type"] && TRUE === ("postgres" === $this->_DBO->DBType || "oracle" === $this->_DBO->DBType)){
							// PostgresとOracleはTO_CHARを使って文字列を所定のフォーマットで取得する
							$fields[] = "TO_CHAR(".$key.", 'yyyy-mm-dd hh24:mi:ss')";
						}else{
							$fields[] = $key;
						}
					}
					$field = implode(",", $fields);
					// 抽出条件が何なのか
					// WHERE句での指定
					if(FALSE !== strpos($argExtractionCondition,"WHERE") && strpos($argExtractionCondition,"WHERE") <= 1){
						$response = $this->_DBO->execute("SELECT " . $field. " FROM " . $this->tableName . " " . $argExtractionCondition . " ", $argBinds);
					}
					elseif(FALSE !== strpos($argExtractionCondition,"=")){
						// 条件のみでの指定
						$response = $this->_DBO->execute("SELECT " . $field. " FROM " . $this->tableName . " WHERE " . $argExtractionCondition . " ", $argBinds);
					}else{
						// Pkey指定として扱う
						$binds = array();
						$binds[$this->pkeyName] = $argExtractionCondition;
						$response = $this->_DBO->execute("SELECT " . $field. " FROM " . $this->tableName . " WHERE " . $this->pkeyName . " = :" .$this->pkeyName . " ", $binds);
					}
				}
				else{
					// 不明な形式指定
					throw new Exception("");
				}
				// responceを評価
				if(FALSE === $response){
					// レコードの抽出に失敗
					throw new Exception("");
				}
				$recordCnt = $response->RecordCount();
				if( 1 < $recordCnt){
					// ADODBインスタンスの場合、1レコードのみ許可
					// 1件以上の結果が帰ってくる指定はエラー
					throw new Exception("");
				}elseif(1 === $recordCnt){
					// インスタンスに値をセット
					$this->init($response->fields);
					$this->pkey = $response->fields[$this->pkeys[0]];
					// saveはアップデートモードへ
					$this->loaded = TRUE;
				}
			}
		}
	}

	public function __call($argMethodName, $arguments){
		if(0 === strpos($argMethodName, "set") || 0 === strpos($argMethodName, "validate") || 0 === strpos($argMethodName, "is")){
			$key = substr($argMethodName, 3);
			$key = lcfirst($key);
			$key = preg_replace("/([A-Z])/", "_$1", $key);
			$key = strtolower($key);
			if(0 === strpos($argMethodName, "set")){
				$this->set($key, $arguments[0]);
			}
			elseif(0 === strpos($argMethodName, "validate")){
				return $this->validate($key, $arguments[0]);
			}
		}
	}

	/**
	 * 連想配列で値をセット
	 * replace属性が書き変わらない値のセット
	 * @param array $argments
	 */
	public function init($argments){
		$fields = array_keys($argments);
		for($fieldNum = 0; count($fields) > $fieldNum; $fieldNum++){
			$key = strtolower($fields[$fieldNum]);
			$this->{$key} = $argments[$fields[$fieldNum]];
			$this->describes[$argKey]["replace"] = FALSE;
			$this->describes[$argKey]["before"] = NULL;
		}
	}

	/**
	 * 連想配列で値をセット
	 * @param array $argments
	 */
	public function sets($argments){
		$fields = array_keys($argments);
		for($fieldNum = 0; count($fields) > $fieldNum; $fieldNum++){
			$key = strtolower($fields[$fieldNum]);
			$this->set($key, &$argments[$fields[$fieldNum]]);
		}
	}

	/**
	 * 割り当て
	 * @param string $argKey
	 * @param mixid $argVal
	 */
	public function set($argKey,&$argVal){
		// 次のDB操作で変更するフィールドとして登録
		$this->describes[$argKey]["replace"] = TRUE;
		$this->describes[$argKey]["before"] = $this->{$argKey};

		$this->{$argKey} = $argVal;
	}

	public function save($argReplaced=TRUE, $argments=NULL){
		// insertかupdateかを決める
		$insert = FALSE;
		if(FALSE === $argReplaced){
			// 強制インサート
			$insert = TRUE;
		}
		elseif(FALSE === $this->loaded){
			// アップデート
			$insert = TRUE;
		}
		if(is_array($argments)){
			// $argmentsが配列ならargmentsの値をセット
			$this->sets($argments);
		}
		$replaceFields = array();
		$replaceCLOBFields = array();
		$replaceBLOBFields = array();
		$binds = array();
		if(TRUE === $insert){
			// インサート処理
			foreach($this->describes as $key => $val){
				if(isset($val["replace"]) && TRUE === $val["replace"]){
					if("clob" === $val["type"]){
						$replaceCLOBFields[$key] = $this->{$key};
					}
					elseif("blob" === $val["type"]){
						$replaceBLOBFields[$key] = $this->{$key};
					}
					elseif("date" === $val["type"]){
						if("mysql" === $this->_DBO->DBType || "postgres" === $this->_DBO->DBType){
							// mysqlとPostgresはそのまま文字列を与えられる
							$replaceFields[$key] = " :".$key;
						}
						elseif("oracle" === $this->_DBO->DBType){
							// Oralceでは日付型はTO_DATE関数で変換する
							$replaceFields[$key] = " TO_DATE(:" . $key . " , 'yyyy-mm-dd hh24:mi:ss')";
						}
						else{
							// 未対応のDBエンジン
							throw new Exception("");
						}
						$binds[$key] = $this->{$key};
					}
					else{
						$replaceFields[$key] = " :".$key;
						$binds[$key] = $this->{$key};
					}
				}
				if(TRUE === $val["exec"]){
					$replaceFields[$key] = " ".$this->_exec[$key]." ";
				}
			}
			// インサートする新しいシーケンスIDの取得に関する処理
			if(NULL !== $this->pkeyName && !isset($replaceFields[$this->pkeyName])){
				// Insertでpkeyにあたるキーの値がセットされていなければシーケンス処理を試みる
				if("mysql" === $this->_DBO->DBType){
					if(isset($this->describes[$this->pkeyName]["autoincrement"]) && TRUE !== $this->describes[$this->pkeyName]["autoincrement"]){
						// mysqlでも独自シーケンスを使っているのであらばそれに準拠する
						if(isset($sequenceSelectQueryForMySQL) && NULL !== $sequenceSelectQueryForMySQL && strlen($sequenceSelectQueryForMySQL) > 0){
							// 独自シーケンス取得SQLが定義済みならばそれを使う
							$seqSql = $sequenceSelectQueryForMySQL;
						}else{
							// 独自シーケンス取得SQLが未定義済みならばフレームワーク固有のSQLを実行
							$seqSql = "UPDATE ".$this->tableName."_".$this->pkeyName."_seq SET id=LAST_INSERT_ID(id+1)";
							$response = $this->_DBO->execute($seqSql);
							if(FALSE === $response){
								throw new Exception("");
							}
							$seqSql .= "SELECT LAST_INSERT_ID() as new_id FROM ".$this->tableName."_".$this->pkeyName."_seq";
						}
					}
					else{
						// インサート完了後、mysqlのlast_insert_idを使う
						$lastInsertIdEnabled = TRUE;
					}
				}
				elseif("postgres" === $this->_DBO->DBType){
					// posgreは先にシーケンスを取れるので取っておく
					if(isset($sequenceSelectQueryForMySQL) && NULL !== $sequenceSelectQueryForMySQL && strlen($sequenceSelectQueryForMySQL) > 0){
						// 独自シーケンス取得SQLが定義済みならばそれを使う
						$seqSql = $sequenceSelectQueryForMySQL;
					}else{
						// 独自シーケンス取得SQLが未定義済みならばフレームワーク固有のSQLを実行
						$seqSql .= "SELECT nextval(".$this->tableName."_".$this->pkeyName."_seq) as new_id";
					}
				}
				elseif("oracle" === $this->_DBO->DBType){
					// oracleは先にシーケンスを取れるので取っておく
					if(isset($sequenceSelectQueryForMySQL) && NULL !== $sequenceSelectQueryForMySQL && strlen($sequenceSelectQueryForMySQL) > 0){
						// 独自シーケンス取得SQLが定義済みならばそれを使う
						$seqSql = $sequenceSelectQueryForMySQL;
					}else{
						// 独自シーケンス取得SQLが未定義済みならばフレームワーク固有のSQLを実行
						$seqSql .= "SELECT ".$this->tableName."_".$this->pkeyName."_seq.NEXTVAL as new_id FROM DUAL";
					}
				}
				else{
					// 未対応のDBエンジン
					throw new Exception("");
				}
				if(!(isset($lastInsertIdEnabled) && TRUE === $lastInsertIdEnabled)){
					$response = $this->_DBO->execute($seqSql);
					if(FALSE === $response){
						throw new Exception("");
					}else{
						$responseArr = $response->GetAll();
						$pkey = $responseArr[0]["new_id"];
					}
					$replaceFields[$this->pkeyName] = $pkey;
				}
			}
			// インサート文
			$sql = "INSERT INTO " . $this->tableName." ";
			$sql .= "(" . implode(",", array_keys($replaceFields)) . ") ";
			$sql .= "VALUES (" . implode(" , ", $replaceFields) . " ) ";

			// DB操作実行
			$response = $this->_DBO->execute($sql, $binds);
			if(FALSE === $response){
				throw new Exception("");
			}

			// MySQLのauto_increment用処理
			if(isset($lastInsertIdEnabled) && TRUE === $lastInsertIdEnabled){
				$response = $this->_DBO->execute("SELECT LAST_INSERT_ID() AS new_id FROM " . $this->tableName . " LIMIT 1");
				if(FALSE === $response){
					throw new Exception("");
				}else{
					$responseArr = $response->GetAll();
					$pkey = $responseArr[0]["new_id"];
				}
			}

			// CLOBを処理する
			$replaceCLOBFieldKeys = array_keys($replaceCLOBFields);
			for($replaceCLOBFieldsNum =0; count($replaceCLOBFields) > $replaceCLOBFieldsNum; $replaceCLOBFieldsNum++){
				$key = $replaceCLOBFieldKeys[$replaceCLOBFieldsNum];
				$where = "1=1";
				for($pkeyNum=0; count($this->pkeys) > $pkeyNum; $pkeyNum++){
					$where .= " AND " . $this->pkeys[$pkeyNum] . " = " . $this->{$this->pkeys[$pkeyNum]};
				}
				if(FALSE === $this->_DBO->updateClob($this->tableName, $key, $replaceCLOBFields[$key], $where)){
					throw new Exception("");
				}
			}
			// BLOBを処理する
			$replaceBLOBFieldKeys = array_keys($replaceBLOBFields);
			for($replaceBLOBFieldsNum =0; count($replaceBLOBFields) > $replaceBLOBFieldsNum; $replaceBLOBFieldsNum++){
				$key = $replaceBLOBFieldKeys[$replaceBLOBFieldsNum];
				$where = "1=1";
				for($pkeyNum=0; count($this->pkeys) > $pkeyNum; $pkeyNum++){
					$where .= " AND " . $this->pkeys[$pkeyNum] . " = " . $this->{$this->pkeys[$pkeyNum]};
				}
				if(FALSE === $this->_DBO->updateBlob($this->tableName, $key, $replaceBLOBFields[$key], $where)){
					throw new Exception("");
				}
			}

			// DB操作に成功したのでpkeyの置き換えを行う
			if(isset($pkey) && count($pkey) > 0){
				$this->pkey = $pkey;
				$this->{$this->pkeyName} = $pkey;
			}

		}else{
			// アップデート処理
			foreach($this->describes as $key => $val){
				if(isset($val["replace"]) && TRUE === $val["replace"]){
					if("clob" === $val["type"]){
						$replaceCLOBFields[$key] = $this->{$key};
					}
					elseif("blob" === $val["type"]){
						$replaceBLOBFields[$key] = $this->{$key};
					}
					elseif("date" === $val["type"]){
						if("mysql" === $this->_DBO->DBType || "postgres" === $this->_DBO->DBType){
							// mysqlとPostgresはそのまま文字列を与えられる
							$replaceFields[$key] = " ".$key." = :".$key;
						}
						elseif("oracle" === $this->_DBO->DBType){
							// MySQLでは日付型はTO_DATE関数で変換する
							$replaceFields[$key] = " ".$key." = TO_DATE(:" . $key . " , 'yyyy-mm-dd hh24:mi:ss')";
						}
						else{
							// 未対応のDBエンジン
							throw new Exception("");
						}
						$binds[$key] = $this->{$key};
					}
					else{
						$replaceFields[$key] = " ".$key." = :".$key;
						$binds[$key] = $this->{$key};
					}
				}
				if(TRUE === $val["exec"]){
					$replaceFields[$key] = " ".$key." = ".$this->_exec[$key];
				}
			}
			// アップデート文
			$sql = "UPDATE ".$this->tableName." ";
			$sql .= "SET " . implode(" , ", $replaceFields) . " ";
			$sql .= "WHERE 1=1 ";
			if(NULL !== $this->pkeys && TRUE === is_array($this->pkeys) && count($this->pkeys) > 1){
				// 複合プライマリーキーの為の処理
				foreach($this->pkeys as $key => $val){
					$sql .= "AND " . $key . " = :pkey_".$key . " ";
					$binds["pkey_".$key] = $val;
				}
			}
			elseif(NULL !== $this->pkeyName){
				// 単一プライマリーキーの処理
				$sql .= "AND " . $this->pkeyName . " = :pkey_".$this->pkeyName . " ";
				$binds["pkey_".$this->pkeyName] = $this->pkey;
			}
			else{
				// プライマリーキーが無いレコードなので
				foreach($replaceFields as $key => $val){
					$sql .= "AND " . $key . " = :before_".$key . " ";
					$binds["before_".$key] = $this->describes[$key]["before"];
				}
			}

			// DB操作実行
			$response = $this->_DBO->execute($sql, $binds);
			if(FALSE === $response){
				throw new Exception("");
			}

			// CLOBを処理する
			$replaceCLOBFieldKeys = array_keys($replaceCLOBFields);
			for($replaceCLOBFieldsNum =0; count($replaceCLOBFields) > $replaceCLOBFieldsNum; $replaceCLOBFieldsNum++){
				$key = $replaceCLOBFieldKeys[$replaceCLOBFieldsNum];
				$where = "1=1";
				for($pkeyNum=0; count($this->pkeys) > $pkeyNum; $pkeyNum++){
					$where .= " AND " . $this->pkeys[$pkeyNum] . " = " . $this->{$this->pkeys[$pkeyNum]};
				}
				if(FALSE === $this->_DBO->updateClob($this->tableName, $key, $replaceCLOBFields[$key], $where)){
					throw new Exception("");
				}
			}
			// BLOBを処理する
			$replaceBLOBFieldKeys = array_keys($replaceBLOBFields);
			for($replaceBLOBFieldsNum =0; count($replaceBLOBFields) > $replaceBLOBFieldsNum; $replaceBLOBFieldsNum++){
				$key = $replaceBLOBFieldKeys[$replaceBLOBFieldsNum];
				$where = "1=1";
				for($pkeyNum=0; count($this->pkeys) > $pkeyNum; $pkeyNum++){
					$where .= " AND " . $this->pkeys[$pkeyNum] . " = " . $this->{$this->pkeys[$pkeyNum]};
				}
				if(FALSE === $this->_DBO->updateBlob($this->tableName, $key, $replaceBLOBFields[$key], $where)){
					throw new Exception("");
				}
			}

			// DB操作に成功したのでpkeyの置き換えを行う(Updateで置き換わってる場合があるので)
			$this->pkey = $this->{$this->pkeyName};
		}

		// DB操作に成功したのでdescribes内のreplaceとexec値を初期化しておく
		foreach($replaceFields as $key => $val){
			$this->describes[$key]["replace"] = NULL;
			$this->describes[$key]["exec"] = NULL;
		}

		return TRUE;
	}

	public function remove(){
		$sql = "DELETE FROM " . $this->tableName . " WHERE 1=1 ";
		for($pkeyNum=0; count($this->pkeys) > $pkeyNum; $pkeyNum++){
			$sql .= " AND " . $this->pkeys[$pkeyNum] . " = " . $this->{$this->pkeys[$pkeyNum]};
		}
		// DB操作実行
		$response = $this->_DBO->execute($sql);
		if(FALSE === $response){
			throw new Exception("");
		}
	}

	/**
	 * NULL値の許容設定に合致しているかどうかを判定する
	 * @param string $argKey
	 * @param mixid $argValue
	 * @throws Exception
	 */
	public function validateNULL($argKey, $argValue){
		if(FALSE === $this->describes[$argKey]["null"] && NULL === $argValue){
			// NULLが許可されていないのに、NULL値が使われようとしているのでエラー
			throw new Exception("NULL");
		}
	}

	public function validateType($argKey,$argValue){
		if("string" === $this->describes[$argKey]["type"] && FALSE === is_string($argValue)){
			// 文字列型チェック
			throw new Exception("TYPE MISSMATCH STRING");
		}
		elseif("blob" === $this->describes[$argKey]["type"] && FALSE === is_string($argValue)){
			// BLOB型チェック
			throw new Exception("TYPE MISSMATCH BLOB");
		}
		elseif("clob" === $this->describes[$argKey]["type"] && FALSE === is_string($argValue)){
			// CLOB型チェック
			throw new Exception("TYPE MISSMATCH CLOB");
		}
		elseif("bigint" === $this->describes[$argKey]["type"] && 0 === preg_match("/^[0-9]{1,20}$/", $argValue)){
			// BIGINT型チェック
			throw new Exception("TYPE MISSMATCH BIGINT");
		}
		elseif("date" === $this->describes[$argKey]["type"] && 0 === preg_match("/^[1-9][0-9]{3}\-[0-9]{2}\-/[0-9]{2}\s[0-9]{2}:[0-9]{2}:[0-9]{2}$/", $argValue)){
			// 日付型チェック
			throw new Exception("TYPE MISSMATCH DATE");
		}
		elseif("int" === $this->describes[$argKey]["type"] && FALSE === is_numeric($argValue)){
			// 数値型チェック
			throw new Exception("TYPE MISSMATCH INT");
		}
		return TRUE;
	}

	public function validateLength($argKey, $argValue){
		if(!isset($this->describes[$argKey]["lenght"])){
			// lenghtの厳密な制限事項ナシ
			return TRUE;
		}
		if(FALSE !== strpos(",", $this->describes[$argKey]["lenght"])){
			$valuelengths = explode(",", (string)$argValue);
			$typelengths = explode(",", $this->describes[$argKey]["lenght"]);
			if(strlen($valuelengths[0]) <= (int)$typelengths[0] && strlen($valuelengths[1]) <= (int)$typelengths[1]){
				return TRUE;
			}
		}
		elseif(strlen($argValue) <= (int)$this->describes[$argKey]["lenght"]){
			return TRUE;
		}
		throw new Exception("LENGTH OVER ".$num);
	}

	public function validate($argKey,$argValue){
		// NULLチェック
		$this->validateNULL($argKey,$argValue);
		// 型チェック
		$this->validateTYPE($argKey,$argValue);
		// 桁チェック
		$this->validateLength($argKey,$argValue);
		return TRUE;
	}
}

?>