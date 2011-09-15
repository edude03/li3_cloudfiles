<?php

namespace li3_cloudfiles\models;

use lithium\data\model\Query;

class Files extends \lithium\data\Model {
    
    protected $_meta = array(
        'connection' => 'cloudfiles',
        'source'     => 'object',
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
                $params['options']['conditions']['name'] = $params['options']['one'];
                return $chain->next($self, $params, $chain);                
            },
            'in'  => function ($self, $params, $chain) {
                $params['options']['conditions'] = array(
                    'container' => $params['options']['in']
                );
                return $chain->next($self, $params, $chain);
            }
        );
    }
    
}
