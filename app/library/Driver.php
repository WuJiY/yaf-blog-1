<?php

abstract class Driver{

	//PDO操作实例
	protected $PDO = null;

	//当前模型名称
	protected $model = null;

	//当前数据库连接
	protected $linked  = null;

	//
	protected $error = '';

	//参数绑定
	protected $bind  =   array(); 

	// 数据库连接参数配置
	protected $config     = array(
		'type'              =>  '',     // 数据库类型
		'hostname'          =>  '127.0.0.1', // 服务器地址
		'database'          =>  '',          // 数据库名
		'username'          =>  '',          // 用户名
		'password'          =>  '',          // 密码
		'hostport'          =>  '',          // 端口     
		'dsn'               =>  '', 		 //          
		'params'            =>  array(), 	 // 数据库连接参数        
		'charset'           =>  'utf8',      // 数据库编码默认采用utf8  
		'prefix'            =>  '',    		 // 数据库表前缀
		'debug'             =>  false,       // 数据库调试模式
		'deploy'            =>  0, 			 // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
		'rw_separate'       =>  false,       // 数据库读写是否分离 主从式有效
		'master_num'        =>  1, 			 // 读写分离后 主服务器数量
		'slave_no'          =>  '', 		 // 指定从服务器序号
		'db_like_fields'    =>  '', 
	);

    // PDO连接参数
    protected $options = array(
        PDO::ATTR_CASE              =>  PDO::CASE_LOWER,
        PDO::ATTR_ERRMODE           =>  PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      =>  PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES =>  false,
    );

    // 数据库表达式
    protected $exp = array('eq'=>'=','neq'=>'<>','gt'=>'>','egt'=>'>=','lt'=>'<','elt'=>'<=','notlike'=>'NOT LIKE','like'=>'LIKE','in'=>'IN','notin'=>'NOT IN','not in'=>'NOT IN','between'=>'BETWEEN','not between'=>'NOT BETWEEN','notbetween'=>'NOT BETWEEN');

    // 查询表达式
	protected $selectSql  = 'SELECT %FIELD% FROM %TABLE%%WHERE%%GROUP%%ORDER%%LIMIT% %COMMENT%';
    

    /**
	 * 读取数据库配置信息
	 * @access public
	 * @param array $config 数据库配置数组
	 */
	public function __construct($config=''){
		if(!empty($config)) {
			$this->config   =   array_merge($this->config,$config);
			if(is_array($this->config['params'])){
				$this->options = $this->config['params'] + $this->options;
			}
		}
	}

    /**
     * 更新记录
     * @access public
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return false | integer
     */
    public function update($data,$options) {
        $table = $this->parseTable($options['table']);
        $sql   = 'UPDATE ' . $table . $this->parseSet($data);
        if(strpos($table,',')){// 多表更新支持JOIN操作
            $sql .= $this->parseJoin(!empty($options['join'])?$options['join']:'');
        }
        $sql .= $this->parseWhere(!empty($options['where'])?$options['where']:'');
        if(!strpos($table,',')){
            //  单表更新支持order和lmit
            $sql   .=  $this->parseOrder(!empty($options['order'])?$options['order']:'')
                .$this->parseLimit(!empty($options['limit'])?$options['limit']:'');
        }
        $sql .=   $this->parseComment(!empty($options['comment'])?$options['comment']:'');
        return $this->execute($sql,!empty($options['fetch_sql']) ? true : false);
    }


   	/**
     * 删除记录
     * @access public
     * @param array $options 表达式
     * @return false | integer
     */
    public function delete($options=array()) {
        $table = $this->parseTable($options['table']);
        $sql   = 'DELETE FROM '.$table;
        $sql .= $this->parseWhere(!empty($options['where'])?$options['where']:'');
        if(!strpos($table,',')){
            $sql .= $this->parseOrder(!empty($options['order'])?$options['order']:'')
            .$this->parseLimit(!empty($options['limit'])?$options['limit']:'');
        }
        $sql .= $this->parseComment(!empty($options['comment'])?$options['comment']:'');
        return $this->execute($sql,!empty($options['fetch_sql']) ? true : false);
	}


    /**
     * 执行查询 返回数据集
     * @access public
     * @param string $str  sql指令
     * @return mixed
     */
    public function query($str,$fetchSql=false) {
		$this->initConnect();
		if( !$this->linked ) return false;

		//释放前次的查询结果
		if ( !empty($this->PDO) ) $this->free();	
		$this->PDOStatement = $this->linked->prepare($str);
        if(false === $this->PDOStatement){
            $this->error();
            return false;
        }
        foreach ($this->bind as $key => $val) {
            if(is_array($val)){
                $this->PDOStatement->bindValue($key, $val[0], $val[1]);
            }else{
                $this->PDOStatement->bindValue($key, $val);
            }
        }
        $this->bind = array();
        $result = $this->PDOStatement->execute();
        if ( false === $result ) {
            $this->error();
            return false;
        } else {
            return $this->getResult();
        }
	}

    /**
     * 获得所有的查询数据
     * @access private
     * @return array
     */
    private function getResult() {
        //返回数据集
        $result =   $this->PDOStatement->fetchAll(PDO::FETCH_ASSOC);
        $this->numRows = count( $result );
        return $result;
    }


    /**
     * 初始化数据库连接
     * @access protected
     * @return void
	 */
	protected function initConnect() {
		$this->linked = $this->connect();
	}


	/**
	 * 连接数据库方法
	 * @access public
	 */
	public function connect($config='') {
		if(empty($config))  $config = $this->config;
		try{
			if(empty($config['dsn'])) {
				$config['dsn']  =   $this->parseDsn($config);
			}
			if(version_compare(PHP_VERSION,'5.3.6','<=')){ 
				$this->options[PDO::ATTR_EMULATE_PREPARES] = false;
			}
			$this->linked = new PDO( $config['dsn'], $config['username'], $config['password'],$this->options);
		}catch (\PDOException $e) {
			E($e->getMessage());
		}
		return $this->linked;
	}


	/**
	 * 查找记录
     * @access public
     * @param array $options 表达式
     * @return mixed
     */
    public function select($options=array()) {
        //$this->model = $options['model'];
        $sql = $this->buildSelectSql($options);
        $result = $this->query($sql,!empty($options['fetch_sql']) ? true : false);
        return $result;
    }

    /**
     * 生成查询SQL
     * @access public
     * @param array $options 表达式
     * @return string
     */
    public function buildSelectSql($options=array()) {
        if(isset($options['page'])) {
            // 根据页数计算limit
            list($page,$listRows)   =   $options['page'];
            $page    =  $page>0 ? $page : 1;
            $listRows=  $listRows>0 ? $listRows : (is_numeric($options['limit'])?$options['limit']:20);
            $offset  =  $listRows*($page-1);
            $options['limit'] =  $offset.','.$listRows;
        }
        $sql = $this->parseSql($this->selectSql,$options);
        return $sql;
    }

    /**
     * 替换SQL语句中表达式
     * @access public
     * @param array $options 表达式
     * @return string
     */
    public function parseSql($sql,$options=array()){
        $sql   = str_replace(
            array('%TABLE%','%FIELD%','%WHERE%','%GROUP%','%ORDER%','%LIMIT%','%COMMENT%'),
            array(
                $this->parseTable($options['table']),
                $this->parseField(!empty($options['field'])?$options['field']:'*'),
                $this->parseWhere(!empty($options['where'])?$options['where']:''),
                $this->parseGroup(!empty($options['group'])?$options['group']:''),
                $this->parseOrder(!empty($options['order'])?$options['order']:''),
                $this->parseLimit(!empty($options['limit'])?$options['limit']:''),
                $this->parseComment(!empty($options['comment'])?$options['comment']:''),
            ),$sql);
        return $sql;
	}

    /**
     * comment分析
     * @access protected
     * @param string $comment
     * @return string
     */
    protected function parseComment($comment) {
        return  !empty($comment)?   ' /* '.$comment.' */':'';
    }


    /**
     * limit分析
     * @access protected
     * @param mixed $lmit
     * @return string
     */
    protected function parseLimit($limit) {
        return !empty($limit)?   ' LIMIT '.$limit.' ':'';
    }

    /**
     * order分析
     * @access protected
     * @param mixed $order
     * @return string
     */
    protected function parseOrder($order) {
        if(is_array($order)) {
            $array   =  array();
            foreach ($order as $key=>$val){
                if(is_numeric($key)) {
                    $array[] =  $this->parseKey($val);
                }else{
                    $array[] =  $this->parseKey($key).' '.$val;
                }
            }
            $order   =  implode(',',$array);
        }
        return !empty($order)?  ' ORDER BY '.$order:'';
    }

    /**
     * group分析
     * @access protected
     * @param mixed $group
     * @return string
     */
    protected function parseGroup($group) {
        return !empty($group)? ' GROUP BY '.$group:'';
    }

	/**
	 * where分析
     * @access protected
     * @param mixed $where
     * @return string
     */
    protected function parseWhere($where) {
        $whereStr = '';
        if(is_string($where)) {
            $whereStr = $where;
        }else{ //使用数组表达式
            $operate  = isset($where['_logic'])?strtoupper($where['_logic']):'';
            if(in_array($operate,array('AND','OR','XOR'))){
                // 定义逻辑运算规则 例如 OR XOR AND NOT
                $operate    =   ' '.$operate.' ';
                unset($where['_logic']);
            }else{
                // 默认进行 AND 运算
                $operate    =   ' AND ';
            }
            foreach ($where as $key=>$val){
                if(is_numeric($key)){
                    $key  = '_complex';
                }
                if(0===strpos($key,'_')) {
    	            // 解析特殊条件表达式
                    $whereStr   .= $this->parseThinkWhere($key,$val);
                }else{
                    // 多条件支持
                    $multi  = is_array($val) &&  isset($val['_multi']);
                    $key    = trim($key);
                    if(strpos($key,'|')) { // 支持 name|title|nickname 方式定义查询字段
                        $array =  explode('|',$key);
                        $str   =  array();
                        foreach ($array as $m=>$k){
                            $v =  $multi?$val[$m]:$val;
                            $str[]   = $this->parseWhereItem($this->parseKey($k),$v);
                        }
                        $whereStr .= '( '.implode(' OR ',$str).' )';
                    }elseif(strpos($key,'&')){
                        $array =  explode('&',$key);
                        $str   =  array();
                        foreach ($array as $m=>$k){
                            $v =  $multi?$val[$m]:$val;
                            $str[]   = '('.$this->parseWhereItem($this->parseKey($k),$v).')';
                        }
                        $whereStr .= '( '.implode(' AND ',$str).' )';
                   }else{
                        $whereStr .= $this->parseWhereItem($this->parseKey($key),$val);
                    }
                }
                $whereStr .= $operate;
            }
            $whereStr = substr($whereStr,0,-strlen($operate));
        }
        return empty($whereStr)?'':' WHERE '.$whereStr;
    }

    // where子单元分析
    protected function parseWhereItem($key,$val) {
        $whereStr = '';
        if(is_array($val)) {
            if(is_string($val[0])) {
				$exp	=	strtolower($val[0]);
                if(preg_match('/^(eq|neq|gt|egt|lt|elt)$/',$exp)) { // 比较运算
                    $whereStr .= $key.' '.$this->exp[$exp].' '.$this->parseValue($val[1]);
                }elseif(preg_match('/^(notlike|like)$/',$exp)){// 模糊查找
                    if(is_array($val[1])) {
                        $likeLogic  =   isset($val[2])?strtoupper($val[2]):'OR';
                        if(in_array($likeLogic,array('AND','OR','XOR'))){
                            $like       =   array();
                            foreach ($val[1] as $item){
                                $like[] = $key.' '.$this->exp[$exp].' '.$this->parseValue($item);
                            }
                            $whereStr .= '('.implode(' '.$likeLogic.' ',$like).')';                          
                        }
                    }else{
                        $whereStr .= $key.' '.$this->exp[$exp].' '.$this->parseValue($val[1]);
                    }
                }elseif('bind' == $exp ){ // 使用表达式
                    $whereStr .= $key.' = :'.$val[1];
                }elseif('exp' == $exp ){ // 使用表达式
                    $whereStr .= $key.' '.$val[1];
                }elseif(preg_match('/^(notin|not in|in)$/',$exp)){ // IN 运算
                    if(isset($val[2]) && 'exp'==$val[2]) {
                        $whereStr .= $key.' '.$this->exp[$exp].' '.$val[1];
                    }else{
                        if(is_string($val[1])) {
                             $val[1] =  explode(',',$val[1]);
                        }
                        $zone      =   implode(',',$this->parseValue($val[1]));
                        $whereStr .= $key.' '.$this->exp[$exp].' ('.$zone.')';
                    }
                }elseif(preg_match('/^(notbetween|not between|between)$/',$exp)){ // BETWEEN运算
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $whereStr .=  $key.' '.$this->exp[$exp].' '.$this->parseValue($data[0]).' AND '.$this->parseValue($data[1]);
                }else{
                    E(L('_EXPRESS_ERROR_').':'.$val[0]);
                }
            }else {
                $count = count($val);
                $rule  = isset($val[$count-1]) ? (is_array($val[$count-1]) ? strtoupper($val[$count-1][0]) : strtoupper($val[$count-1]) ) : '' ; 
                if(in_array($rule,array('AND','OR','XOR'))) {
                    $count  = $count -1;
                }else{
                    $rule   = 'AND';
                }
                for($i=0;$i<$count;$i++) {
                    $data = is_array($val[$i])?$val[$i][1]:$val[$i];
                    if('exp'==strtolower($val[$i][0])) {
                        $whereStr .= $key.' '.$data.' '.$rule.' ';
                    }else{
                        $whereStr .= $this->parseWhereItem($key,$val[$i]).' '.$rule.' ';
                    }
                }
                $whereStr = '( '.substr($whereStr,0,-4).' )';
            }
        }else {
            //对字符串类型字段采用模糊匹配
            $likeFields   =   $this->config['db_like_fields'];
            if($likeFields && preg_match('/^('.$likeFields.')$/i',$key)) {
                $whereStr .= $key.' LIKE '.$this->parseValue('%'.$val.'%');
            }else {
                $whereStr .= $key.' = '.$this->parseValue($val);
            }
        }
        return $whereStr;
    }

    /**
     * 特殊条件分析
     * @access protected
     * @param string $key
     * @param mixed $val
     * @return string
     */
    protected function parseThinkWhere($key,$val) {
        $whereStr   = '';
        switch($key) {
            case '_string':
                // 字符串模式查询条件
                $whereStr = $val;
                break;
            case '_complex':
                // 复合查询条件
                $whereStr = substr($this->parseWhere($val),6);
                break;
            case '_query':
                // 字符串模式查询条件
                parse_str($val,$where);
                if(isset($where['_logic'])) {
                    $op   =  ' '.strtoupper($where['_logic']).' ';
                    unset($where['_logic']);
                }else{
                    $op   =  ' AND ';
                }
                $array   =  array();
                foreach ($where as $field=>$data)
                    $array[] = $this->parseKey($field).' = '.$this->parseValue($data);
                $whereStr   = implode($op,$array);
                break;
        }
        return '( '.$whereStr.' )';
    }


	/*
     * field分析
     * @access protected
     * @param mixed $fields
     * @return string
     */
    protected function parseField($fields) {
        if(is_string($fields) && '' !== $fields) {
            $fields    = explode(',',$fields);
        }
        if(is_array($fields)) {
            // 完善数组方式传字段名的支持
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array   =  array();
            foreach ($fields as $key=>$field){
                if(!is_numeric($key))
                    $array[] =  $this->parseKey($key).' AS '.$this->parseKey($field);
                else
                    $array[] =  $this->parseKey($field);
            }
            $fieldsStr = implode(',', $array);
        }else{
            $fieldsStr = '*';
        }
        return $fieldsStr;
    }


    /**
     * table分析
     * @access protected
     * @param mixed $table
     * @return string
     */
    protected function parseTable($tables) {
        if(is_array($tables)) {// 支持别名定义
            $array   =  array();
            foreach ($tables as $table=>$alias){
                if(!is_numeric($table))
                    $array[] =  $this->parseKey($table).' '.$this->parseKey($alias);
                else
                    $array[] =  $this->parseKey($alias);
            }
            $tables  =  $array;
        }elseif(is_string($tables)){
            $tables  =  explode(',',$tables);
            array_walk($tables, array(&$this, 'parseKey'));
        }
        return implode(',',$tables);
    }


    /**
     * 插入记录
     * @access public
     * @param array $data 数据
     * @param array $options 参数表达式
     * @param boolean $replace 是否replace
     * @return false | int
     */
	public function insert($data = array(), $options = array()){
		$values  =  $fields    = array();
		foreach ($data as $key=>$val){
			if(is_array($val) && 'exp' == $val[0]){
				$fields[]   =  $this->parseKey($key);
				$values[]   =  $val[1];
			}elseif(is_null($val)){
				$fields[]   =   $this->parseKey($key);
				$values[]   =   'NULL';
			}elseif(is_scalar($val)) { // 过滤非标量数据
				$fields[]   =   $this->parseKey($key);
				if(0===strpos($val,':') && in_array($val,array_keys($this->bind))){
					$values[]   =   $this->parseValue($val);
				}else{
					$name       =   count($this->bind);
					$values[]   =   ':'.$name;
					$this->bindParam($name,$val);
				}
			}
		}
		$sql  = 'INSERT INTO '.$options['table'].' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')';
		$sql .= $this->parseComment(!empty($options['comment'])?$options['comment']:'');

		return $this->execute($sql);
	}

	/**
	 * 批量插入记录
	 * @access public
	 * @param mixed $dataSet 数据集
	 * @param array $options 参数表达式
	 * @param boolean $replace 是否replace
	 * @return false | integer
	 */
	public function insertAll($dataSet,$options=array(),$replace=false) {
		$values = array();
		if(!is_array($dataSet[0])) return false;
		$fields =  array_map(array($this,'parseKey'),array_keys($dataSet[0]));
		foreach ($dataSet as $data){
			$value   =  array();
			foreach ($data as $key=>$val){
				if(is_array($val) && 'exp' == $val[0]){
					$value[]   =    $val[1];
				}elseif(is_null($val)){
					$value[]   =   'NULL';
				}elseif(is_scalar($val)){
					if(0===strpos($val,':') && in_array($val,array_keys($this->bind))){
						$value[]   =   $this->parseValue($val);
					}else{
						$name     =   count($this->bind);
						$value[]  =   ':'.$name;
						$this->bindParam($name,$val);
					}
				}
			}
			$values[]    = 'SELECT '.implode(',', $value);
		}
		$sql   =  'INSERT INTO '.$this->parseTable($options['table']).' ('.implode(',', $fields).') '.implode(' UNION ALL ',$values);
		$sql   .= $this->parseComment(!empty($options['comment'])?$options['comment']:'');
		return $this->execute($sql,!empty($options['fetch_sql']) ? true : false);
	}


    /**
     * 执行语句
     * @access public
     * @param string $str  sql指令
     * @param boolean $fetchSql  不执行只是获取SQL
     * @return mixed
     */
    public function execute($str) {
		$this->initConnect();
		if(!$this->linked) return false;
		$this->queryStr = $str;
		if(!empty($this->bind)){
			$that   =   $this;
			$this->queryStr =   strtr($this->queryStr,array_map(function($val) use($that){ return '\''.$that->escapeString($val).'\''; },$this->bind));
		}
		if(!empty($this->PDOStatement)) $this->free();
		$this->PDOStatement = $this->linked->prepare($str);
		if(false === $this->PDOStatement) {
			$this->error();
			return false;
		}
		foreach ($this->bind as $key => $val) {
			if(is_array($val)){
				$this->PDOStatement->bindValue($key, $val[0], $val[1]);
			}else{
				$this->PDOStatement->bindValue($key, $val);
			}
		}
		$this->bind =   array();
		$result =   $this->PDOStatement->execute();
		if(false === $result) {
            $this->error();
            return false;
        }else{
            $this->numRows = $this->PDOStatement->rowCount();
            if(preg_match("/^\s*(INSERT\s+INTO|REPLACE\s+INTO)\s+/i", $str)) {
                $this->lastInsID = $this->linked->lastInsertId();
				return $this->lastInsID;
            }
            return $this->numRows;
        }
	}


	/**
	 * 释放查询结果
	 * @access public
	 */
	public function free() {
        $this->PDO = null;
    }

    /**
     * 字段名分析
     * @access protected
     * @param string $key
     * @return string
     */
    protected function parseKey(&$key) {
        return $key;
    }


    /**
     * 参数绑定
     * @access protected
     * @param string $name 绑定参数名
     * @param mixed $value 绑定值
     * @return void
     */
    protected function bindParam($name,$value){
        $this->bind[':'.$name]  =   $value;
    }


    /**
     * value分析
     * @access protected
     * @param mixed $value
     * @return string
     */
    protected function parseValue($value) {
        if(is_string($value)) {
            $value =  strpos($value,':') === 0 && in_array($value,array_keys($this->bind))? $this->escapeString($value) : '\''.$this->escapeString($value).'\'';
        }elseif(isset($value[0]) && is_string($value[0]) && strtolower($value[0]) == 'exp'){
            $value =  $this->escapeString($value[1]);
        }elseif(is_array($value)) {
            $value =  array_map(array($this, 'parseValue'),$value);
        }elseif(is_bool($value)){
            $value =  $value ? '1' : '0';
        }elseif(is_null($value)){
            $value =  'null';
        }
        return $value;
    }

    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str  SQL字符串
     * @return string
     */
    public function escapeString($str) {
        return addslashes($str);
    }

	/**
     * 数据库错误信息
     * 并显示当前的SQL语句
     * @access public
     * @return string
     */
    public function error() {
        if($this->PDOStatement) {
            $error = $this->PDOStatement->errorInfo();
            $this->error = $error[1].':'.$error[2];
        }else{
            $this->error = '';
        }
        if('' != $this->queryStr){
            $this->error .= "\n [ SQL语句 ] : ".$this->queryStr;
        }
        // 记录错误日志
        trace($this->error,'','ERR');
        if($this->config['debug']) {// 开启数据库调试模式
            E($this->error);
        }else{
            return $this->error;
        }
    }


    /**
     * set分析
     * @access protected
     * @param array $data
     * @return string
     */
    protected function parseSet($data) {
        foreach ($data as $key=>$val){
            if(is_array($val) && 'exp' == $val[0]){
                $set[]  =   $this->parseKey($key).'='.$val[1];
            }elseif(is_null($val)){
                $set[]  =   $this->parseKey($key).'=NULL';
            }elseif(is_scalar($val)) {// 过滤非标量数据
                if(0===strpos($val,':') && in_array($val,array_keys($this->bind)) ){
                    $set[]  =   $this->parseKey($key).'='.$this->escapeString($val);
                }else{
                    $name   =   count($this->bind);
                    $set[]  =   $this->parseKey($key).'=:'.$name;
                    $this->bindParam($name,$val);
                }
            }
        }
        return ' SET '.implode(',',$set);
    }


    /**
     * 获取最近插入的ID
     * @access public
     * @return string
     */
    public function getLastInsID() {
        return $this->lastInsID;
    }


}
