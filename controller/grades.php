<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../model/Course.php');

/**
 * @author Steve Labrinos [stalab at linuxmail.org] on 15/3/2021
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
    $query = 'SELECT role, app_user.id 
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
    $teacherId = $id;

    if ($role !== 'TEACHER') {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Course graded only allowed for TEACHERS type users");
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

//  get the teachers courses
if (empty($_GET)) {
    //  fetch all courses from the DB
    try {
        $query = 'SELECT id, teacher_id, title, type, description, season, ects
                  FROM course
                  WHERE teacher_id = :teacherId
                  ORDER BY season, title';
        $stmt = $readDB->prepare($query);
        $stmt->bindParam(':teacherId', $teacherId, PDO::PARAM_INT);
        $stmt->execute();

        $rowCount = $stmt->rowCount();

        $coursesArray = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            $course = new Course($id,$teacher_id, $title, $type, $description, $season, $ects);

            $coursesArray[] = $course->returnCourseAsArray();
        }

        $returnData = array(
            "rows_returned" => $rowCount,
            "courses" => $coursesArray
        );

        $response = new Response();
        $response->setHttpStatusCode(200);
        $response->setSuccess(true);
        $response->setData($returnData);
        $response->send();
        exit;
    } catch (PDOException $e) {
        error_log("Database query error ".$e, 0);
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Error fetching courses from database".$e->getMessage());
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
//  block unknows rootls
else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("Endpoint for courses not found");
    $response->send();
    exit;
}