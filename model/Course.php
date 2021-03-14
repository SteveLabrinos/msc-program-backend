<?php

/**
 * 
 * @author Steve Labrinos [stalab at linuxmail.org] on 14/3/2021
 */

 class CourseException extends Exception { }

 class Course {
    private $id;
    private $teacherId;
    private $title;
    private $type;
    private $description;
    private $season;
    private $ects;


    //  constructor
    public function __construct($id, $teacherId, $title, $type, 
        $description, $season, $ects) {
        $this->setId($id);
        $this->setTeacherId($teacherId);
        $this->setTitle($title);
        $this->setType($type);
        $this->setDescription($description);
        $this->setSeason($season);
        $this->setEcts($ects);
    }

    //  setters
    public function getId() {
        return $this->id;
    }

    public function getTeacherId() {
        return $this->teacherId;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getType() {
        return $this->type;
    }

    public function getDescription() {
        return $this->description;
    }

    public function getSeason() {
        return $this->season;
    }

    public function getEcts() {
        return $this->ects;
    }


    //  setters
    public function setId($id) {
        //  check if the argument is not numeric or negative number
        //  or the object has an id value
        if(($id !== null) && (!is_numeric($id) || $id <=0 || $this->id !== null)) {
            throw new CourseException("Course ID Error");
        }
        $this->id = intval($id);
    }

    public function setTeacherId($teacherId) {
        //  check if the argument is not numeric or negative number
        //  or the object has an id value
        if(($teacherId !== null) && (!is_numeric($teacherId) || $teacherId <=0 || $this->teacherId !== null)) {
            throw new CourseException("Course Teacher ID Error");
        }
        $this->teacherId = intval($teacherId);
    }

    public function setTitle($title) {
        if(strlen($title) < 0 || strlen($title) > 255) {
            throw new CourseException("Course Title Error");
        }
        $this->title = $title;
    }

    public function setType($type) {
        if(strtoupper($type) !== 'MANDATORY' && strtoupper($type) !== 'NON_MANDATORY') {
            throw new CourseException("Course Type must be MANDATORY or NON_MANDATORY ");
        }
        $this->type = $type;
    }

    public function setDescription($description) {
        if(strlen($description) < 0 || strlen($description) > 255) {
            throw new CourseException("Course Description Error");
        }
        $this->description = $description;
    }

    public function setSeason($season) {
        if(intval($season) < 1 || intval($season) > 3) {
            throw new CourseException("Course Season must be between 1 and 3");
        }
        $this->season = $season;
    }

    public function setEcts($ects) {
        if($ects !== 5 || !is_numeric($ects)) {
            throw new CourseException("Course ECTS Error");
        }
        $this->ects = $ects;
    }


    public function returnCourseAsArray() {
        $course = array(
            "id" => $this->getId(),
            "teacherId" => $this->getTeacherId(),
            "title" => $this->getTitle(),
            "type" => $this->getType(),
            "description" => $this->getDescription(),
            "season" => $this->getSeason(),
            "ects" => $this->getEcts(),
        );

        return $course;
    }
 }