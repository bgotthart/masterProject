<?php

include_once("MainController.php");

/* for debugging */


unset($_SESSION['controller']);

$controller = new MainController();

$_SESSION['controller'] = $controller;




if (!isset($_SESSION['controller'])) {

    $controller = new MainController();
    $_SESSION['controller'] = $controller;

    echo("new session started!");
}

if (isset($_GET['dbpedia']) && isset($_GET['init'])) {

    $_SESSION['controller']->initDBpediaDump();

} else {
    if (isset($_GET['dbpedia'])) {

        $_SESSION['controller']->saveKeyword();

        return;
    }
}

if (isset($_REQUEST['call']) && $_REQUEST['call'] == "handlingAPIRequest") {
    echo $_SESSION['controller']->handlingAPIRequests($_REQUEST['url']);

    return;
}

/*
 * TODO
 * addInterestItem for explicit adding of interest term
 */
if (isset($_POST['function'])) {
    if ($_POST['function'] == "addInterestItem") {
        //return processAction_addInterestText($_POST['item']);
    }

    return;
}

function processAction_addInterestText($text) {
    return "//TODO: addInterest";
}

function processAction_printInterests() {
    return $_SESSION['controller']->printUserInterests();
}

?>
