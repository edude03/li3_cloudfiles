<?php

namespace li3_cloudfiles\extensions\adapter\data\source\http\cloudfiles;

class CdnContainer extends \li3_cloudfiles\extensions\adapter\data\source\http\CloudFiles {
    
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

        $params['url'] = 'files.cdnManagementUrl';
        
        $model    = $query->model();
        $response = $this->_send(__FUNCTION__, $method, $conditions, $params);

        if (!empty($conditions)) {

            $result = array(
                'name'            => $conditions['name'],
                'bytes'           => (integer) $response->headers('X-Container-Bytes-Used'),
                'count'           => (integer) $response->headers('X-Container-Object-Count'),
                'cdnEnabled'      => $response->headers('X-CDN-URI') ?: false,
                'cdnSslUri'       => $response->headers('X-CDN-SSL-URI') ?: null,
                'cdnStreamingUri' => $response->headers('X-CDN-STREAMING-URI') ?: null,
                'cdnTtl'          => (integer) $response->headers('X-TTL'),
                'cdnLogRetention' => (strtolower($response->headers('X-Log-Retention')) == 'true') ? true : false
            );

            return $this->item($model, $result, array('class' => 'entity'));
        }
        
        $result = array();
        
        foreach (json_decode($response->body[0]) as $container) {

            $result[] = $this->_instance($this->_classes['entity'], array(
                'data'   => array(
                    'name'            => $container->name,
                    'bytes'           => isset($container->bytes)             ? $container->bytes : null,
                    'count'           => isset($container->count)             ? $container->count : null,
                    'cdnEnabled'      => isset($container->cdn_enabled)       ? $container->cdn_enabled : false,
                    'cdnSslUri'       => isset($container->cdn_ssl_uri)       ? $container->cdn_ssl_uri : false,
                    'cdnStreamingUri' => isset($container->cdn_streaming_uri) ? $container->cdn_streaming_uri : false,
                    'cdnTtl'          => isset($container->ttl)               ? $container->ttl : false,
                    'cdnLogRetention' => isset($container->log_retention)     ? $container->log_retention : false,
                ),
                'exists' => true,
                'model'  => $model
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
        
        if (isset($data['data']['cdnEnabled']) && $data['data']['cdnEnabled'] === true) {
            $params['url'] = 'files.cdnManagementUrl';
            $params['headers']['X-CDN-Enabled'] = true;
        }
        
        if (isset($data['data']['cdnLogRetention']) && $data['data']['cdnLogRetention'] === true) {
            $params['headers']['X-Log-Retention'] = 'True';
        }
        
        if (isset($data['data']['cdnTtl'])) {
            $params['headers']['X-TTL'] = $data['data']['cdnTtl'];
        }
        
        $response = $this->_send(__FUNCTION__, 'PUT', array('name' => $data['data']['name']), $params);
        
        $result = array(
            'name'            => $data['data']['name'],
            'bytes'           => (integer) $response->headers('X-Container-Bytes-Used'),
            'count'           => (integer) $response->headers('X-Container-Object-Count'),
            'cdnEnabled'      => $response->headers('X-CDN-URI') ?: false,
            'cdnSslUri'       => $response->headers('X-CDN-SSL-URI') ?: null,
            'cdnStreamingUri' => $response->headers('X-CDN-STREAMING-URI') ?: null,
            'cdnTtl'          => (integer) $response->headers('X-TTL'),
            'cdnLogRetention' => (strtolower($response->headers('X-Log-Retention')) == 'true') ? true : false
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