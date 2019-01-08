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
     * @ORM\Column(name="phone", type="integer", length=8, nullable=true, unique=true)
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
     *
     * @ORM\Column(name="address", type="string", length=255, nullable=true)
     *
     */
    protected $address;

    /**
     *
     * @ORM\Column(name="post_number", type="integer", length=4, nullable=true)
     *
     */
    protected $post_number;

    /**
     *
     * @ORM\Column(name="post_place", type="string", length=255, nullable=true)
     *
     */
    protected $post_place;

    /**
     *
     * @ORM\Column(name="birthdate", type="datetime", nullable=true)
     *
     */
    protected $birthdate;

    /**
     * 
     * @ORM\Column(name="pameld_user", type="integer", nullable=true)
     *
     */
    protected $pameld_user;

    /**
     *
     *
     * @ORM\Column(name="kommune_id", type="integer", nullable=true)
     *
     */
    protected $kommune_id;

    /**
     * Samtykker
     * Samtykker vedkommende til informasjonslagring?
     * 
     * @ORM\Column(name="samtykke", type="boolean", nullable=true)
     */
    protected $samtykke;

    /**
     * Navn til eventuell forelder/foresatt
     * 
     * @ORM\Column(name="foresatt_navn", type="string", nullable=true)
     */
    protected $foresatt_navn;

    
    /**
     * Mobilnummer til eventuell forelder/foresatt
     * 
     * @ORM\Column(name="foresatt_mobil", type="integer", nullable=true)
     */
    protected $foresatt_mobil;
    
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
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->getFirstName() .' '. $this->getLastName();
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

    /**
     * Set address
     *
     * @param string $address
     * @return User
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Get address
     *
     * @return string 
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set post_number
     *
     * @param integer $postNumber
     * @return User
     */
    public function setPostNumber($postNumber)
    {
        $this->post_number = $postNumber;

        return $this;
    }

    /**
     * Get post_number
     *
     * @return integer 
     */
    public function getPostNumber()
    {
        (string)$postnummer = $this->post_number;
        while (strlen($postnummer) < 4) {
            $postnummer = '0' . $postnummer;
        }
        return $postnummer;
    }

    /**
     * Set post_place
     *
     * @param string $postPlace
     * @return User
     */
    public function setPostPlace($postPlace)
    {
        $this->post_place = $postPlace;

        return $this;
    }

    /**
     * Get post_place
     *
     * @return string 
     */
    public function getPostPlace()
    {
        return $this->post_place;
    }

    /**
     * Set birthdate
     *
     * @param \DateTime $birthdate
     * @return User
     */
    public function setBirthdate($birthdate)
    {
        $this->birthdate = $birthdate;

        return $this;
    }

    /**
     * Get birthdate
     *
     * @return \DateTime 
     */
    public function getBirthdate()
    {
        return $this->birthdate;
    }

    /**
     * Set pameld_user
     *
     * @param integer $pameldUser
     * @return User
     */
    public function setPameldUser($pameldUser)
    {
        $this->pameld_user = $pameldUser;

        return $this;
    }

    /**
     * Get pameld_user
     *
     * @return integer 
     */
    public function getPameldUser()
    {
        return $this->pameld_user;
    }

    /**
     * Set kommune_id
     *
     * @param integer $kommuneId
     * @return User
     */
    public function setKommuneId($kommuneId)
    {
        $this->kommune_id = $kommuneId;

        return $this;
    }

    /**
     * Get kommune_id
     *
     * @return integer 
     */
    public function getKommuneId()
    {
        return $this->kommune_id;
    }

    /**
     * Set foresatt_navn
     *
     * @param string $foresattNavn
     * @return User
     */
    public function setForesattNavn($foresattNavn)
    {
        $this->foresatt_navn = $foresattNavn;

        return $this;
    }

    /**
     * Get foresatt_navn
     *
     * @return string 
     */
    public function getForesattNavn()
    {
        return $this->foresatt_navn;
    }

    /**
     * Set foresatt_mobil
     *
     * @param integer $foresattMobil
     * @return User
     */
    public function setForesattMobil($foresattMobil)
    {
        $this->foresatt_mobil = $foresattMobil;

        return $this;
    }

    /**
     * Get foresatt_mobil
     *
     * @return integer 
     */
    public function getForesattMobil()
    {
        return $this->foresatt_mobil;
    }

    /**
     * Set samtykke
     *
     * @param boolean $samtykke
     * @return User
     */
    public function setSamtykke($samtykke)
    {
        $this->samtykke = $samtykke;

        return $this;
    }

    /**
     * Get samtykke
     *
     * @return boolean 
     */
    public function getSamtykke()
    {
        return $this->samtykke;
    }
}
