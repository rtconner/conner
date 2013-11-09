<?php

namespace model;

use mysqli;
use cache;
use DisplayableException;
use FrameworkException;
use Setting;

abstract class MysqlModel {

	protected $db;
	protected $schema = array();
	protected $fields = array();
	public $table;
	public $primaryKey = null;

	public function __construct() {
		$this->db = mysqli_connection();

		if($this->table && (is_null($this->primaryKey) || empty($this->fields))) {
			foreach($this->schema() as $key => $field) {
				$this->fields[] = $field['key'];
				if($field['primary']) {
					$this->primaryKey = $key;
				}
			}
		}
	}

	/**
	 * Create and execute an insert statement for one single row. Only does protection from SQL injection and not much else.
	 * @param $row is an associative array of keys and values to insert
	 * @param $options array
	 *   lastid [true]|false - return last insert id
	 */
	public function insert($row, $options=array()) {
		$options = array_merge(array('lastid'=>true), $options);
		$schema = $this->schema();

		$columns = $values = array();
		foreach($row as $column => $value) {
			if(array_key_exists($column, $schema)) {
				$columns[] = '`'.$column.'`';
				$values[] = "'".$this->escape($value)."'"; // todo: handle var types
			}
		}

		$stmt = $this->db->prepare($qs = 'INSERT INTO `'.$this->table.'`('.implode(', ', $columns).') VALUES ('.implode(',', $values).')');

		if($success = $stmt->execute()) {
			$this->loggit($qs);
			if($options['lastid']) {
				$success = $stmt->insert_id;
			}
		} else {
			throw new DisplayableException($this->db->error);
		}

		$stmt->free_result();
		return $success;
	}

	/**
	 * Standard simple read one record function
	 * @param $primaryId
	 * @param $defaults [null]|mixed
	 * @return $data array or $defaults
	 */
	function load($primaryId, $defaults=null) {
		if(empty($primaryId)) { return $defaults; }

		try {
			$data = $this->find('first', array('where'=>'`id` = \''.$this->escape($primaryId).'\''));
			if($data) {
				return $data;
			}
		} catch (FrameworkException $e) {}

		return $defaults;
	}

	/**
	 * Standard find method, based off of cakePHP find syntax
	 * @param $type [all]|first|count
	 * @param $options array
	 *   limit
	 *   where
	 *   order
	 */
	function find($type='all', $options) {
		$where = array();

		$qs = 'SELECT ';

		if(!empty($options['calc_rows'])) {
			$qs .= ' SQL_CALC_FOUND_ROWS ';
		}

		$fields = empty($options['fields'])
			? $this->fields
			: $options['fields'];

		$qs .= ' `'.implode('`, `', $fields).'` FROM `'.$this->table.'`';

		if(array_key_exists('where', $options)) {
			if(is_array($options['where'])) {
				$where = array_merge($where, $options['where']);
			} else {
				$where[] = $options['where'];
			}
		}

		if(!empty($where)) {
			$whereArr = array();

			foreach($where as $wk => $wv) {
				if(is_numeric($wk)) {
					$whereArr[] = $wv;
				} else {
					if(is_array($wv)) {
						$whereArr[] = '`'.$wk.'` IN (\''.implode('\',\'', $wv).'\')';
					} else {
						$whereArr[] = '`'.$wk.'` = \''.$this->escape($wv).'\'';
					}
				}
			}

			$qs .= ' WHERE '.implode(' AND ', $whereArr);
		}

		if(array_key_exists('order', $options) && strlen($options['order'])) {
			$qs .= sprintf(' ORDER BY %s', $options['order']);
		}

		if($type == 'first') {
			$qs .= ' LIMIT 1';
		} elseif(array_key_exists('limit', $options) && is_numeric($options['limit'])) {

			if(array_key_exists('page', $options) && is_numeric($options['page'])) {

				$start = (($options['page']-1) * $options['limit']);
				$limitStr = $start.', '.$options['limit'];
			} else {
				$limitStr = $options['limit'];
			}

			$qs .= sprintf(' LIMIT %s', $limitStr);
		}

		$result = $this->query($qs);

		if(is_object($result)) {
			if($type == 'first') {
				$return = $result->fetch_assoc();
			} else {
				$return = array();
				while ($row = $result->fetch_assoc()) {
					$return[] = $row;
		        }
			}
	        $result->free();
		} else {
			throw new \FrameworkException($qs);
		}
        return $return;
	}

	/**
	 * Returns array of default values as defined in the mysql default value for each field
	 */
	function defaults() {
		$ret = array();
		foreach($this->schema() as $key => $field) {
			if(!$field['primary'] && !is_null($field['default'])) {
				$ret[$key] = $field['default'];
			}
		}

		return $ret;
	}

	/**
	 * Delete single row that has given primary id
	 */
	function delete($id) {
		if(empty($id)) { return true; }

		$res = $this->query('DELETE FROM `'.$this->table.'` WHERE `'.$this->primaryKey.'` = \''.$this->escape($id).'\'');
		return (bool) $res;
	}

	/**
	 * Fetch a single field from a row
	 * @param $key primary row id/key
	 * @param $field string name of field
	 * @param [null] default value to return if none found
	 */
	function field($id, $field, $default=null) {
		if(array_key_exists($field, $this->schema())) {
			$res = $this->query('SELECT `'.$field.'` FROM `'.$this->table.'` WHERE `'.$this->primaryKey.'` = \''.$this->escape($id).'\' LIMIT 1');
			if($row = $res->fetch_row()) {
				$res->free();
				return $row[0];
			}
		} else { /* throw error? */ }
		return $default;
	}

	/**
	 * MySQL escape string
	 */
	function escape($str, $like=false) {
		$str = $this->db->real_escape_string($str);
		if($like) {
			$str = addcslashes($str, '%_');
		}
		return $str;
	}

	/**
	 * Return true/false if this row with given primaryKey exists in database
	 */
	function exists($id) {
		$res = $this->query('SELECT EXISTS (SELECT * FROM `'.$this->table.'` WHERE `'.$this->primaryKey.'` = \''.$this->escape($id).'\')');
		$row = $res->fetch_row();
		$res->free();
		return (bool) $row[0];
	}

	/**
	 * Returns a array representing the Mysql schema of this table
	 */
	function schema() {
		if(empty($this->table)) {
			throw new FrameworkException('Unknown table for schema in model '.get_class($this));
		}

		if(empty($this->schema)) {

			$cacheKey = 'MysqlModel.'.get_class($this).'.schema';

			if(is_null($schema = cache\file($cacheKey, null, MONTH))) {
				$schema = array();
				$res = $this->query('DESCRIBE `'.$this->table.'`');

				if(empty($res)) { // false on error
					return array(); // perhaps throwing an exception here may be a better way? i not sure.
				}

				while ($row = $res->fetch_row()) {
					$schema[$row[0]] = array(
						'key'=>$row[0],
	//					'type'=>$row[1], // todo: parse this into something better
						'primary'=>($row[3]=='PRI'),
						'default'=>$row[4],
					);
		        }
				$res->free();
				cache\file($cacheKey, $schema, MONTH);
			}
			$this->schema = $schema;
		}

		return $this->schema;
	}

	/**
	 * Wrapper for mysqli::query
	 */
	function query($qs) {
		$this->loggit($qs);
		if(!$result = $this->db->query($qs)) {
			debug($this->db->error);
		}
		return $result;
	}

	/**
	 * Run mysql truncate on table
	 */
	public function truncate() {
		$qs = ('TRUNCATE TABLE '.$this->table);
		return $this->query($qs);
	}

	function loggit($qs) {
		if(Setting::get('debug')) {
			global $MYSQL_LOGS;
			$MYSQL_LOGS[] = $qs;
		}
	}

}

/**
 * Return mysqli data connection object
 * http://us.php.net/manual/en/class.mysqli.php
 */
function mysqli_connection() {
	global $MYSQLI_CONNECTION;

	if($MYSQLI_CONNECTION) {
		return $MYSQLI_CONNECTION;
	}

	$MYSQLI_CONNECTION = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB);

	if (mysqli_connect_errno()) {
    	throw new FrameworkException("Connect failed: %s\n". mysqli_connect_error());
    	exit();
	}

	return $MYSQLI_CONNECTION;
}