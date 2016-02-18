<?php

namespace MicroDB;
use Engine\Functions\Object;

/**
 * Description of EntityManager
 *
 * @author Sébastien Dugene
 */
class EntityManager
{
    private static $_instance = null;
	
    protected $joinedProperties = null;
    protected $reflectionClass = null;
    protected $properties = null;
    protected $mapping = null;
    protected $class = null;
	
    private $conf = [];
    private $entity = null;
    private $folder = null;
    private $database;

    /**
     * @return void
     */
    private function __construct($params) {
    	$this->useParams($params);
    	$this->database = new Database();
    	$this->database->secure($this->conf);
    }

    /**
     * @return EntityManager
     */
    public static function getManager($params = [])
    {
        if(is_null(self::$_instance)) {
            self::$_instance = new EntityManager($params);
        }
        return self::$_instance;
    }
    
    /// METHODS
    public function entity($entity)
    {
    	$this->entity = $entity;
    	if (is_object($entity)) {
	    	$this->class = get_class($this->entity);
	    	$this->getClassAnnotations();
	    	$this->database->setPath($this->folder.$this->entity->getClassName());
    	} else {
    		$this->database->setPath($this->folder.$entity);
    	}
    	return $this;
    }

    /**
     * @param int|array $id|$criteria
     * @param array|int $join|$limit
     * @param int|array $limit|$order
     * @param array $order|$group
     * @param array $group
     * @return array
     */
    public function find()
    {
        /**
         *  $args[0] : $criteria
         *  $args[1] : $join
         *  $args[2] : $limit
         *  $args[3] : $order
         *  $args[4] : $group
         */
        $args = $this->getArgs(func_get_args());
        
        if (is_numeric($args[0])) {
            return $this->findById($args[0]);
        }
        
        if ($args[0] == '*') {
        	return $this->findByCriteria();
        }
        
        if (is_array($args[1]) && !empty($args[1])) {
            return $this->findWithJoin($args[0], $args[1], $args[2], $args[3], $args[4]);
        }
        
        if (is_array($args[0])) {
            return $this->findByCriteria($args[0], $args[1], $args[2], $args[3]);
        }
    }
    
    private function findById($id)
    {
    	$result = $this->database->load($id);
    	return Object::fillWithJSon($this->entity, json_encode($result));
    }

    /**
     * @param array $criteria
     * @param bool|false $maxLine
     * @param bool|false $order
     * @param bool|false $group
     * @return array
     */
    private function findByCriteria($criteria = [], $maxLine = false, $order = false, $group = false)
    {
    	$results = [];
    	$array = $this->database->find($criteria);
    	$max = max(array_keys($array));
    	foreach ($array as $key => $value) {
    		if ($maxLine && count($results) == $maxLine) {
    			break;
    		}
    		$resultKey = $this->order($key, $value, $order, $max);
    		$results[$resultKey] = Object::fillWithJSon(new $this->class(), json_encode($value));
    	}
    	
    	if ($order && $order[key($order)] == 'ASC') {
    		ksort($results);
    	} elseif ($order && $order[key($order)] == 'DESC') {
    		krsort($results);
    	}
    	$arraySort = array_values($results);
    	if ($maxLine == 1) {
    		return $arraySort[0];
    	}
    	return $arraySort;
    }

    /**
     * @param array $criteria
     * @param array $join
     * @param bool|false $maxLine
     * @param bool|false $order
     * @param bool|false $group
     * @return array
     */
    private function findWithJoin($criteria = [], $join = [], $maxLine = false, $order = false, $group = false)
    {
    	$results = [];
    	$array = $this->database->find($criteria);
    	$max = max(array_keys($array));
    	foreach ($array as $key => $value) {
    		if ($maxLine && count($results) == $maxLine) {
    			break;
    		}
    		$resultKey = $this->order($key, $value, $order, $max);
    		$this->entity = new $this->class();
    		$object = Object::fillWithJSon($this->entity, json_encode($value));
    		$results[$resultKey] = $this->join($join, $object);
    	}
    	
    	if ($order && $order[key($order)] == 'ASC') {
    		ksort($results);
    	} elseif ($order && $order[key($order)] == 'DESC') {
    		krsort($results);
    	}
    	$arraySort = array_values($results);
    	if ($maxLine == 1) {
    		return $arraySort[0];
    	}
    	return $arraySort;
    }

    /**
     * @param $args
     * @param int $max
     * @return mixed
     */
    private function getArgs($args, $max = 5)
    {
        for($j = 0 ; $j < $max ; $j++) {
            if (!array_key_exists($j, $args) && $j < 2) {
                $args[$j] = [];
            } elseif (!array_key_exists($j, $args)) {
                $args[$j] = false;
            }
        }
        return $args;
    }

    /**
     * @return void
     */
    private function getClassAnnotations()
    {
        $this->mapping = Mapping::getReader($this->class);
        $this->properties = $this->mapping->getPropertiesMapping();
        $this->joinedProperties = $this->mapping->getPropertiesMapping('Joined');
    }
    
    public function insert($input)
    {
    	return $this->database->copy($input);
    }
    
    private function join($join, $result)
    {
    	foreach ($join as $method => $joinArray) {
            $className = key($joinArray);
            $joinCriteria = $this->joinCriteria($joinArray[$className], $className, $result);
            $this->database->setPath($this->folder.ucfirst($className));
            $resultJoin = $this->database->find($joinCriteria);
            
            if (count($resultJoin) == 1) {
            	$resultJoin = $resultJoin[key($resultJoin)]; 
            }
            
            foreach($this->joinedProperties as $property => $needed) {
            	if (preg_match('/'.$className.'_([a-z_-]*)/', $needed, $infos) && array_key_exists($infos[1], $resultJoin)) {
            		$result->$property = $resultJoin[$infos[1]];
            	}
            }
        }
        return $result;
    }

    /**
     * @param $criteria
     * @param $table
     * @return string
     */
    private function joinCriteria($criteria, $table, $result)
    {
    	$joinCriteria = [];
    	foreach ($criteria as $boolean => $column) {
    		if(!is_array($column)) {
                $joinMapping = $this->mapping->getPropertieJoinColumn($column, $table);
            }
            
            foreach ($joinMapping as $key => $value) {
            	preg_match('/^@([a-zA-Z_-]*)\.@([a-zA-Z_-]*)/', $key, $matchesKey);
            	preg_match('/^@([a-zA-Z_-]*)\.@([a-zA-Z_-]*)/', $value, $matchesValue);
            	if ($this->entity->getClassName() == ucfirst($matchesKey[1])) {
            		$joinCriteria[$matchesValue[2]] = $this->entity->$matchesKey[2];
            	}
            }
    	}
    	return $joinCriteria; 
    }
    
    public function delete($id)
    {
    	return $this->database->delete($id);
    }
    
    private function useParams($params)
    {
    	foreach($params as $key => $value) {
    		switch($key) {
    			case 'folder':
    				$this->folder = $value.'/library/data/';
    				break;
    			case 'identification':
    			case 'initialisation':
    				$this->conf[$key] = $value;
    				break;
    		}
    	}
    }

    /// METHODS
    /**
     * @param $entity
     * @param $string
     * @return string
     */
    public function mappingGetValue($entity, $string)
    {
    	$class = get_class($entity);
    	$mapping = Mapping::getReader($class);
    	$reflectionClass = new \ReflectionClass($class);
        $table = $reflectionClass->getShortName();
        $mapping->getPropertiesMapping();
        return $mapping->getName($table).'.'.$mapping->getValue($string);
    }
    
    
    private function order($key, $array, $order, $max)
    {
    	if (!$order) {
    		return $key;
    	}
    	$orderValue = '';
    	foreach ($order as $target => $value) {
    		if (preg_match('/^(.*)\.(.*)$/', $target, $matches)) {
    			$newOrder = lcfirst(str_replace('_', '', ucwords($matches[1], $delimiters = "_"))).'_'.$matches[2];
    			$orderValue .= $this->order($key, $array, [$newOrder => $value], 1);
    		} elseif (is_numeric($array[$target])) {
	    		$orderValue .= $array[$target]*1000000;
	    	} else {
	    		for($i=0 ; $i < 7 ; $i++) {
	    			$ord = ord(substr($array[$target].'       ',$i,1));
	    			
	    			if ($ord < 100) {
	    				$orderValue .= '0'.$ord;
	    			} else {
	    				$orderValue .= $ord;
	    			}
	    		}
	    		$orderValue = $orderValue+100000000000000000;
	    	}
    	}
    	return $resultKey = $orderValue.($key*pow(10,$max+1));
    }
}