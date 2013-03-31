<?php

require_once('../APIs/opencalais/OpenCalais.class.php');
require_once('../APIs/zemanta/Zemanta.class.php');
require_once( "../APIs/alchemy/AlchemyAPI_PHP5-0.8/module/AlchemyAPI_CURL.php");
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

    /*
     * Handles Request of BrowserPlugin
     * and initiiates saving Keywords and Concepts in Database

     */
    public function handlingAPIRequests($url) {

        $text = $this->getTextOfURL($url);

        $responseZemanta = json_decode($this->callZemantaAPIWithText($text), true);

        $keywords = array();
        foreach ($responseZemanta['keywords'] as $keyword) {
            array_push($keywords, $keyword['name']);
        }

        return $this->DB_store->saveKeywordsToUserQuery($url, $keywords);
    }

    /*
     * return the output for the user interests
     */

    public function printTopUserInterests($version) {
        //version 1 = only top 10 extracted Keywords
        //version 2 = only main topics of dbpedia
        //version 3 = top 10 ranked (counted) concepts
        //version 4 = concepts with most connection from others
        
        if(count($this->userInterests) == 0){
           $this->userInterests = $this->DB_store->selectTopUserInterests($version);
        }

        return $this->userInterests;

    }
    
    public function printAllUserInterests() {
        $this->userInterests = $this->DB_store->selectAllUserInterests();

        $HTMLResult = "<ul>";

        foreach ($this->userInterests as $topic) {
            $HTMLResult .= "<li>" . $topic['uri'];
            if(isset($topic['name']))
                $HTMLResult .= ", name: ".$topic['name'];   
            if(isset($topic['count']))
                $HTMLResult .= ", count: ". $topic['count'];
            if(isset($topic['isKeyword']))
                $HTMLResult .= ", isKeyword";
            if (isset($topic['connection']))
                $HTMLResult .= ": " . $topic['connection']['uri'];
            if (isset($topic['weight'])) 
                $HTMLResult .= ', weight: ' . $topic['weight'];
            $HTMLResult .= "</li>";
        }
        
        $HTMLResult .= "</ul>";

        return $HTMLResult;
    }

    public function saveKeywords($keywords) {

        $extracted = explode(", ", $keywords);
        if (count($extracted) > 0 || strlen($extracted) > 0) {

            $extractedResponse = $this->DB_store->saveKeywordsToUserQuery($extracted);
        } else {
            $extractedResponse = '{"response": [{ "status": 400, "function":"insertUserQuery" , "message":"ERROR: Entities NOT added to User Profile" }]}';
        }

        $response = json_decode($extractedResponse, true);

        if ($response['response'][0]['status'] == 400 || $response['response'][0]['status'] == '400') {
            return $extractedResponse;
        } else {
            
            return json_encode($response['response'][0]);
        }
    }

    public function getFeedsForUser($version) {
        
        //Varianten:
        //1: 1. Ebene (Main Topics)
        //2: 1. + 2. Ebene 
        //3. nur  Keywords mit meister Häufigkeit
        //4: Verbindungen 
        if(count($this->userInterests) == 0){
           $this->userInterests = $this->DB_store->selectTopUserInterests($version);
        }
        
        return $this->DB_store->selectFeedsForUser($this->userInterests);      
       
    }
    
     public function getAllFeeds() {
        
        print_r($this->DB_store->selectAllFeedQuery());
       
    }
    public function saveFeeds() {

        $config_xml = $this->loadConfigFile();

        $feedURLs = $config_xml->newsfeeds;

        $feeds = array();
        foreach ($feedURLs->feed as $feed) {

            array_push($feeds, ((string) $feed->filename));
            echo $this->fetchFeedInformation((string) $feed->filename);
        }   
    }
   
    /*
     * Parse RSS Feed of config.xml
     */

    public function fetchFeedInformation($feed_url) {


        $content = file_get_contents($feed_url);

        $x = new SimpleXmlElement($content);
        $urlArray = array();

        
        foreach ($x->channel->item as $entry) {
            array_push($urlArray, $entry->link);

            $text = $this->getTextOfURL($entry->link);
            
            $response = json_decode($this->callZemantaAPIWithText($text), true);
            
            //$response = $this->callOpenCalaisAPI($text);

            //! TODO !
            //savingpubDate + description 
            $keywords = array();
            foreach ($response['keywords'] as $keyword) {
                array_push($keywords, $keyword['name']);
            }
            $this->DB_store->insertFeedQuery($entry, $keywords);
            
           // die("test");
        }


    }

    /*
     * API Calls
     */

    public function callZemantaAPIWithURL($url) {
        $config_xml = $this->loadConfigFile();

        $apikey = (string) $config_xml->apis->zemanta->apikey;

        $zemanta = new Zemanta($apikey);

        $content = $this->getTextOfURL($url);

        $entities = $zemanta->parse($content);

        return json_encode($entities);
    }

    public function callZemantaAPIWithText($text) {

        $config_xml = $this->loadConfigFile();

        $apikey = (string) $config_xml->apis->zemanta->apikey;

        $zemanta = new Zemanta($apikey);
        $entities = $zemanta->parse($text);

        return json_encode($entities);
    }

    private function getTextOfURL($url) {

        $config_xml = $this->loadConfigFile();

        $apikey = (string) $config_xml->apis->alchemy->apikey;
        $alchemyObj = new AlchemyAPI();
        $alchemyObj->setAPIKey($apikey);

        $text = $alchemyObj->URLGetText($url, AlchemyAPI::XML_OUTPUT_MODE);

        $x = new SimpleXmlElement($text);

        return (string) $x->text;
    }
    public function getContentScraping($url){
                $config_xml = $this->loadConfigFile();

        $apikey = (string) $config_xml->apis->alchemy->apikey;
        $alchemyObj = new AlchemyAPI();
        $alchemyObj->setAPIKey($apikey);

        $title = $alchemyObj->URLGetTitle($url, AlchemyAPI::XML_OUTPUT_MODE);
        $constraint = $alchemyObj->URLGetText($url, AlchemyAPI::XML_OUTPUT_MODE);
        
        $titleX = new SimpleXmlElement($title);
        $constraintX = new SimpleXmlElement($constraint);
        
        $linkUrl = (string)$constraintX->url;
        $title = (string)$titleX->title;
        $text = (string)$constraintX->text;

        $array =  array('url'=>$linkUrl, "title"=>$title, "text"=>$text);
        
        
        return $array;
       // $x = new SimpleXmlElement($text);

    }

    private function loadConfigFile() {

        return simplexml_load_file("../config/config.xml");
    }

    public function sendExternalRequest($url) {

        // is curl installed?
        if (!function_exists('curl_init')) {
            die('CURL is not installed!');
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/sparql-results+xml"));
        //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            echo("http code:" . $httpCode);
        }
        if (curl_error($ch)) {
            echo("error: " . curl_error($ch));
        }

        curl_close($ch);

        return $response;
    }
    
    private function callOpenCalaisAPI($text) {
       // $url = urlencode($url);
        $config_xml = $this->loadConfigFile();
        $apikey = (string) $config_xml->apis->opencalais->apikey;

        $oc = new OpenCalais($apikey, "bgotthart");
        $oc->outputFormat = "XML/RDF";
        //$content = file_get_contents($url);
        //$content = $this->getTextOfURL($url);
        $entities = $oc->parse($text);

        return $entities;
    }

    /*     * **** debugging methods **** */

    /*
      private function callAlchemyAPI($url) {
      $config_xml = $this->loadConfigFile();

      $apikey = (string) $config_xml->apis->alchemy->apikey;
      $alchemyObj = new AlchemyAPI();
      $alchemyObj->setAPIKey($apikey);

      $xml = $alchemyObj->URLGetRankedNamedEntities($url, AlchemyAPI::XML_OUTPUT_MODE);
      $xml = new SimpleXMLElement($xml);
      $response['entities'] = $xml->xpath("//entities");

      return $response;
      }
     * 
     */
    /*
      public function wikify($url){
      try {

      $text = $this->getTextOfURL($url);

      $searchUrl = "http://wdm.cs.waikato.ac.nz/services/wikify?source=" . urlencode($text) . "&responseFormat=DIRECT&sourceMode=HTML";
      $response = $this->sendExternalRequest($searchUrl);
      print_r($response);

      die("end wikify");
      $xmlobj = new SimpleXMLElement($response);

      $alternativeTerms = $xmlobj->xpath("//sense");

      $highestPrio = 0.0;
      $newTerm = $term;

      foreach ($alternativeTerms as $alternativeTerm) {

      $prio = (float)$alternativeTerm->attributes()->priorProbability;

      if($highestPrio < $prio){
      $highestPrio = $prio;
      $newTerm = (string)$alternativeTerm->attributes()->title;
      }

      }

      return (string)$newTerm;

      } catch (SQLException $oException) {
      echo ("Caught SQLException: " . $oException->sError );
      }
      }
     */

    public function initDBpediaDump() {
        $this->DB_store->initDBpediaArticleCategoriesDump();
        $this->DB_store->initDBpediaSKOSDump();
    }

    public function selectAllDBpedia() {
        $this->DB_store->selectAllDBpedia();
    }

    public function update() {
        echo($this->DB_store->updateUserQuery("<http://dbpedia.org/resource/IPhone>"));
        $this->printUserInterests();
    }

    public function delete() {
        $this->DB_store->deleteUserQuery();
                $this->printUserInterests();

    }
    /*
     *BACKUP 
    public function printAllUserInterests() {
        $this->userInterests = $this->DB_store->selectAllUserInterests();

        $HTMLResult = "<ul>";

        foreach ($this->userInterests as $topic) {
            $HTMLResult .= "<li>" . $topic['uri'];
            if(isset($topic['name']))
                $HTMLResult .= ", name: ".$topic['name'];   
            if(isset($topic['count']))
                $HTMLResult .= ", count: ". $topic['count'];
            if(isset($topic['isKeyword']))
                $HTMLResult .= ", isKeyword";
            if (isset($topic['connection']))
                $HTMLResult .= ": " . $topic['connection']['uri'];
            if (isset($topic['weight'])) 
                $HTMLResult .= ', weight: ' . $topic['weight'];
            $HTMLResult .= "</li>";
        }
        
        $HTMLResult .= "</ul>";

        return $HTMLResult;
    }
     */

}

?>
