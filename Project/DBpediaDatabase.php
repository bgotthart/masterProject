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
        $this->wikipediaAPI = new WikipediaMiningAPI();
    }

    public function getCategories($keywords) {

        
        $this->graph = array();
        $this->closedList = array();
        $this->openList = array();

        $concepts = array();


        foreach ($keywords as $keyword) {

            if (!key_exists($keyword, $concepts)) {
                $concepts[$keyword] = array();
            }
            $categories = array();

            $categories = $this->getCategoriesForKeyword($keyword);

            if (count($categories) > 0) {
                array_push($categories, $keyword);
                foreach ($categories as $category) {
                    array_push($categories, $category);
                }
            }
            $concepts[$keyword] = $categories;
        }

        return $concepts;
    }

    private function getBestNode($keyword, $categoriesArray) {
        $bestSimilarity = 0.0;

        $bestNode = "";
        foreach ($categoriesArray as $category) {

            if (in_array($category, $this->main_topic_classification)) {
                $bestNode = $category;
                return $bestNode;
            }
            $cleanedTerm = $this->cleaningCategoryTerm($category);
            if($keyword == $cleanedTerm){
                $bestNode = $category;
                return $bestNode;
            }
                
            $similarity = $this->dbpediaSimilarityCheck($keyword, $category);

            if ($bestSimilarity < $similarity) {
                $bestNode = $category;
                $bestSimilarity = $similarity;
            }
        }

        return $bestNode;
    }

    private function lookForDBpediaTerm($term) {
        $term = $this->prepareTermNameForDBpedia($term);
        try {
            $query = 'SELECT DISTINCT * WHERE { { { <http://dbpedia.org/resource/' . $term . '_(disambiguation)> <http://dbpedia.org/ontology/wikiPageRedirects> ?y . }
                UNION
                {
                <http://dbpedia.org/resource/' . $term . '_(disambiguation)> <http://dbpedia.org/ontology/wikiPageDisambiguates> ?x . }
                }
                UNION{
                    <http://dbpedia.org/resource/Android> <http://dbpedia.org/ontology/wikiPageRedirects> ?z .
                    }
                    }';

            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query) . "&format=xml";

            $response = $this->sendExternalRequest($searchUrl);

            $xmlobj = new SimpleXMLElement($response);


            $result = array();
            foreach ($xmlobj->results as $obj) {
                $array = (array) $obj;
                if (!key_exists("result", $array)) {
                    return null;
                }
                foreach ($array["result"] as $a) {
                    $child = (string) $a->uri;
                    array_push($result, $child);
                }
            }

            return $result[0];
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    public function getCategoriesForKeyword($keyword) {

        $categoriesArray = array();
        try {
            $query = 'SELECT DISTINCT * WHERE {
                <http://dbpedia.org/resource/' . $keyword . '>
                    <http://purl.org/dc/terms/subject>
                    ?x
                    }';

            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query);

            $response = $this->sendExternalRequest($searchUrl);

            $xmlobj = new SimpleXMLElement($response);

            array_push($this->closedList, $keyword);

            foreach ($xmlobj->results as $obj) {
                $array = (array) $obj;
                if (isset($array["result"])) {
                    foreach ($array["result"] as $a) {
                        $in_blacklist = false;
                        
                        $child = (string) $a->binding->uri;
                        
                        foreach ($this->blacklist_topics as $blacklist) {

                            if (strpos($child, $blacklist) != 0 || strpos($child, $blacklist) != null) {

                                $in_blacklist = true;
                                break;
                            }
                        }

                        if (in_array($child, $this->blacklist_topics)) {
                            $in_blacklist = true;
                        }
                        if (!$in_blacklist && !in_array($child, $this->closedList)) {
                            array_push($categoriesArray, $child);

                            $this->openList[$child] = array();

                            $this->openList[$child] = $child;
                        }
                        
                    }
                }
            }
/* //TODO: disambiguation: what if keyword not found
            if (count($categoriesArray) == 0) {

                $newTerm = $this->lookForDBpediaTerm($keyword);
                if (strlen($newTerm) == 0) {
                    $this->addToGraph($keyword);
                    return $this->graph;
                }
                $this->getCategoriesForKeyword($keyword);
            }
*/

            $this->addToGraph("http://dbpedia.org/resource/".$keyword);

            $bestNode = $this->getBestNode($keyword, $categoriesArray);
            $this->iterative_search_for_categories($bestNode, $keyword);
            
            print_r($this->graph);
            
            die();
            return $this->graph;
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
                $this->addToGraph($node);

                return;
            }


            $children = $this->getCategoriesOfCategory($node);

            if (count($children) > 0) {
                
                
                
                foreach ($children as $child) {

                    if (!key_exists($child, $this->closedList) || !in_array($child, $this->closedList)) {

                        //add similarity heuristic 
                        if (!key_exists($child, $this->openList)) {
                            $this->openList[$child] = array();
                        }
                        $this->openList[$child] = $child;
                    }
                }
           
                
                $bestNode = $this->getBestNode($keyword, $children);

                if ($bestNode == null) {
                    $children = array_values($this->openList);
                    $bestNode = $this->getBestNode($keyword, $children);

                    $this->addToGraph($node);
                    $this->iterative_search_for_categories($bestNode, $keyword);
                }else{
                    $this->addToGraph($node);
                    $this->iterative_search_for_categories($bestNode, $keyword);
                }
                
            } else {

                if (key_exists($node, $this->graph)) {
                    unset($this->graph[$node]);
                }

                $children = array_values($this->openList);
                $bestNode = $this->getBestNode($keyword, $children);
                
                $this->addToGraph($bestNode);
                
                $this->iterative_search_for_categories($bestNode, $keyword);

                return;
            }
        }
    }

    public function getCategoriesOfCategory($category) {


        $category = $this->prepareTermNameForDBpedia($category);

        try {
            $query = 'SELECT DISTINCT ?x WHERE { <http://dbpedia.org/resource/Category:' . $category . '> <http://www.w3.org/2004/02/skos/core#broader> ?x . }';

            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query) . "&format=xml";

            $response = $this->sendExternalRequest($searchUrl);

            $xmlobj = new SimpleXMLElement($response);


            $categoriesArray = array();
            foreach ($xmlobj->results as $obj) {
                $array = (array) $obj;
                if (!key_exists("result", $array)) {
                    return null;
                }
                foreach ($array["result"] as $a) {
                    $child = (string) $a->binding->uri;

                    if ($child == null) {
                        $child = (string) $a->uri;
                    }
                    $in_blacklist = false;

                    foreach ($this->blacklist_topics as $blacklist) {

                        if (strpos($child, $blacklist) != 0 || strpos($child, $blacklist) != null) {

                            $in_blacklist = true;
                            break;
                        }
                    }

                    if (in_array($child, $this->blacklist_topics)) {
                        $in_blacklist = true;
                    }
                    if (!$in_blacklist && !in_array($child, $this->closedList)) {
                        array_push($categoriesArray, $child);
                    }
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
                        ?c <http://www.w3.org/2004/02/skos/core#broader> ?d .
                      }";
            } else {
                $query = "
                SELECT DISTINCT * WHERE {
                       <http://dbpedia.org/resource/Category:" . $term . ">
                        <http://www.w3.org/2004/02/skos/core#broader> ?a .
                        ?a <http://www.w3.org/2004/02/skos/core#broader> ?b  .
                        ?b <http://www.w3.org/2004/02/skos/core#broader> ?c .
                        ?c <http://www.w3.org/2004/02/skos/core#broader> ?d .
                      }";
            }


            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query) . "&format=json";

            $categories = json_decode($this->sendExternalRequest($searchUrl), true);
            $result = array();


            foreach ($categories['results']['bindings'] as $entry) {
                $categoryA = $entry['a']['value'];
                $categoryB = $entry['b']['value'];
                $categoryC = $entry['c']['value'];
                $categoryD = $entry['d']['value'];
                if (!in_array($categoryA, $result)) {
                    array_push($result, $categoryA);
                }
                if (!in_array($categoryB, $result)) {
                    array_push($result, $categoryB);
                }
                if (!in_array($categoryC, $result)) {
                    array_push($result, $categoryC);
                }
                if (!in_array($categoryD, $result)) {
                    array_push($result, $categoryD);
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
        for ($i = 0; $i < count($aLinksA); $i++) {

            if (in_array($aLinksA[$i], $aLinksB) && !in_array($aLinksA[$i], $combindedCats)) {
                array_push($combindedCats, $aLinksA[$i]);
                $intersection++;
            }
        }

        for ($i = 0; $i < count($aLinksB); $i++) {

            if (in_array($aLinksB[$i], $aLinksA) && !in_array($aLinksB[$i], $combindedCats)) {

                array_push($combindedCats, $aLinksB[$i]);
                $intersection++;
            }
        }

        //calculate google distance inspired measure
        $googleMeasure = 0.0;
        $union = (count($aLinksA) + count($aLinksB));
        
       

        $a = log(count($aLinksA));
        $b = log(count($aLinksB));
        $ab = log($intersection);

        $googleMeasure1 = (max($a, $b) - $ab) / ($union - min($a, $b));
        $googleMeasure11 = 1-((max($a, $b) - $ab) / ($union - min($a, $b)));
        
        $googleMeasure2 = (max($a, $b) - $ab) / (29380711 - min($a, $b));
        $googleMeasure = 1 - ((max($a, $b) - $ab) / (29380711 - min($a, $b)));

        $googleMeasure3 = ($union - count($combindedCats)) / $union;

        $googleMeasure31 = 1 - (($union - count($combindedCats)) / $union);

        if(is_nan($googleMeasure) || is_infinite($googleMeasure) || $googleMeasure < 0 || $googleMeasure > 1){
            return 0;
        }
       // $googleMeasure = 1-$googleMeasure;
        
        return $googleMeasure;
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
