<?php

namespace UKMNorge\NativeAppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;

use DateTime;
use Exception;

use UKMNorge\NativeAppBundle\Entity\RequestToken;
use UKMNorge\NativeAppBundle\Entity\AccessToken;

class AuthController extends Controller
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

            $requestToken = $this->getEm()->getRepository('UKMNAppBundle:RequestToken')->findOneByToken( $requestToken );

            if($requestToken->getUsed()) {
                throw new Exception(
                    'Request token already used',
                    mt_rand()
                );
            }
            
            $expires = new DateTime();
            $expires->modify('+1 year');

            $accessToken = new AccessToken();
            $accessToken->setRequestToken( $requestToken->getId() );
            $accessToken->setUser( $this->get('ukm_user')->getCurrentUser()->getId() );
            $accessToken->setActive( true );
            $accessToken->setExpires( $expires );
            $accessToken->setToken( $this->_createToken( 64 ) );

            $this->getEM()->persist( $accessToken );

            
            $requestToken->setUsed( true );
            $this->getEM()->persist( $requestToken );
            
            $this->getEM()->flush();

        } catch( Exception $e ) {
            $this->log( 'createAccessToken', $e );
            return $this->render('UKMNAppBundle:Auth:sorry.html.twig', ['id' => $e->getCode() ]);
        }
        return $this->render('UKMNAppBundle:Auth:success.html.twig', ['token' => $accessToken->getToken() ]);
    }


    public function createRequestTokenAction( Request $request ) {
        $response = new JsonResponse();

        try {
            if( empty( $request->request->get('UUID') ) ) {
                throw new Exception('Missing UUID', 1);
            }
            $token = new RequestToken();
            $token->setAppUUID( $request->request->get('UUID') );
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
