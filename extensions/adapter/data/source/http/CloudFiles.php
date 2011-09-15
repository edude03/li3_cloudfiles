<?php

namespace li3_cloudfiles\extensions\adapter\data\source\http;

use lithium\util\String;
use lithium\util\Inflector;
use lithium\data\model\QueryException;
use lithium\storage\Cache;
use lithium\net\http\Media;

/**
 * CloudFiles API data source for Lithium.
 */
class CloudFiles extends \lithium\data\source\Http {
    
    /**
     * Map of actions to URI path and parameters.
     *
     * @var array 
     */
    protected $_sources = array();

    /**
     * Fully-name-spaced class references to `CloudFiles` class dependencies.
     *
     * @var array
     */
    protected $_classes = array(
        'service'   => 'lithium\net\http\Service',
        'entity'    => 'lithium\data\entity\Document',
        'set'       => 'lithium\data\collection\DocumentSet',
        'object'    => 'li3_cloudfiles\extensions\adapter\data\source\http\cloudfiles\Object',
        'container' => 'li3_cloudfiles\extensions\adapter\data\source\http\cloudfiles\Container',
    );
    
    /**
     * Key that will be used to store/retrive auth data to/from the cache.
     * 
     * @var string
     */
    protected $_credentialsCacheKey = 'rackspace.cloud.auth';
    
    /**
     * Authentication response headers with their mapped array keys.
     * 
     * @var array
     */
    protected $_authResponseHeader = array(
        'X-Auth-Token'            => 'token',
        'X-CDN-Management-Url'    => 'files.cdnManagementUrl',
        'X-Storage-Url'           => 'files.storageUrl',
        'X-Storage-Token'         => 'files.storageToken',
    );

    /**
     * Initializes a new `CloudFiles` instance with the default settings.
     * 
     * @param array $config Class configuration
     */
    public function __construct(array $config = array()) {
        
        $defaults = array(
            'host'     => 'auth.api.rackspacecloud.com',
            'port'     => 443,
            'scheme'   => 'https',
            'cache'    => false,
            'basePath' => '/v1.0'
        );

        parent::__construct($config + $defaults);
    }
    
    /**
     * Sends an authentication request to CloudFiles server. If `cache` was enabled, 
     * will save/retrieve data to/from it.
     * 
     * @see self::$_authResponseHeader
     * 
     * @return array Authentication credentials.
     */
    protected function credentials() {

        // checking if auth data is already in cache
        if ($this->_config['cache']) {

            $credentials = Cache::read($this->_config['cache'], $this->_credentialsCacheKey);
            
            if ($credentials) {
                return $credentials;
            }
        }
        
        $this->connection->get($this->_config['basePath'], array(), array(
            'headers' => array(
                'X-Auth-User' => $this->_config['login'],
                'X-Auth-Key'  => $this->_config['password']
            )
        ));
        
        $response    = $this->connection->last->response;
        $credentials = array();
        
        foreach ($this->_authResponseHeader as $header => $key) {
            $credentials[$key] = $response->headers($header);
        }
        
        // save to cache if enabled, respecting the Cache-Control header
        if ($this->_config['cache']) {
            $expires = sscanf($response->headers('Cache-Control'), 's-maxage=%d');
            Cache::write($this->_config['cache'], $this->_credentialsCacheKey, $credentials, $expires[0]);
        }

        return $credentials;
    }
    
    /**
     * Maps action/parameters to the URI path to be used in the request.
     * 
     * @param string $type Action being performed (`create`, `read`, `update` or `delete).
     * @param array $params Action parameters.
     * 
     * @return string URI path to be used in the request.
     */
    protected function _path($type, array $params = array()) {

        if (!isset($this->_sources[$type])) {
            return null;
        }      
        
        $path = null;        
        $keys = array_keys($params);
        sort($keys);

        foreach ($this->_sources[$type] as $sourcePath => $sourceParams) {

            sort($sourceParams);

            if ($sourceParams === $keys) {
                $path = String::insert($sourcePath, array_map('urlencode', $params) + $this->_config);
                break;
            }            
        }

        return $path;
    }
    
    /**
     * Sends a request and returns the response object.
     * 
     * @param type $type Request type (`create`, `read`, `update`, `delete)
     * @param type $method HTTP method.
     * @param type $data Request data.
     * @param array $options Additional request options (eg. `headers`).
     * 
     * @return object Instance of net\http\Response.
     */
    protected function _send($type, $method, $data, array $options = array()) {
        
        $defaults = array('url' => 'files.storageUrl');
        $options  = $options + $defaults;

        $credentials = $this->credentials();
        $path        = $this->_path($type, $data);
        $service     = $this->_instance($this->_classes['service'], parse_url($credentials[$options['url']]));

        if (!$path) {
            throw new QueryException('Unknown request type');
        }
        
        if (isset($options['headers'])) {
            $options['headers']['X-Auth-Token'] = $credentials['token'];
        } else {
            $options['headers'] = array('X-Auth-Token' => $credentials['token']);
        }

        if (in_array($type, array('create', 'update'))) {
            $options['type'] = $data['type'];
            $options['headers']['Content-type'] = $data['type'];
            $data            = $data['content'];
        }

        if ($type === 'create') {
            $options['headers']['Content-Length'] = mb_strlen($data);
        }

        if (isset($options['data']) && is_array($options['data'])) {
            $data += $options['data'];
        }

        $service->send($method, $path, $data, $options);

        $status = $service->last->response->status;

        if ($status['code'] >= 400) {
            throw new QueryException('Could not process request: ' . $status['message'], $status['code']);
        }

        return $service->last->response;
    }
    
    public function read($query, array $options = array()) {
        extract($query->export($this, array('source')));
        $instance = $this->_instance($this->_classes[$source], $this->_config);
        return $instance->read($query, $options);
    }
    
    public function create($query, array $options = array()) {
        extract($query->export($this, array('source')));
        $instance = $this->_instance($this->_classes[$source], $this->_config);
        return $instance->create($query, $options);
    }
    
    public function update($query, array $options = array()) {
        extract($query->export($this, array('source')));
        $instance = $this->_instance($this->_classes[$source], $this->_config);
        return $instance->update($query, $options);
    }
}