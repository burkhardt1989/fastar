<?php

include 'Student.php';

$studentModel = new student();

$student = $studentModel->findByPk(1);

var_dump($student->attributes);