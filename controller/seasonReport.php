<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../model/User.php');


/**
 * @author Steve Labrinos [stalab at linuxmail.org] on 25/4/2021
 */

 // Create a new db connection in writeDB
 try {
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

//  apis to get season report in XML form
if(array_key_exists("season", $_GET)) {
    $season = $_GET['season'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        //  get data from DB to populate the XML response
        try {
            $query = 'SELECT first_name,
                             last_name,
                             ROUND(AVG(grade), 2) average_grade,
                             COUNT(*)             courses_passed
                      FROM app_user
                            INNER JOIN season s ON app_user.id = s.student_id
                            INNER JOIN registrations r ON app_user.id = r.user_id
                      WHERE season_number = :season
                        AND grade >= 5
                      GROUP BY app_user.id, first_name, last_name
                      ORDER BY last_name, first_name';
            $stmt = $readDB->prepare($query);
            $stmt->bindParam(':season', $season, PDO::PARAM_STR);
            $stmt->execute();

            $rowCount = $stmt->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("No students found to report");
                $response->send();
                exit;
            } 

            $imp = new DOMImplementation;

            $dtd = $imp->CreateDocumentType('students', '', 'students.dtd');
            $dom = $imp->createDocument("", "", $dtd);
            $dom->encoding = 'UTF-8';
            $dom->standalone = false;

            $root = $dom->createElement('Students');
            $season_node = $dom->createElement('Season', $season);
            $root->appendChild($season_node);
            //  creating all the students based on the SQL query
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                //  new Student node
                $student_node = $dom->createElement('Studen');
                $child_node_first_name = $dom->createElement('FirstName', $first_name);
                $student_node->appendChild($child_node_first_name);
                $child_node_last_name = $dom->createElement('LastName', $last_name);
                $student_node->appendChild($child_node_last_name);
                $child_node_avg_grade = $dom->createElement('AverageGrade', $average_grade);
                $student_node->appendChild($child_node_avg_grade);
                $child_node_courses_passed = $dom->createElement('CoursesPasses', $courses_passed);
                $student_node->appendChild($child_node_courses_passed);
                $root->appendChild($student_node);
            }
            $dom->appendChild($root);
            //  send the xml to the frontend
            echo $dom->saveXML();
            //  save an XML file fro the exercise needs
            $xml_file_name = 'students_season_report.xml';
            $dom->save($xml_file_name);

            // $dom = new DOMDocument;
            // $dom->load('students_season_report.xml');
            if ($dom->validate()) {
                echo "This is valid doc";
            } else {
                echo "Not a valid doc";
            }

            //  generate the XML file
            //  create a new DOM object and set the properties
            // $dom = new DOMDocument('1.0', 'utf-8');
            // $dom->formatOutput = true;

            // //  create the XML file and populate the data from the dom object
            // $xml_file_name = 'studends_season_report.xml';
            // //  root element
            // $root = $dom->createElement('Students');
            // $season_node = $dom->createElement('Season', $season);
            // $root->appendChild($season_node);
            // //  creating all the students based on the SQL query
            // while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            //     extract($row);
            //     //  new Student node
            //     $student_node = $dom->createElement('Studen');
            //     $child_node_first_name = $dom->createElement('FirstName', $first_name);
            //     $student_node->appendChild($child_node_first_name);
            //     $child_node_last_name = $dom->createElement('LastName', $last_name);
            //     $student_node->appendChild($child_node_last_name);
            //     $child_node_avg_grade = $dom->createElement('AverageGrade', $average_grade);
            //     $student_node->appendChild($child_node_avg_grade);
            //     $child_node_courses_passed = $dom->createElement('CoursesPasses', $courses_passed);
            //     $student_node->appendChild($child_node_courses_passed);
            //     $root->appendChild($student_node);
            // }
            // $dom->appendChild($root);
            // echo '<xmp>'.$dom->saveXML().'</xmp>';
            // $dom->save($xml_file_name);
            // // return $xml_file_name;
            // echo "$xml_file_name succesfully created";
            
            exit;
        } catch (PDOException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("There was getting the student list. " + $ex.getMessage());
            $response->send();
            exit;
        }
    }
    //  only get actions are allowed
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
    $response->addMessage("Endpoint for student list not found");
    $response->send();
    exit;
}
