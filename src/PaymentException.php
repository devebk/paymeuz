<?php
namespace devebk\payme;

use Exception;
class PaymentException extends Exception {

    public $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function render()
    {
            header('Content-Type: application/json; charset=UTF-8');
         print  (json_encode($this->response));

        return true;
      
    }



}
