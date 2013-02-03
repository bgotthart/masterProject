<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DBpedia_Database
 *
 * @author biancagotthart
 */

require_once("../APIs/WikipediaMiningAPI.class.php");

class DBpedia_DatabaseClass extends DatabaseClass {

    private $main_topic_classification;
    private $blacklist_topics;
    private $limit = 5;
    private $depth = 0;
    private $max_depth = 5;
    private $graph = array();

    private $wikipediaAPI;
    
    public function __construct() {
        $this->initMainTopicClassification();
        $this->blacklist_topics = array("Wikipedia_categories", "_in_the", "Categories_by_", "Categories_of_", "Article_Feedback_Pilot", "Living_people", "Category:Categories_for_renaming", "Category:Articles", "Category:Fundamental", "Category:Concepts", "_by_", "Category:Wikipedia_articles_with_missing_information", "Category:Wikipedia_maintenance", "Category:Chronology");
        $this->wikipediaAPI = new WikipediaMiningAPI();
        
    }

   /* public function calculateSimilarity($term1, $term2) {


        $similarity = $this->wikipediaMiningSimilarity($term1, $term2);
        //$this->googleSimilarityDistance();
        //$this->dbpediaSimilarityCheck();

        //echo("Similarity between " . $term1 . " and " . $term2 . ": " . $similarity);
        return $similarity;
    }
*/
    private function cleaningTerm($term) {
        if (strstr($term, 'Category:') !== false) {
            $termArray = explode("Category:", $term);
            $categoryName = $termArray[1];
        } else {
            $categoryName = $term;
        }

        if (strstr($categoryName, "_") !== false) {
            $categoryName = str_replace("_", "%20", $categoryName);
        }

        return $categoryName;
    }

    
    private function wikipediaMiningSimilarity($term1, $term2) {

        
        $similarity = 0.0;

        $term1 = $this->wikipediaAPI->searchForTerm($term1);
        $term2 = $this->wikipediaAPI->searchForTerm($term2);
        
        $categoryName1 = urlencode($term1);
        $categoryName2 = urlencode($term2);
        $url = "http://wdm.cs.waikato.ac.nz/services/compare?term1=" . $categoryName1 . "&term2=" . $categoryName2 . "&responseFormat=xml&disambiguationDetails&connections";

        $response = $this->sendExternalRequest($url);

        $xmlobj = new SimpleXMLElement($response);
        
       
        $returnObject = array();

        $returnObject['term2'] = $term2;
        
        /*
        if(($xmlobj->attributes()->error) != null){
               
            $unknownTerm = (string)$xmlobj->attributes()->unknownTerm;
            $term = (string)$this->wikipediaAPI->searchForTerm($unknownTerm);
 
            $term = urlencode($term);

            if($term == null){
                echo("\n ERROR NO TERM FOUND!!! \n");
                print_r($unknownTerm);
                return array("error" => "unknown Term", "term" => $unknownTerm);
                
            }
                       
        }*/
        
        $similarity = (float) $xmlobj->attributes()->relatedness;

        $connections = $xmlobj->xpath("//connection");

        foreach ($connections as $connection) {
            $connectionTitle = (string)$connection->attributes()->title;
            $relatednessTerm1 = (string)$connection->attributes()->relatedness1;
            $relatednessTerm2 = (string)$connection->attributes()->relatedness2;
        }
                    
        $returnObject['similarity'] = $similarity;
     
        return $returnObject;
    }


    private function getBestNode($keyword, $categoriesArray) {
        $bestSimilarity = 0.0;

        $bestNode = "";
        foreach ($categoriesArray as $category) {
            $similarityObject = $this->wikipediaMiningSimilarity($keyword, $category);
     
            //$similarity = $this->calculateSimilarity($keyword, $category);
            if ($bestSimilarity < $similarityObject['similarity']) {        
                $bestNode = $similarityObject['term2'];
                
            }
        }

        
        return $bestNode;
    }

  
    
    
    
    public function callWikipediaMiningForKeywordInformation($keyword) {


        try {
            
            //$categories = $this->callDBpediaWithSparql($keyword);
            
            $categories = $this->wikipediaAPI->getArticleDetails($keyword);

            $this->graph = array();

            $bestNode = $this->getBestNode($keyword, $categories);

            
            $this->iterative_deepening_depth_first_search($bestNode, null, $keyword);
            
            echo("...GRAPH...");
            print_r($this->graph);
            die("getCategoriesOfArticle");
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }
    private function addToGraph($parent, $nodes) {
        if (!array_key_exists($parent, $this->graph)) {
            $this->graph[$parent] = $nodes;
        }
    }

    private function iterative_deepening_depth_first_search($node, $parent, $keyword) {
        
        if (count($node) > 0) {

            if (in_array($node, $this->main_topic_classification)) {
                echo("\n FOUND GOAL in node: " . $node);
                array_push($this->graph, $node);

                return;
            }

            //$children = $this->getCategoriesOfCategory($node);
            $children = $this->wikipediaAPI->getCategoryDetails($node);
            
            if (count($children) > 0) {
                $bestNode = $this->getBestNode($keyword, $children);

                if ($bestNode == null) {
                    echo("ERROR: no best node found in ");
                    print_r($children);
                    
                    //zurück gehen im baum
                    
                    return;
                }

                if (in_array($bestNode, $this->main_topic_classification)) {
                    echo("\n FOUND GOAL in child: " . $bestNode);
                    array_push($this->graph, $bestNode);
                    return;
                }

                array_push($this->graph, $bestNode);
                
                $this->iterative_deepening_depth_first_search($bestNode, $node, $keyword);
            }else{
                echo("!!!children null!!!!");
                print_r($node);
                return;
            }
        }


                
            
        
    }

      
    private function googleSimilarityDistance() {
        
       // $term = urlencode($term);
        $url = "https://www.googleapis.com/customsearch/v1?key=AIzaSyC8KhqZYkRXdKhaUvz67B7IGRzMjzo5lsc&cx=013707050272249182185:ldylhbskn0a&q=iPhone&alt=json";

        $response = $this->sendExternalRequest($url);

        $node1Result = json_decode($response);

        print_r($node1Result);
    }

    private function dbpediaSimilarityCheck() {

        $node1 = "http://dbpedia.org/resource/Category:CNET_Networks";
        $node2 = "CNET";

        $similarity = 0;

        //todo unterscheidung artikel u kategorie
        $node1Categories = $this->getCategoriesOfCategory($node1);


        /* $node1Category = array();
          foreach($node1Categories as $node){
          array_push($$node1Category, $node);
          }
          $node1Categories = $node1Category;
         * 
         */
        $node2Categories = $this->getCategoriesOfArticle($node2);


        for ($i = 0; $i < 5; $i++) {

            $categories = $this->getCategoriesOfCategory($node1Categories[$i]);

            foreach ($categories as $category) {
                array_push($node1Categories, $category);

                $subcategories = $this->getCategoriesOfCategory($category);

                foreach ($subcategories as $subcategory) {
                    array_push($node1Categories, $subcategory);
                }
            }
        }


        for ($i = 0; $i < 5; $i++) {

            $categories = $this->getCategoriesOfCategory($node2Categories[$i]);

            foreach ($categories as $category) {
                array_push($node2Categories, $category);

                $subcategories = $this->getCategoriesOfCategory($category);

                foreach ($subcategories as $subcategory) {
                    array_push($node2Categories, $subcategory);
                }
            }
        }

        $combindedCats = array();
        for ($i = 0; $i < count($node1Categories); $i++) {

            if (in_array($node1Categories[$i], $node2Categories) && !in_array($node1Categories[$i], $combindedCats)) {
                array_push($combindedCats, $node1Categories[$i]);
                $similarity++;
            }
        }

        for ($i = 0; $i < count($node2Categories); $i++) {

            if (in_array($node2Categories[$i], $node1Categories) && !in_array($node2Categories[$i], $combindedCats)) {

                array_push($combindedCats, $node2Categories[$i]);
                $similarity++;
            }
        }

        print_r($combindedCats);
        print_r($similarity / (count($node1Categories) + count($node2Categories)));
    }

    private function lookForDBpediaTerm($term) {
        /*
         * SELECT *
          WHERE
          {

          <http://dbpedia.org/resource/Programming_(disambiguation)> <http://dbpedia.org/ontology/wikiPageDisambiguates> ?y .

          <http://dbpedia.org/resource/Programming> <http://dbpedia.org/ontology/wikiPageRedirects> ?y .

          }
         */
    }

    public function getCategoriesOfArticle($keyword) {


        try {
            $query = '
                    SELECT ?x WHERE {
                        <http://dbpedia.org/resource/' . $keyword . '>
                        <http://purl.org/dc/terms/subject>
                        ?x
                      }
                ';

            
            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query) . "&format=json";

            $categories = json_decode($this->sendExternalRequest($searchUrl), true);
            $result = array();
            foreach ($categories['results']['bindings'] as $entry) {
                $category = $entry['x']['value'];

                array_push($result, $category);
            }
            return $result;
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }
    public function callDBpediaWithSparql($keyword) {


        try {
            $query = '
                    SELECT ?x WHERE {
                        <http://dbpedia.org/resource/' . $keyword . '>
                        <http://purl.org/dc/terms/subject>
                        ?x
                      }
                ';

            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query) . "&format=json";

            $categories = json_decode($this->sendExternalRequest($searchUrl), true);
            $this->graph = array();

            $categoriesArray = $categories['results']['bindings'];

            $categoriesArray = array();


            foreach ($categories['results']['bindings'] as $entry) {
                $category = $entry['x']['value'];

                array_push($categoriesArray, $category);
                
                //$array = array($category);
                // $this->iterative_deepening_depth_first_search($array, null, $keyword);
            }

            $bestNode = $this->getBestNode($keyword, $categoriesArray);

            $this->iterative_deepening_depth_first_search($bestNode, null, $keyword);
            
            echo("...GRAPH...");
            print_r($this->graph);
            die("getCategoriesOfArticle");
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    public function getCategoriesOfCategory($category) {
        
        $categoryObject = explode("Category:", $category);

        try {
            $query = '
                    SELECT ?x WHERE {
                        <http://dbpedia.org/resource/Category:' . $categoryObject[1] . '>
                        <http://www.w3.org/2004/02/skos/core#broader>
                        ?x
                      }
                ';

            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query) . "&format=json";

            $categories = json_decode($this->sendExternalRequest($searchUrl), true);

            $categoriesArray = array();
            foreach ($categories['results']['bindings'] as $categoryObj) {
                $child = $categoryObj['x']['value'];
                $in_blacklist = false;
                foreach ($this->blacklist_topics as $blacklist) {

                    if (strpos($child, $blacklist) != 0 || strpos($child, $blacklist) != null) {

                        $in_blacklist = true;
                    }
                }

                if (in_array($child, $this->blacklist_topics)) {
                    $in_blacklist = true;
                }
                if (!$in_blacklist) {
                    array_push($categoriesArray, $child);
                }
            }


            return $categoriesArray;
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function initMainTopicClassification() {

        $this->main_topic_classification = array();

        try {
            $query = '
                    SELECT ?x WHERE {
                        ?x
                        <http://www.w3.org/2004/02/skos/core#broader>
                        <http://dbpedia.org/resource/Category:Main_topic_classifications>
                      }
                ';

            $searchUrl = "http://dbpedia.org/sparql?query=".urlencode($query)."&format=json";

            $mainCategories = json_decode($this->sendExternalRequest($searchUrl), true);

            foreach ($mainCategories['results']['bindings'] as $mainCategory) {
                array_push($this->main_topic_classification, $mainCategory['x']['value']);
            }
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }

    }

    public function sendExternalRequest($url) {
                
        // is curl installed?
        if (!function_exists('curl_init')) {
            die('CURL is not installed!');
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        echo curl_error($ch);

        curl_close($ch);

        return $response;
    }
    
   
}

?>
