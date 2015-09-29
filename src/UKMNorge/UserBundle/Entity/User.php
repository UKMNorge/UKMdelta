<?php

namespace UKMNorge\UserBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

/**
 * User
 *
 * @ORM\Entity(repositoryClass="UKMNorge\UserBundle\Entity\UserRepository")
 * @ORM\Table(name="ukm_user")
 *
 * @UniqueEntity("email")
 * @UniqueEntity("username")
 * @UniqueEntity("phone")
 */
class User extends BaseUser
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    
    /**
     * @var string
     *
     * @ORM\Column(name="facebook_id", type="string", nullable=true)
     */
    protected $facebook_id;
    /**
     * @var string
     *
     * @ORM\Column(name="facebook_id_unencrypted", type="string", nullable=true)
     */
    protected $facebook_id_unencrypted;
    /** 
     *
     * @ORM\Column(name="facebook_access_token", type="string", length=255, nullable=true)
     */
    protected $facebook_access_token;
    
    /**
     *
     * @ORM\Column(name="first_name", type="string", length=255, nullable=true)
     *
     */
    protected $first_name;
    /**
     *
     * @ORM\Column(name="last_name", type="string", length=255, nullable=true)
     *
     */
    protected $last_name;
    
    /**
     *
     * @ORM\Column(name="phone", type="integer", length=8, nullable=true)
     *
     */
    protected $phone;
    
    /**
     *
     * @ORM\Column(name="sms_validated", type="boolean")
     *
     */
    protected $sms_validated = false;
    
    /**
     *
     * @ORM\Column(name="sms_validation_code", type="integer", length=6, nullable=true)
     *
     */
    protected $sms_validation_code;

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
     * Set facebook_id
     *
     * @param string $facebookId
     * @return User
     */
    public function setFacebookId($facebookId)
    {
        $this->facebook_id = $facebookId;

        return $this;
    }

    /**
     * Get facebook_id
     *
     * @return string 
     */
    public function getFacebookId()
    {
        return $this->facebook_id;
    }

    /**
     * Set facebook_id_unencrypted
     *
     * @param string $facebookIdUnencrypted
     * @return User
     */
    public function setFacebookIdUnencrypted($facebookIdUnencrypted)
    {
        $this->facebook_id_unencrypted = $facebookIdUnencrypted;

        return $this;
    }

    /**
     * Get facebook_id_unencrypted
     *
     * @return string 
     */
    public function getFacebookIdUnencrypted()
    {
        return $this->facebook_id_unencrypted;
    }

    /**
     * Set facebook_access_token
     *
     * @param string $facebookAccessToken
     * @return User
     */
    public function setFacebookAccessToken($facebookAccessToken)
    {
        $this->facebook_access_token = $facebookAccessToken;

        return $this;
    }

    /**
     * Get facebook_access_token
     *
     * @return string 
     */
    public function getFacebookAccessToken()
    {
        return $this->facebook_access_token;
    }

    /**
     * Set first_name
     *
     * @param string $firstName
     * @return User
     */
    public function setFirstName($firstName)
    {
        $this->first_name = $firstName;

        return $this;
    }

    /**
     * Get first_name
     *
     * @return string 
     */
    public function getFirstName()
    {
        return $this->first_name;
    }

    /**
     * Set last_name
     *
     * @param string $lastName
     * @return User
     */
    public function setLastName($lastName)
    {
        $this->last_name = $lastName;

        return $this;
    }

    /**
     * Get last_name
     *
     * @return string 
     */
    public function getLastName()
    {
        return $this->last_name;
    }

    /**
     * Set phone
     *
     * @param integer $phone
     * @return User
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get phone
     *
     * @return integer 
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set email
     *
     * OVERRIDES SYSTEM DEFAULT AS TO BYPASS USERNAME FIELD ON USER CREATION
     * 
     * @param string $email
     * @return User
     */
    public function setEmail($email)
    {
		parent::setEmail( $email );
		parent::setUsername( $email );
        return $this;
    }


    /**
     * Set sms_validated
     *
     * @param boolean $smsValidated
     * @return User
     */
    public function setSmsValidated($smsValidated)
    {
        $this->sms_validated = $smsValidated;

        return $this;
    }

    /**
     * Get sms_validated
     *
     * @return boolean 
     */
    public function getSmsValidated()
    {
        return $this->sms_validated;
    }

    /**
     * Set sms_validation_code
     *
     * @param integer $smsValidationCode
     * @return User
     */
    public function setSmsValidationCode($smsValidationCode)
    {
        $this->sms_validation_code = $smsValidationCode;

        return $this;
    }

    /**
     * Get sms_validation_code
     *
     * @return integer 
     */
    public function getSmsValidationCode()
    {
        return $this->sms_validation_code;
    }
}
