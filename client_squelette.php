#!/usr/bin/env
<?php

class SocketClient {

  function __construct($host='localhost', $port=9000){
    # Fixe le temps limit d'un script (0 => infini)
    set_time_limit(0);

    # Creation d'une socket
    # AF_INET => IPV4
    # SOCK_STREAM => flux d'octets organisés (TCP)
    # SOL_TCP => Protocole TCP
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

    # Modification des options de la socket
    # Options au niveau de la socket
    # SO_REUSEADDR indique que les adresses locales peuvent être réutilisées
    socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

    # Tentative de connexion au serveur
    $connected = socket_connect($socket, $host, $port);

    echo 'serveur conecté ' . $connected . PHP_EOL;

    # Passage de la socket au niveau de l'objet
    $this->socket = $socket;
  }

  function __destruct(){
    socket_close($this->socket);
  }

  function run(){
    while(true){
      $this->sendMessage();
    }
  }

  function sendMessage(){
    $message = readline("message > ");
    @socket_write($socket, $message);
  }

}

( new SocketClient())->run();
