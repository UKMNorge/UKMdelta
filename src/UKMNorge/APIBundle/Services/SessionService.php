<?php

namespace UKMNorge\APIBundle\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorage;
use Exception;


require_once('UKM/Autoloader.php');

/*
    session start kalles fra PHP derfor brukes PhpBridgeSessionStorage
    https://symfony.com/doc/current/components/http_foundation/session_php_bridge.html
*/

ini_set('session.save_handler', 'files');
ini_set('session.save_path', '/tmp');
session_start();

class SessionService {
    public function __construct() {
    }

    /**
     * Hent Session
     *
     * @return Session
     */
    public static function getSession() : Session {
        // Bruker PhpBridgeSessionStorage fordi session startes av PHP. Se dokumentasjon på https://symfony.com/doc/current/components/http_foundation/session_php_bridge.html
        $session = new Session(new PhpBridgeSessionStorage());
        // Hvis session har ikke startet, start det!
        if(!$session->isStarted()) {
            $session->start();
        }
        return $session;
	}
}