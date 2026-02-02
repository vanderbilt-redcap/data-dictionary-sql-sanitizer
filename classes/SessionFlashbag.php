<?php

namespace VUMC\SessionFlashbag;

class SessionFlashbag
{
    private $flashbag = [];
    private $sessionKey = '';

    public function __construct($sessionKey)
    {
        $this->sessionKey = $sessionKey;
        if(isset($_SESSION[$this->sessionKey])) {
            $this->flashbag = $_SESSION[$this->sessionKey];
        } else {
            $this->flashbag = $this->getEmptyFlashbag();
        }
    }

    public function add($messageType, $message): void
    {
        $this->flashbag[] = [
            'type' => $messageType,
            'message' => $message,
        ];
        $_SESSION[$this->sessionKey] = $this->flashbag;
    }
    public function getMessages($messageType = '')
    {
        $returnArr = $this->flashbag;
        $this->flashbag = $this->getEmptyFlashbag();
        $_SESSION[$this->sessionKey] = $this->flashbag;
        return $returnArr;
    }
    public function getEmptyFlashbag()
    {
        return [];
    }
}
