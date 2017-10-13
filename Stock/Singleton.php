<?php
namespace Stock;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/09/2017
 * Time: 11:30
 */
class Singleton extends Base  {
	/**
	 * Get a singleton of this class.
	 *
	 * @return Base
	 */
	static function single(){
		static $single = [];
		$class = get_called_class();

		if( empty($single[$class]) ) {
			$single[$class] = static::getPersisted();
			if (!$single[$class]) {
				$single[$class] = new $class();
			}
		}
		return $single[$class];
	}
}