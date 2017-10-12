<?php
namespace Stock;

use Exception;
/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/09/2017
 * Time: 11:31
 */
class Base {
	const PFX_GET = 'get_';
	const PFX_SET = 'set_';
	const PFX_IS = 'is_';

	/**
	 * To be extended by inheritors if persistence needed, as in Singleton
	 */
	protected function persist($key=null){
	}
	static protected function getPersisted($key=null){
		return null;
	}

	public function __set($name,$value){
		$method = self::PFX_SET.$name;
		if(method_exists($this,$method)){
			$this->$method($value);
		} else {
			// Getter/Setter not defined so set as property of object
			if (property_exists($this,$name)) {
				$this->$name = $value;
			} else {
				throw new Exception("Property '{$name}' does not exist in ".get_called_class());
			}
		}
		$this->persist();
	}

	public function __get($name){
		$methods = [self::PFX_GET,self::PFX_IS];
		foreach ($methods as $method){
			$method .= $name;
			if(method_exists($this, $method)){
				return $this->$method();
			}
		}
		if (property_exists($this,$name)) {
			// Getter/Setter not defined so return property if it exists
			return $this->$name;
		} else {
			throw new Exception("Property '{$name}' does not exist in ".get_called_class());
		}
	}

	public function __isset($name){
		return (
			property_exists($this,$name) ||
			method_exists($this,self::PFX_SET.$name) || // For a write-only property
			method_exists($this,self::PFX_GET.$name) || // For a read-only property
			method_exists($this,self::PFX_IS.$name)     // For a read-only boolean property
		);
	}
	public function __unset($name){
		unset($this->$name);
	}
}