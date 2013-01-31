<?php

require_once('../APIs/opencalais/OpenCalais.class.php');
require_once('../APIs/zemanta/Zemanta.class.php');
require_once( "../APIs/alchemy/AlchemyAPI_PHP5-0.8/module/AlchemyAPI.php");
require_once("../arc2/ARC2.php");
require_once("../APIs/mediawiki/Wikipedia.class.php");
require_once("Database.php");

class MainController {

    //put your code here

    private $userInterestst;
    private $DB_store;
    
    public function __construct() {
        $this->userInterestst = array();
        
        $this->loadConfigFile();
        $this->DB_store = new DatabaseClass();

        
        $this->userInterestst = $this->DB_store->selectQuery();

    }

   
    public function queryReallyAll() {
        
        $this->DB_store->queryReallyAll();
        
    }
    
    public function queryAllTriples() {
        $this->DB_store->queryAllTriples();
    }
    
    public function handlingAPIRequests($url) {

        //TODO call asynchrone    
        //$responseCalais = $this->callOpenCalaisAPI($url);
        $responseZemanta = $this->callZemantaAPI($url);
        $responseWikipedia = $this->callMediaWikipediaAPI($responseZemanta['keywords']);
        
        $response = array();

        $response['opencalais'] = $responseCalais;

        $response['zemanta'] = $responseZemanta;
        
        $response['wikipedia'] = $responseWikipedia;
        //return $this->insertUserQuery($response);
    }

    public function printUserInterests() {
        $result = "";
       
        foreach ($this->userInterestst as $topic) {
            $result .= "<p>" . $topic['name'];
            $result .= ": " . $topic['weight'] . "</p>";
            if (isset($topic['keywords']) && $topic['keywords'] != null) {
                $result .= "<ul>";

                foreach ($topic['keywords'] as $keyword) {

                    $result .= "<li>" . $keyword['name'] . "</li>";
                }
                $result .= "</ul>";
            }
        }
        $result .= "</table>";
        return $result;
    }

    private function callOpenCalaisAPI($url) {
        $config_xml = $this->loadConfigFile();
        $apikey = (string) $config_xml->apis->opencalais->apikey;

        $oc = new OpenCalais($apikey, "bgotthart");
        $oc->outputFormat = "XML/RDF";
        $content = file_get_contents($url);
        $entities = $oc->parse($content);

        return $entities;
    }

    private function loadConfigFile() {
        
        return simplexml_load_file("../config/config.xml");
    }

    private function callZemantaAPI($url) {
        $config_xml = $this->loadConfigFile();

        $apikey = (string) $config_xml->apis->zemanta->apikey;

        $zemanta = new Zemanta($apikey, 1, 0, 0);
        $content = file_get_contents($url);
        $entities = $zemanta->parse($content);
        return $entities;
    }
    public function callMediaWikipediaAPI($terms) {
        print_r($terms);
        
        $wikiAPI = new Wikipedia();
        
        $response = $wikiAPI->callAPIWithData($terms);
        
        echo($response);
        
    }
    
    public function callDBpedia(){
        $keyword = "Volleyball";
        
        $categories = $this->DB_store->getCategories($keyword);
  
        
  
      

    }
    function getFeed($feed_url) {
	
	$content = file_get_contents($feed_url);
	
	$x = new SimpleXmlElement($content);
        $urlArray = array();

        foreach($x->channel->item as $entry) {
            array_push($urlArray,$entry->link);
		
        }
        
        return $urlArray;
   }

    /*
      private function callAlchemyAPI($configXML, $url){
      $apikey = (string)$configXML->apis->alchemy->apikey;

      $alchemyObj = new AlchemyAPI();
      $alchemyObj->setAPIKey($apikey);

      //$content = file_get_contents("../data/example.html");
      //$result = $alchemyObj ->URLGetCategory("http://www.cnn.com/2011/09/28/us/massachusetts-pentagon-plot-arrest/index.html?hpt=hp_t1", 'json');

      $xml = $alchemyObj->URLGetCategory($url, AlchemyAPI::XML_OUTPUT_MODE);
      $xml = new SimpleXMLElement($xml);
      $response['categories'] = $xml->xpath("//category");

      $xml = $alchemyObj->URLGetRankedNamedEntities($url);
      $xml = new SimpleXMLElement($xml);
      $response['entities'] = $xml->xpath("//entities");

      $xml = $alchemyObj->URLGetRankedKeywords($url, AlchemyAPI::XML_OUTPUT_MODE);
      $xml = new SimpleXMLElement($xml);
      $response['keywords'] = $xml->xpath("//keyword");

      print_r($response);

      return $response;

      }

     */
}

?>
