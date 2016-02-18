<?php

namespace MicroDB;

/**
 * Description of Mapping
 *
 * @author Sébastien Dugène
 */
class Mapping
{
    private $reader = null;
    private $propertiesMappingColumn = null;
    private $propertiesMappingJoined = null;
    private $reflectionClass = null;
    private $classMapping = [];
    private $className = null;

    /**
     * @param $className
     * @return void
     */
    private function __construct($className)
    {
        $this->reader = \Minime\Annotations\Reader::createFromDefaults();
        $this->reflectionClass = new \ReflectionClass($className);
        $this->className = $className;
    }

    /**
     * @param $className
     * @return Mapping
     */
    public static function getReader($className)
    {
        return new Mapping($className);
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getClassMapping($key)
    {
        if (!array_key_exists($key, $this->classMapping)) {
            $annotations = $this->reader->getClassAnnotations($this->className);
            $value = $annotations->get($key);
            $values = json_encode($this->mappingParse($value));
            $this->classMapping[$key] = json_decode($values);
        }
        return $this->classMapping[$key];
    }

    /**
     * @param $table
     * @return mixed
     */
    public function getProperties($table)
    {
        $nameSpace = $this->reflectionClass->getNamespaceName();
        $mapping = Mapping::getReader($nameSpace.'\\'.ucfirst($table));
        return $mapping->getPropertiesMapping();
    }

    /**
     * @param $table
     * @return mixed
     */
    public function getName($table)
    {
        $nameSpace = $this->reflectionClass->getNamespaceName();
        $mapping = Mapping::getReader($nameSpace.'\\'.ucfirst($table));
        return $mapping->getClassMapping('Table')->name;
    }

    /**
     * @return array|null
     */
    public function getPropertiesMapping($type = 'Column')
    {
    	$attributeName = "propertiesMapping".$type;
        if (is_null($this->$attributeName)) {
            $array = [];
            $properties = $this->reflectionClass->getProperties();
            foreach($properties as $property) {
                $column = $this->reader->getPropertyAnnotations($this->className, $property->name)->get('ORM\\'.$type);
                if(!is_null($column)) {
                    $values = $this->mappingParse($column);
                    $array[$property->name] = $values['name'];
                }
            }
            $this->$attributeName = $array;
        }
        return $this->$attributeName;
    }

    /**
     * @param $column
     * @param $table
     * @return array
     */
    public function getPropertieJoinColumn($column, $table) {
        $joinColumn = $this->reader
                ->getPropertyAnnotations($this->className, $column)->get('ORM\JoinColumn');
        
        if (is_array($joinColumn)) {
            foreach ($joinColumn as $line) {
                $values = $this->mappingParse($line);
                if ($values['name'] == $table) {
                    return [
                        '@'.$this->reflectionClass->getShortName().'.@'.$column
                            => '@'.ucfirst($table).'.@'. $values['referencedColumnName']
                    ];
                }
            }
        }
        
        if(!is_null($joinColumn)) {
            $values = $this->mappingParse($joinColumn);
            return [
                '@'.$this->reflectionClass->getShortName().'.@'.$column
                    => '@'.ucfirst($table).'.@'. $values['referencedColumnName']
            ];
        }
    }

    /**
     * @param $tring
     * @return mixed
     */
    public function getValue($tring) {
        return $this->propertiesMappingColumn[$tring];
    }

    /**
     * @param $string
     * @return array
     */
    private function mappingParse($string)
    {
        $array = [];
        $matches = [];
        preg_match_all('/([a-zA-Z\d]+)="([a-zA-Z\d_\-]+)"|([a-zA-Z\d]+)=({"[a-zA-Z\d_\-]+"})/', $string, $matches, PREG_SET_ORDER) ;
        foreach ($matches as $value) {
            if ($value[1] != '') {
                $array[$value[1]] = $value[2];
            } elseif ($value[3] != '') {
                $array[$value[3]] = json_decode(str_replace(array('{','}'),array('[',']'),$value[4]), true);
            }
        }
        return $array;
    }

    /**
     * @param $string
     * @return string
     */
    public function valueMapping($string)
    {
        $matches = [];
        if (preg_match('/@([a-zA-Z\d_\-]+).@([a-zA-Z\d_\-]+)/', $string, $matches)) {
            $table = '';
            $column = '';
            if ($matches[1] == 'this') {
                $table = $this->classMapping['Table']->name;
                $column = $this->propertiesMapping[$matches[2]];
            } else {
                $nameSpace = $this->reflectionClass->getNamespaceName();
                $mapping = Mapping::getReader($nameSpace.'\\'.ucfirst($matches[1]));
                $table = $mapping->getClassMapping('Table')->name;
                $properties = $mapping->getPropertiesMapping();
                $column = $properties[$matches[2]];
            }
            return '`'.$table.'`' . '.' . '`'.$column.'`';
        } else {
            return $string;
        }
    }
}