<?php

namespace UKMNorge\APIBundle\Controller;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;


require_once('UKM/Autoloader.php');

class SuperController extends Controller {

    /**
     * Hent current user
     *
     */
    public function hentCurrentUser() {
        return $this->get('ukm_user')->getCurrentUser();
    }
    
    /**
     * Konverter $request til obligatoriske og opsjonale argumenter med data
     *
     * Denne metoden verifiserer om obligatoriske data magles. Exception returneres i slike tilfeller.
     * 
     * @param JsonResponse $request
     * @param array $arr_key
     * @return array
     */
    protected  function getData($request, $arr_key, $arr_key_optional = []) {
        $arr_data = [];
        foreach ($arr_key as $key) {
            $data = $request->request->get($key);
            if(empty($data)) {
                throw new Exception($key . ' is not provided');
            }
            $arr_data[$key] = $data;
        }

        foreach ($arr_key_optional as $optional_key) {
            $data = $request->request->get($optional_key);
            $arr_data[$optional_key] = empty($data) ? null : $data;
        }

        return $arr_data;
    }
}