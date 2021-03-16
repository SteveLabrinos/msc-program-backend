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

//  get statistics for a student
if (array_key_exists("studentid", $_GET)) {
    $studendId = $_GET['studentid'];

    if ($studendId === '' || !is_numeric($studendId)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $studendId === '' ? $response->addMessage("Student ID cannot be blank") : false;
        !is_numeric($studendId) ? $response->addMessage("Student ID must be a number") : false;
        $response->send();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        //  get total registered courses and student season
        try {
            $query = '  SELECT count(*) enrolls, s.season_number season
                        FROM registrations r JOIN season s ON r.user_id = s.student_id
                        WHERE r.user_id = :studentId
                        AND r.status = \'REGISTERED\'';
            $stmt = $readDB->prepare($query);
            $stmt->bindParam(':studentId', $studendId, PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            //  enrolls and season set
            extract($row);

            $query = '  SELECT count(*) mandatory_courses
                        FROM registrations r JOIN course c ON r.course_id = c.id
                        WHERE r.user_id = :studentId
                        AND grade >= 5
                        AND c.type = \'MANDATORY\'';
            $stmt = $readDB->prepare($query);
            $stmt->bindParam(':studentId', $studendId, PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            //  mandatory count set
            extract($row);

            $query = '  SELECT count(*) non_mandatory_courses
                        FROM registrations r JOIN course c ON r.course_id = c.id
                        WHERE r.user_id = :studentId
                        AND grade >= 5
                        AND c.type = \'NON_MANDATORY\'';
            $stmt = $readDB->prepare($query);
            $stmt->bindParam(':studentId', $studendId, PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            //  non mandatory count set
            extract($row);

            $totalEcts = 5 * ($mandatory_courses + $non_mandatory_courses);
            $remainingMandatoryCourses = 8 - $mandatory_courses;
            $remainingNonMandatoryCourses = 2 - $non_mandatory_courses;
            $remainingEcts = 45 - $totalEcts;

            //  prepare the response
            $returnData = array(
                "enrolls" => $enrolls,
                "season" => $season ? $season : '',
                "mandatoryCourses" => $mandatory_courses,
                "nonMandatoryCourses" => $non_mandatory_courses,
                "totalEcts" => $totalEcts,
                "remainingMandatoryCourses" => $remainingMandatoryCourses,
                "remainingNonMandatoryCourses" => $remainingNonMandatoryCourses,
                "remainingEcts" => $remainingEcts
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
            $response->addMessage("Database Connection Error".$e->getMessage());
            $response->send();
            exit;
        }
    }
}