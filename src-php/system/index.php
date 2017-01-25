<?php

include 'bootstrap.php';

class HttpRequest {
    public function __construct(&$server, &$request, $contentstream) {
        
    }
}

// Create HTTP request
$req = new HttpRequest($_SERVER, $_REQUEST, 'php://input');

// Notify
Soho::notify('http request', $req);
