<?php
	namespace z;
	abstract class sql{
		protected $DB_ERROR;//错误信息
		protected $DB_TABLE;
		protected $DB_TABLES;
		protected $DB_PRIKEY;
		protected $DB_DELTABLE;
		protected $DB_SUBNAME;
		protected $DB_LOGIC;
		protected $DB_WHERE;//WHERE条件数组
		protected $DB_BINDVAL;//绑定的参数数组
		protected $DB_FIELDS;
		protected $DB_FIELD='*';
		protected $DB_JOIN;
		protected $DB_LIMIT;
		protected $DB_ORDER;
		protected $DB_GROUP;
		protected $DB_HAVING;
		protected $DB_PAGE;
		protected $DB_R=0;
		protected $DB_SQL;
		protected $DB_ARGS;
		protected $DB_PREFIX;
		/**
		 * 获取表前缀
		 */
		public function getPrefix(){
			return $this->DB_PREFIX;
		}

		/**
		 * 获取错误信息
		 * @param  boolean $arr [是否返回数组]
		 * @return [type]       [description]
		 */
		public function getError($isarr=false){
			if(empty($this->DB_ERROR)) return NULL;
			return $isarr || is_string($this->DB_ERROR) ? $this->DB_ERROR : implode("<br>",$this->DB_ERROR);
		}

		/**
		 * 获取最后执行的sql语句
		 * @return [string] [description]
		 */
		public function getSql(){
			return $this->DB_SQL;
		}

		/**
		 * 获取绑定的参数
		 * @return [array] [description]
		 */
		public function getArgs(){
			return $this->DB_ARGS;
		}

		/**
		 * 获取分页数据
		 * @return [array] [description]
		 */
		public function getPage(){
			return $this->DB_PAGE??null;
		}

		/**
		 * 设置要操作的字段
		 * @param  [string] $field [description]
		 * @return [type]        [description]
		 */
		public function field($field){
			if(!empty($field['EXCEPT'])){
				$except = is_array($field['EXCEPT']) ? $field['EXCEPT'] : explode(',',$field['EXCEPT']);
				unset($field['EXCEPT']);
				$field += array_diff($this->getFields(),$except,array_keys($field));
			}
			if(is_array($field)){
				foreach($field as $k=>$v){
					$field_arr[] = is_numeric($k) ? $this->DB_setField($v) : $this->DB_setField($k) . " AS `{$v}`";
				}
				$this->DB_FIELD = implode(',',$field_arr);
			}else{
				$this->DB_FIELD = $field;
			}
			return $this;
		}

		/**
		 * 设置要操作的数据表
		 * @param  [string] $table [description]
		 * @return [type]        [description]
		 */
		public function table($table=null){
			if($table && ($table != $this->DB_SUBNAME || strpos($this->DB_TABLE,' '))){
				$this->DB_SUBNAME = $table;
				$this->DB_TABLE = $this->DB_tableName($table);
			}
			return $this;
		}

		/**
		 * 设置表别名
		 * @param  [string] $name [description]
		 * @return [object]       [description]
		 */
		public function alias($name=null){
			$name && $this->DB_TABLE = "`{$this->DB_PREFIX}{$this->DB_SUBNAME}` AS {$name}";
			return $this;
		}

		/**
		 * 设置where条件
		 * @param  [type] $where [description]
		 * @return [type]        [description]
		 */
		public function where($where=null,$arr=null){
			if(!$where){
				return $this;
			}
			if(is_array($where)){
				if(!$this->DB_isAssoc($where)){
					if(2 == count($where) && is_array($where[1])){
						$this->DB_LOGIC[] = strtoupper($where[0]);
						$this->DB_WHERE[] = $this->DB_whereArr($where[1]);
					}else{
						$prikey = $this->getPrimaryKey();
						$this->DB_LOGIC[] = 'AND';
						$this->DB_WHERE[] = $this->DB_bind($prikey[0],$where,'IN');
					}
				}else{
					$this->DB_WHERE[] = $this->DB_whereArr($where);
				}
			}elseif(is_numeric($where)){
				$prikey = $this->getPrimaryKey();
				$this->DB_LOGIC[] = 'AND';
				$this->DB_WHERE[] = $this->DB_bind($prikey[0],$where);
			}else{
				$this->DB_LOGIC[] = 'AND';
				$this->DB_WHERE[] = $this->DB_whereStr($where,$arr);
			}
			return $this;
		}

		/**
		 * 设置join条件
		 * @param  [string] $join [description]
		 * @return [object]       [description]
		 */
		public function join($join=null){
			if(!$join){
				return $this;
			}
			switch(gettype($join)){
				case 'string':
					$join = $this->DB_tojoin($join);
					$join && $this->DB_JOIN[] = $join;
				break;
				case 'array':
					foreach($join as $v){
						$join = $this->DB_tojoin($v);
						$join && $this->DB_JOIN[] = $join;
					}
				break;
			}
			return $this;
		}

		/**
		 * 设置返回行数
		 * @param  integer $start  [起始行]
		 * @param  integer $number [中止行]
		 * @return [object]        [description]
		 */
		public function limit($start,$number=0){
			if($number){
				$this->DB_LIMIT = "{$start},{$number}";
			}else{
				$this->DB_LIMIT = is_array($start) ? "{$start[0]},{$start[1]}" : "{$start}";
			}
			return $this;
		}

		/**
		 * 设置排序
		 * @param  [type] $order [description]
		 * @return [type]        [description]
		 */
		public function order($order=null){
			if(!$order){
				return $this;
			}
			if(strpos($order,',')){
				$order = explode(',',$order);
				foreach($order as $k=>$v){
					$order[$k] = $this->DB_setField($v);
				}
				$order = implode(',',$order);
			}else{
				$order = $this->DB_setField($order);
			}
			$this->DB_ORDER = "{$order}";
			return $this;
		}

		/**
		 * 设置分组
		 * @param  [type] $field [description]
		 * @return [type]        [description]
		 */
		public function group($field=null){
			if(!$field){
				return $this;
			}
			if(strpos($field,',')){
				$field = explode(',',$field);
				foreach($field as $k=>$v){
					$field[$k] = $this->DB_setField($v);
				}
				$field = implode(',',$field);
			}else{
				$field = $this->DB_setField($field);
			}
			$this->DB_GROUP = "{$field}";
			return $this;
		}

		/**
		 * 聚合条件
		 * @param  [type] $having [description]
		 * @return [type]         [description]
		 */
		public function having($having=null,$arr=null){
			if(!$having){
				return $this;
			}
			switch(gettype($having)){
				case 'string' :
					$this->DB_HAVING = $this->DB_whereStr($having,$arr);
				break;
				case 'array' :
					$this->DB_HAVING = $this->DB_whereArr($having);
				break;
				default :
					throw new \PDOException("having参数错误:{$having}");
				break;
			}
			return $this;
		}
		
		private function DB_tojoin($join=null){
			stristr($join,'join') || $join = "RIGHT JOIN {$join}";
			$preg = '/(.+(JOIN|join)\s+)(\w+)\s+(.+)/';
			$sql = preg_replace_callback($preg,function($match){
				return "{$match[1]} `{$this->DB_PREFIX}{$match[3]}` {$match[4]}";
			},$join);
			if(!$sql || $sql == $join){
				throw new \PDOException("join语句错误:{$join}");
			}
			return $sql;
		}
		
		protected function DB_isAssoc($arr){
			return !is_numeric(implode('',array_keys($arr)));
		}
		protected function DB_bindKey($key){
			return ':'.str_replace(['(',')','.'],['','','_'],$key);
		}
		protected function DB_key($key){
			$preg = '#([\w\s]*)(\()?(\b\w+\.)?(\b\w+\b)#';
			return preg_replace($preg,'$1$2$3`$4`',$key);
		}
		protected function DB_bindStr($value){
			$preg = '/^\{(.+)\}$/';
			return preg_match($preg,$value,$match) ? $this->DB_key($match[1]) : false;
		}

		protected function DB_checkKey($key){
			$preg = '/(\&|\|AND\s+|OR\s+)?\s*(\S+)(\s*[\<\>\=\!]+|\s+(IN|NOT\s+IN|BETWEEN|NOT\s+BETWEEN|LIKE|NOT\s+LIKE))?/i';
			if(preg_match($preg,$key,$match)){
				switch($match[1]){
					case '&':
						$return['logic'] = 'AND';
					break;
					case '|':
						$return['logic'] = 'OR';
					break;
					default:
						$return['logic'] = trim($match[1]??'');
					break;
				}
				$return['key'] = $match[2];
				$return['operator'] = trim($match[3]??'');
			}else{
				$return['key'] = $key;
				$return['logic'] = false;
				$return['operator'] = false;
			}
			strpos($return['key'],'|') && $return['key'] = explode('|',$return['key']);
			return $return;
		}

		protected function DB_checkLike($value){
			$preg = '/^\%(.+)\%$/';
			return preg_match($preg,trim($value),$match) ? $match[1] : false;
		}

		protected function DB_setField($field){
			$preg = '/^(\w+)$/';
			if(preg_match($preg,$field)){
				return "`{$field}`";
			}elseif(preg_match('/^(\w+)\s+(\w+)$/',$field,$match)){
				$field = in_array($match[1],['ALL','DISTINCT','DISTINCTROW','TOP']) ? $match[1] . " `$match[2]`" : "`$match[1]` " . strtoupper($match[2]);
			}elseif($field != $_field = preg_replace('/(\w+\.)(\w+)/',"$1`$2`",$field)){
				$field = $_field;
			}elseif($field != $_field = preg_replace('/\((\w+)\)/',"(`$1`)",$field)){
				$field = $_field;
			}
			return $field;
		}

		protected function DB_cl(){
			$this->DB_ORDER = null;
			$this->DB_ARGS = $this->DB_BINDVAL;
			$this->DB_WHERE = null;
			$this->DB_LOGIC = null;
			$this->DB_BINDVAL = null;
			$this->DB_JOIN = null;
			$this->DB_DELTABLE = null;
			$this->DB_TABLES = null;
			$this->DB_FIELD = '*';
			$this->DB_HAVING = null;
			$this->DB_GROUP = null;
			$this->DB_R = 0;
			$this->DB_LIMIT = null;
		}
		protected function DB_bindValue($name,$value=null){
			$this->DB_BINDVAL[$name] = $value;
		}
		protected function DB_bind($key,$value,$operator='='){
			$key = trim($key);
			$bind_key = $this->DB_bindKey($key);
			$_key = $this->DB_key($key);
			if(is_array($value)){
				$between_arr = ['between','BETWEEN','not between','NOT BETWEEN'];
				if(in_array($operator,$between_arr)){
					$bind_key1 = "{$bind_key}_" . ($this->DB_R ++);
					$this->DB_BINDVAL[$bind_key1] = $value[0];
					$bind_key2 = "{$bind_key}_" . ($this->DB_R ++);
					$this->DB_BINDVAL[$bind_key2] = $value[1];
					$where = "{$_key} {$operator} {$bind_key1} AND {$bind_key2}";
				}else{
					foreach($value as $v){
						$sub_key_arr[] = $sub_key = "{$bind_key}_" . ($this->DB_R ++);
						$this->DB_BINDVAL[$sub_key] = $v;
					}
					$where = "{$_key} {$operator} (" . implode(',',$sub_key_arr) . ')';
				}
			}else{
				$key_str = $this->DB_bindStr($value);//参数被{}包裹代表是字段名，不绑定参数。
				if($key_str){
					$where = "{$_key} {$operator} {$key_str}";
				}else{
					isset($this->DB_BINDVAL[$bind_key]) && $bind_key = "{$bind_key}_" . (++$this->DB_R);
					$where = "{$_key} {$operator} {$bind_key}";
					$this->DB_BINDVAL[$bind_key] = $value;
				}
			}
			return $where;
		}
		protected function DB_tableName($table){
			$table = trim($table);
			if(strpos($table,',')){
				$table = explode(',',$table);
				foreach($table as $v){
					$v = trim($v);
					if(strpos($v,' ')){
						$tableName_arr = explode(' ',$v);
						$tableName = array_shift($tableName_arr);
						$this->DB_TABLES && in_array($tableName,$this->DB_TABLES) || $this->DB_TABLES[] = $tableName;
						$tableArr[] = "{$tableName}` " . implode(' ',$tableName_arr);
					}else{
						in_array($v,$this->DB_TABLES) || $this->DB_TABLES[] = $v;
						$tableArr[] = "{$v}`";
					}
				}
				$table_name = "`{$this->DB_PREFIX}" . implode(",`{$this->DB_PREFIX}",$tableArr);
			}else{
				if(strpos($table,' ')){
					$tableName_arr = explode(' ',$table);
					$this->DB_SUBNAME = array_shift($tableName_arr);
					!$this->DB_TABLES || !in_array($this->DB_SUBNAME,$this->DB_TABLES) && $this->DB_TABLES[] = $this->DB_SUBNAME;
					$table = "{$this->DB_SUBNAME}` " . implode(' ',$tableName_arr);
					$table_name = "`{$this->DB_PREFIX}{$table}";
				}else{
					$table_name = "`{$this->DB_PREFIX}{$table}`";
					empty($this->DB_TABLES) || !in_array($table,$this->DB_TABLES) && $this->DB_TABLES[] = $table;
				}
			}
			return $table_name;
		}
		protected function DB_whereArr($where){
			$sql = '';
			foreach($where as $k=>$value){
				$ch = $this->DB_checkKey($k);
				$key = $ch['key'];
				$logic = $ch['logic'] ?: 'AND';
				$operator = $ch['operator'] ?: '=';
				$sql || $this->DB_LOGIC[] = $logic;

				if(is_array($value)){
					if(!$value) continue;
					switch($operator){
						case '<>':
						case '!=':
							$operator = 'NOT IN';
						break;
						default:
							$operator = 'IN';
						break;
					}
				}elseif(self::DB_checkLike($value)){
					switch($operator){
						case '=':
							$operator = 'LIKE';
						break;
						case '<>':
						case '!=':
							$operator = 'NOT LIKE';
						break;
					}
				}elseif('SELECT' == strtoupper(substr($value,0,6))){
					$operator || $operator = 'IN';
					$sql && $sql .= " {$logic} ";
					$sql .= "{$key} {$operator} ({$value})";
					continue;
				}

				if(is_array($key)){
					$subSql[] = [];
					foreach($key as $kk){
						$subSql[] = $this->DB_bind(trim($kk),$value,$operator);
					}
					$sql && $sql .= " {$logic} ";
					$sql .= '(' . implode(' OR ',$subSql) . ')';
				}else{
					$sql && $sql .= " {$logic} ";
					$sql .= $this->DB_bind($key,$value,$operator);
				}
			}
			return $sql;
		}

		protected function DB_whereStr($where,$arr=null){
			if($arr){
				foreach($arr as $k=>$v){
					if(is_array($v)){
						foreach($v as $a){
							$bind_keys[] = $bind_key = "{$k}_" . (++$this->DB_R);
							$this->DB_BINDVAL[$bind_key] = $a;
						}
						$find[] = $k;
						$replace[] = implode(',',$bind_keys);
					}else{
						if(isset($this->DB_BINDVAL[$k])){
							$bind_key = "{$k}_" . (++$this->DB_R);
							$find[] = $k;
							$replace[] = $bind_key;
						}else{
							$bind_key = $k;
						}
						$this->DB_BINDVAL[$bind_key] = $v;
					}
				}
				isset($find) && isset($replace) && $where = str_replace($find,$replace,$where);
			}
			return $where;
		}
		protected function DB_addUpdate($data,$safe=true,$type=0){
			if(is_array($data)){
				$fields = $this->getFields();
				$preg = '/\{('.implode('|',$fields).')\}(\s*[\+\-\*\/\%\|])/';
				foreach($data as $key=>$value){
					if(!in_array($key,$fields)){
						continue;
					}
					$safe && $value = htmlspecialchars($value,ENT_QUOTES);
					$bind_key = preg_replace($preg,'`\1`\2',$value);
					if(!$bind_key || $bind_key == $value){
						$bind_key = $this->DB_bindKey($key);
						if(isset($this->DB_BINDVAL[$bind_key])) $bind_key = "{$bind_key}_" . (++$this->DB_R);
						$this->DB_bindValue($bind_key,$value);
					}
					$sql[] = $this->DB_key($key) . "={$bind_key}";
					$keys[] = $this->DB_key($key);
					$bind[] = $bind_key;
				}
				return $type ? '('.implode(',',$keys).') VALUES ('.implode(',',$bind).')' : implode(',',$sql);
			}else{
				throw new \PDOException('写入参数错误!');
			}
		}
		protected function DB_sqlWhere(){
			switch($this->DB_WHERE ? count($this->DB_WHERE) : 0){
				case 0:
					$where = null;
					break;
				case 1:
					$where = " WHERE {$this->DB_WHERE[0]}";
					break;
				default:
					$where = " WHERE";
					$this->DB_LOGIC[0] = '';
					foreach($this->DB_WHERE as $k=>$v){
						$where .= " {$this->DB_LOGIC[$k]} ({$v})";
					}
					break;
			}
			return $where;
		}
		protected function DB_SQL($field=false){
			$where = $this->DB_WHERE ? $this->DB_sqlWhere() : '';
			$join = $this->DB_JOIN ? ' ' . implode(' ',$this->DB_JOIN) : '';
			$group = $this->DB_GROUP ? " GROUP BY {$this->DB_GROUP}" : '';
			$having = $this->DB_HAVING ? " HAVING {$this->DB_HAVING}" : '';
			if($group && 'COUNT(*)' === $field){
				$sql = "SELECT COUNT(*) FROM (SELECT {$this->DB_FIELD} FROM {$this->DB_TABLE}{$join}{$where}{$group}{$having}) DB_n";
			}else{
				$field = $field ?: $this->DB_FIELD;
				$limit = $this->DB_LIMIT ? " LIMIT {$this->DB_LIMIT}" : '';
				$order = $this->DB_ORDER ? " ORDER BY {$this->DB_ORDER}" : '';
				$sql = "SELECT {$field} FROM {$this->DB_TABLE}{$join}{$where}{$group}{$having}{$order}{$limit}";
			}
			return $sql;
		}
	}