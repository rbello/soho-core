<?php

namespace \Soho\Models;

/**
 * @ORM\Entity
 * @ORM\Table(name="personnes")
 * @ORM\InheritanceType("JOINED")
 **/
class Session
{

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @var int
     **/
    public $id;
    
    /**
     * @ORM\Column(type="string", columnDefinition="ENUM('M', 'Mme', 'Mlle')")
     * @var string
     **/
    public $civilite;
    
    /**
     * @ORM\Column(type="string")
     * @var string
     **/
    public $lastName;
    
    /**
     * @ORM\Column(type="string")
     * @var string
     **/
    public $firstName;
    
    /**
     * @ORM\Column(type="string", unique=false, nullable=true)
     * @var string
     **/
    public $email;

    /**
     * @ORM\Column(type="date", nullable=true)
     * @var string
     **/
    public $birthDay;
    
    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     **/
    public $birthPlace;
    
    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     **/
    public $nationality;
    
    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     **/
    public $phoneNumber;
    
    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     **/
    public $cellNumber;
    
    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     **/
    public $addressCity;
    
    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     * @var string
     **/
    public $addressPostalCode;
    
    public function setId($value) {
        $this->id = $value;
    }
    
    public function setCivilite($value) {
        $this->civilite = $value;
    }
    
    public function setFirstName($value) {
        $this->firstName = $value;
    }
    
    public function setLastName($value) {
        $this->lastName = $value;
    }
    
    public function setBirthDay(\DateTime $value = null) {
        $this->birthday = $value;
    }
    
    public function setBirthPlace($value) {
        $this->birthplace = $value;
    }
    
    public function setNationality($value) {
        $this->nationality = $value;
    }
    
    public function setEmail($value) {
        $this->email = $value;
    }
    
    public function setPhoneNumber($value) {
        $this->phoneNumber = $value;
    }
    
    public function setCellNumber($value) {
        $this->cellNumber = $value;
    }
    
    public function setAddressCity($value) {
        $this->addressCity = $value;
    }
    
    public function setAddressPostalCode($value) {
        $this->addressPostalCode = $value;
    }

}