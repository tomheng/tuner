<?php
class Model {

	protected $db    = null;
	protected $table = '';

	public function __construct() {

	}

	/**
	 * 魔法__call
	 */
	public function __call($func, $args) {
		if (!method_exists($this->db, $func)) {
			$class_name = get_class($this);
			throw new Exception("not found({$class_name}::{$func}).");
		}
		return call_user_func_array(array($this->db, $func), $args);
	}

	public function queryCacheRow($sql, $cache_time = 300) {
		$cache_key  = md5("queryCacheRow_{$sql}");
		$cache_data = Cache::getData($cache_key);
		if ($cache_data) {
			return $cache_data;
		}
		$re = $this->db->queryRow($sql);
		Cache::setData($cache_key, $re, $cache_time);
		return $re;
	}

	public function queryCacheRows($sql, $cache_time = 300) {
		$cache_key  = md5("queryCacheRow_{$sql}");
		$cache_data = Cache::getData($cache_key);
		if ($cache_data) {
			return $cache_data;
		}
		$re = $this->db->queryRows($sql);
		Cache::setData($cache_key, $re, $cache_time);
		return $re;
	}

	public function getCacheRow($where_array, $table = '', $cache_time = 300) {
		$sql = $this->buildSql($where_array, $table);
		return $this->queryCacheRow($sql, $cache_time);
	}

	public function getCacheRows($where_array, $table = '', $cache_time = 300) {
		$sql = $this->buildSql($where_array, $table);
		return $this->queryCacheRows($sql, $cache_time);
	}

	protected function buildSql($where_array, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$where_str       = $this->getWhereStr($where_array);
		$sql             = "SELECT * FROM `$table` $where_str";
		return $sql;
	}

	protected function buildCountSql($where_array, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$where_str       = $this->getWhereStr($where_array);
		$sql             = "SELECT count(*) FROM `$table` $where_str";
		return $sql;
	}

	// 获取单条记录
	public function getRow($where_array, $table = '') {
		$sql = $this->buildSql($where_array, $table);
		$sql .= " LIMIT 1";
		return $this->queryRow($sql);
	}

	// 获取多条记录
	public function getRows($where_array, $table = '') {
		$sql = $this->buildSql($where_array, $table);
		return $this->queryRows($sql);
	}

	//分页获取
	public function getPageRows($where_array, $order = '', $limit = '', $table = '') {
		$sql       = $this->buildSql($where_array, $table);
		$count_sql = $this->buildCountSql($where_array, $table);
		$result    = array(
			'count' => $this->queryFirst($count_sql),
			'rows'  => array(),
		);
		if (!$result['count']) {
			return $result;
		}
		if ($order) {
			$sql .= " $order ";
		}
		if ($limit) {
			$sql .= " $limit ";
		}
		$result['rows'] = $this->queryRows($sql);
		return $result;
	}

	// 转义字符
	public function escape($v) {
		$search  = array("\\", "\x00", "\n", "\r", "'", '"', "\x1a");
		$replace = array("\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z");
		return str_replace($search, $replace, $v);
	}

	// get sql where str
	/*
	         //where_array 支持的条件表达方式
	         $where_array = array(
	                'status' => 1,
	                'id' => array(1, 2, 3),
	                'status' => array('!=' => 1),
	                'title' => array('like' => '%hello%'),
	        );
*/
	public function getWhereStr($where_array) {
		if (!is_array($where_array) || !$where_array) {
			return false;
		}
		$where = array();
		foreach ($where_array as $key => $value) {
			//Todo be more safe check
			if (stripos($key, '.') === false) {
				$key = " `$key` ";
			}
			if (is_array($value)) {
				$in_value = array();
				foreach ($value as $k => $v) {
					if (is_int($k)) {
						$v          = $this->escape($v);
						$in_value[] = $v;
					} else { //key作为操作符使用
						$k = $this->escape($k);
						if (is_array($v)) {
							$v       = implode("','", array_map(array($this, 'escape'), $v));
							$where[] = " $key $k ('$v') ";
						} else {
							$v       = $this->escape($v);
							$where[] = " $key $k '$v' ";
						}
					}
				}
				if ($in_value) {
					$in_value = implode("','", $in_value);
					$where[]  = " $key in( '$in_value' ) ";
				}
			} else {
				$value   = $this->escape($value);
				$where[] = " $key = '$value' ";
			}
		}
		return " WHERE " . implode(' AND ', $where);
	}

	// get sql set str
	public function getSetStr($data_array) {
		if (!is_array($data_array) || !$data_array) {
			throw new Exception("set array invalid");
		}
		$set_array = array();
		foreach ($data_array as $key => $value) {
			$value       = $this->escape($value);
			$key         = $this->escape($key);
			$set_array[] = " `$key` = '{$value}' ";
		}
		return ' SET ' . implode(',', $set_array);
	}

	// 插入记录
	public function insertOne($sets, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$set_str         = $this->getSetStr($sets);
		$sql             = "INSERT INTO `$table` $set_str";
		$re              = $this->query($sql);
		return $re;
	}

	// 更新单条记录
	public function updateOne($sets, $wheres, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$set_str         = $this->getSetStr($sets);
		$where_str       = $this->getWhereStr($wheres);
		$sql             = "UPDATE `$table` $set_str $where_str LIMIT 1";
		$re              = $this->query($sql);
		return $re;
	}

	// 更新多条记录
	public function updateBatch($sets, $wheres, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$set_str         = $this->getSetStr($sets);
		$where_str       = $this->getWhereStr($wheres);
		$sql             = "UPDATE `$table` $set_str $where_str";
		$re              = $this->query($sql);
		return $re;
	}

	// 删除多条记录
	public function deleteBatch($wheres, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$where_str       = $this->getWhereStr($wheres);
		$sql             = "DELETE FROM  `$table` $where_str";
		$re              = $this->query($sql);
		return $re;
	}

	// 删除单条记录
	public function deleteOne($wheres, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$where_str       = $this->getWhereStr($wheres);
		$sql             = "DELETE FROM `$table` $where_str LIMIT 1";
		$re              = $this->query($sql);
		return $re;
	}

	public function getValues($rows, $fields = array()) {
		if (!is_array($fields)) {
			$fields = array($fields);
		}
		$re = array();
		foreach ($rows as $row) {
			foreach ($fields as $f) {
				if (!is_string($f)) {
					continue;
				}
				$re[$f][] = $row[$f];
			}
		}
		return count($re) > 1 ? $re : current($re);
	}

	public function formatRows($rows, $field) {
		if (!is_array($rows)) {
			return $rows;
		}
		$re = array();
		foreach ($rows as $row) {
			if (!isset($row[$field])) {
				//skip
				continue;
			}
			$re[$row[$field]] = $row;
		}
		return $re;
	}
}