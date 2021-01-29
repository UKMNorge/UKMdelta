<?php

// CLIENT-IMPLEMENTATION FOR
// UKM-ID:
// https://bshaffer.github.io/oauth2-server-php-docs/grant-types/authorization-code/

use UKMNorge\Http\Curl;

error_reporting(E_ALL);
ini_set('display_errors', true);
session_start();

require_once('UKMconfig.inc.php');
require_once('UKM/Autoloader.php');

class IDAuth
{

    const URL_AUTH =  'https://id.' . UKM_HOSTNAME . '/auth.php';
    const URL_TOKEN = 'https://id.' . UKM_HOSTNAME . '/api/auth/access-token.php';

    const RETURN_URL = 'https://delta.ukm.dev';
    const APP_ID = 'delta';
    const APP_SECRET = 'a42fb071e415fd9a31e7459fe51af2605c6fa04b';

    const SCOPE = 'identify';

    public static function getAuthUrl()
    {
        return static::URL_AUTH .
            '?redirect_uri=' . static::getReturnUrl() .
            '&client_id=' . static::APP_ID .
            '&scope=' . static::SCOPE .
            '&state=' . substr(uniqid('', true), -5)
            ;
    }

    public static function getReturnUrl()
    {
        return urlencode(static::RETURN_URL);
    }

    public static function getAccessTokenUrl($code)
    {
        return static::URL_TOKEN .
            '?code=' . $code
            ;
    }
}


if( isset($_GET['logout'])) {
    unset($_SESSION['accessToken']);
    echo 'Du er nå logget ut. <br />'.
        '<a href="/">Logg inn</a>';
}
// Brukeren er logget inn
elseif (isset($_SESSION['accessToken']) && !isset($_GET['code'])) {
    echo 'Har token aka er logget inn.';
    
    echo '<pre>';
    var_dump(json_decode($_SESSION['accessToken']));
    echo '</pre>';

    echo '<a href="?logout=true">Logg ut</a>';
} 
// Brukeren er ikke logget inn, men vi har fått en kode
// tilbake fra ID
elseif (isset($_GET['code'])) {
    $request = new Curl();
    $request->timeout(4);
    $request->user(IDAuth::APP_ID.':'.IDAuth::APP_SECRET);
    $request->post([
        'redirect_uri' => IDAuth::getReturnUrl(),
        'code' => $_GET['code']
    ]);
    $response = $request->process(IDAuth::getAccessTokenUrl($_GET['code']));

    $_SESSION['accessToken'] = json_encode($response);

    echo 'Fikk svar fra ID ('. IDAuth::getAccessTokenUrl($_GET['code']) .'): ' .
        '<p>' .
        '<b>Dette er responsen fra CURL delta sendte til ID: 👇</b>'.
        '<code><pre>' .
        var_export($response, true) .
        '<code></pre>'.
        '</p>'.
        '<p><a href="/">Refresh siden for å være logget inn</a></p>';
} 
// Brukeren er ikke logget inn. Start innlogging
else {
    echo 'Redirect to ID for login: <a href="' . IDAuth::getAuthUrl() . '">Logg inn</a>';
}
