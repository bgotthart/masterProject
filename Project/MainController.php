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

    /*
     * Handles Request of BrowserPlugin
     * and initiiates saving Keywords and Concepts in Database

     */

    public function callSemanticApis($url) {
        //    return $response;
    }

    public function handlingAPIRequests($url) {

        $text = $this->getTextOfURL($url);

        $responseZemanta = json_decode($this->callZemantaAPIWithText($text), true);

        $keywords = array();
        foreach ($responseZemanta['keywords'] as $keyword) {
            array_push($keywords, $keyword['name']);
        }

        return $this->DB_store->insertUserQuery($keywords);
    }

    /*
     * return the output for the user interests
     */

    public function printUserInterests() {

        $this->userInterests = $this->DB_store->selectUserQuery();

        $result = "<ul>";

        foreach ($this->userInterests as $topic) {
            $result .= "<li>" . $topic['name'];
            if (isset($topic['connection']))
                $result .= ": " . $topic['connection']['uri'];
            if (isset($topic['weight'])) {
                $result .= ': ' . $topic['weight'];
            }


            $result .= "</li>";
        }
        $result .= "</ul>";

        return $result;
    }

    public function saveKeywords($keywords) {

        $extracted = explode(", ", $keywords);


        if (count($extracted) > 0 || strlen($extracted) > 0) {

            $extractedResponse = $this->DB_store->insertUserQuery($extracted);
            //$this->userInterests = $this->DB_store->selectUserQuery();
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

    public function getFeeds() {
        
        print_r($this->DB_store->selectFeedQuery());
       
    }
    public function saveFeeds() {

        $config_xml = $this->loadConfigFile();

        $feedURLs = $config_xml->newsfeeds;

        $feeds = array();
        foreach ($feedURLs->feed as $feed) {

            array_push($feeds, ((string) $feed->filename));
            return $this->fetchFeedInformation((string) $feed->filename);
        }
        
       
    }

    /*
     * Parse RSS Feed of config.xml
     */

    public function fetchFeedInformation($feed_url) {


        $content = file_get_contents($feed_url);

        $x = new SimpleXmlElement($content);
        $urlArray = array();

        $i = 10;
        foreach ($x->channel->item as $entry) {
            
           
            if ($i == 9) {
                return;
            }
              
            
            array_push($urlArray, $entry->link);

            $text = $this->getTextOfURL($entry->link);
            $response = json_decode($this->callZemantaAPIWithText($text), true);
            
            //$response = $this->callOpenCalaisAPI($text);

 
            $keywords = array();
            foreach ($response['keywords'] as $keyword) {
                array_push($keywords, $keyword['name']);
            }

           
            $i--;


            return $this->DB_store->insertFeedQuery($entry, $keywords);
            
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
        $content = file_get_contents($url);
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

    public function getMainTopics() {
        $this->DB_store->getMainTopics();
    }

    public function update() {
        $this->DB_store->updateUserQuery();
    }

    public function delete() {
        $this->DB_store->deleteUserQuery();
    }

    public function printAllUserInterests() {
        $this->userInterests = $this->DB_store->selectAllQuery();
        $result = "<ul>";

        foreach ($this->userInterests as $topic) {
            $result .= "<li>" . $topic['name'] . ": " . $topic['weight'];

            if (isset($topic['connections'])) {
                $result .= "<ul> ";
                foreach ($topic['connections'] as $connection) {
                    $result .= "<li>" . $connection['connectionName'] . "</li> ";
                    //$result .= $connection['connectionWeight']."</li>";
                }
                $result .= "</ul></li>";
            } else {
                $result .= "</li>";
            }
        }
        $result .= "</ul>";
        echo($result);
        return $result;
    }

    public function calcSimilarityBetweenTerms($term1, $term2) {

        print_r($this->DB_store->similarityCheckWithLinks($term1, $term2));
        // print_r($this->DB_store->similarityCheckWithCategories($term1, $term2));
    }

}

?>
