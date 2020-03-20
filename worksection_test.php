<?php
//print '<pre>' . print_r($eventsArray, true) . '</pre>';


$worksectionConfig = [
    'email' => '',
    'password' => '',
    'domain' => 'xxxxx.worksection.com',
    'apikey' => '',
];


include('./worksection_api.php');

$worksectionHandler = new worksectionHandler($worksectionConfig);


if ($worksectionHandler->isAuthed() == false){

    print 'authing<br/>';
    $worksectionHandler->doHttpAuth();

    if ($worksectionHandler->isAuthed() == false){
        print 'unable to doHttpAuth';
        exit();
    }
}

print 'Ready<br/>';

$myEvents = $worksectionHandler->getLastEvents();

//$taskLogs = $worksectionHandler->getTaskLogs('74675', 1799939);

//$tasksList = $worksectionHandler->getAllTasks();

print '<pre>' . print_r($myEvents, true) . '</pre>';


?>