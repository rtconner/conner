<?php

namespace model;

use \phpcassa\Connection\ConnectionPool;
use \phpcassa\ColumnSlice;
use \phpcassa\SystemManager;
use cassandra;
use Setting;

abstract class phpcassaModel {

	private $conn;
	protected $family = null;
	public $super = false;

	public function __construct() {}

	/**
	 * Return a list of all rows in this column family
	 * @param $keys (option) list of primary keys
	 */
	public function all($keys=null) {

		if($keys) {
			if($this->super) {
				$list = $this->conn()->multiget_super_column($keys, $this->super);
				$this->loggit('MULTIGET SUPER('.$this->family().')');
			} else {
				$list = $this->conn()->multiget($keys);
				$this->loggit('MULTIGET('.$this->family().')');
			}
		} else {

			if($this->super) {
				$list = $this->conn()->get_super_column_range($this->super);
			} else {
				$list = $this->conn()->get_range('','',
						5000, // row count
						null,
						null,
						null,
						null);
			}
			$this->loggit('GET RANGE('.$this->family().')');

		}

		$ret = array();
		foreach($list as $key => $cols) {
			$ret[$key] = $cols;
			$ret[$key]['id'] = $key;
		}

		return $ret;
	}

	/**
	 * Fetch the connection object (create it if it it does not exist yet)
	 *
	 * @param $reconnect bool force connection object to be re-created
	 */
	protected function conn() {
		if(is_null($this->conn)) {

			if(is_null($this->family())) {
				throw new \ConnerException('Column Family Missing, '.get_class($this));
			}

			try {
				if($this->super) {
					$this->conn = new \phpcassa\SuperColumnFamily($this->phpcassa_pool(), $this->family());
				} else {
					$this->conn = new \phpcassa\ColumnFamily($this->phpcassa_pool(), $this->family());
				}
				$this->loggit("CONNECT(".$this->family().")");
			} catch(\NoServerAvailable $e) {
				exit('No DB Server Found');
			} catch(cassandra\NotFoundException $e) {
				exit('Sorry, I could not find your database (data_'.$this->family().')');
			}

		}

		return $this->conn;
	}

	/**
	 * Return true or false if connected to column family
	 */
	function connected() {
		return !empty($this->conn);
	}

	/**
	 * Delete a row
	 *
	 * Notes: I've gotten an uncaught TTransportException here once
	 */
	public function delete($key) {
		if($this->super) {
			$this->conn()->remove_super_column($key, $this->super);
		} else {
			$this->conn()->remove($key);
		}
		$this->loggit("REMOVE(".$this->family().", $key)");

		if(method_exists($this, 'clearCaches')) {
			$this->clearCaches();
		}

		return true;
	}

	/**
	 * Tries to remove connections and free up memory
	 */
	public function disconnect() {
		$this->conn = null;
	}

	/**
	 * Does a record with this primary key exists. This method should not
	 * and will not test for soft deletes or delete flags of any sort.
	 */
	public function exists($key) {
		if(!mb_strlen($key)) { return false; }

		try {
			if($this->super) {
				$exists = (bool) $this->conn()->get_subcolumn_count($key, $this->super);
			} else {
				// todo: this can be sped up by specify a column or slice
				$exists = (bool) $this->conn()->get_count($key, null, null, null);
			}
			$this->loggit("COUNT(".$this->family().", $key)");
			return $exists;
		} catch(\cassandra\InvalidRequestException $e) {
			return false;
		} catch(\cassandra\NotFoundException $e) {
			return false;
		}
	}

	/**
	 * Return the column family name, this function allows for overwriting in Model for dynamic CF names
	 */
	public function family() {
		return $this->family;
	}

	/**
	 * Fetch a single field from a row
	 * @param $key primary row id/key
	 * @param $field string name of field
	 * @param [null] default value to return if none found
	 */
	public function field($key, $field, $default=null) {
		if(is_null($key)) {
			throw new Exception('Your key is null.');
		}

		try {
			$ret = $this->get($key, null, array($field), null);
			return array_key_exists($field, $ret) ? $ret[$field] : $default;
		} catch(\Thrift\Exception\TException $e) {
			return $default;
		}

	}

	/**
	 * Wrapper function for cassandra->get
	 */
	 public function get($key, $slice=null, $column_names=null, $consistency_level=null) {
	 	if($this->super) {
			$ret = $this->conn()->get_super_column($key,
                                 $this->super,
                                 $slice,
                                 $column_names,
                                 $consistency_level);
		} else {
			$ret = $this->conn()->get($key, $slice, $column_names, $consistency_level);
		}
		$this->loggit("GET(".$this->family().", $key) [".implode(",", (array)$column_names)."]");
		return $ret;
	 }

	/**
	 * Wrapper for Connection->insert()
	 * Very standard cassandara insert into database.
	 */
	public function insert($key, $columns, $timestamp=null, $ttl=null, $consistency_level=null) {
		if($this->super) {
			$insertData = array($this->super=>$columns);
		} else {
			$insertData = $columns;
		}

		$this->conn()->insert($key, $insertData, $timestamp, $ttl, $consistency_level);
		$this->loggit("INSERT(".$this->family().(
			$this->super?"{{$this->super}}":''
		).", $key) [".implode(',', array_keys($columns))."]");
	}

	/**
	 * Load data from memory.
	 * @param $id primary key
	 * @param $default returned if fails to load data (null)
	 */
	public function load($key, $default=null) {
		if(!mb_strlen($key)) { return $default; }

		try {
			$ret = $this->get($key);
			return $ret;
		} catch(\cassandra\InvalidRequestException $e) {
			return $default;
		} catch(\cassandra\NotFoundException $e) {
			return $default;
		}
	}

	/**
	 * Save one single field to the database. Does not do any checking on if key or field actually exist already
	 */
	public function saveField($key, $field, $value) {
		$timestamp = $ttl = $consistency_level = null;
		$this->insert($key, array($field=>$value), $timestamp, $ttl, $consistency_level);
		return true;
	}

	/**
	 * Return array formatted data representing cassandra schema
	 */
	public function schema() {
		$schema = $this->conn()->cfdef;
		$this->loggit('SCHEMA('.$this->family().')');
		return $schema;
	}

	public function loggit($qs) {
		if(Setting::get('debug')) {
			global $CASS_LOGS;
			$CASS_LOGS[] = $qs;
		}
	}

	/**
	 * Find a single row using it's metadata index
	 * @param $field string name
	 * @param $value string value to search with
	 * @return primary key of the found row
	 */
	public function find($field, $value) {
		$raw = $this->phpcassa_pool()->get();
 		$raw->client->set_cql_version("3.0.0");
 		$qs = "SELECT * FROM ".$this->family()." WHERE $field='$value' LIMIT 1";
 		try {
			$result = $raw->client->execute_cql_query($qs, cassandra\Compression::NONE);
			if(empty($result->rows)) {
				return null;
			}
			return $result->rows[0]->key;
 		} catch (cassandra\InvalidRequestException $e) {
 			// probably the index does not exist
 		}
	}

	/**
	 * Return a phpcassa ConnectionPool instance
	 */
	function phpcassa_pool() {
		global $PYCASSA_POOL;

		$credentials = null;
		if(($un = Setting::get('Cass.username')) && ($pw = Setting::get('Cass.password'))) {
			$credentials = array('username'=>$un, 'password'=>$pw);
		}

		if(empty($PYCASSA_POOL)) {
			$PYCASSA_POOL = new ConnectionPool(
				Setting::get('Cass.keyspace'),
				Setting::get('Cass.servers'),
				NULL, // $pool_size
				5, // $max_retries
				5000, // $send_timeout
				5000, // $recv_timeout
				10000, // $recycle
				$credentials // $credentials
			);
		}

		return $PYCASSA_POOL;
	}

	/**
	 * Return a new instance of
	 */
	public function systemManager() {
		$servers = Setting::get('Cass.servers');
		$credentials = null;
		if(($un = Setting::get('Cass.username')) && ($pw = Setting::get('Cass.password'))) {
			$credentials = array('username'=>$un, 'password'=>$pw);
		}
		$sys = new SystemManager($servers[0], $credentials);
		$this->loggit("SYSMANAGER()");
		return $sys;
	}

}