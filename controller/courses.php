<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../model/Course.php');

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

//  apis to get a specific course - UPDATE, DELETE
if(array_key_exists("courseid", $_GET)) {
    $courseId = $_GET['courseid'];
    echo $courseId;

    if ($courseId === '' || !is_numeric($courseId)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $courseId === '' ? $response->addMessage("Course ID cannot be blank") : false;
        !is_numeric($courseId) ? $response->addMessage("Course ID must be a number") : false;
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

    //  DELETE method to delete a course
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        //  Delete the current session loging out the user
        try {
            $query = 'DELETE FROM course
                      WHERE id = :courseId';
            $stmt = $writeDB->prepare($query);
            $stmt->bindParam(':courseId', $courseId, PDO::PARAM_INT);
            $stmt->execute();

            $rowCount = $stmt->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("No course to delete");
                $response->send();
                exit;
            } 

            //  Response after deleting the user
            $returnData = array( "id" => intval($courseId) );

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("Course Deleted");
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PDOException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("There was an issue deleting the course. Please try again");
            $response->send();
            exit;
        }
    }
    //  PATCH method to update a course
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
            $course = new Course(
                $courseId,
                $jsonData->teacherId,
                $jsonData->title,
                $jsonData->type,
                $jsonData->description,
                $jsonData->season,
                $jsonData->ects
            );

            $id = $course->getId();
            $teacherId = $course->getTeacherId();
            $title = $course->getTitle();
            $type = $course->getType();
            $description = $course->getDescription();
            $season = $course->getSeason();
            $ects = $course->getEcts();

            $query = 'UPDATE course
                      SET teacher_id = :teacherId,
                          title = :title,
                          type = :type,
                          description = :description,
                          season = :season,
                          ects = :ects
                      WHERE id = :id';
            $stmt = $writeDB->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':teacherId', $teacherId, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':season', $season, PDO::PARAM_STR);
            $stmt->bindParam(':ects', $ects, PDO::PARAM_INT);
            $stmt->execute();

            $rowCount = $stmt->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No Course found to update");
                $response->send();
                exit;
            }

            //  prepare the return data
            $returnData = $course->returnCourseAsArray();
            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage("Course updated");
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PDOException $e) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Error updating course - Check the input data");
            $response->send();
            exit;
        } catch (CourseException $e) {
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
        $response->addMessage("Request method not allowed for specific course");
        $response->send();
        exit;
    }
}
//  apis for non specific course - CREATE, FETCH
elseif (empty($_GET)) {
    //  GET all courses - no auth required
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        //  fetch all courses from the DB
        try {
            $query = 'SELECT id, teacher_id, title, type, description, season, ects
                      FROM course
                      ORDER BY season, title';
            $stmt = $readDB->prepare($query);
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
    //  POST - create a new course - auth required
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $query = 'SELECT role 
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

            if ($role !== 'STUFF') {
                $response = new Response();
                $response->setHttpStatusCode(405);
                $response->setSuccess(false);
                $response->addMessage("Course creation only allowed for STUFF type users");
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
        if (!isset($jsonData->teacherId) || !isset($jsonData->title) || 
            !isset($jsonData->type) || !isset($jsonData->description) || 
            !isset($jsonData->season) || !isset($jsonData->ects)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            !isset($jsonData->teacherId) ? $response->addMessage("Teacher ID filed is mandatory") : false;
            !isset($jsonData->title) ? $response->addMessage("Title filed is mandatory") : false;
            !isset($jsonData->type) ? $response->addMessage("Type filed is mandatory") : false;
            !isset($jsonData->description) ? $response->addMessage("Description filed is mandatory") : false;
            !isset($jsonData->season) ? $response->addMessage("Season filed is mandatory") : false;
            !isset($jsonData->ects) ? $response->addMessage("ECTS filed is mandatory") : false;
            $response->send();
            exit;
        }
        
        //  inserting the input data to the DB
        try {
            //  create the User model
            $course = new Course(
                null,
                $jsonData->teacherId,
                $jsonData->title,
                $jsonData->type,
                $jsonData->description,
                $jsonData->season,
                $jsonData->ects
            );

            //  insert the new user
            $teacherId = $course->getTeacherId();
            $title = $course->getTitle();
            $type = $course->getType();
            $description = $course->getDescription();
            $season = $course->getSeason();
            $ects = $course->getEcts();

            $query = '  INSERT INTO course (teacher_id, title, type, description, season, ects)
                        VALUES (:teacherId, :title, :type, :description, :season, :ects)';
            $stmt = $writeDB->prepare($query);
            $stmt->bindParam(':teacherId', $teacherId, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':season', $season, PDO::PARAM_STR);
            $stmt->bindParam(':ects', $ects, PDO::PARAM_INT);
            $stmt->execute();

            $rowCount = $stmt->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to insert course");
                $response->send();
                exit;
            }

            //  get the created course id
            $createdCourseId = $writeDB->lastInsertId();

            $returnData = array(
                "id" => intval($createdCourseId),
                "teacherId" => $course->getTeacherId(),
                "title" => $course->getTitle(),
                "type" => $course->getType(),
                "description" => $course->getDescription(),
                "season" => $course->getSeason(),
                "ects" => $course->getEcts()
            );
        
            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage("Course created");
            $response->setData($returnData);
            $response->send();
            exit;
        } catch (PDOException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Error while creating new course".$e->getMessage());
            $response->send();
            exit;
        } catch (CourseException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit;
        }
    }
    //  Block other request methods
    else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed for courses");
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