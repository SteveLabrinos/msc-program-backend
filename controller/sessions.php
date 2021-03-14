<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../model/User.php');

/**
 * @author Steve Labrinos [stalab at linuxmail.org] on 11/3/2021
 */

// Create a new db connection in writeDB
try {
    $writeDB = DB::connectWriteDB();
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

//  delete a session
if (array_key_exists("sessionid", $_GET)) {
    //  the user provides the session id and the access token
    $sessionId = $_GET['sessionid'];

    if ($sessionId === '' || !is_numeric($sessionId)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $sessionId === '' ? $response->addMessage("Session ID cannot be blank") : false;
        !is_numeric($sessionId) ? $response->addMessage("Session ID must be a number") : false;
        $response->send();
        exit;
    }

    //  check the Authorization in the header
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        !isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from th header") : false;
        strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false;
        $response->send();
        exit;
    }

    $accessToken = $_SERVER['HTTP_AUTHORIZATION'];

    //  Allow only DELETE method
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method is not allowed");
        $response->send();
        exit;
    }

    //  Delete the current session loging out the user
    try {
        $query = 'DELETE FROM session
                  WHERE id = :sessionId
                  AND token = :accessToken';
        $stmt = $writeDB->prepare($query);
        $stmt->bindParam(':sessionId', $sessionId, PDO::PARAM_INT);
        $stmt->bindParam(':accessToken', $accessToken, PDO::PARAM_STR);
        $stmt->execute();

        $rowCount = $stmt->rowCount();

        if ($rowCount === 0) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Failed to log out of this session using token provided");
            $response->send();
            exit;
        } 

        //  Response for log out
        $returnData = array( "session_id" => intval($sessionId) );

        $response = new Response();
        $response->setHttpStatusCode(200);
        $response->setSuccess(true);
        $response->addMessage("Logged out");
        $response->setData($returnData);
        $response->send();
        exit;
    } catch (PDOException $ex) {
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("There was an issue loggin out. Please try again".$ex->getMessage());
        $response->send();
        exit;
    }
}
//  create a new session
elseif (empty($_GET)) {
    //  only accept POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    } 

    //  help to prevent a brute force attacks
    sleep(0.2);

    //  check that the body is json
    if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Content type header not set to json");
        $response->send();
        exit;
    }

    //  check for valid json
    $rawPOSTData = file_get_contents('php://input');
    if (!$jsonData = json_decode($rawPOSTData)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Post body is not valid json");
        $response->send();
        exit;
    }

    //  data validation with mandatoty fields
    if (!isset($jsonData->email) || !isset($jsonData->password)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        !isset($jsonData->email) ? $response->addMessage("Email mandatory not supplied") : false;
        !isset($jsonData->password) ? $response->addMessage("Password mandatory not supplied") : false;
        $response->send();
        exit;
    }

    //  validate the parammeters min and max length
    if (strlen($jsonData->email) < 0 || strlen($jsonData->password) < 0 ||
        strlen($jsonData->email) > 255 || strlen($jsonData->password) > 255) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        strlen($jsonData->email) < 0 ? $response->addMessage("Email can't be bank") : false;
        strlen($jsonData->email) > 255 ? $response->addMessage("Email can't be greather than 255 characters") : false;
        strlen($jsonData->password) < 0 ? $response->addMessage("Password can't be bank") : false;
        strlen($jsonData->password) > 255 ? $response->addMessage("Password can't be greather than 255 characters") : false;
        $response->send();
        exit;
    }

    try {
        //  prepare the user input to query the DB
        $email = trim($jsonData->email);
        $password = $jsonData->password;

        $query = 'SELECT id, password return_password, role, first_name, last_name
                  FROM app_user
                  WHERE email = :email';
        $stmt = $writeDB->prepare($query);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        //  must return 1 row because username is unique
        $rowCount = $stmt->rowCount();

        if ($rowCount === 0) {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("Email or password is incorrect");
            $response->send();
            exit;
        } 

        //  retrieve stored data to perform validations
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        extract($row);

        //  verify the password with the returned hashed password
        if (!password_verify($password, $return_password)) {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("Email or password is incorrect");
            $response->send();
            exit;
        }
        
        //  create a new session
        //  generate radom tokens
        $accessToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
    } catch (PDOException $ex) {
        error_log("Database error ".$ex, 0);
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Error quering the DataBase".$ex->getMessage());
        $response->send();
        exit;
    }

    //  Create the new session
    $query = 'INSERT INTO session (user_id, token)
              VALUES (:id, :token)';
    $stmt = $writeDB->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':token', $accessToken, PDO::PARAM_STR);
    $stmt->execute();

    $returnSessionId = $writeDB->lastInsertId();

    $returnData = array(
        "sessionId" => $returnSessionId,
        "accessToken" => $accessToken,
        "role" => $role,
        "firstName" => $first_name,
        "lastName" => $last_name,
        "userId" => $id
    );

    $response = new Response();
    $response->setHttpStatusCode(201);
    $response->setSuccess(true);
    $response->setData($returnData);
    $response->send();
    exit;
}
//  404 for invalid roots
else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("Endpoint for sessions not found");
    $response->send();
    exit;
}