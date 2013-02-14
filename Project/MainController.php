<?php

require_once('../APIs/opencalais/OpenCalais.class.php');
require_once('../APIs/zemanta/Zemanta.class.php');
require_once( "../APIs/alchemy/AlchemyAPI_PHP5-0.8/module/AlchemyAPI.php");
require_once("../APIs/arc2/ARC2.php");
require_once("Database.php");

class MainController {

    private $userInterests;
    private $DB_store;

    public function __construct() {
        $this->userInterests = array();

        $this->loadConfigFile();

        $this->DB_store = new DatabaseClass();
    }

    public function initDBpediaDump() {
        $this->DB_store->initDBpediaDump();
    }

    /*
     * Handles Request of BrowserPlugin
     * and initiiates saving Keywords and Concepts in Database

     */

    public function handlingAPIRequests($url) {
        $responseZemanta = json_decode($this->callZemantaAPI($url));

        $keywords = array();
        foreach ($responseZemanta['keywords'] as $keyword) {
            array_push($keywords, $keyword['name']);
        }


        return $this->DB_store->insertUserQuery($keywords);
    }

    /*
     * return the output for the user interests
     */

    public function printAllUserInterests() {

      
        $this->userInterests = $this->DB_store->selectAllQuery();
        
        $result = "<ul>";

        
        foreach ($this->userInterests as $topic) {
            $result .= "<li>" . $topic['name'];;
            
            if(isset($topic['connections'])){
                $result .=  "<ul> ";
                foreach($topic['connections'] as $connection){
                    $result .= "<li>". $connection['connectionName']."</li> ";
                    //$result .= $connection['connectionWeight']."</li>";
                }
                $result .= "</ul></li>";
            }else{
                $result .= "</li>";
            }
            
            
        }
        $result .= "</ul>";
        
        return $result;
    }
    public function printUserInterests() {

      
        $this->userInterests = $this->DB_store->selectQuery();
        
        $result = "<ul>";

        
        foreach ($this->userInterests as $topic) {
            $result .= "<li>" .$topic['name'];
            if(isset($topic['weight'])){
                //$result .= ': '.$topic['weight'];
            }
            
            
            $result .= "</li>";
            
    
        }
        $result .= "</ul>";
        
        return $result;
    }
    
    public function saveKeywords($keywords) {
        
        $extracted = explode(", ", $keywords);

      
        if(count($extracted) > 0 || strlen($extracted) > 0){
            
            $extractedResponse = $this->DB_store->insertUserQuery($extracted);
            //$this->userInterests = $this->DB_store->selectQuery();
        }else{
            $extractedResponse = '{"response": [{ "status": 400, "function":"insertUserQuery" , "message":"ERROR: Entities NOT added to User Profile" }]}';
        }

        $response = json_decode($extractedResponse, true);
        
        
        if($response['response'][0]['status'] == 400 || $response['response'][0]['status'] == '400'){
            return $extractedResponse;
        }else{
            return json_encode($response['response'][0]);
        }
        

    }

    /*
     * Parse RSS Feed of config.xml
     */

    function getFeed($feed_url) {

        $content = file_get_contents($feed_url);

        $x = new SimpleXmlElement($content);
        $urlArray = array();

        foreach ($x->channel->item as $entry) {
            array_push($urlArray, $entry->link);
        }

        return $urlArray;
    }

    /*
     * API Calls
     */

    private function callOpenCalaisAPI($url) {
        $url = urlencode($url);
        $config_xml = $this->loadConfigFile();
        $apikey = (string) $config_xml->apis->opencalais->apikey;

        $oc = new OpenCalais($apikey, "bgotthart");
        $oc->outputFormat = "XML/RDF";
        $content = file_get_contents($url);
        $entities = $oc->parse($content);

        return $entities;
    }

    public function callZemantaAPI($url) {

        $config_xml = $this->loadConfigFile();

        $apikey = (string) $config_xml->apis->zemanta->apikey;

        $zemanta = new Zemanta($apikey);
        $content = file_get_contents($url);
        $entities = $zemanta->parse($content);
        

        return json_encode($entities);
    }

    private function callAlchemyAPI($url) {
        $config_xml = $this->loadConfigFile();

        $apikey = (string) $config_xml->apis->alchemy->apikey;
        $alchemyObj = new AlchemyAPI();
        $alchemyObj->setAPIKey($apikey);

        $xml = $alchemyObj->URLGetRankedNamedEntities($url, AlchemyAPI::XML_OUTPUT_MODE);
        $xml = new SimpleXMLElement($xml);
        $response['entities'] = $xml->xpath("//entities");

        $xml = $alchemyObj->URLGetRankedKeywords($url, AlchemyAPI::XML_OUTPUT_MODE);
        $xml = new SimpleXMLElement($xml);
        $response['keywords'] = $xml->xpath("//keyword");

        return $response;
    }

    private function loadConfigFile() {

        return simplexml_load_file("../config/config.xml");
    }
    public function update(){
        $this->DB_store->updateUserQuery();
    }
    public function delete(){
        $this->DB_store->deleteUserQuery();
    }
}



?>
