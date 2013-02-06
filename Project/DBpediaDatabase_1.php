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
    private $openList;
    private $closedList;

    public function __construct() {
        $this->initMainTopicClassification();

        $this->blacklist_topics = array("_by_", "WikiProject_", "Wikipedia_categories", "Categories_by_", "Categories_of_", "Article_Feedback_Pilot", "Living_people", "Category:Categories_for_renaming", "Category:Articles", "Category:Fundamental", "Category:Concepts", "Category:Wikipedia_articles_with_missing_information", "Category:Wikipedia_maintenance", "Category:Chronology");
    }

    public function getCategories($keywords) {

        $this->graph = array();
        $this->closedList = array();
        $this->openList = array();

        $concepts = array();

        foreach ($keywords as $keyword) {
            
            if (!key_exists($keyword, $concepts)) {
                $concepts[$keyword] = array();
                $concepts[$keyword] = $keyword;
            }



            $categories = $this->getCategoriesForKeyword($keyword);

    
            if (count($categories) > 0) {
                foreach ($categories as $category) {
                    array_push($concepts[$keyword], $category);
                }
            }
        }

        return $concepts;
    }

    public function calculateSimilarity($term1, $term2) {
        $similarity = $this->dbpediaSimilarityCheck($term1, $term2);
        return $similarity;
    }

    private function getBestNode($keyword, $categoriesArray) {
        $bestSimilarity = 0.0;

        $bestNode = "";
        foreach ($categoriesArray as $category) {

            if (in_array($category, $this->main_topic_classification)) {
                $bestNode = $category;
                return $bestNode;
            }
            $similarity = (float)$this->dbpediaSimilarityCheck($keyword, $category);

            if ($bestSimilarity < $similarity) {
                $bestNode = $category;
                $bestSimilarity = $similarity;
            }
        }

        return $bestNode;
    }

    public function getCategoriesForKeyword($keyword) {

        $categoriesArray = array();
        try {
            $query = 'SELECT DISTINCT * WHERE { <http://dbpedia.org/resource/' . $keyword . '> <http://purl.org/dc/terms/subject> ?x . }';
            $searchUrl = 'http://dbpedia.org/sparql?query=' . urlencode($query) . "&format=json";

            $response = json_decode($this->sendExternalRequest($searchUrl), true);

            array_push($this->closedList, $keyword);

            foreach ($response['results']['bindings'] as $entry) {
                $category = $entry['x']['value'];

                array_push($categoriesArray, $category);
                
                if (!key_exists($category, $this->openList)) {
                    $this->openList[$category] = array();
                }
                $this->openList[$category] = $category;
            }

            
            if (count($categoriesArray) > 0) {
                $bestNode = $this->getBestNode($keyword, $categoriesArray);
                $this->addToGraph($bestNode);
                $this->iterative_search_for_categories($bestNode, $keyword);
                               
                echo("graph: ");
                print_r($this->graph);
                
                die();
                return ($this->graph);
            } else {
                return null;
            }
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function addToGraph($node) {
        if (!key_exists($node, $this->graph)) {
            $this->graph[$node] = array();
        }

        $this->graph[$node] = $node;
    }

    private function iterative_search_for_categories($node, $keyword) {

        if (count($node) > 0) {

            if (key_exists($node, $this->openList)) {
                unset($this->openList[$node]);
            }

            array_push($this->closedList, $node);


            if (in_array($node, $this->main_topic_classification)) {
                //echo("\n FOUND GOAL in node: " . $node);
                $this->graph[$node] = $node;
                return;
            }

            $children = $this->getCategoriesOfCategory($node);

            if (count($children) > 0) {
                foreach ($children as $child) {

                    //check if node already expanded (in closed List)
                    //!!!!!!!and node is not in open list with lower similarity TODO!!!!!!!!!!
                    if (!key_exists($child, $this->closedList) || !in_array($child, $this->closedList)) {

                        //add similarity heuristic 
                        if (!key_exists($child, $this->openList)) {
                            $this->openList[$child] = array();
                        }
                        $this->openList[$child] = $child;
                    }
                }
            }

            if (count($children) > 0) {

                $bestNode = $this->getBestNode($keyword, $children);

                if ($bestNode == null) {
                    echo("bestnode null");
                    print_r($children);
                    echo("node");
                        print_r($node);
                   
                        
       
                $children = array_values($this->openList);
                $bestNode = $this->getBestNode($keyword, $children);

                $this->iterative_search_for_categories($bestNode, $keyword);
                }

                $this->addToGraph($node);

                //array_push($this->graph, $node);
                $this->iterative_search_for_categories($bestNode, $keyword);
            } else {
                echo("no children");
                print_r($node);
                if (in_array($node, $this->openList)) {
                    unset($this->openList[$node]);
                }

                if (!key_exists($node, $this->graph)) {
                    unset($this->graph[$node]);
                }

                $children = array_values($this->openList);
                $bestNode = $this->getBestNode($keyword, $children);

                $this->iterative_search_for_categories($bestNode, $keyword);

                return;
            }
        }
    }

    public function getCategoriesOfCategory($category) {
        $category = $this->prepareTermNameForDBpedia($category);

        try {
            $query = 'SELECT DISTINCT ?x WHERE { <http://dbpedia.org/resource/Category:' . $category . '> <http://www.w3.org/2004/02/skos/core#broader> ?x . }';

            $searchUrl = 'http://dbpedia.org/sparql?query=' . urlencode($query) . "&format=json";

            $response = json_decode($this->sendExternalRequest($searchUrl), true);

            $categoriesArray = array();

            foreach ($response['results']['bindings'] as $entry) {
                $child = $entry['x']['value'];
                $in_blacklist = false;
                foreach ($this->blacklist_topics as $blacklist) {

                    if (strpos($child, $blacklist) != 0 || strpos($child, $blacklist) != null) {

                        $in_blacklist = true;
                    }
                }

                if (in_array($child, $this->blacklist_topics)) {
                    $in_blacklist = true;
                }
                if (!$in_blacklist && !in_array($child, $this->closedList)) {
                    array_push($categoriesArray, $child);
                }
            }

            return $categoriesArray;
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function get3LevelCategories($term, $level) {


        try {
            if ($level == 1) {
                $query = "
                SELECT DISTINCT * WHERE {
                       <http://dbpedia.org/resource/" . $term . "> <http://purl.org/dc/terms/subject> ?a .
                        ?a <http://www.w3.org/2004/02/skos/core#broader> ?b  .
                        ?b <http://www.w3.org/2004/02/skos/core#broader> ?c .
                      }";
            } else {
                $query = "
                SELECT DISTINCT * WHERE {
                       <http://dbpedia.org/resource/Category:" . $term . ">
                        <http://www.w3.org/2004/02/skos/core#broader> ?a .
                        ?a <http://www.w3.org/2004/02/skos/core#broader> ?b  .
                        ?b <http://www.w3.org/2004/02/skos/core#broader> ?c .
                      }";
            }
            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query) . "&format=json";

            $categories = json_decode($this->sendExternalRequest($searchUrl), true);
            $result = array();


            foreach ($categories['results']['bindings'] as $entry) {
                $categoryA = $entry['a']['value'];
                $categoryB = $entry['b']['value'];
                $categoryC = $entry['c']['value'];

                if (!in_array($categoryA, $result)) {
                    array_push($result, $categoryA);
                }
                if (!in_array($categoryB, $result)) {
                    array_push($result, $categoryB);
                }
                if (!in_array($categoryC, $result)) {
                    array_push($result, $categoryC);
                }
            }


            return $result;
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function dbpediaSimilarityCheck($termA, $termB) {

        $termA = $this->cleaningCategoryTerm($termA);

        $termB = $this->cleaningCategoryTerm($termB);
        if ($termA == $termB)
            return 1;

        $aLinksA = $this->get3LevelCategories($termA, 1);
        $aLinksB = $this->get3LevelCategories($termB, 2);

        $intersection = 0;

        $combindedCats = array();
        for ($i = 0; $i < count($aLinksB); $i++) {

            if (in_array($aLinksB[$i], $aLinksA) && !in_array($aLinksB[$i], $combindedCats)) {

                array_push($combindedCats, $aLinksB[$i]);
                $intersection++;
            }
        }
        
        for ($i = 0; $i < count($aLinksA); $i++) {

            if (in_array($aLinksA[$i], $aLinksB) && !in_array($aLinksA[$i], $combindedCats)) {
                array_push($combindedCats, $aLinksA[$i]);
                $intersection++;
            }
        }

         $googleMeasure = 0.0;
        if($intersection > 0){
            //calculate google distance inspired measure
           

            $a = log(count($aLinksA));
            $b = log(count($aLinksB));
            $ab = log($intersection);

            $googleMeasure = (max($a, $b) - $ab) / (4159085 - min($a, $b));
        }
        

        if ($googleMeasure == null)
            return 0 ;
		
        if ($googleMeasure >= 1)
            return 0 ;
	        
        return 1-$googleMeasure ;
    }

    private function initMainTopicClassification() {

        $this->main_topic_classification = array();

        try {
            $query = 'SELECT DISTINCT * WHERE {
                ?x
                <http://www.w3.org/2004/02/skos/core#broader>
                <http://dbpedia.org/resource/Category:Main_topic_classifications>
                }';

            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query) . "&format=json";

            $mainCategories = json_decode($this->sendExternalRequest($searchUrl), true);

            foreach ($mainCategories['results']['bindings'] as $mainCategory) {

                array_push($this->main_topic_classification, urldecode($mainCategory['x']['value']));
            }
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function prepareTermNameForDBpedia($term) {


        if (strstr($term, 'Category:') !== false) {
            $termArray = explode("Category:", $term);
            $category = $termArray[1];
        } else {
            $category = $term;
        }

        if (strstr($category, " ") !== false) {
            $category = str_replace(" ", "_", $term);
        }

        return $category;
    }

    private function cleaningTermsForWikipediaMining($term) {
        $categoryName = $this->cleaningCategoryTerm($term);

        if (strstr($categoryName, "_") !== false) {
            $categoryName = str_replace("_", " ", $categoryName);
        }

        return $categoryName;
    }

    private function cleaningCategoryTerm($term) {
        if (strstr($term, 'Category:') !== false) {
            $termArray = explode("Category:", $term);
            $categoryName = $termArray[1];
        } else {
            $categoryName = $term;
        }



        return $categoryName;
    }

    public function sendExternalRequest($url) {

        // Initialize cURL request and set parameters        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, "Testing for http://stackoverflow.com/questions/8956331/how-to-get-results-from-the-wikipedia-api-with-php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_TIMEOUT, 100);
        $result = curl_exec($ch);

        if (!$result) {
            echo('cURL Error: ' . curl_error($ch));
            die();
        }

        curl_close($ch);

        return $result;
    }

}

?>
