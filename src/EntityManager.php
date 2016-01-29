<?php

namespace MicroDB;

/**
 * Description of EntityManager
 *
 * @author Sébastien Dugene
 */
class EntityManager
{
	private static $_instance = null;
	
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
    	$this->database->setPath($this->folder.$entity);
    	return $this;
    }
    
    public function find($id)
    {
    	return $this->database->load($id);
    }
    
    public function insert($input)
    {
    	return $this->database->copy($input);
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
}