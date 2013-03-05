<?php

set_time_limit(0);

include "AlchemyAPI_PHP5-0.8/module/AlchemyAPI.php";

if(!isset($_POST['url'])){
    $url = "http://techcrunch.com/";
}else{
    $url = $_POST['url'];
}
$config_xml = simplexml_load_file("../../config/config.xml");

        $apikey = (string) $config_xml->apis->alchemy->apikey;
        $alchemyObj = new AlchemyAPI();
        $alchemyObj->setAPIKey($apikey);

//$content = file_get_contents("../data/example.html");
//$result = $alchemyObj ->URLGetCategory("http://www.cnn.com/2011/09/28/us/massachusetts-pentagon-plot-arrest/index.html?hpt=hp_t1", 'json');

//$xml = $alchemyObj->URLGetCategory($url, AlchemyAPI::XML_OUTPUT_MODE);
$xml = $alchemyObj->URLGetConstraintQuery($url, "inside");
print_r($xml);
?>