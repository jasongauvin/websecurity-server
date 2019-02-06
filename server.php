#!/usr/bin/env
<?php

class SocketServer {

  protected $socket;
  protected $clients = [];
  protected $changed;

  function _construct($host='localhost', $port=9000){
    set_time_limit(0);
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_set_options($socket, SOL_SOCKET, SO_REUSEADDR, 1);

    socket_bind($socket, 0, $port);
    socket_listen($socket);

    $this->socket = $socket;
  }

  function _destruct(){
    socket_close($socket);
  }

  function run(){
    while(true){
      $this->waitForChange();
      $this->checkNewClients();
      $this->checkMessageReceived();
      $this->checkDisconnect();
    }
  }

  function waitForChange(){
    $this->changed = array_merge([$this->socket], $this->clients);
    $null = null;
    socket_select($this->changed, $null, $null, null);
  }

  function checkNewClients(){

    if(!in_array($this->socket, $this->changed)){
      return; //On le connait
    }

    $socket_new = socket_accept($this->socket);
    $first_line = socket_read($socket_new, 1024);
    $this->sendMessage('Un nouveau client est connecté' . PHP_EOL);
    $this->sendMessage('message: ' . trim($first_line) . PHP_EOL);
    $this->clients[] = $socket_new;

  }

  function sendMessage($msg){
    foreach ($this->clients as $client) {
      socket_write($client, $msg, strlen($msg));
    }
  }

  function checkMessageReceived(){
    foreach ($this->changed as $key => $socket) {
      $buffer = null;

      while(socket_recv($socket, $buffer, 1024, 0) >= 1){
        $this->sendMessage(trim($buffer) . PHP_EOL);
        unset($this->changed[$key]);
        break;
      }
    }
  }

  function checkDisconnect(){
    foreach ($this->changed as $changed_socket) {
      $buffer = socket_read($changed_socket, 1024, PHP_NORMAL_READ);
      if( $buffer != false) {
        continue;
      }

      $found_socket = array_search($changed_socket, $this->clients);
      socket_getpeername($changed_socket, $ip);
      unset($this->clients[$found_socket]);
      $reponse = 'client ' . $ip . ' est déconnecté';
      $this->sendMessage($reponse);
    }
  }
}

( new SocketServer())->run();
?>
