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

        $this->blacklist_topics = array("_by_", "WikiProject_", "Wikipedia_categories", "_in_the", "Categories_by_", "Categories_of_", "Article_Feedback_Pilot", "Living_people", "Category:Categories_for_renaming", "Category:Articles", "Category:Fundamental", "Category:Concepts", "Category:Wikipedia_articles_with_missing_information", "Category:Wikipedia_maintenance", "Category:Chronology");
        //$this->wikipediaAPI = new WikipediaMiningAPI();
    }

    public function getCategories($keywords) {
        //$this->callWikipediaMiningForKeywordInformation($keyword);


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

        //$similarity = $this->ownSimilarityFunctionWithCategories($term1, $term2);
        //$similarity = $this->wikipediaMiningSimilarity($term1, $term2);
        //$this->googleSimilarityDistance();
        $similarity = $this->dbpediaSimilarityCheck($term1, $term2);
        //echo("Similarity between " . $term1 . " and " . $term2 . ": " . $similarity);
        return $similarity;
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

    private function wikipediaMiningSimilarity($term1, $term2) {
        $similarity = 0.0;

        $term1 = $this->cleaningTermsForWikipediaMining($term1);
        $term2 = $this->cleaningTermsForWikipediaMining($term2);
        $term1 = $this->wikipediaAPI->searchForTerm($term1);

        $categoryName1 = urlencode($term1);
        $categoryName2 = urlencode($term2);
        $url = "http://wdm.cs.waikato.ac.nz/services/compare?term1=" . $categoryName1 . "&term2=" . $categoryName2 . "&responseFormat=xml&disambiguationDetails&connections";

        $response = $this->sendExternalRequest($url);

        $xmlobj = new SimpleXMLElement($response);

        if (($xmlobj->attributes()->error) != null) {

            $unknownTerm = (string) $xmlobj->attributes()->unknownTerm;
            $newTerm = (string) $this->wikipediaAPI->searchForTerm($unknownTerm);

            $newTerm = urlencode($newTerm);

            if ($newTerm == null) {
                echo("\n ERROR NO TERM FOUND!!! \n");
                print_r($unknownTerm);
                return array("error" => "unknown Term", "term" => $unknownTerm);
            }

            $url = "http://wdm.cs.waikato.ac.nz/services/compare?term1=" . $categoryName1 . "&term2=" . $newTerm . "&responseFormat=xml&disambiguationDetails&connections";

            $response = $this->sendExternalRequest($url);

            $xmlobj = new SimpleXMLElement($response);
            if (($xmlobj->attributes()->error) != null) {
                echo("\n ERROR NO TERM FOUND!!! \n");
                print_r($unknownTerm);
                return array("error" => "unknown Term", "term" => $unknownTerm);
            }
        }
        $similarity = (float) $xmlobj->attributes()->relatedness;

        $connections = $xmlobj->xpath("//connection");

        foreach ($connections as $connection) {
            $connectionTitle = $connection->attributes()->title;
            $relatednessTerm1 = $connection->attributes()->relatedness1;
            $relatednessTerm2 = $connection->attributes()->relatedness2;
        }
        return $similarity;
    }

    private function getBestNode($keyword, $categoriesArray) {
        $bestSimilarity = 0.0;

        $bestNode = "";
        foreach ($categoriesArray as $category) {

            if (in_array($category, $this->main_topic_classification)) {
                echo("FOUND MAIN ");
                $bestNode = $category;
                return $bestNode;
            }
            $similarity = $this->calculateSimilarity($keyword, $category);

            if ($bestSimilarity < $similarity) {
                $bestNode = $category;
                $bestSimilarity = $similarity;
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

    public function getCategoryDetails($term) {


        try {
            $term = urlencode($term);
            $searchUrl = "http://wdm.cs.waikato.ac.nz/services/exploreCategory?title=" . $term . "&labels=true&parentCategories=true&responseFormat=xml";

            $response = $this->sendExternalRequest($searchUrl);

            $xmlobj = new SimpleXMLElement($response);

            $tmp = array(($xmlobj->xpath("/message")));

            $categories = array();
            foreach ($tmp[0] as $obj) {

                if ($obj->xpath("//parentCategory") != null) {
                    $parentCategories = $obj->xpath("//parentCategory");
                    foreach ($parentCategories as $item) {
                        array_push($categories, (string) $item->attributes()->title);
                    }
                }
            }
            return($categories);
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    public function getArticleDetails($term) {


        try {
            $term = urlencode($term);
            $searchUrl = "http://wdm.cs.waikato.ac.nz/services/exploreArticle?title=" . $term . "&labels=true&parentCategories=true&responseFormat=xml";


            $response = $this->sendExternalRequest($searchUrl);

            $xmlobj = new SimpleXMLElement($response);

            $tmp = array(($xmlobj->xpath("/message")));

            $categories = array();
            foreach ($tmp[0] as $obj) {

                if ($obj->xpath("//parentCategory") != null) {
                    $parentCategories = $obj->xpath("//parentCategory");
                    foreach ($parentCategories as $item) {
                        array_push($categories, (string) $item->attributes()->title);
                    }
                }
            }

            return($categories);
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
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
            }
            
            $bestNode = $this->getBestNode($keyword, $categoriesArray);

            $this->iterative_deepening_depth_first_search($bestNode, null, $keyword);

            echo("...GRAPH...");
            print_r($this->graph);
            die();
            return ($this->graph);
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
            //parse one category


            if (key_exists($node, $this->openList)) {
                unset($this->openList[$node]);
            }

            array_push($this->closedList, $node);

           
            if (in_array($node, $this->main_topic_classification)) {
                echo("\n FOUND GOAL in node: " . $node);
                return;
            }


            $children = $this->getCategoriesOfCategory($node);
            //$children = $this->wikipediaAPI->getCategoryDetails($node);
            //add expanded Node to closed list to prevent from circles

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
                    echo("ERROR: no best node found in ");
                    print_r($children);

                    //zurück gehen im baum

                    return;
                }

                if (!key_exists($node, $this->graph)) {
                    $this->graph[$node] = array();
                }

                $this->graph[$node] = $node;

                //array_push($this->graph, $node);
                $this->iterative_deepening_depth_first_search($bestNode, $node, $keyword);
            } else {
                echo("ERROR: children null");

                //TODO: delete old nodes from old path -> update graph object
                if (in_array($node, $this->openList)) {
                    unset($this->openList[$node]);
                }

                //delete $node from graph 
                if (!key_exists($node, $this->graph)) {
                    unset($this->graph[$node]);
                }
                
                $children = array_values($this->openList);
                $bestNode = $this->getBestNode($keyword, $children);

                $this->iterative_deepening_depth_first_search($bestNode, $node, $keyword);

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

            return $categoriesArray;
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function googleSimilarityDistance() {
        $url = "https://www.googleapis.com/customsearch/v1?key=AIzaSyC8KhqZYkRXdKhaUvz67B7IGRzMjzo5lsc&cx=013707050272249182185:ldylhbskn0a&q=iPhone&alt=json";

        $response = $this->sendExternalRequest($url);

        $node1Result = json_decode($response);

        print_r($node1Result);
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

            /*
             * ?c <http://www.w3.org/2004/02/skos/core#broader> ?d .
              ?d <http://www.w3.org/2004/02/skos/core#broader> ?e .
             */
            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query) . "&format=json";

            $categories = json_decode($this->sendExternalRequest($searchUrl), true);
            $result = array();


            foreach ($categories['results']['bindings'] as $entry) {
                $categoryA = $entry['a']['value'];
                $categoryB = $entry['b']['value'];
                $categoryC = $entry['c']['value'];
                //$categoryD = $entry['d']['value'];
                // $categoryE = $entry['e']['value'];
                if (!in_array($categoryA, $result)) {
                    array_push($result, $categoryA);
                }
                if (!in_array($categoryB, $result)) {
                    array_push($result, $categoryB);
                }
                if (!in_array($categoryC, $result)) {
                    array_push($result, $categoryC);
                }
                /* if (!in_array($categoryD, $result)) {
                  array_push($result, $categoryD);
                  }
                  if (!in_array($categoryE, $result)) {
                  array_push($result, $categoryE);
                  }
                 *
                 */
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
        $googleMeasure = null;

        $a = log(count($aLinksA));
        $b = log(count($aLinksB));
        $ab = log($intersection);

        $googleMeasure = (max($a, $b) - $ab) / ((count($aLinksA) + count($aLinksB)) - min($a, $b));


        return $googleMeasure;
        // return $this->normalizeGoogleMeasure($googleMeasure);
    }

    private function lookForDBpediaTerm($term) {
        try {
            $query = 'SELECT DISTINCT * WHERE { <http://dbpedia.org/resource/' . $term . '_(disambiguation)> <http://dbpedia.org/ontology/wikiPageDisambiguates> ?y .
<http://dbpedia.org/resource/' . $term . '> <http://dbpedia.org/ontology/wikiPageRedirects> ?x .
    }';

            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query) . "&format=xml";

            $categories = $this->sendExternalRequest($searchUrl);
            $result = array();
            foreach ($categories['results']['bindings'] as $entry) {
                $category = $entry['x']['value'];

                array_push($result, $category);
            }

            print_r($categories);
            return $result;
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function getLink($term) {
        try {


            $searchUrl = "https://en.wikipedia.org/w/api.php?action=query&prop=extlinks&format=xml&titles=" . $term;

            print_r($searchUrl);
            $categories = json_decode($this->sendExternalRequest($searchUrl), true);
            print_r($categories);
            die();
            $this->graph = array();

            $categoriesArray = array();


            foreach ($categories['results']['bindings'] as $entry) {
                $category = $entry['x']['value'];

                //$category = $this->cleaningTerm($category);

                array_push($categoriesArray, $category);

                //$array = array($category);
                // $this->iterative_deepening_depth_first_search($array, null, $keyword);
            }


            die("links");
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function ownSimilarityFunctionWithCategories($termA, $termB) {

        if ($termA == $termB)
            return 1;

        $aLinksA = $this->getCategoriesOfArticle($termA);
        $aLinksB = $this->getCategoriesOfCategory($termB);



        for ($i = 0; $i < 3; $i++) {

            $categories = $this->getCategoriesOfCategory($aLinksA[$i]);

            foreach ($categories as $category) {
                array_push($aLinksA, $category);

                $subcategories = $this->getCategoriesOfCategory($category);

                foreach ($subcategories as $subcategory) {
                    array_push($aLinksA, $subcategory);
                }
            }
        }


        for ($i = 0; $i < 3; $i++) {

            $categories = $this->getCategoriesOfCategory($aLinksB[$i]);

            foreach ($categories as $category) {
                array_push($aLinksB, $category);

                $subcategories = $this->getCategoriesOfCategory($category);

                foreach ($subcategories as $subcategory) {
                    array_push($aLinksB, $subcategory);
                }
            }
        }


        //we can't do anything if there are no links
        if (count($aLinksA) == 0 || count($aLinksB) == 0)
            return "null";


        $intersection = 0;
        $union = 0;
        $indexA = 0;
        $indexB = 0;


        while ($indexA < count($aLinksA) || $indexB < count($aLinksB)) {

            //identify which links to use (A, B, or both)
            $useA = false;
            $useB = false;

            $linkA = null;
            $linkB = null;

            if ($indexA < count($aLinksA))
                $linkA = $aLinksA[$indexA];

            if ($indexB < count($aLinksB))
                $linkB = $aLinksB[$indexB];


            if ($linkA != null && $linkB != null && ($linkA == $linkB)) {
                $useA = true;
                $useB = true;
                // $linkArt = new Article(wikipedia . getEnvironment(), linkA);
                $intersection++;
            } else {
                if ($linkA != null && ($linkB == null || $linkA == $linkB)) {
                    $useA = true;
                    //$linkArt = new Article(wikipedia . getEnvironment(), linkA);

                    if ($linkA == $linkB) {
                        $intersection++;
                    }
                } else {
                    $useB = true;
                    //$linkArt = new Article(wikipedia . getEnvironment(), linkB);

                    if ($linkB == $linkA) {
                        $intersection++;
                    }
                }
            }
            $union++;

            if ($useA)
                $indexA++;
            if ($useB)
                $indexB++;
        }


        //calculate google distance inspired measure
        $googleMeasure = null;

        if ($intersection == 0) {
            $googleMeasure = 1.0;
        } else {
            $a = log(count($aLinksA));
            $b = log(count($aLinksB));
            $ab = log($intersection);

            $googleMeasure = (max($a, $b) - $ab) / ($m - min($a, $b));
        }

        print_r($googleMeasure);
        die();

        return $this->normalizeGoogleMeasure(googleMeasure);
    }

    private function normalizeGoogleMeasure($googleMeasure) {

        if ($googleMeasure == null)
            return 0;

        if ($googleMeasure >= 1)
            return 0;

        return 1 - $this->normalizeGoogleMeasure($googleMeasure);
    }

    public function getCategoriesOfArticle($keyword) {

        $this->cleaningCategoryTerm($keyword);
        try {
            $query = 'SELECT DISTINCT * WHERE {
                <http://dbpedia.org/resource/' . $keyword . '>
                    <http://purl.org/dc/terms/subject>
                    ?x
                    }';

            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query);

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
