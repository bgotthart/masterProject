<?php

include_once("MainController.php");

/* for debugging */

/*
$controller = new MainController();

$_SESSION['controller'] = $controller;

*/

$controller = new MainController();

$_SESSION['controller'] = $controller;
    
/*
//unset($_SESSION);
if (!isset($_SESSION['controller'])) {

    $controller = new MainController();
    $_SESSION['controller'] = $controller;

    echo("new session started!");
}

  */  


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
    if(isset($_GET['version'])){
        $version = $_GET['version'];
    }else{
        $version = 1;
    }
    
    return $_SESSION['controller']->printTopUserInterests($version);
}
function processAction_printFeedsForUser() {
    if(isset($_GET['version'])){
        $version = $_GET['version'];
    }else{
        $version = 1;
    }
    return ($_SESSION['controller']->getFeedsForUser($version) );
}

function processAction_printTextOfURL($url){
    return ($_SESSION['controller']->getContentScraping($url));

}
/*debug*/

function processAction_printAllFeeds() {
    return ($_SESSION['controller']->getAllFeeds() );
}

if(isset($_GET['getFeeds'])){
    $_SESSION['controller']->getAllFeeds();
    
    return;
}

if(isset($_GET['saveFeeds'])){
    $_SESSION['controller']->saveFeeds();
    
    return;
}
  
if (isset($_GET['update'])) {
    $_SESSION['controller']->update();
}

if (isset($_GET['delete'])) {
    $_SESSION['controller']->delete();
}
if (isset($_GET['dbpedia']) && isset($_GET['init'])) {

    $_SESSION['controller']->initDBpediaDump();

} 
if(isset($_GET['saveKeywords'])) {

    
    echo $_SESSION['controller']->saveKeywords($_REQUEST['saveKeywords']);
    return;
    
}
if(isset($_GET['selectDBpedia'])){
        echo $_SESSION['controller']->selectAllDBpedia();

}
if(isset($_GET['printAll'])) {

    
    echo $_SESSION['controller']->printAllUserInterests();
    return;
    
}
if (isset($_REQUEST['call']) && $_REQUEST['call'] == "callZemantaAPI") {
    echo $_SESSION['controller']->callZemantaAPIWithURL($_REQUEST['url']);

    return;
}

if (isset($_REQUEST['call']) && $_REQUEST['call'] == "handlingAPIRequest") {
    
    echo $_SESSION['controller']->handlingAPIRequests($_REQUEST['url']);

    return;
}

if(isset($_GET['checkSimilarity'])){
    $_SESSION['controller']->calcSimilarityBetweenTerms($_GET['term1'], $_GET['term2']);
    
    return;
}

?>
