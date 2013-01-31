<?php

/*for debuggin*/
unset($_SESSION['controller']);

$controller = new MainController();

$_SESSION['controller'] = $controller;
    
/*
if(!isset($_SESSION['controller'])){
  
    $controller = new MainController();
    $_SESSION['controller'] = $controller;

    echo("new session started!");
}

*/

if(isset($_GET['dbpedia'])){
    $_SESSION['controller']->callDBpedia();
    
    return;
}
if(isset($_GET['init'])){
    
    //TODO Zeitkomponente
    echo("init feed data");
    return $_SESSION['controller']->getFeed($_GET['url']);
}

if (isset($_REQUEST['call']) && $_REQUEST['call'] == "handlingAPIRequest") {
    echo $_SESSION['controller']->handlingAPIRequests($_REQUEST['url']);
    
    return;
}

if (isset($_POST['function'])) {

    if ($_POST['function'] == "addInterestText") {

        return "TODO: addInterestText";
        $_SESSION['controller']->insertUserQuery($data);
    }
    
    return;
}

if (isset($_GET['all']) && $_GET['all'] == "1") {
    $_SESSION['controller']->queryReallyAll();
}

if (isset($_GET['all']) && $_GET['all'] == "2") {
    $_SESSION['controller']->queryAllTriples();
}


function processAction_addInterestText($text) {

    return "TODO: addInterest";
 //   $data = $_POST['interestText'];
}

function processAction_printInterests() {
    return $_SESSION['controller']->printUserInterests();
}

function addURLToUserProfile() {
    return "TODO: addURLToProfile";
}

?>
