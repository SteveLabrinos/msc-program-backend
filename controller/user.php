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
    $userId = $_GET['userid'];

    if ($userId === '' || !is_numeric($userId)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $userId === '' ? $response->addMessage("User ID cannot be blank") : false;
        !is_numeric($userId) ? $response->addMessage("User ID must be a number") : false;
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

    //  check that the token is valid
    try {
        $query = 'SELECT id FROM session
                  WHERE token = :accessToken';
        $stmt = $writeDB->prepare($query);
        $stmt->bindParam(':accessToken', $accessToken, PDO::PARAM_STR);
        $stmt->execute();
    
        $rowCount = $stmt->rowCount();
    
        if ($rowCount === 0) {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("Failed to retrieve user credential for the access token");
            $response->send();
            exit;
        }
    } catch (PDOException $ex) {
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Failed to execute database query");
        $response->send();
        exit;
    }

    //  Delete the current user
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        //  Delete the current session loging out the user
        try {
            $query = 'DELETE FROM app_user
                      WHERE id = :userId';
            $stmt = $writeDB->prepare($query);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $rowCount = $stmt->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("No user to delete");
                $response->send();
                exit;
            } 

            //  Response after deleting the user
            $returnData = array( "id" => intval($userId) );

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("User Deleted");
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PDOException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("There was an issue deleting the user. Please try again");
            $response->send();
            exit;
        }
    } 
    //  Update the current user
    else if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        //  check the content type
        if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Content type header not set to JSON");
            $response->send();
            exit;
        }

        $rawPATCHData = file_get_contents('php://input');

        if (!$jsonData = json_decode($rawPATCHData)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Request body is not valid JSON");
            $response->send();
            exit;
        }

        try {
            $user = new User(
                $userId,
                $jsonData->firstName,
                $jsonData->lastName,
                $jsonData->email,
                $jsonData->password,
                $jsonData->role
            );
            $user->setSeasonNumber($jsonData->seasonNumber);

            $id = $user->getId();
            $firstName = $user->getFirstName();
            $lastName = $user->getLastName();
            $email = $user->getEmail();
            $password = $user->getPassword();
            $role = $user->getRole();
            $seasonNumber = $user->getSeasonNumber();

            $query = 'UPDATE app_user
                      SET first_name = :firstName,
                          last_name = :lastName,
                          email = :email,
                          password = :password,
                          role = :role
                      WHERE id = :id';
            $stmt = $writeDB->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':firstName', $firstName, PDO::PARAM_STR);
            $stmt->bindParam(':lastName', $lastName, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password', $password, PDO::PARAM_STR);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            $stmt->execute();

            $rowCount = $stmt->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No User found to update");
                $response->send();
                exit;
            }

            //  update season number if the user is STUDENT
            if ($role === 'STUDENT') {
                $query = 'UPDATE season
                          SET season_number = :seasonNumber
                          WHERE student_id = :studentId';
                $stmt = $writeDB->prepare($query);
                $stmt->bindParam(':seasonNumber', $seasonNumber, PDO::PARAM_STR);
                $stmt->bindParam(':studentId', $id, PDO::PARAM_INT);
                $stmt->execute();
                echo $seasonNumber;
                echo $id;

                $rowCount = $stmt->rowCount();

                if ($rowCount === 0) {
                    $response = new Response();
                    $response->setHttpStatusCode(404);
                    $response->setSuccess(false);
                    $response->addMessage("No Season found to update");
                    $response->send();
                    exit;
                }

                //  Delete registrations from that aren't rated and enrolled
                $query = 'DELETE FROM registrations
                          WHERE user_id = :userId
                          AND status = \'NOT_REGISTERED\'';
                $stmt = $writeDB->prepare($query);
                $stmt->bindParam(':userId', $id, PDO::PARAM_INT);
                $stmt->execute();

                //  insert the registrations init for the season
                $query = 'INSERT INTO registrations (user_id, course_id)
                          (SELECT :user_id, id
                           FROM course
                           WHERE CONVERT(season, INTEGER) = :seasonNumber)';
                $stmt = $writeDB->prepare($query);
                $stmt->bindParam(':user_id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':seasonNumber', $seasonNumber, PDO::PARAM_INT);
                $stmt->execute();

                $rowCount = $stmt->rowCount();

                if ($rowCount === 0) {
                    $response = new Response();
                    $response->setHttpStatusCode(500);
                    $response->setSuccess(false);
                    $response->addMessage("Failed to insert registrations for the user");
                    $response->send();
                    exit;
                }
            }

            //  prepare the return data
            $returnData = $user->returnUserAsArray();
            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage("User updated");
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PDOException $e) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Error updating user - Check the input data".$e->getMessage());
            $response->send();
            exit;
        } catch (UserException $e) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        }
    }
    //  Block other type of requests
    else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method is not allowed");
        $response->send();
        exit;
    }
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
        $user->setRegistrationNumber(null);
        $user->setSignupDate(null);
        $user->setSeasonNumber($jsonData->seasonNumber);

        //  insert the new user
        $firstName = $user->getFirstName();
        $lastName = $user->getLastName();
        $email = $user->getEmail();
        $password = $user->getPassword();
        $role = $user->getRole();
        $signupDate = $user->getSignupDate();
        $registration = $user->getRegistrationNumber();
        $seasonNumber = $user->getSeasonNumber();

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

            //  insert season if the user has the STUDENT role
            if ($user->getRole() === 'STUDENT') {
                $query = '  INSERT INTO season (student_id, season_number)
                            VALUES (:id, :season)';
                $stmt = $writeDB->prepare($query);
                $stmt->bindParam(':id', $createdUserId, PDO::PARAM_INT);
                $stmt->bindParam(':season', $seasonNumber, PDO::PARAM_STR);
                $stmt->execute();

                if ($rowCount === 0) {
                    $response = new Response();
                    $response->setHttpStatusCode(500);
                    $response->setSuccess(false);
                    $response->addMessage("Failed to insert season for the user");
                    $response->send();
                    exit;
                }

                //  insert the registrations init for the season
                $query = 'INSERT INTO registrations (user_id, course_id)
                          (SELECT :user_id, id
                           FROM course
                           WHERE CONVERT(season, INTEGER) <= :seasonNumber)';
                $stmt = $writeDB->prepare($query);
                $stmt->bindParam(':user_id', $createdUserId, PDO::PARAM_INT);
                $stmt->bindParam(':seasonNumber', $seasonNumber, PDO::PARAM_INT);
                $stmt->execute();

                $rowCount = $stmt->rowCount();

                if ($rowCount === 0) {
                    $response = new Response();
                    $response->setHttpStatusCode(500);
                    $response->setSuccess(false);
                    $response->addMessage("Failed to insert registrations for the user");
                    $response->send();
                    exit;
                }
            }

            $returnData = array(
                "id" => intval($createdUserId),
                "firstName" => $user->getFirstName(),
                "lastName" => $user->getLastName(),
                "email" => $user->getEmail(),
                "role" => $user->getRole(),
                "signupDate" => $user->getSignupDate(),
                "registrationNumber" => $user->getRegistrationNumber(),
                "seasonNumber" => $seasonNumber
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
            $query = 'SELECT u.id id, first_name, last_name, password, email, role, phone, address, 
                             birth_date, signup_date, registration_number, season_number
                      FROM app_user u LEFT JOIN season s ON u.id = s.student_id
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
                $user->setSignupDate(null);
                $user->setRegistrationNumber(null);
                $user->setSeasonNumber($season_number);

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
//  404 for invalid roots
else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("Endpoint for users not found");
    $response->send();
    exit;
}



