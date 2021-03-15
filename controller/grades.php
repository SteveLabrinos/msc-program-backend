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
//  get students for a specific course
elseif (array_key_exists("courseid", $_GET)) {
    $courseId = $_GET['courseid'];

    if ($courseId === '' || !is_numeric($courseId)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $courseId === '' ? $response->addMessage("Course ID cannot be blank") : false;
        !is_numeric($courseId) ? $response->addMessage("Course ID must be a number") : false;
        $response->send();
        exit;
    }

    //  fetch all students that enrolled for the course
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        //  get all registrations that have enrolled for this course
        try {
            $query = '  SELECT r.user_id, u.first_name, u.last_name, u.registration_number, r.grade, r.id
                        FROM registrations r JOIN app_user u ON r.user_id = u.id
                        WHERE r.course_id = :courseId
                        AND status = \'REGISTERED\'';
            $stmt = $readDB->prepare($query);
            $stmt->bindParam(':courseId', $courseId, PDO::PARAM_INT);
            $stmt->execute();

            $rowCount = $stmt->rowCount();

            //  prepare the response data
            $studentsArray = array();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $student = array(
                    "id" => $user_id,
                    "firstName" => $first_name,
                    "lastName" => $last_name,
                    "registrationNumber" => $registration_number,
                    "grade" => $grade === null ? '' : $grade,
                    "registrationId" => $id
                );

                $studentsArray[] = $student;
            }

            $returnData = array(
                "rows_returned" => $rowCount,
                "students" => $studentsArray
            );

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PDOException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("There was an issue getting course enrolled students. ".$e->getMessage());
            $response->send();
            exit;
        }
    }
    //  update the grade for a student provided the registration id
    elseif($_SERVER['REQUEST_METHOD'] === 'PATCH') {
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
            $query = '  UPDATE registrations
                        SET grade = :grade
                        WHERE id = :registrationId';
            $stmt = $writeDB->prepare($query);
            $stmt->bindParam(':grade', $jsonData->grade, PDO::PARAM_INT);
            $stmt->bindParam(':registrationId', $jsonData->registrationId, PDO::PARAM_INT);
            $stmt->execute();

            $rowCount = $stmt->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No registration found to update");
                $response->send();
                exit;
            }

            //  prepare the response data
            $responseData = array(
                "rows_returned" => $rowCount,
                "grade" => $jsonData->grade
            );
            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage("Registration updated");
            $response->setData($responseData);
            $response->send();
            exit;

        } catch (PDOException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Error updating the registration. ".$e->getMessage());
            $response->send();
            exit;
        }



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