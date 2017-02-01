<?php


namespace Symfony\Component\HttpFoundation
{
    class ParameterBag implements \IteratorAggregate, \Countable
    {
        protected $parameters;
        public function __construct(array $parameters = array())
        {
            $this->parameters = $parameters;
        }
        public function all()
        {
            return $this->parameters;
        }
        public function keys()
        {
            return array_keys($this->parameters);
        }
        public function replace(array $parameters = array())
        {
            $this->parameters = $parameters;
        }
        public function add(array $parameters = array())
        {
            $this->parameters = array_replace($this->parameters, $parameters);
        }
        public function get($key, $default = null)
        {
            return array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $default;
        }
        public function set($key, $value)
        {
            $this->parameters[$key] = $value;
        }
        public function has($key)
        {
            return array_key_exists($key, $this->parameters);
        }
        public function remove($key)
        {
            unset($this->parameters[$key]);
        }
        public function getAlpha($key, $default ='')
        {
            return preg_replace('/[^[:alpha:]]/','', $this->get($key, $default));
        }
        public function getAlnum($key, $default ='')
        {
            return preg_replace('/[^[:alnum:]]/','', $this->get($key, $default));
        }
        public function getDigits($key, $default ='')
        {
            return str_replace(array('-','+'),'', $this->filter($key, $default, FILTER_SANITIZE_NUMBER_INT));
        }
        public function getInt($key, $default = 0)
        {
            return (int) $this->get($key, $default);
        }
        public function getBoolean($key, $default = false)
        {
            return $this->filter($key, $default, FILTER_VALIDATE_BOOLEAN);
        }
        public function filter($key, $default = null, $filter = FILTER_DEFAULT, $options = array())
        {
            $value = $this->get($key, $default);
            if (!is_array($options) && $options) {
                $options = array('flags'=> $options);
            }
            if (is_array($value) && !isset($options['flags'])) {
                $options['flags'] = FILTER_REQUIRE_ARRAY;
            }
            return filter_var($value, $filter, $options);
        }
        public function getIterator()
        {
            return new \ArrayIterator($this->parameters);
        }
        public function count()
        {
            return count($this->parameters);
        }
    }
}
namespace Symfony\Component\HttpFoundation
{
    class HeaderBag implements \IteratorAggregate, \Countable
    {
        protected $headers = array();
        protected $cacheControl = array();
        public function __construct(array $headers = array())
        {
            foreach ($headers as $key => $values) {
                $this->set($key, $values);
            }
        }
        public function __toString()
        {
            if (!$this->headers) {
                return'';
            }
            $max = max(array_map('strlen', array_keys($this->headers))) + 1;
            $content ='';
            ksort($this->headers);
            foreach ($this->headers as $name => $values) {
                $name = implode('-', array_map('ucfirst', explode('-', $name)));
                foreach ($values as $value) {
                    $content .= sprintf("%-{$max}s %s\r\n", $name.':', $value);
                }
            }
            return $content;
        }
        public function all()
        {
            return $this->headers;
        }
        public function keys()
        {
            return array_keys($this->headers);
        }
        public function replace(array $headers = array())
        {
            $this->headers = array();
            $this->add($headers);
        }
        public function add(array $headers)
        {
            foreach ($headers as $key => $values) {
                $this->set($key, $values);
            }
        }
        public function get($key, $default = null, $first = true)
        {
            $key = str_replace('_','-', strtolower($key));
            if (!array_key_exists($key, $this->headers)) {
                if (null === $default) {
                    return $first ? null : array();
                }
                return $first ? $default : array($default);
            }
            if ($first) {
                return count($this->headers[$key]) ? $this->headers[$key][0] : $default;
            }
            return $this->headers[$key];
        }
        public function set($key, $values, $replace = true)
        {
            $key = str_replace('_','-', strtolower($key));
            $values = array_values((array) $values);
            if (true === $replace || !isset($this->headers[$key])) {
                $this->headers[$key] = $values;
            } else {
                $this->headers[$key] = array_merge($this->headers[$key], $values);
            }
            if ('cache-control'=== $key) {
                $this->cacheControl = $this->parseCacheControl($values[0]);
            }
        }
        public function has($key)
        {
            return array_key_exists(str_replace('_','-', strtolower($key)), $this->headers);
        }
        public function contains($key, $value)
        {
            return in_array($value, $this->get($key, null, false));
        }
        public function remove($key)
        {
            $key = str_replace('_','-', strtolower($key));
            unset($this->headers[$key]);
            if ('cache-control'=== $key) {
                $this->cacheControl = array();
            }
        }
        public function getDate($key, \DateTime $default = null)
        {
            if (null === $value = $this->get($key)) {
                return $default;
            }
            if (false === $date = \DateTime::createFromFormat(DATE_RFC2822, $value)) {
                throw new \RuntimeException(sprintf('The %s HTTP header is not parseable (%s).', $key, $value));
            }
            return $date;
        }
        public function addCacheControlDirective($key, $value = true)
        {
            $this->cacheControl[$key] = $value;
            $this->set('Cache-Control', $this->getCacheControlHeader());
        }
        public function hasCacheControlDirective($key)
        {
            return array_key_exists($key, $this->cacheControl);
        }
        public function getCacheControlDirective($key)
        {
            return array_key_exists($key, $this->cacheControl) ? $this->cacheControl[$key] : null;
        }
        public function removeCacheControlDirective($key)
        {
            unset($this->cacheControl[$key]);
            $this->set('Cache-Control', $this->getCacheControlHeader());
        }
        public function getIterator()
        {
            return new \ArrayIterator($this->headers);
        }
        public function count()
        {
            return count($this->headers);
        }
        protected function getCacheControlHeader()
        {
            $parts = array();
            ksort($this->cacheControl);
            foreach ($this->cacheControl as $key => $value) {
                if (true === $value) {
                    $parts[] = $key;
                } else {
                    if (preg_match('#[^a-zA-Z0-9._-]#', $value)) {
                        $value ='"'.$value.'"';
                    }
                    $parts[] = "$key=$value";
                }
            }
            return implode(', ', $parts);
        }
        protected function parseCacheControl($header)
        {
            $cacheControl = array();
            preg_match_all('#([a-zA-Z][a-zA-Z_-]*)\s*(?:=(?:"([^"]*)"|([^ \t",;]*)))?#', $header, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $cacheControl[strtolower($match[1])] = isset($match[3]) ? $match[3] : (isset($match[2]) ? $match[2] : true);
            }
            return $cacheControl;
        }
    }
}
namespace Symfony\Component\HttpFoundation
{
    use Symfony\Component\HttpFoundation\File\UploadedFile;
    class FileBag extends ParameterBag
    {
        private static $fileKeys = array('error','name','size','tmp_name','type');
        public function __construct(array $parameters = array())
        {
            $this->replace($parameters);
        }
        public function replace(array $files = array())
        {
            $this->parameters = array();
            $this->add($files);
        }
        public function set($key, $value)
        {
            if (!is_array($value) && !$value instanceof UploadedFile) {
                throw new \InvalidArgumentException('An uploaded file must be an array or an instance of UploadedFile.');
            }
            parent::set($key, $this->convertFileInformation($value));
        }
        public function add(array $files = array())
        {
            foreach ($files as $key => $file) {
                $this->set($key, $file);
            }
        }
        protected function convertFileInformation($file)
        {
            if ($file instanceof UploadedFile) {
                return $file;
            }
            $file = $this->fixPhpFilesArray($file);
            if (is_array($file)) {
                $keys = array_keys($file);
                sort($keys);
                if ($keys == self::$fileKeys) {
                    if (UPLOAD_ERR_NO_FILE == $file['error']) {
                        $file = null;
                    } else {
                        $file = new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['size'], $file['error']);
                    }
                } else {
                    $file = array_map(array($this,'convertFileInformation'), $file);
                }
            }
            return $file;
        }
        protected function fixPhpFilesArray($data)
        {
            if (!is_array($data)) {
                return $data;
            }
            $keys = array_keys($data);
            sort($keys);
            if (self::$fileKeys != $keys || !isset($data['name']) || !is_array($data['name'])) {
                return $data;
            }
            $files = $data;
            foreach (self::$fileKeys as $k) {
                unset($files[$k]);
            }
            foreach ($data['name'] as $key => $name) {
                $files[$key] = $this->fixPhpFilesArray(array('error'=> $data['error'][$key],'name'=> $name,'type'=> $data['type'][$key],'tmp_name'=> $data['tmp_name'][$key],'size'=> $data['size'][$key],
                ));
            }
            return $files;
        }
    }
}
namespace Symfony\Component\HttpFoundation
{
    class ServerBag extends ParameterBag
    {
        public function getHeaders()
        {
            $headers = array();
            $contentHeaders = array('CONTENT_LENGTH'=> true,'CONTENT_MD5'=> true,'CONTENT_TYPE'=> true);
            foreach ($this->parameters as $key => $value) {
                if (0 === strpos($key,'HTTP_')) {
                    $headers[substr($key, 5)] = $value;
                }
                elseif (isset($contentHeaders[$key])) {
                    $headers[$key] = $value;
                }
            }
            if (isset($this->parameters['PHP_AUTH_USER'])) {
                $headers['PHP_AUTH_USER'] = $this->parameters['PHP_AUTH_USER'];
                $headers['PHP_AUTH_PW'] = isset($this->parameters['PHP_AUTH_PW']) ? $this->parameters['PHP_AUTH_PW'] :'';
            } else {
                $authorizationHeader = null;
                if (isset($this->parameters['HTTP_AUTHORIZATION'])) {
                    $authorizationHeader = $this->parameters['HTTP_AUTHORIZATION'];
                } elseif (isset($this->parameters['REDIRECT_HTTP_AUTHORIZATION'])) {
                    $authorizationHeader = $this->parameters['REDIRECT_HTTP_AUTHORIZATION'];
                }
                if (null !== $authorizationHeader) {
                    if (0 === stripos($authorizationHeader,'basic ')) {
                        $exploded = explode(':', base64_decode(substr($authorizationHeader, 6)), 2);
                        if (count($exploded) == 2) {
                            list($headers['PHP_AUTH_USER'], $headers['PHP_AUTH_PW']) = $exploded;
                        }
                    } elseif (empty($this->parameters['PHP_AUTH_DIGEST']) && (0 === stripos($authorizationHeader,'digest '))) {
                        $headers['PHP_AUTH_DIGEST'] = $authorizationHeader;
                        $this->parameters['PHP_AUTH_DIGEST'] = $authorizationHeader;
                    } elseif (0 === stripos($authorizationHeader,'bearer ')) {
                        $headers['AUTHORIZATION'] = $authorizationHeader;
                    }
                }
            }
            if (isset($headers['AUTHORIZATION'])) {
                return $headers;
            }
            if (isset($headers['PHP_AUTH_USER'])) {
                $headers['AUTHORIZATION'] ='Basic '.base64_encode($headers['PHP_AUTH_USER'].':'.$headers['PHP_AUTH_PW']);
            } elseif (isset($headers['PHP_AUTH_DIGEST'])) {
                $headers['AUTHORIZATION'] = $headers['PHP_AUTH_DIGEST'];
            }
            return $headers;
        }
    }
}
namespace Symfony\Component\HttpFoundation
{
    use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
    use Symfony\Component\HttpFoundation\Session\SessionInterface;
    class Request
    {
        const HEADER_FORWARDED ='forwarded';
        const HEADER_CLIENT_IP ='client_ip';
        const HEADER_CLIENT_HOST ='client_host';
        const HEADER_CLIENT_PROTO ='client_proto';
        const HEADER_CLIENT_PORT ='client_port';
        const METHOD_HEAD ='HEAD';
        const METHOD_GET ='GET';
        const METHOD_POST ='POST';
        const METHOD_PUT ='PUT';
        const METHOD_PATCH ='PATCH';
        const METHOD_DELETE ='DELETE';
        const METHOD_PURGE ='PURGE';
        const METHOD_OPTIONS ='OPTIONS';
        const METHOD_TRACE ='TRACE';
        const METHOD_CONNECT ='CONNECT';
        protected static $trustedProxies = array();
        protected static $trustedHostPatterns = array();
        protected static $trustedHosts = array();
        protected static $trustedHeaders = array(
            self::HEADER_FORWARDED =>'FORWARDED',
            self::HEADER_CLIENT_IP =>'X_FORWARDED_FOR',
            self::HEADER_CLIENT_HOST =>'X_FORWARDED_HOST',
            self::HEADER_CLIENT_PROTO =>'X_FORWARDED_PROTO',
            self::HEADER_CLIENT_PORT =>'X_FORWARDED_PORT',
        );
        protected static $httpMethodParameterOverride = false;
        public $attributes;
        public $request;
        public $query;
        public $server;
        public $files;
        public $cookies;
        public $headers;
        protected $content;
        protected $languages;
        protected $charsets;
        protected $encodings;
        protected $acceptableContentTypes;
        protected $pathInfo;
        protected $requestUri;
        protected $baseUrl;
        protected $basePath;
        protected $method;
        protected $format;
        protected $session;
        protected $locale;
        protected $defaultLocale ='en';
        protected static $formats;
        protected static $requestFactory;
        public function __construct(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
        {
            $this->initialize($query, $request, $attributes, $cookies, $files, $server, $content);
        }
        public function initialize(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
        {
            $this->request = new ParameterBag($request);
            $this->query = new ParameterBag($query);
            $this->attributes = new ParameterBag($attributes);
            $this->cookies = new ParameterBag($cookies);
            $this->files = new FileBag($files);
            $this->server = new ServerBag($server);
            $this->headers = new HeaderBag($this->server->getHeaders());
            $this->content = $content;
            $this->languages = null;
            $this->charsets = null;
            $this->encodings = null;
            $this->acceptableContentTypes = null;
            $this->pathInfo = null;
            $this->requestUri = null;
            $this->baseUrl = null;
            $this->basePath = null;
            $this->method = null;
            $this->format = null;
        }
        public static function createFromGlobals()
        {
            $server = $_SERVER;
            if ('cli-server'=== PHP_SAPI) {
                if (array_key_exists('HTTP_CONTENT_LENGTH', $_SERVER)) {
                    $server['CONTENT_LENGTH'] = $_SERVER['HTTP_CONTENT_LENGTH'];
                }
                if (array_key_exists('HTTP_CONTENT_TYPE', $_SERVER)) {
                    $server['CONTENT_TYPE'] = $_SERVER['HTTP_CONTENT_TYPE'];
                }
            }
            $request = self::createRequestFromFactory($_GET, $_POST, array(), $_COOKIE, $_FILES, $server);
            if (0 === strpos($request->headers->get('CONTENT_TYPE'),'application/x-www-form-urlencoded')
                && in_array(strtoupper($request->server->get('REQUEST_METHOD','GET')), array('PUT','DELETE','PATCH'))
            ) {
                parse_str($request->getContent(), $data);
                $request->request = new ParameterBag($data);
            }
            return $request;
        }
        public static function create($uri, $method ='GET', $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = null)
        {
            $server = array_replace(array('SERVER_NAME'=>'localhost','SERVER_PORT'=> 80,'HTTP_HOST'=>'localhost','HTTP_USER_AGENT'=>'Symfony/3.X','HTTP_ACCEPT'=>'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8','HTTP_ACCEPT_LANGUAGE'=>'en-us,en;q=0.5','HTTP_ACCEPT_CHARSET'=>'ISO-8859-1,utf-8;q=0.7,*;q=0.7','REMOTE_ADDR'=>'127.0.0.1','SCRIPT_NAME'=>'','SCRIPT_FILENAME'=>'','SERVER_PROTOCOL'=>'HTTP/1.1','REQUEST_TIME'=> time(),
            ), $server);
            $server['PATH_INFO'] ='';
            $server['REQUEST_METHOD'] = strtoupper($method);
            $components = parse_url($uri);
            if (isset($components['host'])) {
                $server['SERVER_NAME'] = $components['host'];
                $server['HTTP_HOST'] = $components['host'];
            }
            if (isset($components['scheme'])) {
                if ('https'=== $components['scheme']) {
                    $server['HTTPS'] ='on';
                    $server['SERVER_PORT'] = 443;
                } else {
                    unset($server['HTTPS']);
                    $server['SERVER_PORT'] = 80;
                }
            }
            if (isset($components['port'])) {
                $server['SERVER_PORT'] = $components['port'];
                $server['HTTP_HOST'] = $server['HTTP_HOST'].':'.$components['port'];
            }
            if (isset($components['user'])) {
                $server['PHP_AUTH_USER'] = $components['user'];
            }
            if (isset($components['pass'])) {
                $server['PHP_AUTH_PW'] = $components['pass'];
            }
            if (!isset($components['path'])) {
                $components['path'] ='/';
            }
            switch (strtoupper($method)) {
                case'POST':
                case'PUT':
                case'DELETE':
                    if (!isset($server['CONTENT_TYPE'])) {
                        $server['CONTENT_TYPE'] ='application/x-www-form-urlencoded';
                    }
                case'PATCH':
                    $request = $parameters;
                    $query = array();
                    break;
                default:
                    $request = array();
                    $query = $parameters;
                    break;
            }
            $queryString ='';
            if (isset($components['query'])) {
                parse_str(html_entity_decode($components['query']), $qs);
                if ($query) {
                    $query = array_replace($qs, $query);
                    $queryString = http_build_query($query,'','&');
                } else {
                    $query = $qs;
                    $queryString = $components['query'];
                }
            } elseif ($query) {
                $queryString = http_build_query($query,'','&');
            }
            $server['REQUEST_URI'] = $components['path'].(''!== $queryString ?'?'.$queryString :'');
            $server['QUERY_STRING'] = $queryString;
            return self::createRequestFromFactory($query, $request, array(), $cookies, $files, $server, $content);
        }
        public static function setFactory($callable)
        {
            self::$requestFactory = $callable;
        }
        public function duplicate(array $query = null, array $request = null, array $attributes = null, array $cookies = null, array $files = null, array $server = null)
        {
            $dup = clone $this;
            if ($query !== null) {
                $dup->query = new ParameterBag($query);
            }
            if ($request !== null) {
                $dup->request = new ParameterBag($request);
            }
            if ($attributes !== null) {
                $dup->attributes = new ParameterBag($attributes);
            }
            if ($cookies !== null) {
                $dup->cookies = new ParameterBag($cookies);
            }
            if ($files !== null) {
                $dup->files = new FileBag($files);
            }
            if ($server !== null) {
                $dup->server = new ServerBag($server);
                $dup->headers = new HeaderBag($dup->server->getHeaders());
            }
            $dup->languages = null;
            $dup->charsets = null;
            $dup->encodings = null;
            $dup->acceptableContentTypes = null;
            $dup->pathInfo = null;
            $dup->requestUri = null;
            $dup->baseUrl = null;
            $dup->basePath = null;
            $dup->method = null;
            $dup->format = null;
            if (!$dup->get('_format') && $this->get('_format')) {
                $dup->attributes->set('_format', $this->get('_format'));
            }
            if (!$dup->getRequestFormat(null)) {
                $dup->setRequestFormat($this->getRequestFormat(null));
            }
            return $dup;
        }
        public function __clone()
        {
            $this->query = clone $this->query;
            $this->request = clone $this->request;
            $this->attributes = clone $this->attributes;
            $this->cookies = clone $this->cookies;
            $this->files = clone $this->files;
            $this->server = clone $this->server;
            $this->headers = clone $this->headers;
        }
        public function __toString()
        {
            try {
                $content = $this->getContent();
            } catch (\LogicException $e) {
                return trigger_error($e, E_USER_ERROR);
            }
            return
                sprintf('%s %s %s', $this->getMethod(), $this->getRequestUri(), $this->server->get('SERVER_PROTOCOL'))."\r\n".
                $this->headers."\r\n".
                $content;
        }
        public function overrideGlobals()
        {
            $this->server->set('QUERY_STRING', static::normalizeQueryString(http_build_query($this->query->all(), null,'&')));
            $_GET = $this->query->all();
            $_POST = $this->request->all();
            $_SERVER = $this->server->all();
            $_COOKIE = $this->cookies->all();
            foreach ($this->headers->all() as $key => $value) {
                $key = strtoupper(str_replace('-','_', $key));
                if (in_array($key, array('CONTENT_TYPE','CONTENT_LENGTH'))) {
                    $_SERVER[$key] = implode(', ', $value);
                } else {
                    $_SERVER['HTTP_'.$key] = implode(', ', $value);
                }
            }
            $request = array('g'=> $_GET,'p'=> $_POST,'c'=> $_COOKIE);
            $requestOrder = ini_get('request_order') ?: ini_get('variables_order');
            $requestOrder = preg_replace('#[^cgp]#','', strtolower($requestOrder)) ?:'gp';
            $_REQUEST = array();
            foreach (str_split($requestOrder) as $order) {
                $_REQUEST = array_merge($_REQUEST, $request[$order]);
            }
        }
        public static function setTrustedProxies(array $proxies)
        {
            self::$trustedProxies = $proxies;
        }
        public static function getTrustedProxies()
        {
            return self::$trustedProxies;
        }
        public static function setTrustedHosts(array $hostPatterns)
        {
            self::$trustedHostPatterns = array_map(function ($hostPattern) {
                return sprintf('#%s#i', $hostPattern);
            }, $hostPatterns);
            self::$trustedHosts = array();
        }
        public static function getTrustedHosts()
        {
            return self::$trustedHostPatterns;
        }
        public static function setTrustedHeaderName($key, $value)
        {
            if (!array_key_exists($key, self::$trustedHeaders)) {
                throw new \InvalidArgumentException(sprintf('Unable to set the trusted header name for key "%s".', $key));
            }
            self::$trustedHeaders[$key] = $value;
        }
        public static function getTrustedHeaderName($key)
        {
            if (!array_key_exists($key, self::$trustedHeaders)) {
                throw new \InvalidArgumentException(sprintf('Unable to get the trusted header name for key "%s".', $key));
            }
            return self::$trustedHeaders[$key];
        }
        public static function normalizeQueryString($qs)
        {
            if (''== $qs) {
                return'';
            }
            $parts = array();
            $order = array();
            foreach (explode('&', $qs) as $param) {
                if (''=== $param ||'='=== $param[0]) {
                    continue;
                }
                $keyValuePair = explode('=', $param, 2);
                $parts[] = isset($keyValuePair[1]) ?
                    rawurlencode(urldecode($keyValuePair[0])).'='.rawurlencode(urldecode($keyValuePair[1])) :
                    rawurlencode(urldecode($keyValuePair[0]));
                $order[] = urldecode($keyValuePair[0]);
            }
            array_multisort($order, SORT_ASC, $parts);
            return implode('&', $parts);
        }
        public static function enableHttpMethodParameterOverride()
        {
            self::$httpMethodParameterOverride = true;
        }
        public static function getHttpMethodParameterOverride()
        {
            return self::$httpMethodParameterOverride;
        }
        public function get($key, $default = null)
        {
            if ($this !== $result = $this->attributes->get($key, $this)) {
                return $result;
            }
            if ($this !== $result = $this->query->get($key, $this)) {
                return $result;
            }
            if ($this !== $result = $this->request->get($key, $this)) {
                return $result;
            }
            return $default;
        }
        public function getSession()
        {
            return $this->session;
        }
        public function hasPreviousSession()
        {
            return $this->hasSession() && $this->cookies->has($this->session->getName());
        }
        public function hasSession()
        {
            return null !== $this->session;
        }
        public function setSession(SessionInterface $session)
        {
            $this->session = $session;
        }
        public function getClientIps()
        {
            $clientIps = array();
            $ip = $this->server->get('REMOTE_ADDR');
            if (!$this->isFromTrustedProxy()) {
                return array($ip);
            }
            $hasTrustedForwardedHeader = self::$trustedHeaders[self::HEADER_FORWARDED] && $this->headers->has(self::$trustedHeaders[self::HEADER_FORWARDED]);
            $hasTrustedClientIpHeader = self::$trustedHeaders[self::HEADER_CLIENT_IP] && $this->headers->has(self::$trustedHeaders[self::HEADER_CLIENT_IP]);
            if ($hasTrustedForwardedHeader) {
                $forwardedHeader = $this->headers->get(self::$trustedHeaders[self::HEADER_FORWARDED]);
                preg_match_all('{(for)=("?\[?)([a-z0-9\.:_\-/]*)}', $forwardedHeader, $matches);
                $forwardedClientIps = $matches[3];
                $forwardedClientIps = $this->normalizeAndFilterClientIps($forwardedClientIps, $ip);
                $clientIps = $forwardedClientIps;
            }
            if ($hasTrustedClientIpHeader) {
                $xForwardedForClientIps = array_map('trim', explode(',', $this->headers->get(self::$trustedHeaders[self::HEADER_CLIENT_IP])));
                $xForwardedForClientIps = $this->normalizeAndFilterClientIps($xForwardedForClientIps, $ip);
                $clientIps = $xForwardedForClientIps;
            }
            if ($hasTrustedForwardedHeader && $hasTrustedClientIpHeader && $forwardedClientIps !== $xForwardedForClientIps) {
                throw new ConflictingHeadersException('The request has both a trusted Forwarded header and a trusted Client IP header, conflicting with each other with regards to the originating IP addresses of the request. This is the result of a misconfiguration. You should either configure your proxy only to send one of these headers, or configure Symfony to distrust one of them.');
            }
            if (!$hasTrustedForwardedHeader && !$hasTrustedClientIpHeader) {
                return $this->normalizeAndFilterClientIps(array(), $ip);
            }
            return $clientIps;
        }
        public function getClientIp()
        {
            $ipAddresses = $this->getClientIps();
            return $ipAddresses[0];
        }
        public function getScriptName()
        {
            return $this->server->get('SCRIPT_NAME', $this->server->get('ORIG_SCRIPT_NAME',''));
        }
        public function getPathInfo()
        {
            if (null === $this->pathInfo) {
                $this->pathInfo = $this->preparePathInfo();
            }
            return $this->pathInfo;
        }
        public function getBasePath()
        {
            if (null === $this->basePath) {
                $this->basePath = $this->prepareBasePath();
            }
            return $this->basePath;
        }
        public function getBaseUrl()
        {
            if (null === $this->baseUrl) {
                $this->baseUrl = $this->prepareBaseUrl();
            }
            return $this->baseUrl;
        }
        public function getScheme()
        {
            return $this->isSecure() ?'https':'http';
        }
        public function getPort()
        {
            if ($this->isFromTrustedProxy()) {
                if (self::$trustedHeaders[self::HEADER_CLIENT_PORT] && $port = $this->headers->get(self::$trustedHeaders[self::HEADER_CLIENT_PORT])) {
                    return $port;
                }
                if (self::$trustedHeaders[self::HEADER_CLIENT_PROTO] &&'https'=== $this->headers->get(self::$trustedHeaders[self::HEADER_CLIENT_PROTO],'http')) {
                    return 443;
                }
            }
            if ($host = $this->headers->get('HOST')) {
                if ($host[0] ==='[') {
                    $pos = strpos($host,':', strrpos($host,']'));
                } else {
                    $pos = strrpos($host,':');
                }
                if (false !== $pos) {
                    return (int) substr($host, $pos + 1);
                }
                return'https'=== $this->getScheme() ? 443 : 80;
            }
            return $this->server->get('SERVER_PORT');
        }
        public function getUser()
        {
            return $this->headers->get('PHP_AUTH_USER');
        }
        public function getPassword()
        {
            return $this->headers->get('PHP_AUTH_PW');
        }
        public function getUserInfo()
        {
            $userinfo = $this->getUser();
            $pass = $this->getPassword();
            if (''!= $pass) {
                $userinfo .= ":$pass";
            }
            return $userinfo;
        }
        public function getHttpHost()
        {
            $scheme = $this->getScheme();
            $port = $this->getPort();
            if (('http'== $scheme && $port == 80) || ('https'== $scheme && $port == 443)) {
                return $this->getHost();
            }
            return $this->getHost().':'.$port;
        }
        public function getRequestUri()
        {
            if (null === $this->requestUri) {
                $this->requestUri = $this->prepareRequestUri();
            }
            return $this->requestUri;
        }
        public function getSchemeAndHttpHost()
        {
            return $this->getScheme().'://'.$this->getHttpHost();
        }
        public function getUri()
        {
            if (null !== $qs = $this->getQueryString()) {
                $qs ='?'.$qs;
            }
            return $this->getSchemeAndHttpHost().$this->getBaseUrl().$this->getPathInfo().$qs;
        }
        public function getUriForPath($path)
        {
            return $this->getSchemeAndHttpHost().$this->getBaseUrl().$path;
        }
        public function getRelativeUriForPath($path)
        {
            if (!isset($path[0]) ||'/'!== $path[0]) {
                return $path;
            }
            if ($path === $basePath = $this->getPathInfo()) {
                return'';
            }
            $sourceDirs = explode('/', isset($basePath[0]) &&'/'=== $basePath[0] ? substr($basePath, 1) : $basePath);
            $targetDirs = explode('/', isset($path[0]) &&'/'=== $path[0] ? substr($path, 1) : $path);
            array_pop($sourceDirs);
            $targetFile = array_pop($targetDirs);
            foreach ($sourceDirs as $i => $dir) {
                if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                    unset($sourceDirs[$i], $targetDirs[$i]);
                } else {
                    break;
                }
            }
            $targetDirs[] = $targetFile;
            $path = str_repeat('../', count($sourceDirs)).implode('/', $targetDirs);
            return !isset($path[0]) ||'/'=== $path[0]
            || false !== ($colonPos = strpos($path,':')) && ($colonPos < ($slashPos = strpos($path,'/')) || false === $slashPos)
                ? "./$path" : $path;
        }
        public function getQueryString()
        {
            $qs = static::normalizeQueryString($this->server->get('QUERY_STRING'));
            return''=== $qs ? null : $qs;
        }
        public function isSecure()
        {
            if ($this->isFromTrustedProxy() && self::$trustedHeaders[self::HEADER_CLIENT_PROTO] && $proto = $this->headers->get(self::$trustedHeaders[self::HEADER_CLIENT_PROTO])) {
                return in_array(strtolower(current(explode(',', $proto))), array('https','on','ssl','1'));
            }
            $https = $this->server->get('HTTPS');
            return !empty($https) &&'off'!== strtolower($https);
        }
        public function getHost()
        {
            if ($this->isFromTrustedProxy() && self::$trustedHeaders[self::HEADER_CLIENT_HOST] && $host = $this->headers->get(self::$trustedHeaders[self::HEADER_CLIENT_HOST])) {
                $elements = explode(',', $host);
                $host = $elements[count($elements) - 1];
            } elseif (!$host = $this->headers->get('HOST')) {
                if (!$host = $this->server->get('SERVER_NAME')) {
                    $host = $this->server->get('SERVER_ADDR','');
                }
            }
            $host = strtolower(preg_replace('/:\d+$/','', trim($host)));
            if ($host &&''!== preg_replace('/(?:^\[)?[a-zA-Z0-9-:\]_]+\.?/','', $host)) {
                throw new \UnexpectedValueException(sprintf('Invalid Host "%s"', $host));
            }
            if (count(self::$trustedHostPatterns) > 0) {
                if (in_array($host, self::$trustedHosts)) {
                    return $host;
                }
                foreach (self::$trustedHostPatterns as $pattern) {
                    if (preg_match($pattern, $host)) {
                        self::$trustedHosts[] = $host;
                        return $host;
                    }
                }
                throw new \UnexpectedValueException(sprintf('Untrusted Host "%s"', $host));
            }
            return $host;
        }
        public function setMethod($method)
        {
            $this->method = null;
            $this->server->set('REQUEST_METHOD', $method);
        }
        public function getMethod()
        {
            if (null === $this->method) {
                $this->method = strtoupper($this->server->get('REQUEST_METHOD','GET'));
                if ('POST'=== $this->method) {
                    if ($method = $this->headers->get('X-HTTP-METHOD-OVERRIDE')) {
                        $this->method = strtoupper($method);
                    } elseif (self::$httpMethodParameterOverride) {
                        $this->method = strtoupper($this->request->get('_method', $this->query->get('_method','POST')));
                    }
                }
            }
            return $this->method;
        }
        public function getRealMethod()
        {
            return strtoupper($this->server->get('REQUEST_METHOD','GET'));
        }
        public function getMimeType($format)
        {
            if (null === static::$formats) {
                static::initializeFormats();
            }
            return isset(static::$formats[$format]) ? static::$formats[$format][0] : null;
        }
        public static function getMimeTypes($format)
        {
            if (null === static::$formats) {
                static::initializeFormats();
            }
            return isset(static::$formats[$format]) ? static::$formats[$format] : array();
        }
        public function getFormat($mimeType)
        {
            $canonicalMimeType = null;
            if (false !== $pos = strpos($mimeType,';')) {
                $canonicalMimeType = substr($mimeType, 0, $pos);
            }
            if (null === static::$formats) {
                static::initializeFormats();
            }
            foreach (static::$formats as $format => $mimeTypes) {
                if (in_array($mimeType, (array) $mimeTypes)) {
                    return $format;
                }
                if (null !== $canonicalMimeType && in_array($canonicalMimeType, (array) $mimeTypes)) {
                    return $format;
                }
            }
        }
        public function setFormat($format, $mimeTypes)
        {
            if (null === static::$formats) {
                static::initializeFormats();
            }
            static::$formats[$format] = is_array($mimeTypes) ? $mimeTypes : array($mimeTypes);
        }
        public function getRequestFormat($default ='html')
        {
            if (null === $this->format) {
                $this->format = $this->attributes->get('_format', $default);
            }
            return $this->format;
        }
        public function setRequestFormat($format)
        {
            $this->format = $format;
        }
        public function getContentType()
        {
            return $this->getFormat($this->headers->get('CONTENT_TYPE'));
        }
        public function setDefaultLocale($locale)
        {
            $this->defaultLocale = $locale;
            if (null === $this->locale) {
                $this->setPhpDefaultLocale($locale);
            }
        }
        public function getDefaultLocale()
        {
            return $this->defaultLocale;
        }
        public function setLocale($locale)
        {
            $this->setPhpDefaultLocale($this->locale = $locale);
        }
        public function getLocale()
        {
            return null === $this->locale ? $this->defaultLocale : $this->locale;
        }
        public function isMethod($method)
        {
            return $this->getMethod() === strtoupper($method);
        }
        public function isMethodSafe()
        {
            if (!func_num_args() || func_get_arg(0)) {
                @trigger_error('Checking only for cacheable HTTP methods with Symfony\Component\HttpFoundation\Request::isMethodSafe() is deprecated since version 3.2 and will throw an exception in 4.0. Disable checking only for cacheable methods by calling the method with `false` as first argument or use the Request::isMethodCacheable() instead.', E_USER_DEPRECATED);
                return in_array($this->getMethod(), array('GET','HEAD'));
            }
            return in_array($this->getMethod(), array('GET','HEAD','OPTIONS','TRACE'));
        }
        public function isMethodIdempotent()
        {
            return in_array($this->getMethod(), array('HEAD','GET','PUT','DELETE','TRACE','OPTIONS','PURGE'));
        }
        public function isMethodCacheable()
        {
            return in_array($this->getMethod(), array('GET','HEAD'));
        }
        public function getContent($asResource = false)
        {
            $currentContentIsResource = is_resource($this->content);
            if (PHP_VERSION_ID < 50600 && false === $this->content) {
                throw new \LogicException('getContent() can only be called once when using the resource return type and PHP below 5.6.');
            }
            if (true === $asResource) {
                if ($currentContentIsResource) {
                    rewind($this->content);
                    return $this->content;
                }
                if (is_string($this->content)) {
                    $resource = fopen('php://temp','r+');
                    fwrite($resource, $this->content);
                    rewind($resource);
                    return $resource;
                }
                $this->content = false;
                return fopen('php://input','rb');
            }
            if ($currentContentIsResource) {
                rewind($this->content);
                return stream_get_contents($this->content);
            }
            if (null === $this->content || false === $this->content) {
                $this->content = file_get_contents('php://input');
            }
            return $this->content;
        }
        public function getETags()
        {
            return preg_split('/\s*,\s*/', $this->headers->get('if_none_match'), null, PREG_SPLIT_NO_EMPTY);
        }
        public function isNoCache()
        {
            return $this->headers->hasCacheControlDirective('no-cache') ||'no-cache'== $this->headers->get('Pragma');
        }
        public function getPreferredLanguage(array $locales = null)
        {
            $preferredLanguages = $this->getLanguages();
            if (empty($locales)) {
                return isset($preferredLanguages[0]) ? $preferredLanguages[0] : null;
            }
            if (!$preferredLanguages) {
                return $locales[0];
            }
            $extendedPreferredLanguages = array();
            foreach ($preferredLanguages as $language) {
                $extendedPreferredLanguages[] = $language;
                if (false !== $position = strpos($language,'_')) {
                    $superLanguage = substr($language, 0, $position);
                    if (!in_array($superLanguage, $preferredLanguages)) {
                        $extendedPreferredLanguages[] = $superLanguage;
                    }
                }
            }
            $preferredLanguages = array_values(array_intersect($extendedPreferredLanguages, $locales));
            return isset($preferredLanguages[0]) ? $preferredLanguages[0] : $locales[0];
        }
        public function getLanguages()
        {
            if (null !== $this->languages) {
                return $this->languages;
            }
            $languages = AcceptHeader::fromString($this->headers->get('Accept-Language'))->all();
            $this->languages = array();
            foreach ($languages as $lang => $acceptHeaderItem) {
                if (false !== strpos($lang,'-')) {
                    $codes = explode('-', $lang);
                    if ('i'=== $codes[0]) {
                        if (count($codes) > 1) {
                            $lang = $codes[1];
                        }
                    } else {
                        for ($i = 0, $max = count($codes); $i < $max; ++$i) {
                            if ($i === 0) {
                                $lang = strtolower($codes[0]);
                            } else {
                                $lang .='_'.strtoupper($codes[$i]);
                            }
                        }
                    }
                }
                $this->languages[] = $lang;
            }
            return $this->languages;
        }
        public function getCharsets()
        {
            if (null !== $this->charsets) {
                return $this->charsets;
            }
            return $this->charsets = array_keys(AcceptHeader::fromString($this->headers->get('Accept-Charset'))->all());
        }
        public function getEncodings()
        {
            if (null !== $this->encodings) {
                return $this->encodings;
            }
            return $this->encodings = array_keys(AcceptHeader::fromString($this->headers->get('Accept-Encoding'))->all());
        }
        public function getAcceptableContentTypes()
        {
            if (null !== $this->acceptableContentTypes) {
                return $this->acceptableContentTypes;
            }
            return $this->acceptableContentTypes = array_keys(AcceptHeader::fromString($this->headers->get('Accept'))->all());
        }
        public function isXmlHttpRequest()
        {
            return'XMLHttpRequest'== $this->headers->get('X-Requested-With');
        }
        protected function prepareRequestUri()
        {
            $requestUri ='';
            if ($this->headers->has('X_ORIGINAL_URL')) {
                $requestUri = $this->headers->get('X_ORIGINAL_URL');
                $this->headers->remove('X_ORIGINAL_URL');
                $this->server->remove('HTTP_X_ORIGINAL_URL');
                $this->server->remove('UNENCODED_URL');
                $this->server->remove('IIS_WasUrlRewritten');
            } elseif ($this->headers->has('X_REWRITE_URL')) {
                $requestUri = $this->headers->get('X_REWRITE_URL');
                $this->headers->remove('X_REWRITE_URL');
            } elseif ($this->server->get('IIS_WasUrlRewritten') =='1'&& $this->server->get('UNENCODED_URL') !='') {
                $requestUri = $this->server->get('UNENCODED_URL');
                $this->server->remove('UNENCODED_URL');
                $this->server->remove('IIS_WasUrlRewritten');
            } elseif ($this->server->has('REQUEST_URI')) {
                $requestUri = $this->server->get('REQUEST_URI');
                $schemeAndHttpHost = $this->getSchemeAndHttpHost();
                if (strpos($requestUri, $schemeAndHttpHost) === 0) {
                    $requestUri = substr($requestUri, strlen($schemeAndHttpHost));
                }
            } elseif ($this->server->has('ORIG_PATH_INFO')) {
                $requestUri = $this->server->get('ORIG_PATH_INFO');
                if (''!= $this->server->get('QUERY_STRING')) {
                    $requestUri .='?'.$this->server->get('QUERY_STRING');
                }
                $this->server->remove('ORIG_PATH_INFO');
            }
            $this->server->set('REQUEST_URI', $requestUri);
            return $requestUri;
        }
        protected function prepareBaseUrl()
        {
            $filename = basename($this->server->get('SCRIPT_FILENAME'));
            if (basename($this->server->get('SCRIPT_NAME')) === $filename) {
                $baseUrl = $this->server->get('SCRIPT_NAME');
            } elseif (basename($this->server->get('PHP_SELF')) === $filename) {
                $baseUrl = $this->server->get('PHP_SELF');
            } elseif (basename($this->server->get('ORIG_SCRIPT_NAME')) === $filename) {
                $baseUrl = $this->server->get('ORIG_SCRIPT_NAME'); } else {
                $path = $this->server->get('PHP_SELF','');
                $file = $this->server->get('SCRIPT_FILENAME','');
                $segs = explode('/', trim($file,'/'));
                $segs = array_reverse($segs);
                $index = 0;
                $last = count($segs);
                $baseUrl ='';
                do {
                    $seg = $segs[$index];
                    $baseUrl ='/'.$seg.$baseUrl;
                    ++$index;
                } while ($last > $index && (false !== $pos = strpos($path, $baseUrl)) && 0 != $pos);
            }
            $requestUri = $this->getRequestUri();
            if ($baseUrl && false !== $prefix = $this->getUrlencodedPrefix($requestUri, $baseUrl)) {
                return $prefix;
            }
            if ($baseUrl && false !== $prefix = $this->getUrlencodedPrefix($requestUri, rtrim(dirname($baseUrl),'/'.DIRECTORY_SEPARATOR).'/')) {
                return rtrim($prefix,'/'.DIRECTORY_SEPARATOR);
            }
            $truncatedRequestUri = $requestUri;
            if (false !== $pos = strpos($requestUri,'?')) {
                $truncatedRequestUri = substr($requestUri, 0, $pos);
            }
            $basename = basename($baseUrl);
            if (empty($basename) || !strpos(rawurldecode($truncatedRequestUri), $basename)) {
                return'';
            }
            if (strlen($requestUri) >= strlen($baseUrl) && (false !== $pos = strpos($requestUri, $baseUrl)) && $pos !== 0) {
                $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
            }
            return rtrim($baseUrl,'/'.DIRECTORY_SEPARATOR);
        }
        protected function prepareBasePath()
        {
            $filename = basename($this->server->get('SCRIPT_FILENAME'));
            $baseUrl = $this->getBaseUrl();
            if (empty($baseUrl)) {
                return'';
            }
            if (basename($baseUrl) === $filename) {
                $basePath = dirname($baseUrl);
            } else {
                $basePath = $baseUrl;
            }
            if ('\\'=== DIRECTORY_SEPARATOR) {
                $basePath = str_replace('\\','/', $basePath);
            }
            return rtrim($basePath,'/');
        }
        protected function preparePathInfo()
        {
            $baseUrl = $this->getBaseUrl();
            if (null === ($requestUri = $this->getRequestUri())) {
                return'/';
            }
            if ($pos = strpos($requestUri,'?')) {
                $requestUri = substr($requestUri, 0, $pos);
            }
            $pathInfo = substr($requestUri, strlen($baseUrl));
            if (null !== $baseUrl && (false === $pathInfo ||''=== $pathInfo)) {
                return'/';
            } elseif (null === $baseUrl) {
                return $requestUri;
            }
            return (string) $pathInfo;
        }
        protected static function initializeFormats()
        {
            static::$formats = array('html'=> array('text/html','application/xhtml+xml'),'txt'=> array('text/plain'),'js'=> array('application/javascript','application/x-javascript','text/javascript'),'css'=> array('text/css'),'json'=> array('application/json','application/x-json'),'xml'=> array('text/xml','application/xml','application/x-xml'),'rdf'=> array('application/rdf+xml'),'atom'=> array('application/atom+xml'),'rss'=> array('application/rss+xml'),'form'=> array('application/x-www-form-urlencoded'),
            );
        }
        private function setPhpDefaultLocale($locale)
        {
            try {
                if (class_exists('Locale', false)) {
                    \Locale::setDefault($locale);
                }
            } catch (\Exception $e) {
            }
        }
        private function getUrlencodedPrefix($string, $prefix)
        {
            if (0 !== strpos(rawurldecode($string), $prefix)) {
                return false;
            }
            $len = strlen($prefix);
            if (preg_match(sprintf('#^(%%[[:xdigit:]]{2}|.){%d}#', $len), $string, $match)) {
                return $match[0];
            }
            return false;
        }
        private static function createRequestFromFactory(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
        {
            if (self::$requestFactory) {
                $request = call_user_func(self::$requestFactory, $query, $request, $attributes, $cookies, $files, $server, $content);
                if (!$request instanceof self) {
                    throw new \LogicException('The Request factory must return an instance of Symfony\Component\HttpFoundation\Request.');
                }
                return $request;
            }
            return new static($query, $request, $attributes, $cookies, $files, $server, $content);
        }
        public function isFromTrustedProxy()
        {
            return self::$trustedProxies && IpUtils::checkIp($this->server->get('REMOTE_ADDR'), self::$trustedProxies);
        }
        private function normalizeAndFilterClientIps(array $clientIps, $ip)
        {
            $clientIps[] = $ip; $firstTrustedIp = null;
            foreach ($clientIps as $key => $clientIp) {
                if (preg_match('{((?:\d+\.){3}\d+)\:\d+}', $clientIp, $match)) {
                    $clientIps[$key] = $clientIp = $match[1];
                }
                if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
                    unset($clientIps[$key]);
                    continue;
                }
                if (IpUtils::checkIp($clientIp, self::$trustedProxies)) {
                    unset($clientIps[$key]);
                    if (null === $firstTrustedIp) {
                        $firstTrustedIp = $clientIp;
                    }
                }
            }
            return $clientIps ? array_reverse($clientIps) : array($firstTrustedIp);
        }
    }
}
namespace
{require __DIR__.'/../vendor/symfony/symfony/src/Symfony/Component/ClassLoader/ClassCollectionLoader.php';}
