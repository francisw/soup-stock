<?php
namespace Stock;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/09/2017
 * Time: 11:30
 */
class SitePersistedSingleton extends Singleton  {
    /**
     * Persist and recall this object in options
     * @param null $key
     * @return SitePersistedSingleton The object calling this method
     */
	protected function persist($key=null){
		$class = get_called_class();
		update_option("vs-{$class}",$this,false);
		return $this;
	}
	static protected function getPersisted($key=null){
		$class = get_called_class();
		return get_option("vs-{$class}");
	}
}