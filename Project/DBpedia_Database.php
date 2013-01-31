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

    private $main_topic_classification;
    private $blacklist_topics;
    private $limit = 5;
    private $depth = 0;
    private $max_depth = 5;
    private $graph = array();

    public function __construct() {
        $this->initMainTopicClassification();
        $this->blacklist_topics = array("Wikipedia_categories", "_in_the", "Categories_by_", "Categories_of_", "Article_Feedback_Pilot", "Living_people", "Category:Categories_for_renaming", "Category:Articles", "Category:Fundamental", "Category:Concepts", "_by_", "Category:Wikipedia_articles_with_missing_information", "Category:Wikipedia_maintenance", "Category:Chronology");
    }

    public function calculateSimilarity($term1, $term2) {


        $similarity = $this->wikipediaMiningSimilarity($term1, $term2);
        //$this->googleSimilarityDistance();
        //$this->dbpediaSimilarityCheck();

        echo("Similarity between " . $term1 . " and " . $term2 . ": " . $similarity);
        return $similarity;
    }

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
        $categoryName1 = $this->cleaningTerm($term1);

        $categoryName2 = $this->cleaningTerm($term2);

        $url = "http://wdm.cs.waikato.ac.nz/services/compare?term1=" . $categoryName1 . "&term2=" . $categoryName2 . "&responseFormat=xml&disambiguationDetails&connections";

        $response = $this->sendExternalRequest($url);

        $xmlobj = new SimpleXMLElement($response);

        $similarity = (float) $xmlobj->attributes()->relatedness;

        $connections = $xmlobj->xpath("//connection");

        foreach ($connections as $connection) {
            $connectionTitle = $connection->attributes()->title;
            $relatednessTerm1 = $connection->attributes()->relatedness1;
            $relatednessTerm2 = $connection->attributes()->relatedness2;
        }
        return $similarity;
    }

    private function googleSimilarityDistance() {
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

    private function getBestNode($keyword, $categoriesArray) {
        $bestSimilarity = 0.0;

        $bestNode;
        foreach ($categoriesArray as $category) {
            $similarity = $this->calculateSimilarity($keyword, $category);

            if ($bestSimilarity < $similarity) {
                $bestNode = $category;
            }
        }

        return $bestNode;
    }

    public function getCategoriesOfArticleWithCategories($keyword) {


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



                $array = array($category);

                // $this->iterative_deepening_depth_first_search($array, null, $keyword);
            }

            $bestNode = $this->getBestNode($keyword, $categoriesArray);

            $this->iterative_deepening_depth_first_search($bestNode, null, $keyword);
            die();
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

    private function iterative_deepening_depth_first_search($nodes, $parent, $keyword) {

        $childrenArray = array();

        if (count($nodes) > 0) {
            //parse one category

            foreach ($nodes as $node) {


                if (in_array($node, $this->main_topic_classification)) {
                    echo("\n FOUND GOAL in node: " . $node);
                    array_push($this->graph, $node);
                    continue;
                }


                $children = $this->getCategoriesOfCategory($node);

                if (!array_key_exists($node, $childrenArray) && count($children) > 0) {
                    $childrenArray[$node] = array();
                }

                if (count($children) > 0) {
                    foreach ($children as $child) {
                        if (in_array($child, $this->main_topic_classification)) {
                            echo("\n FOUND GOAL in child: " . $child);
                            array_push($this->graph, $child);
                            continue;
                        } else if (!in_array($child, $this->graph)) {

                            array_push($childrenArray[$node], $child);
                        }

                        array_push($this->graph, $child);
                    }
                }
            }
        }

        if (count($childrenArray) > 0) {

            //print_r($childrenArray);
            $keys = array_keys($childrenArray);
            foreach ($keys as $key) {
                $this->iterative_deepening_depth_first_search($childrenArray[$key], $key, $keyword);
            }
        }
    }

    /* ITERATIVE DEEPENING SEARCH */
    /*
      private function iterative_deepening_depth_first_search($root) {

      $depth = 0;
      while (1 == 1) {

      $result = $this->depth_limited_search($root, $depth);

      print_r($result);
      if (in_array($result, $this->main_topic_classification)){
      return result;
      }

      $depth = $depth + 1;
      }
      }

      private function depth_limited_search($node, $depth) {

      if ($depth == 0 && in_array($node, $this->main_topic_classification)) {
      echo("+++++found main topic++++++");
      return $node;
      } else if ($depth > 0) {

      $children = $this->getCategoriesOfCategory($node);

      echo("\n node \n");
      print_r($node);
      echo("\n children \n");
      print_r($children);

      foreach ($children as $child) {

      $this->depth_limited_search($child, $depth -1 );
      }
      } else {
      return null;
      }
      }
     */

    /*
      private function iterative_deepening_depth_first_search($nodes, $root) {

      echo("nodes: \n");

      print_r($nodes);
      $nodesChildren = array();
      if(count($nodes) > 0){
      //parse one category
      foreach($nodes[0] as $node){
      $children = array();
      if(in_array($node, $this->main_topic_classification)){
      echo("\n FOUND GOAL in: ". $node);
      return;
      }

      $children = $this->getCategoriesOfCategory($node);

      // print_r($children);

      $nodesChildren[$node] = $children;

      array_push($this->graph, $nodesChildren);
      if(count($children) > 0 ){
      foreach($children as $child){
      $childArray = array(array($child));
      print_r($child);
      $this->iterative_deepening_depth_first_search($childArray,$child);
      }
      }



      }





      }


      }
     * 
     */
    /*
      private function iterative_deepening_depth_first_search($root) {


      if(count($root) > 0){
      $children = array();
      foreach($root[0] as $parent){
      $storeList = array();

      $storeList = $this->getCategoriesOfCategory($parent);
      array_push($children, $storeList);
      }

      // $this->depth = $this->depth + 1;
      array_push($this->graph, $children);
      $this->iterative_deepening_depth_first_search($children);

      }
      print_r($this->graph);
      }

     */

    /*
      private $openList = array();

      private $closedList = array();


      private function expandNode($currentNode){

      $newNodes = $this->getCategoriesOfCategory($currentNode);

      foreach($newNodes as $node){
      if(in_array($node, $this->closedList)){

      continue;
      }


      if(in_array($node, $this->openList))
      {
      continue;
      }

      //update ob besserer weg zum knoten gefunden
      array_push($this->openList, $node);

      }

      }

      private function iterative_deepening_depth_first_search($root) {


      array_push($this->openList, $root);

      do{

      $currentNode = array_pop($this->openList);

      if (in_array($currentNode, $this->main_topic_classification)){
      echo("!!!FOUND!!! ".$currentNode. "!!!");
      return $currentNode;
      }


      $this->expandNode($currentNode);


      array_push($this->closedList, $currentNode);

      }while(count($this->openList) != 0);


      echo("end");
      return null;
      }
     */

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

            $searchUrl = "http://dbpedia.org/sparql?query=" . urlencode($query) . "&format=json";

            $mainCategories = json_decode($this->sendExternalRequest($searchUrl), true);

            foreach ($mainCategories['results']['bindings'] as $mainCategory) {
                array_push($this->main_topic_classification, $mainCategory['x']['value']);
            }
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }

    private function sendExternalRequest($url) {
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
