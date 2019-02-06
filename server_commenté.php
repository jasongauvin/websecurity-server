#!/usr/bin/env
<?php

/**
*
*/
class SocketServer {

  private $socket;
  private $clients = [];
  private $changed;

  /**
  * Constructeur de la classe
  * :param localhost: Url d'écoute du serveur
  * :param port: Port d'écoute du serveur
  **/
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

    # Lie un nom à une socket
    # 0 => pour l'adresse
    # $port => pour le port
    socket_bind($socket, 0, $port);

    # Attente d'une connexion sur la socket
    socket_listen($socket);

    # Passage de la socket au niveau de l'objet
    $this->socket = $socket;
  }

  /**
  * Destructeur de la classe
  */
  function __destruct(){
    # Fermeture de la socket
    socket_close($socket);
  }

  /**
  * Méthode appelant séquentiellement les fonctions:
  * - attente de changement
  * - check de nouveaux clients
  * - check de réception de message
  * - check des déconnexions clientes
  */
  function run(){

    # Boucle infinie
    while(true){
      # Attente des changements
      $this->waitForChange();
      # Check de nouveaux clients
      $this->checkNewClients();
      # Check les messages recus
      $this->checkMessageReceived();
      # Check les déconnexions clientes
      $this->checkDisconnect();
    }
  }

  /**
  * Méthode attendant les changements
  */
  function waitForChange(){
    # Permet de fusionner le tableaux des client avec la socket
    $this->changed = array_merge([$this->socket], $this->clients);

    $null = null;

    # Exécute l'appel système select() sur un tableau de sockets avec une durée d'expiration
    # Du à une limitation du moteur Zend, il n'est pas possible de passer une constante comme
    # NULL directement comme paramètre à cette fonction, qui attend une valeur par référence.
    # socket_select(read, write, except, timeout)
    socket_select($this->changed, $null, $null, null);
  }

  /**
  * Méthode permettant de connaître les nouveaux clients.
  */
  function checkNewClients(){

    # Si la socket est dans le tableau des chnagements alors c'est un client déjà connu
    if(!in_array($this->socket, $this->changed)){
      # On sort de la méthode
      return; //On le connait
    }

    # On accepte la nouvelle connexion
    $socket_new = socket_accept($this->socket);

    # On lit les données liées à la socket
    $first_line = socket_read($socket_new, 1024);

    # On envoie la notification d'une nouvelle connexion à tous les clients
    $this->sendMessage('Un nouveau client est connecté' . PHP_EOL);

    # On envoie le message du client à tous les clients
    $this->sendMessage('message: ' . trim($first_line) . PHP_EOL);

    # On ajoute le nouveau client au tableau des clients.
    $this->clients[] = $socket_new;

  }

 /**
 * Méthode permettant d'envoyer un message.
 * :param msg: Le message à envoyer
 */
  function sendMessage($msg){

    # On itére le tableau des clients
    foreach ($this->clients as $client) {

      # Ecrit dans la socket du client le message passé en parametre
      @socket_write($client, $msg, strlen($msg));
    }
  }

  /**
  * Méthode permettant de vérifier les messages recus
  */
  function checkMessageReceived(){
    # On itére sur les changements (pour rappel juste read)
    foreach ($this->changed as $key => $socket) {

      # Mémoire tampon
      $buffer = null;

      # Tant que la socket recoit des données, les écrit dans le buffer
      while(socket_recv($socket, $buffer, 1024, 0) >= 1){
        # Renvoie le message à tous les clients
        $this->sendMessage(trim($buffer) . PHP_EOL);
        # Retire le changement du tableau
        unset($this->changed[$key]);
        break;
      }
    }
  }

  /**
  * Méthode permettant de vérifier les déconnexions clientes.
  */
  function checkDisconnect(){
    # On itére le tableau des changements
    foreach ($this->changed as $changed_socket) {
      # On lit les sockets présentes
      $buffer = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);

      # Si la socket est déconnecté socket_read retourne False
      if( $buffer != false) {
        # On passe à l'autre item du tableau
        continue;
      }

      # On recherche la socket déconnectée dans le tableau client
      $found_socket = array_search($changed_socket, $this->clients);

      # Interroge l'aure extrémité de la connexion pour récupérer l'adresse IP
      socket_getpeername($changed_socket, $ip);

      # Retire la socket du tableau client
      unset($this->clients[$found_socket]);

      $reponse = 'client ' . $ip . ' est déconnecté';

      # Envoie le message de déconnexion à tous les clients.
      $this->sendMessage($reponse);
    }
  }
} // End class

# Lancement du serveur
( new SocketServer())->run();
?>
