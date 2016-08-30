<?php

namespace UKMNorge\UserBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * APIKeys
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="UKMNorge\UserBundle\Entity\APIKeysRepository")
 */
class APIKeys
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="api_key", type="string", length=255)
     */
    private $apiKey;

    /**
     * @var string
     *
     * @ORM\Column(name="api_secret", type="string", length=255)
     */
    private $apiSecret;

    /**
     * @var string
     *
     * @ORM\Column(name="api_returnurl", type="string", length=255)
     */
    private $apiReturnURL;

    /**
     *
     * @ORM\Column(name="api_tokenurl", type="string", length=255)
     */
    private $apiTokenURL;

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set apiKey
     *
     * @param string $apiKey
     * @return APIKeys
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Get apiKey
     *
     * @return string 
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Set apiSecret
     *
     * @param string $apiSecret
     * @return APIKeys
     */
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;

        return $this;
    }

    /**
     * Get apiSecret
     *
     * @return string 
     */
    public function getApiSecret()
    {
        return $this->apiSecret;
    }

    /**
     * Set apiReturnurl
     *
     * @param string $apiReturnurl
     * @return APIKeys
     */
    public function setApiReturnURL($apiReturnurl)
    {
        $this->apiReturnURL = $apiReturnurl;

        return $this;
    }

    /**
     * Get apiReturnurl
     *
     * @return string 
     */
    public function getApiReturnURL()
    {
        return $this->apiReturnURL;
    }

    /**
     * Set apiTokenURL
     *
     * @param string $apiTokenURL
     * @return APIKeys
     */
    public function setApiTokenURL($apiTokenURL)
    {
        $this->apiTokenURL = $apiTokenURL;

        return $this;
    }

    /**
     * Get apiTokenURL
     *
     * @return string 
     */
    public function getApiTokenURL()
    {
        return $this->apiTokenURL;
    }
}
