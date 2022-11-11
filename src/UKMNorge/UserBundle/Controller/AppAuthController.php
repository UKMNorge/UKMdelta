<?php

namespace UKMNorge\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;

use DateTime;
use Exception;

use UKMNorge\UserBundle\Entity\RequestToken;
use UKMNorge\UserBundle\Entity\DipToken;

class AppAuthController extends Controller
{

    private function log( $error, $e ) {
        $this->get('logger')->error( 
            $error. ':FAIL('.$e->getCode().'): Exception follows. '. $this->getIp() 
        );
        $this->get('logger')->info(
            ' -> '. $e->getMessage()
        );
    }

    public function createAccessTokenAction( Request $request, $requestToken )
    {
        try {
            if( empty( $requestToken ) ) {
                throw new Exception('Missing requestToken', 1);
            }

            $requestToken = $this->getEm()->getRepository('UKMUserBundle:RequestToken')->findOneByToken( $requestToken );

            if($requestToken->getUsed()) {
                throw new Exception(
                    'Request token already used',
                    mt_rand()
                );
            }
            
            $expires = new DateTime();
            $expires->modify('+1 year');

            $accessToken = new DipToken();
            $accessToken->setLocation('nativeapp');
            $accessToken->setUUID( $requestToken->getUUID() );
            $accessToken->setUserId( $this->get('ukm_user')->getCurrentUser()->getId() );
            $accessToken->setActive( true );
            $accessToken->setExpires( $expires );
            $accessToken->setToken( $this->_createToken( 64 ) );

            $this->getEM()->persist( $accessToken );

            $requestToken->setUsed( true );
            $this->getEM()->persist( $requestToken );
            
            $this->getEM()->flush();

        } catch( Exception $e ) {
            $this->log( 'createAccessToken', $e );
            return $this->render('UKMUserBundle:AppAuth:sorry.html.twig', ['id' => $e->getCode() ]);
        }
        return $this->render('UKMUserBundle:AppAuth:success.html.twig', ['token' => $accessToken->getToken() ]);
    }


    public function createRequestTokenAction( Request $request ) {
        $response = new JsonResponse();

        try {
            if( empty( $request->request->get('UUID') ) ) {
                throw new Exception('Missing UUID', 1);
            }
            $token = new RequestToken();
            $token->setUUID( $request->request->get('UUID') );
            $token->setTime( new DateTime() );
            $token->setToken( $this->_createToken( 32 ) );

            
            $this->getEm()->persist( $token );
            $this->getEm()->flush();

            $response->setData( 
                ['token' => $token->getToken() ]
            );
        } catch( Exception $e ) {
            $this->log( 'createRequestToken', $e );
            $response->setData(false);
        }

        return $response;
    }


    private function _createToken( $length ) {
        return bin2hex( random_bytes( $length ) );
    }

    public function getIp() {
        if( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    public function getEm() {
        return $this->getDoctrine()->getManager();
    }
}
