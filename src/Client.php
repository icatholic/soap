<?php
namespace Soap;

use Soap\Async;
use Soap\SocketsRegistry;
use Soap\Exception;

class Client extends \SoapClient
{

    public $asyncFunctionName = null;

    protected $_asynchronous = false;

    protected $_asyncResult = null;

    protected $_asyncAction = null;

    public function __construct($wsdl, $options)
    {
        parent::SoapClient($wsdl, $options);
    }

    public function __call($functionName, $arguments)
    {
        if ($this->_asyncResult == null) {
            $this->_asynchronous = false;
            $this->_asyncAction = null;
            
            if (preg_match('/Async$/', $functionName) == 1) {
                $this->_asynchronous = true;
                $functionName = str_replace('Async', '', $functionName);
                $this->asyncFunctionName = $functionName;
            }
        }
        
        $result = @parent::__call($functionName, $arguments);
        if ($this->_asynchronous == true) {
            return $this->_asyncAction;
        }
        return $result;
    }

    public function __doRequest($request, $location, $action, $version, $one_way = false)
    {
        if ($this->_asyncResult != null) {
            $result = $this->_asyncResult;
            unset($this->_asyncResult);
            return $result;
        }
        
        if ($this->_asynchronous == false) {
            $result = parent::__doRequest($request, $location, $action, $version, $one_way);
            return $result;
        } else {
            $this->_asyncAction = new Async($this, $this->asyncFunctionName, $request, $location, $action);
            
            if (SocketsRegistry::isRegistered('idbAsync'))
                $idbAsync = SocketsRegistry::get('idbAsync');
            else
                $idbAsync = array();
            array_push($idbAsync, $this->_asyncAction);
            SocketsRegistry::set('idbAsync', $idbAsync);
            
            return '';
        }
    }

    public function handleAsyncResult($functionName, $result)
    {
        $this->_asynchronous = false;
        $this->_asyncResult = $result;
        return $this->__call($functionName, array());
    }
}