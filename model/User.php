<?php

/**
 * 
 * @author Steve Labrinos [stalab at linuxmail.org] on 10/3/2021
 */

 class UserException extends Exception { }

 class User {
    private $id;
    private $firstName;
    private $lastName;
    private $email;
    private $password;
    private $role;
    private $phone;
    private $address;
    private $birthDate;
    private $signupDate;
    private $registrationNumber;

    //  constructor
    public function __construct($id, $firstName, $lastName, $email, 
        $password, $role, $signupDate, $registrationNumber) {
        $this->setId($id);
        $this->setFirstName($firstName);
        $this->setLastName($lastName);
        $this->setEmail($email);
        $this->setPassword($password);
        $this->setRole($role);
        $this->setSignupDate($signupDate);
        $this->setRegistrationNumber($registrationNumber);
    }

    //  setters
    public function getId() {
        return $this->id;
    }

    public function getFirstName() {
        return $this->firstName;
    }

    public function getLastName() {
        return $this->lastName;
    }

    public function getEmail() {
        return $this->email;
    }

    public function getPassword() {
        return $this->password;
    }

    public function getRole() {
        return $this->role;
    }

    public function getSignupDate() {
        return $this->signupDate;
    }

    public function getRegistrationNumber() {
        return $this->registrationNumber;
    }

    public function getPhone() {
        return $this->phone;
    }

    public function getAddress() {
        return $this->address;
    }

    public function getBirthDate() {
        return $this->birthDate;
    }

    //  setters
    public function setId($id) {
        //  check if the argument is not numeric or negative number
        //  or the object has an id value
        if(($id !== null) && (!is_numeric($id) || $id <=0 || $this->_id !== null)) {
            throw new TaskException("User ID Error");
        }
        $this->id = $id;
    }

    public function setFirstName($firstName) {
        if(strlen($firstName) < 0 || strlen($firstName) > 50) {
            throw new TaskException("User First Name Error");
        }
        $this->firstName = $firstName;
    }

    public function setLastName($lastName) {
        if(strlen($lastName) < 0 || strlen($lastName) > 50) {
            throw new TaskException("User Last Name Error");
        }
        $this->lastName = $lastName;
    }

    public function setEmail($email) {
        if(strlen($email) < 0 || strlen($email) > 50) {
            throw new TaskException("User Email Error");
        }
        $this->email = $email;
    }

    public function setPassword($password) {
        if(strlen($password) < 0 || strlen($password) > 50) {
            throw new TaskException("User Email Error");
        }
        $this->password = $password;
    }

    public function setRole($role) {
        if(strtoupper($role) !== 'STUFF' 
            && strtoupper($role) !== 'TEACHER'
            && strtoupper($role) !== 'STUDENT') {
            throw new TaskException("User Role must be STUFF or TEACHER or STUDENT");
        }
        $this->role = $tole;
    }

    public function setPhone($phone) {
        if(($phone !== null) && (strlen($phone) > 20 )) {
            throw new TaskException("User Phone Error");
        }
        $this->phone = $phone;
    }

    public function setAddress($address) {
        if(($address !== null) && (strlen($address) > 100 )) {
            throw new TaskException("User Address Error");
        }
        $this->address = $address;
    }

    public function setBirthDate($birthDate) {
        if(($birthDate !== null) 
            && date_format(date_create_from_format('d/m/Y', $birthDate), 'd/m/Y') !== $birthDate) {
            throw new TaskException("User Birth Date Error");
        }
        $this->birhDate = $birthDate;
    }
    
    public function setSignupDate($signupDate) {
        if(($signupDate !== null) 
            && date_format(date_create_from_format('d/m/Y', $signupDate), 'd/m/Y') !== $signupDate) {
            throw new TaskException("User Sign up Date Error");
        }
        $this->signupDate = $signupDate;
    }

    public function setRegistrationNumber($registrationNumber) {
        if(($registrationNumber !== null) && (!is_numeric($registrationNumber))) {
            throw new TaskException("User Registration Number Error");
        }
        $this->registrationNumber = "MSC-".$registrationNumber;
    }

    public function returnUserAsArray() {
        $user = array(
            "id" => $this->getId(),
            "firstName" => $this->getFirstName(),
            "lastName" => $this->getLastName(),
            "email" => $this->getEmail(),
            "password" => $this->getPassword(),
            "role" => $this->getRole(),
            "phone" => $this->getPhone(),
            "address" => $this->getAddress(),
            "birthDate" => $this->getBirthDate(),
            "signupDate" => $this->getSignupDate(),
            "registrationNumber" => $this->getRegistrationNumber()
        );

        return $user;
    }
 }