<?php

namespace li3_cloudfiles\models;

use lithium\data\model\Query;

class Containers extends \lithium\data\Model {
    
    protected $_meta = array(
        'connection' => 'cloudfiles',
        'source'     => 'container',
        'key'        => 'name'
    );
    
    public static function __init() {
        
        parent::__init();
        
        $nullFinder = function ($self, $params, $chain) {
            return null;
        };
        
        $self = static::_object();
        
        $self->_finders = array(
            'one' => function ($self, $params, $chain) {
                return $chain->next($self, $params, $chain);                
            },
            'list' => function ($self, $params, $chain) {
                return $result;
            },
            'all' => function ($self, $params, $chain) {
                return $chain->next($self, $params, $chain);
            }
        );
    }
    
}
