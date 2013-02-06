<?php

require_once('../APIs/opencalais/OpenCalais.class.php');
require_once('../APIs/zemanta/Zemanta.class.php');
require_once( "../APIs/alchemy/AlchemyAPI_PHP5-0.8/module/AlchemyAPI.php");
require_once("../APIs/arc2/ARC2.php");
require_once("Database.php");

class MainController {

    private $userInterestst;
    private $DB_store;

    public function __construct() {
        $this->userInterestst = array();
        
        $this->loadConfigFile();
        
        $this->DB_store = new DatabaseClass();

    }
    
    public function initDBpediaDump(){
        $this->DB_store->initDBpediaDump();
    }

    /*
     * Handles Request of BrowserPlugin
     * and initiiates saving Keywords and Concepts in Database

     */
    public function handlingAPIRequests($url) {
        $responseZemanta = $this->callZemantaAPI($url);

        $keywords = array();
        foreach($responseZemanta['keywords'] as $keyword){
            array_push($keywords, $keyword['name']);
        }
        

        $this->DB_store->insertUserQuery($keywords);
    }

    /*
     * return the output for the user interests
     */
    public function printUserInterests() {
        
        $this->userInterestst = $this->DB_store->selectQuery();
        
        $result = "";


        foreach ($this->userInterestst as $topic) {
            $result .= "<p>" . $topic['name']. ": ";
            $result .= $topic['connection']."<p>";
            
        }
        $result .= "</table>";
        
        return $result;
    }

    /*
     * Only for testing
     * Initiiates saving Keywords and Concepts in Database with get-parameter dbpedia=1
     */
    public function saveKeyword() {
        $keywords = array(0 => "Volleyball");

        $this->DB_store->insertUserQuery($keywords);
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
        $config_xml = $this->loadConfigFile();
        $apikey = (string) $config_xml->apis->opencalais->apikey;

        $oc = new OpenCalais($apikey, "bgotthart");
        $oc->outputFormat = "XML/RDF";
        $content = file_get_contents($url);
        $entities = $oc->parse($content);

        return $entities;
    }

    private function callZemantaAPI($url) {
        $config_xml = $this->loadConfigFile();

        $apikey = (string) $config_xml->apis->zemanta->apikey;

        $zemanta = new Zemanta($apikey);
        $content = file_get_contents($url);
        $entities = $zemanta->parse($content);
        return $entities;
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

}

?>
