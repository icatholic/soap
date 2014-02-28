<?php
namespace Soap;

class Async
{

    /**
     * 获取当前soapclient对象
     */
    protected $_soapClient;

    /**
     * 被叫方法名
     *
     * @var string
     */
    protected $_functionName;

    /**
     * 连接SOAP客户端的socket资源
     *
     * @var resource
     */
    protected $_socket;

    protected $_soapResult = '';

    public function __construct($soapClient, $functionName, $request, $location, $action)
    {
        preg_match('%^(http(?:s)?)://(.*?)(/.*?)$%', $location, $matches);
        
        $this->_soapClient = $soapClient;
        $this->_functionName = $functionName;
        
        $protocol = $matches[1];
        $host = $matches[2];
        $endpoint = $matches[3];
        
        $headers = array(
            'POST ' . $endpoint . ' HTTP/1.1',
            'Host: ' . $host,
            'User-Agent: PHP-SOAP/' . phpversion(),
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . $action . '"',
            'Content-Length: ' . strlen($request),
            'Connection: close'
        );
        
        if ($protocol == 'https') {
            $host = 'ssl://' . $host;
            $port = 443;
        } else {
            $port = 80;
        }
        
        $data = implode("\r\n", $headers) . "\r\n\r\n" . $request . "\r\n";
        $this->_socket = fsockopen($host, $port, $errorNumber, $errorMessage);
        
        if ($this->_socket === false) {
            $this->_socket = null;
            throw new Exception('Unable to make an asynchronous API call: ' . $errorNumber . ': ' . $errorMessage);
        }
        
        if (fwrite($this->_socket, $data) === false) {
            throw new Exception('Unable to write data to an asynchronous API call.');
        }
    }

    public function wait()
    {
        while (! feof($this->_socket)) {
            $this->_soapResult .= fread($this->_socket, 8192);
        }
        
        list ($headers, $data) = explode("\r\n\r\n", $this->_soapResult);
        return $this->rst($this->_soapClient->handleAsyncResult($this->_functionName, $data));
    }

    /**
     * 格式化返回结果
     *
     * @param string $rst            
     * @return array
     */
    private function rst($rst)
    {
        return isset($rst['result']) ? $rst['result'] : array(
            'unset result async'
        );
    }

    public function __destruct()
    {
        if ($this->_socket != null) {
            fclose($this->_socket);
        }
    }
}