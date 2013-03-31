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
    private $blacklist_keywords;
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
        $this->blacklist_keywords = array("CNN", "TechCrunch");

        $this->blacklist_topics = array("_by_", "WikiProject_", "Government_of_", "Wikipedia_categories", "Categories_by_", "Categories_of_", "Article_Feedback_Pilot", "Living_people", "Category:Categories_for_renaming", "Category:Articles", "Category:Fundamental", "Category:Concepts", "Category:Wikipedia_articles_with_missing_information", "Category:Wikipedia_maintenance", "Category:Chronology");
    }

    public function getCategories($keywords) {

        $this->graph = array();
        $this->closedList = array();
        $this->openList = array();

        $concepts = array();
        
        
        foreach ($keywords as $keyword) {
            $in_blacklist = false;
            foreach ($this->blacklist_keywords as $blacklist) {

                if (strpos($keyword, $blacklist) != 0 || strpos($keyword, $blacklist) != null) {

                    $in_blacklist = true;
                    break;
                }
            }

            if (in_array($keyword, $this->blacklist_keywords)) {
                $in_blacklist = true;
            }

            if(!$in_blacklist){
                $categories = $this->getCategoriesForKeyword($keyword);

                if (count($categories) > 0) {
                    if (!key_exists($keyword, $concepts)) {
                        $concepts[$keyword] = array();
                    }
                    foreach ($categories as $category) {
                        array_push($concepts[$keyword], $category);
                    }
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

            $similarity = $this->dbpediaSimilarityCheck($keyword, $category);

            //if keyword has own category name
            /* if ($keyword == $cleanedTerm) {
              $term = new TermItem($category, $cleanedTerm, $similarity);

              //$bestNode = $category;
              $bestNode = $term;
              return $bestNode;
              } */

            if (in_array($category, $this->main_topic_classification)) {
                $term = new TermItem($category, $cleanedTerm, $similarity, true);

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

    public function lookForDBpediaConcept($term) {

        try {

            $url = "http://wdm.cs.waikato.ac.nz/services/search?query=" . urlencode($term);

            $response = $this->sendExternalRequest($url);

            if ($response == null) {
                return null;
            }

            $obj = new SimpleXMLElement($response);

            $maxProbability = 0;
            $bestConcept = '';
            $bestConcept = '';
            foreach ($obj->label->senses->sense as $item) {

                if ($maxProbability < $item->attributes()->priorProbability) {
                    $maxProbability = $item->attributes()->priorProbability;
                    $bestConcept = $this->prepareTermNameForDBpedia((string)$item->attributes()->title);
                }
            }

            return $bestConcept;
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    public function lookForDBpediaConcept_V2($term, $keywords) {

        try {

            $response = $this->sendExploreRequest('<http://dbpedia.org/resource/' . $term . '>');

            if ($response == null) {
                return null;
            }

            $obj = new SimpleXMLElement($response);

            $conceptsArray = array();
            foreach ($obj->results->result as $result) {
                array_push($conceptsArray, (string) $result->binding[2]->uri);
            }

            $bestSimilarity = 0.0;
            $bestConcept = '';
            if (count($conceptsArray) > 1) {

                foreach ($keywords as $keyword) {
                    //nur wenn wir nicht die selben begriffe sind!
                    $concept = $this->getBestConcept($keyword, $conceptsArray);

                    if ($bestSimilarity < $concept->getWeight()) {
                        $bestConcept = $concept;
                        $bestSimilarity = $concept->getWeight();
                    }
                }
            }

            print_r($bestConcept);

            die();

            return $concept;
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function getBestConcept($keyword, $conceptsArray) {
        $bestSimilarity = 0.0;

        $bestNode = "";
        foreach ($conceptsArray as $concept) {

            $cleanedTerm = $this->cleaningCategoryTerm($concept);

            $similarity = $this->getBestConceptsOfArray($keyword, $concept);

            if ($bestSimilarity < $similarity) {
                $term = new TermItem($concept, $cleanedTerm, $similarity);

                $bestNode = $term;
                $bestSimilarity = $similarity;
            }
        }

        return $bestNode;
    }

    public function getCategoriesForKeyword($keyword) {

        $keywordConcept = '';

        $this->graph = array();

        $keyword = $this->lookForDBpediaConcept($keyword);

        if (strlen($keyword) == 0)
            return null;

        $keywordConcept = "http://dbpedia.org/resource/" . $keyword;
        $this->categoriesOfKeyword = $this->get3LevelCategories($keyword, 1);
        if ($this->categoriesOfKeyword == null)
            return null;
        $categoriesArray = array();
        try {

            $select = 'SELECT DISTINCT * WHERE {
                            <http://dbpedia.org/resource/' . $keyword . '> <http://purl.org/dc/terms/subject> ?x
                                }';

            $response = $this->sendQueryRequest($select);

            if ($response == null) {
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
/*
        set_time_limit(15);
   */
        
        if(!($node instanceof TermItem)){
            $this->graph = null;
            return;
           // die("test");
        }
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

            $response = $this->sendQueryRequest($select);
            if ($response == null) {
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

            $response = $this->sendQueryRequest($select);

            if ($response == null) {
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

            return $result;
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    public function dbpediaSimilarityLinkCheck($termA, $termB) {
        return 0;
    }

    public function getBestConceptsOfArray($termA, $termB) {
        $termB = $this->cleaningCategoryTerm($termA);
        $termB = $this->cleaningCategoryTerm($termB);
        $aLinksB = $this->get3LevelCategories($termB, 1);
        $aLinksA = $this->get3LevelCategories($termA, 1);


        return $this->similarityCalculation($aLinksA, $aLinksB);
    }

    public function dbpediaSimilarityCheck($termA, $termB) {
        $termA = $this->cleaningCategoryTerm($termA);
        $termB = $this->cleaningCategoryTerm($termB);
        $aLinksB = $this->get3LevelCategories($termB, 2);
        $aLinksA = $this->categoriesOfKeyword;

        if (count($aLinksA) == 0) {
            array_push($this->closedList, $termB);
            return;
        }
        return $this->similarityCalculation($aLinksA, $aLinksB);
    }

    public function similarityCalculation($aLinksA, $aLinksB) {


        $intersection = 0;

        $combindedCats = array();
        for ($i = 0; $i < count($aLinksA); $i++) {

            if (isset($aLinksB) && in_array($aLinksA[$i], $aLinksB) && !in_array($aLinksA[$i], $combindedCats)) {
                array_push($combindedCats, $aLinksA[$i]);
                $intersection++;
            }
        }

        for ($i = 0; $i < count($aLinksB); $i++) {

            if (isset($aLinksB) && in_array($aLinksB[$i], $aLinksA) && !in_array($aLinksB[$i], $combindedCats)) {

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

            $response = $this->sendQueryRequest($query);
            if ($response == null) {
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

    public function cleaningCategoryTerm($term) {
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
        //curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/sparql-results+xml"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
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

    public function sendExploreRequest($resource) {

        $url = "http://localhost:8080/openrdf-work-bench/repositories/dbpedia_store/explore?resource=" . urlencode($resource) . "&limit=10";

        // is curl installed?
        if (!function_exists('curl_init')) {
            die('CURL is not installed!');
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/sparql-results+xml"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode != 200) {
            echo("http code:" . $httpCode);
        }
        $error = curl_error($ch);
        if ($error) {
            echo("error: " . curl_error($ch));
        }
        curl_close($ch);
        return $response;
    }

    public function sendQueryRequest($select) {

        $url = "http://localhost:8080/openrdf-sesame/repositories/dbpedia_store?query=" . urlencode($select) . "&infer=true";

        // is curl installed?
        if (!function_exists('curl_init')) {
            die('CURL is not installed!');
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/sparql-results+xml"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode != 200) {
            echo("http code:" . $httpCode);
        }
        $error = curl_error($ch);
        if ($error) {
            echo("error: " . curl_error($ch));
        }


        curl_close($ch);


        if ($httpCode == 400) {
            return null;
        } else {

            $obj = new SimpleXMLElement($response);
            return $obj->results;
        }
    }

}

?>
