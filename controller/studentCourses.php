<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../model/User.php');

/**
 * @author Steve Labrinos [stalab at linuxmail.org] on 14/3/2021
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

//  methods to get course statistics
if (empty($_GET)) {
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
        $query = 'SELECT app_user.role, app_user.id
                FROM session JOIN app_user ON session.user_id = app_user.id
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

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        //  getting user role based on the action token
        extract($row);
        $userId = $id;

        if ($role !== 'STUDENT') {
            $response = new Response();
            $response->setHttpStatusCode(405);
            $response->setSuccess(false);
            $response->addMessage("Enrolles only allowed for STUDENT type users");
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

    //  GET all requests with choices
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $query = 'SELECT c.season, title, teacher_id, ects, type, status, grade, r.user_id, r.id reg_id
                  FROM course c LEFT JOIN (SELECT * FROM registrations 
                                           WHERE user_id = :userId) r ON c.id = r.course_id
                  ORDER BY c.season, title';
        $stmt = $writeDB->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $rowCount = $stmt->rowCount();

        //  prepare the response data
        $courseArray = array();

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $course = array(
                "season" => $season,
                "title" => $title,
                "teacherId" => $teacher_id,
                "ects" => $ects,
                "type" => $type,
                "status" => $status,
                "grade" => $grade,
                "userId" => $user_id,
                "registrationId" => $reg_id
            );
            $courseArray[] = $course;
        }
        $returnData = array(
            "rows_returned" => $rowCount,
            "courses" => $courseArray
        );
        $response = new Response();
        $response->setHttpStatusCode(200);
        $response->setSuccess(true);
        $response->setData($returnData);
        $response->send();
        exit;
    }
}
//  requests for specific
elseif (array_key_exists("registrationid", $_GET)) {
    $registrationId = $_GET['registrationid'];
    //  update the registration
    if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $rawPATCHData = file_get_contents('php://input');

        if (!$jsonData = json_decode($rawPATCHData)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Request body is not valid JSON");
            $response->send();
            exit;
        }

        $status = $jsonData->status;
        try {
            //  update the registration id
            $query = 'UPDATE registrations
                    SET status = :status
                    WHERE id = :registrationId';
            $stmt = $writeDB->prepare($query);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':registrationId', $registrationId, PDO::PARAM_INT);
            $stmt->execute();

            $rowCount = $stmt->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No registrations to update");
                $response->send();
                exit;
            }

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("Course updated");
            $response->send();
            exit;
        } catch (PDOException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Unable to update registration ".$e->getMessage());
            $response->send();
            exit;
        }
        
    }
}