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
class DBpedia_DatabaseClass extends DatabaseClass {

    public $main_topic_classification;
    private $blacklist_topics;
    private $limit = 5;
    private $depth = 0;
    private $max_depth = 5;
    private $graph = array();
    private $wikipediaAPI;
    private $openList;
    private $closedList;
    private $categoriesOfKeyword;

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
            }
            $categories = array();

            $categories = $this->getCategoriesForKeyword($keyword);

            if (count($categories) > 0) {
                foreach ($categories as $category) {
                    array_push($concepts[$keyword], $category);
                }
            }
        }


        return $concepts;
    }

    private function getBestNode($keyword, $categoriesArray) {
        $bestSimilarity = 0.0;

        $bestNode = "";
        foreach ($categoriesArray as $category) {

            $cleanedTerm = $this->cleaningCategoryTerm($category);
            if (in_array($category, $this->main_topic_classification)) {
                $term = new TermItem($category, $cleanedTerm, 1, true);

                //$bestNode = $category;
                $bestNode = $term;
                return $bestNode;
            }

            $similarity = $this->dbpediaSimilarityCheck($keyword, $category);

            //if keyword has own category name
            if ($keyword == $cleanedTerm) {
                $term = new TermItem($category, $cleanedTerm, $similarity);

                //$bestNode = $category;
                $bestNode = $term;
                return $bestNode;
            }
            
            if ($similarity == 1) {
                $bestNode = $category;
                return $bestNode;
            }
            if ($bestSimilarity < $similarity) {
                $term = new TermItem($category, $cleanedTerm, $similarity);

                //$bestNode = $category;
                $bestNode = $term;
                $bestSimilarity = $similarity;
            }
        }

        return $bestNode;
    }

    public function getCategoriesForKeyword($keyword) {

        $keywordConcept = '';

        $this->graph = array();

        $this->categoriesOfKeyword = $this->get3LevelCategories($keyword, 1);

        $keywordConcept = "http://dbpedia.org/resource/" . $keyword;


        $categoriesArray = array();
        try {

            $select = 'SELECT DISTINCT * WHERE {
                            <http://dbpedia.org/resource/' . $keyword . '> <http://purl.org/dc/terms/subject> ?x
                                }';

            $response = $this->sendExternalRequestWithSelect($select);

            if($response == null){
                return null;
            }
            $child = '';
            foreach ($response->result as $item) {
                $child = (string) $item->binding->uri;
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

                    $this->openList[$child] = array();

                    $this->openList[$child] = $child;
                }
            }
            $term = new TermItem($keywordConcept, $keyword, 0);
            $this->addToGraph($term);

            $bestNode = $this->getBestNode($keyword, $categoriesArray);
            $this->iterative_search_for_categories($bestNode, $keyword);
            return $this->graph;
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function addToGraph($node) {
        $uri = $node->getUri();
        if (!key_exists($uri, $this->graph)) {
            $this->graph[$uri] = array();
        }

        $this->graph[$uri] = $node;
    }

    private function iterative_search_for_categories($node, $keyword) {


            $uri = $node->getUri();
            if (key_exists($uri, $this->openList)) {
                unset($this->openList[$uri]);
            }

            array_push($this->closedList, $uri);

            if (in_array($uri, $this->main_topic_classification)) {
                $this->addToGraph($node);
                return;
            }


            $children = $this->getCategoriesOfCategory($uri);

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

                    if ($bestNode == null) {
                        return $this->graph;
                    }
                    $this->addToGraph($node);
                    $this->iterative_search_for_categories($bestNode, $keyword);
                } else {
                    $this->addToGraph($node);
                    $this->iterative_search_for_categories($bestNode, $keyword);
                }
            } else {
                if (key_exists($uri, $this->graph)) {
                    unset($this->graph[$uri]);
                }

                $children = array_values($this->openList);
                $bestNode = $this->getBestNode($keyword, $children);

                if ($bestNode == null) {
                    return $this->graph;
                }

                $this->addToGraph($bestNode);

                $this->iterative_search_for_categories($bestNode, $keyword);
            }
        
    }

    public function getCategoriesOfCategory($category) {


        $category = $this->prepareTermNameForDBpedia($category);

        try {
            $select = 'SELECT DISTINCT ?x WHERE { <http://dbpedia.org/resource/Category:' . $category . '> <http://www.w3.org/2004/02/skos/core#broader> ?x . }';

            $response = $this->sendExternalRequestWithSelect($select);
            if($response == null){
                return null;
            }
            
            $child = '';
            $results = array();
            foreach ($response->result as $item) {
                $child = (string) $item->binding->uri;
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
                    array_push($results, $child);
                }
            }

            return $results;
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function get3LevelCategories($term, $level) {

        try {
            if ($level == 1) {
                $select = 'SELECT DISTINCT * WHERE {
                              <http://dbpedia.org/resource/' . $term . '> <http://purl.org/dc/terms/subject> ?a .
                            ?a <http://www.w3.org/2004/02/skos/core#broader> ?b  . 
                         ?b <http://www.w3.org/2004/02/skos/core#broader> ?c . 
                         ?c <http://www.w3.org/2004/02/skos/core#broader> ?d 

                      }';
                
                
               /* $select = 'SELECT DISTINCT * WHERE {
                              <http://dbpedia.org/resource/' . $term . '> <http://purl.org/dc/terms/subject> ?a .
                            ?a <http://www.w3.org/2004/02/skos/core#broader> ?b  .
                      }';
                * 
                */
            } else {
                $select = 'SELECT DISTINCT * WHERE { <http://dbpedia.org/resource/Category:' . $term . '> <http://www.w3.org/2004/02/skos/core#broader> ?a . 
                     ?a <http://www.w3.org/2004/02/skos/core#broader> ?b  . 
                     ?b <http://www.w3.org/2004/02/skos/core#broader> ?c . 
                     ?c <http://www.w3.org/2004/02/skos/core#broader> ?d 

                    }';
                 
                //$select = 'SELECT DISTINCT * WHERE { <http://dbpedia.org/resource/Category:' . $term . '> <http://www.w3.org/2004/02/skos/core#broader> ?a . ?a <http://www.w3.org/2004/02/skos/core#broader> ?b  . }';
            }

            $response = $this->sendExternalRequestWithSelect($select);

            if($response == null){
                return null;
            }
            $child = '';
            $result = array();


            foreach ($response->result as $a) {

                foreach ($a->binding as $item) {
                    $child = (string) $item->uri;

                    if (!in_array($child, $result)) {
                        array_push($result, $child);
                    }
                }
            }
            /*
            $subresult = array();        
            foreach($result as $child){

                $select = 'SELECT DISTINCT * WHERE { <' . $child . '> <http://www.w3.org/2004/02/skos/core#broader> ?a . 
                     ?a <http://www.w3.org/2004/02/skos/core#broader> ?b  . }';
                
                $subResponse = $this->sendExternalRequestWithSelect($select);
                
                
                if($subResponse != null){
                   foreach ($subResponse->result as $a) {

                        foreach ($a->binding as $item) {
                            $child = (string) $item->uri;

                            if (!in_array($child, $result)) {
                                array_push($result, $child);
                                array_push($subresult, $child);
                            }
                        }
                    }
                }
            }
            
            $subresult2 = array();
            foreach($subresult as $child){

                $select = 'SELECT DISTINCT * WHERE { <' . $child . '> <http://www.w3.org/2004/02/skos/core#broader> ?a . 
                     ?a <http://www.w3.org/2004/02/skos/core#broader> ?b  . }';
                
                $subResponse = $this->sendExternalRequestWithSelect($select);

                if($subResponse != null){
                   foreach ($subResponse->result as $a) {

                        foreach ($a->binding as $item) {
                            $child = (string) $item->uri;

                            if (!in_array($child, $result)) {
                                array_push($result, $child);
                                array_push($subresult2, $child);
                            }
                        }
                    }
                }
            }
            $subresult3 = array();
            foreach($subresult2 as $child){

                $select = 'SELECT DISTINCT * WHERE { <' . $child . '> <http://www.w3.org/2004/02/skos/core#broader> ?a . 
                     ?a <http://www.w3.org/2004/02/skos/core#broader> ?b  . }';
                
                $subResponse = $this->sendExternalRequestWithSelect($select);
                
                $subresult = array();
                if($subResponse != null){
                   foreach ($subResponse->result as $a) {

                        foreach ($a->binding as $item) {
                            $child = (string) $item->uri;

                            if (!in_array($child, $result)) {
                                array_push($result, $child);
                                array_push($subresult3, $child);
                            }
                        }
                    }
                }
            }
            $subresult4 = array();
            foreach($subresult3 as $child){

                $select = 'SELECT DISTINCT * WHERE { <' . $child . '> <http://www.w3.org/2004/02/skos/core#broader> ?a . 
                     ?a <http://www.w3.org/2004/02/skos/core#broader> ?b  . }';
                
                $subResponse = $this->sendExternalRequestWithSelect($select);
                
                $subresult = array();
                if($subResponse != null){
                   foreach ($subResponse->result as $a) {

                        foreach ($a->binding as $item) {
                            $child = (string) $item->uri;

                            if (!in_array($child, $result)) {
                                array_push($result, $child);
                                array_push($subresult4, $child);
                            }
                        }
                    }
                }
            }
           /* foreach($subresult4 as $child){

                $select = 'SELECT DISTINCT * WHERE { <' . $child . '> <http://www.w3.org/2004/02/skos/core#broader> ?a . 
                     ?a <http://www.w3.org/2004/02/skos/core#broader> ?b  . }';
                
                $subResponse = $this->sendExternalRequestWithSelect($select);
                
                $subresult = array();
                if($subResponse != null){
                   foreach ($subResponse->result as $a) {

                        foreach ($a->binding as $item) {
                            $child = (string) $item->uri;

                            if (!in_array($child, $result)) {
                                array_push($result, $child);
                            }
                        }
                    }
                }
            }
                */

            return $result;
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function dbpediaSimilarityCheck($termA, $termB) {

        //$termA = $this->cleaningCategoryTerm($termA);

        $termB = $this->cleaningCategoryTerm($termB);

        //$aLinksA = $this->get3LevelCategories($termA, 1);

        $aLinksB = $this->get3LevelCategories($termB, 2);


        if (count($this->categoriesOfKeyword) == 0) {
            array_push($this->closedList, $termB);
            return;
        }
        $intersection = 0;

        $combindedCats = array();
        for ($i = 0; $i < count($this->categoriesOfKeyword); $i++) {

            if (isset($aLinksB) && in_array($this->categoriesOfKeyword[$i], $aLinksB) && !in_array($this->categoriesOfKeyword[$i], $combindedCats)) {
                array_push($combindedCats, $this->categoriesOfKeyword[$i]);
                $intersection++;
            }
        }

        for ($i = 0; $i < count($aLinksB); $i++) {

            if (isset($aLinksB) && in_array($aLinksB[$i], $this->categoriesOfKeyword) && !in_array($aLinksB[$i], $combindedCats)) {

                array_push($combindedCats, $aLinksB[$i]);
                $intersection++;
            }
           
        }

        //calculate google distance inspired measure
        $googleMeasure = 0.0;
        $union = (count($this->categoriesOfKeyword) + count($aLinksB));


        $a = log(count($this->categoriesOfKeyword));
        $b = log(count($aLinksB));
        $ab = log($intersection);

        $googleMeasure = ((max($a, $b) - $ab) / (log(3573789) - min($a, $b)));

        if (is_nan($googleMeasure) || is_infinite($googleMeasure) || $googleMeasure < 0 || $googleMeasure > 1) {
            return 0;
        }
        $newGoogle = 1 - $googleMeasure;
        
        return $newGoogle;
    }
    
    private function initMainTopicClassification() {

        $this->main_topic_classification = array();

        try {
            $query = 'SELECT DISTINCT * WHERE {
                ?x
                <http://www.w3.org/2004/02/skos/core#broader>
                <http://dbpedia.org/resource/Category:Main_topic_classifications>
                }';

            $response = $this->sendExternalRequestWithSelect($query);
            if($response == null){
                return null;
            }
            $child = '';
            foreach ($response->result as $item) {
                $child = (string) $item->binding->uri;

                array_push($this->main_topic_classification, urldecode($child));
            }

            //print_r($this->main_topic_classification);
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
        } else if (strstr($term, 'resource/') !== false) {
            $termArray = explode("resource/", $term);
            $categoryName = $termArray[1];
        } else if (strstr($term, 'page/') !== false) {
            $termArray = explode("page/", $term);
            $categoryName = $termArray[1];
        } else {
            $categoryName = $term;
        }
        return $categoryName;
    }

    public function convertNameToGetLiteral($term) {


        if (strstr($term, "_") !== false) {
            $term = str_replace("_", " ", $term);
        }

        return $term;
    }

    public function sendExternalRequest($url) {
        // is curl installed?
        if (!function_exists('curl_init')) {
            die('CURL is not installed!');
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/sparql-results+xml"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

        $response = curl_exec($ch);

        echo curl_error($ch);

        curl_close($ch);

        return $response;
    }

    public function sendExternalRequestWithSelect($select) {

        $url = "http://localhost:8080/openrdf-sesame/repositories/articles_categories_nt?query=" . urlencode($select) . "&infer=true";

        // is curl installed?
        if (!function_exists('curl_init')) {
            die('CURL is not installed!');
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/sparql-results+xml"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if($httpCode == 400) {
            return null;
        }else{
            
            $obj = new SimpleXMLElement($response);
            $obj = $obj->results;


            return $obj;
        }
        

    }

}

?>
