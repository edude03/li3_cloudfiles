<?php

namespace li3_cloudfiles\extensions\adapter\data\source\http\cloudfiles;

class StorageContainer extends \li3_cloudfiles\extensions\adapter\data\source\http\CloudFiles {
    
    protected $_sources = array(
        'read' => array(
            '/'        => array(),
            '/{:name}' => array('name')
        ),
        'create' => '/{:name}',
        'update' => '/{:name}'
    );

    public function read($query, array $options = array()) {
        
        $params  = array();
        $method  = 'HEAD';
        
        extract($query->export($this, array('conditions')));

        if (!$conditions) {
            $conditions = array();
        }

        if (!isset($conditions['name'])) {
            $conditions = array();
            $params     = array('data' => array('format' => 'json'));
            $method     = 'GET';
        }
        
        $params['url'] = 'files.storageUrl';
        $model         = $query->model();
        $response      = $this->_send(__FUNCTION__, $method, $conditions, $params);

        if (!empty($conditions)) {

            $result = array(
                'name'            => $conditions['name'],
                'bytes'           => (integer) $response->headers('X-Container-Bytes-Used'),
                'count'           => (integer) $response->headers('X-Container-Object-Count'),
            );

            return $this->item($model, $result, array('class' => 'entity'));
        }
        
        $result = array();
        
        foreach (json_decode($response->body[0]) as $container) {

            $result[] = $this->_instance($this->_classes['entity'], array(
                'exists' => true,
                'model'  => $model,
                'data'   => array(
                    'name'  => $container->name,
                    'bytes' => isset($container->bytes) ? $container->bytes : null,
                    'count' => isset($container->count) ? $container->count : null,
                )
            ));
        }

        return $this->item($model, $result, array('class' => 'set'));       
    }
    
    public function create($query, array $options = array()) {
        
        $params = array();
        
        extract($query->export($this, array('data')));
        
        if (isset($data['data']['meta'])) {
            foreach ($data['data']['meta'] as $meta => $value) {
                $header = 'X-Container-Meta-' . ucfirst($meta);
                $params['headers'][$header] = $value;
            }
            unset($data['data']['meta']);
        }
        
        $response = $this->_send(__FUNCTION__, 'PUT', array('name' => $data['data']['name']), $params);
        
        $result = array(
            'name'  => $data['data']['name'],
            'bytes' => (integer) $response->headers('X-Container-Bytes-Used'),
            'count' => (integer) $response->headers('X-Container-Object-Count')
        );
        
        $query->entity()->sync(null, $result);
        
        return true;
    }
    
    public function update($query, array $options = array()) {
        throw new QueryException('Update not implemented');
    }
    
    public function delete($query, array $options = array()) {
        throw new QueryException('Delete not implemented');
    }

}

?>