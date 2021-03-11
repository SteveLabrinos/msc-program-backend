<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../model/User.php');

/**
 * @author Steve Labrinos [stalab at linuxmail.org] on 10/3/2021
 */

 // Create a new db connection in writeDB
try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    //  Log the error
    error_log("Connection error - ".$ex, 0);
    //  return an error response
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Database Connection Error".$ex->getMessage());
    $response->send();
    exit;
}

//  Hadle options request method for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS, PATCH, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    $response = new Response();
    $response->setHttpStatusCode(200);
    $response->setSuccess(true);
    $response->send();
    exit;
}

//  apis to get a specific user, update, delete
if(array_key_exists("userid", $_GET)) {

} 
//  apis to get all users and create a new user
elseif (empty($_GET)) {
    //  Create a new user with POST method
    //  API belong to the STUFF role
    //  Creation only applys mandatory fields
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        //  Check that content is json
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Content type header not set to json");
            $response->send();
            exit;
        }

        //  Check that it is valid json
        $rawPOSTData = file_get_contents('php://input');

        if (!$jsonData = json_decode($rawPOSTData)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Request body is not valid json");
            $response->send();
            exit;
        }

        //  Check the mandatory fields
        if (!isset($jsonData->firstName) || !isset($jsonData->lastName) || 
            !isset($jsonData->email) || !isset($jsonData->password) || !isset($jsonData->role)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            !isset($jsonData->firstName) ? $response->addMessage("First name filed is mandatory") : false;
            !isset($jsonData->lastName) ? $response->addMessage("Last name filed is mandatory") : false;
            !isset($jsonData->email) ? $response->addMessage("Email filed is mandatory") : false;
            !isset($jsonData->password) ? $response->addMessage("Password filed is mandatory") : false;
            !isset($jsonData->role) ? $response->addMessage("Role filed is mandatory") : false;
            $response->send();
            exit;
        }
        //  Check that the role belongs to enum values
        if ($jsonData->role !== 'STUFF' && $jsonData->role !== 'TEACHER' && $jsonData->role !== 'STUDENT') {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Role value is not valid");
            $response->send();
            exit;
        }

        $registrationNumber = random_int(1, 1000);

        //  create the User model
        $user = new User(
            null,
            $jsonData->firstName,
            $jsonData->lastName,
            $jsonData->email,
            $jsonData->password,
            $jsonData->role
        );

        //  insert the new user
        $firstName = $user->getFirstName();
        $lastName = $user->getLastName();
        $email = $user->getEmail();
        $password = $user->getPassword();
        $role = $user->getRole();
        $signupDate = $user->getSignupDate();
        $registration = $user->getRegistrationNumber();

        try {
            $query = 'INSERT INTO app_user (first_name, last_name, email, password, role, signup_date, registration_number)
                      VALUES (:fName, :lName, :email, :pass, :role, STR_TO_DATE(:sDate, \'%d/%m/%Y\'), :regNum)';
            $stmt = $writeDB->prepare($query);
            $stmt->bindParam(':fName', $firstName, PDO::PARAM_STR);
            $stmt->bindParam(':lName', $lastName, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':pass', $password, PDO::PARAM_STR);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            $stmt->bindParam(':sDate', $signupDate, PDO::PARAM_STR);
            $stmt->bindParam(':regNum', $registration, PDO::PARAM_STR);
            $stmt->execute();

            $rowCount = $stmt->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to insert user");
                $response->send();
                exit;
            }

            //  get the created user id
            $createdUserId = $writeDB->lastInsertId();

            $returnData = array(
                "userId" => $createdUserId,
                "firstName" => $user->getFirstName(),
                "lastName" => $user->getLastName(),
                "email" => $user->getEmail(),
                "role" => $user->getRole(),
                "signupDate" => $user->getSignupDate(),
                "registrationNumber" => $user->getRegistrationNumber()
            );
        
            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage("User created");
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PDOException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Error while creating new user".$e->getMessage());
            $response->send();
            exit;
        } catch (UserException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        }
    }
    //  Get all users
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        //  fetch all users from the DB
        try {
            $query = 'SELECT id, first_name, last_name, password, email, role, phone, address, 
                             birth_date, signup_date, registration_number
                      FROM app_user
                      ORDER BY role, last_name';
            $stmt = $readDB->prepare($query);
            $stmt->execute();

            $rowCount = $stmt->rowCount();

            $usersArray = array();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $user = new User($id, $first_name, $last_name, $email, $password, $role);
                $user->setAddress($address);
                $user->setPhone($phone);
                $user->setBirthDate($birth_date);

                $usersArray[] = $user->returnUserAsArray();
            }

            $returnData = array(
                "rows_returned" => $rowCount,
                "users" => $usersArray
            );

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PDOException $e) {
            error_log("Database query erro".$e, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Error fetching users from database".$e->getMessage());
            $response->send();
            exit;
        } catch (UserException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        }
    }
    //  no other methods are allowed
    else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
}



