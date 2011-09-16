<?php

namespace li3_cloudfiles\models;

use lithium\data\model\Query;

class StorageContainers extends \lithium\data\Model {
    
    protected $_meta = array(
        'connection' => 'cloudfiles',
        'source'     => 'storageContainer',
        'key'        => 'name'
    );
    
    public static function __init() {
        
        parent::__init();

        $self = static::_object();
        
        $self->_finders = array(
            'one' => function ($self, $params, $chain) {
                return $chain->next($self, $params, $chain);                
            },
            'all' => function ($self, $params, $chain) {
                return $chain->next($self, $params, $chain);
            }
        );
    }
    
}
