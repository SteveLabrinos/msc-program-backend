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
    private $seasonNumber;

    //  constructor
    public function __construct($id, $firstName, $lastName, $email, 
        $password, $role) {
        $this->setId($id);
        $this->setFirstName($firstName);
        $this->setLastName($lastName);
        $this->setEmail($email);
        $this->setPassword($password);
        $this->setRole($role);
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
        // return $this->phone === null ? '' : $this->phone;
        return $this->phone;
    }

    public function getAddress() {
        // return $this->address === null ? '' : $this->address;
        return $this->address;
    }

    public function getBirthDate() {
        // return $this->birthDate === null ? '' : $this->birthDate;
        return $this->birthDate;
    }

    public function getSeasonNumber() {
        return $this->seasonNumber;
    }

    //  setters
    public function setId($id) {
        //  check if the argument is not numeric or negative number
        //  or the object has an id value
        if(($id !== null) && (!is_numeric($id) || $id <=0 || $this->id !== null)) {
            throw new TaskException("User ID Error");
        }

        $this->id = intval($id);
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
        if(strlen($password) < 0 || strlen($password) > 255) {
            throw new UserException("User Email Error");
        }
        //  create the hashed password before inserting
        $this->password = password_hash($password, PASSWORD_DEFAULT);
    }

    public function setRole($role) {
        if(strtoupper($role) !== 'STUFF' 
            && strtoupper($role) !== 'TEACHER'
            && strtoupper($role) !== 'STUDENT') {
            throw new UserException("User Role must be STUFF or TEACHER or STUDENT");
        }
        $this->role = $role;
    }

    public function setPhone($phone) {
        if(($phone !== null) && (strlen($phone) > 20 )) {
            throw new UserException("User Phone Error");
        }
        $this->phone = $phone;
    }

    public function setAddress($address) {
        if(($address !== null) && (strlen($address) > 100 )) {
            throw new UserException("User Address Error");
        }
        $this->address = $address;
    }

    public function setBirthDate($birthDate) {
        if(($birthDate !== null) 
            && date_format(date_create_from_format('d/m/Y', $birthDate), 'd/m/Y') !== $birthDate) {
            throw new UserException("User Birth Date Error");
        }
        $this->birthDate = $birthDate;
    }
    
    public function setSignupDate($signupDate) {
        if(($signupDate !== null) 
            && date_format(date_create_from_format('d/m/Y', $signupDate), 'd/m/Y') !== $signupDate) {
            throw new UserException("User Sign up Date Error");
        } elseif (!$signupDate) {
            $this->signupDate = date("d/m/Y");
        } else {
            $this->signupDate = $signupDate;
        }
    }

    public function setRegistrationNumber($registrationNumber) {
        if(($registrationNumber !== null) && (strlen($registrationNumber) > 50)) {
            throw new UserException("User Registration Number Error");
        } elseif (!$registrationNumber) {
            $this->registrationNumber = "MSC-".random_int(1000, 9999);
        } else {
            $this->registrationNumber = $registrationNumber;
        }
    }

    public function setSeasonNumber($seasonNumber) {
        if ((strlen($seasonNumber) > 0) && (intval($seasonNumber) < 1 || intval($seasonNumber) > 3)) {
            throw new UserException("User Season Number Error");
        } 
        $this->seasonNumber = $seasonNumber;
    }

    public function returnUserAsArray() {
        $user = array(
            "id" => $this->getId(),
            "firstName" => $this->getFirstName(),
            "lastName" => $this->getLastName(),
            "email" => $this->getEmail(),
            "role" => $this->getRole(),
            "phone" => $this->getPhone(),
            "address" => $this->getAddress(),
            "birthDate" => $this->getBirthDate(),
            "signupDate" => $this->getSignupDate(),
            "registrationNumber" => $this->getRegistrationNumber(),
            "seasonNumber" => $this->getSeasonNumber()
        );

        return $user;
    }
 }