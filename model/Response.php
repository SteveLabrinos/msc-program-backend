<?php

/**
 * Wrapper Class for all the api response
 * @author Steve Labrinos [stalab at linuxmail.org] on 10/3/2021
 */

class Response {

    private $_success;
    private $_httpStatusCode;
    private $_messages = array();
    private $_data;
    private $_toCache = false;
    private $_responseData = array();

    public function setSuccess($success) {
        $this->_success = $success;
    }

    public function setHttpStatusCode($httpStatusCode) {
        $this->_httpStatusCode = $httpStatusCode;
    }

    public function addMessage($message) {
        $this->_messages[] = $message;
    }

    public function setData($data) {
        $this->_data = $data;
    }

    public function toCache($toCache) {
        $this->_toCache = $toCache;
    }

    #   use responseData var to build the final response array
    public function send() {

        header('Content-type: application/json;charset=utf-8');

        if ($this->_toCache == true) {
            header('Cache-control: max-age=60');
        } else {
            header('Cache-control: no-cache, no-store');
        }

        //  exception handling if response is defferent from boolean
        //  or httpResponseCode is not a number -> Generate error response
        if (($this->_success !== false && $this->_success !== true) || 
            !is_numeric($this->_httpStatusCode)) {
            http_response_code(500);

            $this->_responseData['statusCode'] = 500;
            $this->_responseData['success'] = false;
            $this->addMessage("Response creation error");
            $this->_responseData['messages'] = $this->_messages;
        } else {
            http_response_code($this->_httpStatusCode);

            $this->_responseData['statusCode'] = $this->_httpStatusCode;
            $this->_responseData['success'] = $this->_success;
            $this->_responseData['messages'] = $this->_messages;
            $this->_responseData['data'] = $this->_data;
        }

        echo json_encode($this->_responseData);
    }
}