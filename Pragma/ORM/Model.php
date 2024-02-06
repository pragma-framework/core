<?php
namespace Pragma\ORM;

use Pragma\DB\DB;
use \PDO;
use Pragma\Exceptions\DBException;

class Model extends QueryBuilder implements \JsonSerializable
{
    protected static $table_desc = [];
    //Extra AI > in order to load extra autoincrement after an insert
    protected static $table_extra_ai = [];

    protected $fields = [];
    protected $new = true;
    protected $desc = [];
    protected $inclusions = [];

    //Hooks
    protected $before_save_hooks = [];
    protected $after_save_hooks = [];
    protected $before_delete_hooks = [];
    protected $after_delete_hooks = [];
    protected $after_open_hooks = [];
    protected $after_build_hooks = [];
    protected $skipHooks = false;

    protected $changes_detection = false;
    protected $initialized = false;//usefull for sub-traits
    protected $initial_values = [];

    protected $primary_key = 'id'; //mixed - string or array of strings

    protected $default_matchers = null;
    protected $default_loaders = null;

    protected $forced_id_allowed = false;

    //mass assignment
    protected static $mass_allowed = [];

    //hooks names
    protected $hook_names = [];

    //Extra AI > in order to load extra autoincrement after an insert
    protected $extra_ai = null;

    // Stored objects for multiples inserts
    protected static $stored_objects = [];

    public function __construct($tb_name, $pk = null)
    {
        parent::__construct($tb_name);
        $this->fields = $this->describe();
        $error = false;
        if (is_null($pk) || ! is_array($pk)) {
            if (is_null($pk)) {
                $pk = 'id';
            }

            if (array_key_exists($pk, $this->fields)) {
                $this->primary_key = $pk;
            } else {
                $error = true;
            }
        } else {
            foreach ($pk as $k) {
                if (! array_key_exists($k, $this->fields)) {
                    $error = true;
                    break;
                }
            }
            if (!$error) {
                $this->primary_key = $pk;
            }
        }

        if ($error) {
            throw new \Exception("Error getting an instance of ".get_class($this)." - PK Error", 1);
        }
    }

    public function allowForcedId($val = true)
    {
        $this->forced_id_allowed = $val;
        return $this;
    }

    public function __get($attr)
    {
        if (array_key_exists($attr, $this->describe())) {
            return $this->fields[$attr];
        } elseif (array_key_exists($attr, $this->inclusions)) {
            return $this->inclusions[$attr];
        }
        return null;
    }

    public function __set($attr, $value)
    {
        if (array_key_exists($attr, $this->describe())) {
            $this->fields[$attr] = $value;
        }
        return $this;
    }

    public function __isset($attr)
    {
        if (array_key_exists($attr, $this->describe())) {
            return isset($this->fields[$attr]);
        } elseif (array_key_exists($attr, $this->inclusions)) {
            return isset($this->inclusions[$attr]);
        }
        return false;
    }

    public function is_new()
    {
        return $this->new;
    }

    public function get_table()
    {
        return $this->table;
    }

    public function get_primary_key()
    {
        return $this->primary_key;
    }

    public function open($pk)
    {
        $db = DB::getDB();
        $sql = "SELECT * FROM ".$this->table." WHERE ";
        $params = [];

        if (! is_array($pk) && ! is_array($this->primary_key)) {
            $sql .= $this->primary_key ." = :pk";
            $params[':pk'] = $pk;
        } elseif (is_array($pk) && is_array($this->primary_key)) {
            $i = 1;
            $mypks = array_flip($this->primary_key);
            foreach ($pk as $k => $val) {
                if (! isset($mypks[$k])) {
                    throw new \Exception("Error opening the instance of ".get_class($this)." - unknown PK column", 1);
                }
                if ($i > 1) {
                    $sql .= " AND ";
                }

                $sql .= " $k = :pk$i ";
                $params[":pk$i"] = $val;
                $i++;
            }
        } else {
            throw new \Exception("Error opening the instance of ".get_class($this)." - wrong pk signature", 1);
        }

        $res = $db->query($sql, $params);
        $data = $db->fetchrow($res);
        $res->closeCursor();
        if ($data) {
            $this->openWithFields($data);
            //don't play after_open_hooks here, it will be played in openWithFields
            return $this;
        }

        return null;
    }

    public static function find($pk)
    {
        $o = new static();

        if (! is_array($pk) && ! is_array($o->primary_key)) {
            $obj = static::forge()->where($o->primary_key, '=', $pk)->first();
        } elseif (is_array($pk) && is_array($o->primary_key)) {
            $qb = static::forge();
            $mypks = array_flip($o->primary_key);
            foreach ($pk as $k => $val) {
                if (! isset($mypks[$k])) {
                    throw new \Exception("Error opening the instance of ".get_class($o)." - unknown PK column", 1);
                }
                $qb->where($k, '=', $val);
            }

            $obj = $qb->first();
        } else {
            throw new \Exception("Error opening the instance of ".get_class($o)." - wrong pk signature", 1);
        }

        //don't play after_open_hooks here, it will be played in the $qb->first via openWithFields
        return $obj;
    }

    public function openWithFields($data, $whitelist = null)
    {
        if (! empty($data)) {
            // Check if all primary keys are in $data else we can't correctly save/update object
            if (is_array($this->primary_key)) {
                foreach ($this->primary_key as $k) {
                    if (!isset($data[$k])) {
                        return null;
                    } elseif (DB::getDB()->getConnector() == DB::CONNECTOR_PGSQL && defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql') {
                        return trim($data[$k]);
                    }
                }
            } elseif (!isset($data[$this->primary_key])) {
                return null;
            } elseif ((DB::getDB()->getConnector() == DB::CONNECTOR_PGSQL || DB::getDB()->getConnector() == DB::CONNECTOR_MSSQL) && defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql') {
                $data[$this->primary_key] = trim($data[$this->primary_key]);
            }

            //whitelist allows to get the description on an object and check if data is correct
            //the idea is to optimize by doing the describe only once
            if (! is_null($whitelist)) {
                foreach ($data as $f => $val) {
                    if (! array_key_exists($f, $whitelist)) {
                        unset($data[$f]);
                    }
                }
            }

            $this->fields = $data;
            $this->new = false;
            $this->playHooks($this->after_open_hooks);
            //changes detection initializator
            if ($this->isChangesDetection()) {
                $this->initChangesDetection();//create a copy of the fields of the object in this->initial_values
            }

            return $this;
        }

        return null;
    }

    public function delete()
    {
        $this->playHooks($this->before_delete_hooks);
        $e = $this->escape ? self::$escapeChar : "";
        if (! $this->new) {
            $db = DB::getDB();
            $sql = 'DELETE FROM '.$e.$this->table.$e.' WHERE ';
            $params = [];
            if (! is_array($this->primary_key)) {
                $sql .= $e.$this->primary_key.$e.' = :pk';
                $params[":pk"] = $this->fields[$this->primary_key];
            } else {
                $i = 1;
                foreach ($this->primary_key as $pk) {
                    if ($i > 1) {
                        $sql .= ' AND ';
                    }
                    $sql .= $e . $pk . $e . (is_null($this->fields[$pk]) ? " IS NULL" : " = :pk$i ");
                    if (! is_null($this->fields[$pk])) {
                        $params[":pk$i"] = $this->fields[$pk];
                    }
                    $i++;
                }
            }

            $st = $db->query($sql, $params);
            $st->closeCursor();
        }
        $this->playHooks($this->after_delete_hooks);
    }

    //see QueryBuilder::build_arrays_of to understand the usage of callbacks
    public static function all($idkey = true, $rootCallback = null, $openedCallback = null)
    {
        return static::forge()->get_objects($idkey ? self::USE_PK : null, false, true, true, $rootCallback, $openedCallback);
    }

    //$bypass_ma = bypass_mass_assignment_control : the developper knows what he's doing
    public static function build($data = [], $bypass_ma = false)
    {
        $obj = new static();
        $obj->fields = $obj->describe();

        $obj->merge($data, $bypass_ma);
        $obj->playHooks($obj->after_build_hooks);
        return $obj;
    }

    public function merge($data, $bypass_ma = false)
    {
        if (!$bypass_ma && isset(self::$mass_allowed[get_class($this)])) {
            $data = array_intersect_key($data, self::$mass_allowed[get_class($this)]);
        }

        $this->fields = array_intersect_key($data + $this->fields, $this->fields);

        return $this;
    }

    /**
     * Builds values for SQL INSERT
     * @return string
     */
    private static function build_insert_values($db, $pks, $e, $obj, &$values, &$strategy, &$counter)
    {
        $sql = '(';

        $first = true;
        foreach ($obj->describe() as $col => $default) {
			 if (! $obj->forced_id_allowed && ((! is_array($obj->primary_key) && $col == $obj->primary_key) || (is_array($obj->primary_key) && $col == 'id' && isset($pks['id'])))) {
                if (defined('ORM_ID_AS_UID') && ORM_ID_AS_UID) {
                    if (! defined('ORM_UID_STRATEGY')) {
                        $strategy = 'php';
                    } else {
                        switch (ORM_UID_STRATEGY) {
                            default:
                                $strategy = 'php';
                                break;
                            case 'mysql':
                                $strategy = defined('DB_CONNECTOR') && DB_CONNECTOR == 'mysql' ? 'mysql' : 'php';
                                break;
                            case 'laravel-uuid':
                                $strategy = ORM_UID_STRATEGY;
                                break;
                        }
                    }
                }
				if ($db->getConnector() == DB::CONNECTOR_MSSQL && $strategy === 'ai') {
					continue;
				}
			}
            $paramCol = 'c'.($counter++);
            if (!$first) {
                $sql .= ', ';
            } else {
                $first = false;
            }

            if (! $obj->forced_id_allowed && ((! is_array($obj->primary_key) && $col == $obj->primary_key) || (is_array($obj->primary_key) && $col == 'id' && isset($pks['id'])))) {
                switch ($strategy) {
                    case 'ai':
                        if ($db->getConnector() == DB::CONNECTOR_PGSQL) {
                            $sql .= 'DEFAULT';
                        } else {
                            $sql .= ':'.$paramCol;
                            $values[":$paramCol"] = null;
                        }
                        break;
                    case 'php':
                        $sql .= ':'.$paramCol;
                        $values[":$paramCol"] = $obj->$col = uniqid('', true);
                        break;
                    case 'laravel-uuid':
                        $sql .= ':'.$paramCol;
                        $values[":$paramCol"] = $obj->$col = \Webpatser\Uuid\Uuid::generate(4)->string;
                        break;
                    case 'mysql':
                        $suid = 'UUID()';
                        if (DB_CONNECTOR == 'sqlite') {
                            $suid = 'LOWER(HEX(RANDOMBLOB(18)))';
                        } elseif ($db->getConnector() == DB::CONNECTOR_PGSQL) {
                            // $db->query('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
                            $suid = 'uuid_generate_v4()';
                        }
                        $uuidRS = $db->query('SELECT '.$suid.' as uuid');//PDO doesn't return the uuid whith lastInsertId
                        $uuidRes = $db->fetchrow($uuidRS);
                        $uuidRS->closeCursor();
                        $obj->$col = $uuidRes['uuid'];
                        $sql .= ':'.$paramCol;
                        $values[":$paramCol"] = $obj->id;
                        break;
                }
            } else {
                $sql .= ':'.$paramCol;
                $values[':'.$paramCol] = array_key_exists($col, $obj->fields) ? $obj->$col : '';
            }
        }

        $sql .= ")";

        return $sql;
    }

    public function save()
    {
        $this->playHooks($this->before_save_hooks);
        $db = DB::getDB();

        if (is_array($this->primary_key)) {
            $pks = array_flip($this->primary_key);
        } else {
            $pks = [$this->primary_key => $this->primary_key];
        }

        $e = $this->escape ? self::$escapeChar : "";

        if ($this->new) {//INSERT
			$sql = '';
			if (DB::getDB()->getConnector() == DB::CONNECTOR_MSSQL && $this->forced_id_allowed) {
				$sql .= 'SET IDENTITY_INSERT '. $this->get_table() .' ON; ';
			}
            $sql .= 'INSERT INTO '.$e.$this->table.$e.' (';
            $first = true;
            foreach ($this->describe() as $col => $default) {

				if ($db->getConnector() == DB::CONNECTOR_MSSQL && ! $this->forced_id_allowed && ((! is_array($this->primary_key) && $col == $this->primary_key) || (is_array($this->primary_key) && $col == 'id' && isset($pks['id'])))) {
					if (!defined('ORM_ID_AS_UID') || !ORM_ID_AS_UID) {
						continue;
					}
				}
                if (!$first) {
                    $sql .= ', ';
                } else {
                    $first = false;
                }
                $sql .= $e.$col.$e;
            }

            $sql .= ') VALUES ';

            $values = [];
            $strategy = 'ai';//autoincrement
            $counter = 1;
            $sql .= self::build_insert_values($db, $pks, $e, $this, $values, $strategy, $counter);

			if (DB::getDB()->getConnector() == DB::CONNECTOR_MSSQL && $this->forced_id_allowed) {
				$sql .= '; SET IDENTITY_INSERT '. $this->get_table() .' OFF; ';
			}

            $st = $db->query($sql, $values);
            $st->closeCursor();

            if (! $this->forced_id_allowed && $strategy == 'ai') {
                if (! is_array($this->primary_key)) {
                    $this->fields[$this->primary_key] = $db->getLastId($db->getConnector() == DB::CONNECTOR_PGSQL ? ($this->table.'_'.$this->primary_key.'_seq') : $this->primary_key);
                } elseif (isset($pks['id'])) {
                    $this->id = $db->getLastId($db->getConnector() == DB::CONNECTOR_PGSQL ? ($this->table.'_'.$pks['id'].'_seq') : $pks['id']);
                }
            } elseif ($this->forced_id_allowed && $strategy == 'ai' && $db->getConnector() == DB::CONNECTOR_PGSQL) {
                $pk = !is_array($this->primary_key) ? $this->primary_key : (isset($pks['id']) ? $pks['id'] : null);
                if (!empty($pk) && $this->fields[$pk] > $db->getLastId($this->table.'_'.$pk.'_seq')) {
                    $res = $db->query('SELECT MAX('.$e.$pk.$e.') as m FROM '.$e.$this->table.$e.' LIMIT 1 OFFSET 0');
                    if ($r = $db->fetchrow($res)) {
                        $res->closeCursor();
                        $res = $db->query('ALTER SEQUENCE '.$e.$this->table.'_'.$pk.'_seq'.$e.' RESTART WITH '.($r['m'] + 1));
                    }
                    $res->closeCursor();
                }
            }

            if (! empty(self::$table_extra_ai[$this->table])) {
                $this->{self::$table_extra_ai[$this->table]} = $db->getLastId($db->getConnector() == DB::CONNECTOR_PGSQL ? ($this->table.'_'.self::$table_extra_ai[$this->table].'_seq') : self::$table_extra_ai[$this->table]);
            }

            $this->new = false;
        } else {//UPDATE
            $sql = 'UPDATE '.$e.$this->table.$e.' SET ';
            $first = true;
            $values = [];
            $counter = 1;
            foreach ($this->describe() as $col => $default) {
                $paramCol = $paramCol = 'c'.($counter++);
                if (! isset($pks[$col]) && array_key_exists($col, $this->fields)) {//the primary key members are not updatable
                    if (!$first) {
                        $sql .= ', ';
                    } else {
                        $first = false;
                    }
                    $sql .= $e.$col.$e.' = :'.$paramCol;
                    $values[':'.$paramCol] = $this->$col;
                }
            }

            $i = 1;
            $sql .= ' WHERE ';
            foreach ($pks as $pk => $lambda) {
                $paramCol = $paramCol = 'pk'.$i;
                if ($i > 1) {
                    $sql .= ' AND ';
                }
                $sql .= " $e$pk$e = :$paramCol";
                $values[":$paramCol"] = $this->fields[$pk];
                $i++;
            }

            if(!$first){ // True if there is no updatable column
                $st = $db->query($sql, $values);
                $st->closeCursor();
            }
        }
        $this->playHooks($this->after_save_hooks);
        //changes detection re-initializator
        if ($this->isChangesDetection()) {
            $this->initChangesDetection(true);//force the initial copy to be reset
        }
        return $this;
    }

    public function toJSON()
    {
        return json_encode($this->as_array());
    }

    public function as_array()
    {
        $inclusions = [];
        if (! empty($this->inclusions)) {
            foreach ($this->inclusions as $name => $obj) {
                if (is_array($obj)) {
                    $inclusions[$name] = [];
                    foreach ($obj as $o) {
                        if ($o instanceof self) {
                            $inclusions[$name][] = $o->as_array();
                        } else {
                            $inclusions[$name][] = $o;
                        }
                    }
                } elseif (!empty($obj)) {
                    if ($obj instanceof self) {
                        $inclusions[$name] = $obj->as_array();
                    } else {
                        $inclusions[$name] = $obj;
                    }
                }
            }
        }
        return array_merge($this->fields, $inclusions);
    }

    public function add_inclusion($name, $value)
    {
        $this->inclusions[$name] = $value;
    }

    public function clean_inclusions($name = null)
    {
        if (!is_null($name)) {
            if (isset($this->inclusions[$name])) {
                unset($this->inclusions[$name]);
            }
        } else {
            $this->inclusions = [];
        }
        return $this;
    }

    public function describe($force = false)
    {
        $db = DB::getDB();

        if (empty(self::$table_desc[$this->table]) || $force) {
            foreach ($db->describe($this->table) as $data) {
                if ($data['default'] === null && !$data['null']) {
                    self::$table_desc[$this->table][$data['field']] = '';
                } else {
                    self::$table_desc[$this->table][$data['field']] = $data['default'];
                }

                if ($data['extra'] == 'auto_increment' && $data['key'] != 'PRI') {
                    self::$table_extra_ai[$this->table] = $data['field'];
                }
            }
        }


        return self::$table_desc[$this->table];
    }

    protected function pushHook($type, $hook, $name = null, $beforeSiblingHooks = null, $afterSiblingHooks = null)
    {
        $hooks = null;
        switch ($type) {
            case 'before_save':
                $hooks = &$this->before_save_hooks;
                break;
            case 'after_save':
                $hooks = &$this->after_save_hooks;
                break;
            case 'before_delete':
                $hooks = &$this->before_delete_hooks;
                break;
            case 'after_delete':
                $hooks = &$this->after_delete_hooks;
                break;
            case 'after_open':
                $hooks = &$this->after_open_hooks;
                break;
            case 'after_build':
                $hooks = &$this->after_build_hooks;
                break;
        }

        $key = md5(json_encode($hook));
        if (!is_null($hooks) && !isset($hooks[$key]) && (empty($name) || ! isset($this->hook_names[$type][$name]))) {
            if (!empty($name)) {
                if (!isset($this->hook_names[$type])) {
                    $this->hook_names[$type] = [];
                }
                if (!empty($name)) {
                    $this->hook_names[$type][$name] = $key;
                }
            }

            if (! is_null($beforeSiblingHooks) && ! is_array($beforeSiblingHooks)) {
                $beforeSiblingHooks = [$beforeSiblingHooks];
            }

            if (! is_null($afterSiblingHooks) && ! is_array($afterSiblingHooks)) {
                $afterSiblingHooks = [$afterSiblingHooks];
            }

            $hooks[$key] = ['hook' => $hook, 'key' => $key, 'name' => $name, 'before' => $beforeSiblingHooks, 'after' => $afterSiblingHooks];
        } elseif (isset($this->hook_names[$type][$name])) {
            throw new \Exception("ORM\Model::pushHook [$name] already exists in $type");
        }
    }
    protected function removeHook($type, $hook, $name = null)
    {
        $hooks = null;
        switch ($type) {
            case 'before_save':
                $hooks = &$this->before_save_hooks;
                break;
            case 'after_save':
                $hooks = &$this->after_save_hooks;
                break;
            case 'before_delete':
                $hooks = &$this->before_delete_hooks;
                break;
            case 'after_delete':
                $hooks = &$this->after_delete_hooks;
                break;
            case 'after_open':
                $hooks = &$this->after_open_hooks;
                break;
            case 'after_build':
                $hooks = &$this->after_build_hooks;
                break;
        }

        $key = md5(json_encode($hook));
        if (!is_null($hooks) && isset($hooks[$key]) && (empty($name) || isset($this->hook_names[$type][$name]))) {
            if (!empty($name)) {
                unset($this->hook_names[$type][$name]);
            }
            unset($hooks[$key]);
        }
    }

    protected function playHooks($hooks)
    {
        if (!empty($hooks) && ! $this->skipHooks) {
            //refs will help us to convert names to keys
            $refs = [];
            foreach ($hooks as $idx => $h) {
                if (! is_array($h)) {//for backwards compatibility (before the pushHook method)
                    $md5 = md5(json_encode($h));
                    if (! isset($hooks[$md5])) {
                        $hooks[$md5] = ['hook' => $h, 'key' => $md5];
                    }
                    unset($hooks[$idx]);
                    $h = $hooks[$md5];
                }
                if (!empty($h['name'])) {
                    $refs[$h['name']] = $h['key'];
                }
            }

            $sortedHooks = $hooks;

            if (! empty($refs)) { //optimization : if there is no refs then there is no graph to build
                //building edges of the topological graph
                $edges = [];
                foreach ($hooks as $key => $h) {
                    if (!empty($h['before'])) {
                        foreach ($h['before'] as $b) {
                            if (isset($refs[$b])) {
                                if (! isset($edges[$key])) {
                                    $edges[$key] = [];
                                }
                                $edges[$key][$refs[$b]] = $refs[$b];
                            }
                        }
                    }

                    if (!empty($h['after'])) {
                        foreach ($h['after'] as $a) {
                            if (isset($refs[$a])) {
                                if (! isset($edges[$refs[$a]])) {
                                    $edges[$refs[$a]] = [];
                                }
                                $edges[$refs[$a]][$key] = $key;
                            }
                        }
                    }
                }

                // --- Kahn's algorithm for sorting hooks according to their dependencies ----
                $noIncomingEdges = [];
                foreach ($hooks as $h) {
                    $found = false;
                    foreach ($edges as $start => $tos) {
                        foreach ($tos as $to) {
                            if ($h['key'] == $to) {
                                $found = true;
                                break 2;
                            }
                        }
                    }
                    if (! $found) {
                        $noIncomingEdges[] = $h;
                    }
                }

                $sortedHooks = [];

                while (!empty($noIncomingEdges)) {
                    $h = array_shift($noIncomingEdges);
                    array_push($sortedHooks, $h);

                    if (!empty($edges[$h['key']])) {
                        foreach ($edges[$h['key']] as $idx => $target) {
                            if (count($edges[$h['key']]) == 1) {
                                unset($edges[$h['key']]);
                            } else {
                                unset($edges[$h['key']][$idx]);
                            }

                            $stillHaveEdges = false;
                            foreach ($edges as $k => $targets) {
                                foreach ($targets as $node) {
                                    if ($node == $target) {
                                        $stillHaveEdges = true;
                                        break 2;
                                    }
                                }
                            }
                            if (! $stillHaveEdges) {
                                array_unshift($noIncomingEdges, $hooks[$target]);
                            }
                        }
                    }
                }

                if (!empty($edges)) { //oh oh, there is a cycle !
                    throw new \Exception('Pragma\ORM\Model::playHooks > graph has at least one cycle');
                }
            }//end of if(!empty($refs)) / optimization

            // -> Let's play the sorted hooks
            $i = 0;
            $count = count($hooks);
            foreach ($sortedHooks as $hook) {
                // error_log(print_r($hook, true));
                $this->callHook($hook['hook'], ++$i == $count);
            }
        }
    }

    private function callHook($callback, $last)
    {
        if (is_string($callback) && method_exists($this, $callback)) {
            // Specific case if $callback is a local method name
            call_user_func([$this, $callback], $last, $this);
        } elseif (is_callable($callback)) {
            call_user_func($callback, $last, $this);
        }
    }

    //Method allowing to skip all Hooks
    public function skipHooks($val = true)
    {
        $this->skipHooks = $val;
        return $this;
    }

    protected function add_relation($type, $classto, $name, $custom = [])
    {
        if (is_null($name)) {
            throw new \Exception('The name of the relation ['.$type.'] should not be empty');
        }

        if (array_key_exists($name, $this->describe())) {
            throw new \Exception("The name of the relation $name should not be the same as a field attribute");
        }

        if (! Relation::is_stored(get_class($this), $name) && ! Relation::is_in_progress(get_class($this), $name)) {
            //default matchers & loaders
            if (empty($custom['matchers']) && ! empty($this->default_matchers)) {
                $custom['matchers'] = $this->default_matchers;
            }
            if (empty($custom['loaders']) && ! empty($this->default_loaders)) {
                $custom['loaders'] = $this->default_loaders;
            }
            Relation::store_in_progress(get_class($this), $name, ['onpk' => $this->get_primary_key(), 'type' => $type, 'classto' => $classto, 'custom' => $custom]); // We store work in progress
        }
    }

    protected function drop_relation($name)
    {
        if (!	Relation::drop(get_class($this), $name)) {
            throw new \Exception("The relation called $name doesn't exist");
        }
    }

    public function belongs_to($classto, $name, $custom = [])
    {
        $this->add_relation('belongs_to', $classto, $name, $custom);
    }

    public function has_one($classto, $name, $custom = [])
    {
        $this->add_relation('has_one', $classto, $name, $custom);
    }

    public function has_many($classto, $name, $custom = [])
    {
        $this->add_relation('has_many', $classto, $name, $custom);
    }

    public function has_many_through($classto, $name, $custom = [])
    {
        $this->add_relation('has_many_through', $classto, $name, $custom);
    }

    public function rel($name, $order = null, $reload = false, $overriding = [])
    {
        $rel = Relation::get(get_class($this), $name);
        if (is_null($rel)) {
            throw new \Exception("Unknown relation $name");
        }
        if (!array_key_exists($name, $this->inclusions) || $reload) {
            $obj = $rel->fetch($this, $order, $overriding);
            $this->add_inclusion($name, $obj);
            return $obj;
        } else {
            return $this->inclusions[$name];
        }
    }

    public function set_default_matchers($default)
    {
        $this->default_matchers = $default;
    }

    public function set_default_loaders($default)
    {
        $this->default_loaders = $default;
    }

    //mass assignment
    public function attrs_allowed($attrs, $force = false)
    {
        if (!empty($attrs) && (!isset(self::$mass_allowed[get_class($this)]) || $force)) {
            self::$mass_allowed[get_class($this)] = [];
            foreach ($attrs as $a) {
                if (array_key_exists($a, $this->describe())) {
                    self::$mass_allowed[get_class($this)][$a] = $a;
                }
            }
        }
        return $this;
    }

    public function jsonSerialize():mixed
    {
        return $this->as_array();
    }

    //$startIntialization allows you to init the values even after a previous opening
    //example : $u = \App\Models\User::forge()->first()->enableChangesDetection(true);
    public function enableChangesDetection($startInitialization = false)
    {
        $this->changes_detection = true;
        if ($startInitialization) {
            $this->initChangesDetection();
        }
        return $this;
    }

    public function disableChangesDetection()
    {
        $this->changes_detection = false;
        $this->inital_values = [];//reset the inital_values
        return $this;
    }

    protected function isChangesDetection()
    {
        return $this->changes_detection;
    }

    public function initChangesDetection($force = false)
    {
        if (! $this->isChangesDetection()) {
            trigger_error("Changes detection is not enabled on this object. Please consider using the method enableChangesDetection before");
        }
        if (! $this->initialized || $force) {
            $this->initial_values = $this->fields;
            $this->initialized = true;
        }
        return $this;
    }

    //$blacklist should be indexed with the fields' names
    public function changed($blacklist = [])
    {
        if (! $this->isChangesDetection()) {
            trigger_error("Changes detection is not enabled on this object. Please consider using the method enableChangesDetection before");
            return false;
        }
        $changed = false;
        foreach ($this->fields as $k => $v) {
            if (! isset($blacklist[$k]) && array_key_exists($k, $this->initial_values) &&
                $v != $this->initial_values[$k]
                ) {
                $changed = true;
                break;
            }
        }
        return $changed;
    }

    //$blacklist should be indexed with the fields' names
    public function changes($blacklist = [])
    {
        $changes = [];
        if (! $this->isChangesDetection()) {
            trigger_error("Changes detection is not enabled on this object. Please consider using the method enableChangesDetection before");
            return $changes;
        }
        foreach ($this->fields as $k => $v) {
            if (! isset($blacklist[$k]) && array_key_exists($k, $this->initial_values) &&
                $v != $this->initial_values[$k]
                ) {
                $changes[$k] = [
                'before' => $this->initial_values[$k],
                'after' => $v
                ];
            }
        }
        return $changes;
    }

    /**
     * Stores objects
     * @return void
     */
    public static function store_object($obj)
    {
        $class = get_class($obj);
        if (!isset(self::$stored_objects[$class])) {
            self::$stored_objects[$class] = [];
        }
        self::$stored_objects[$class][] = $obj;
    }

    /**
     * Saves stored objects in database
     * @return void
     */
    public static function save_stored_objects($escape = true, $ignoreErrors = false)
    {
        $class = static::class;
        if (!empty(self::$stored_objects[$class])) {
            $db = DB::getDB();
            $obj = $class::forge();
            $fields = $obj->describe();

            $first_object = current(self::$stored_objects[static::class]);
            if (is_array($first_object->primary_key)) {
                $pks = array_flip($first_object->primary_key);
            } else {
                $pks = [$first_object->primary_key => $first_object->primary_key];
            }

            $e = $escape ? self::$escapeChar : "";

            $dbMaxPlaceholdersPerStmt = defined('DB_MAX_PLACEHOLDERS_PER_STMT') ? DB_MAX_PLACEHOLDERS_PER_STMT : 65535;
            $chunkedObjects = array_chunk(self::$stored_objects[static::class], intdiv($dbMaxPlaceholdersPerStmt, count($fields)));

            foreach ($chunkedObjects as $objects) {
                $first = true;
                $sql = 'INSERT ' . ($ignoreErrors ? 'IGNORE ' : '') . 'INTO '.$e.$obj->table.$e.' (';
                foreach ($fields as $col => $default) {
                    if (!$first) {
                        $sql .= ', ';
                    } else {
                        $first = false;
                    }
                    $sql .= $e.$col.$e;
                }
                $sql .= ') VALUES ';

                $counter = 1;
                $first = true;
                $values = [];
                $strategy = 'ai';//autoincrement

                foreach ($objects as $object) {
                    if (!$first) {
                        $sql .= ', ';
                    } else {
                        $first = false;
                    }
                    $sql .= self::build_insert_values($db, $pks, $e, $object, $values, $strategy, $counter);
                }

                $st = $db->query($sql, $values);
                $st->closeCursor();
            }
        }

        self::$stored_objects[$class] = [];
    }

    /**
     * Verrouille la table
     * @return void
     * @throws DBException
     */
    public static function lockTable()
    {
        $db = DB::getDB();
        $db->query('SET AUTOCOMMIT=0;')->execute();
        $db->query('LOCK TABLES ' . self::build()->get_table() .' WRITE;')->execute();
    }

    /**
     * Déverrouille la table
     * @return void
     * @throws DBException
     */
    public static function unlockTable()
    {
        $db = DB::getDB();
        $db->query('UNLOCK TABLES;')->execute();
        $db->query('COMMIT;')->execute();
        $db->query('SET AUTOCOMMIT=1;')->execute();
    }
}
