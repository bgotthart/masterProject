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
        $this->blacklist_topics = array("Wikipedia_categories", "Categories_by_","Categories_of_", "Article_Feedback_Pilot", "Living_people", "Category:Categories_for_renaming", "Category:Articles", "Category:Fundamental", "Category:Concepts", "_by_", "Category:Wikipedia_articles_with_missing_information", "Category:Wikipedia_maintenance", "Category:Chronology");


        // $this->blacklist_topics = array();
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
            $this->graph = array();
            
            foreach ($categories['results']['bindings'] as $entry) {
                $category = $entry['x']['value'];
                
                
                $array = array(array($category));
                
                $this->iterative_deepening_depth_first_search($array, $category);
                 
                //die("!!!!only one time!!!!!");
                

            }

            //echo("GRAPH");
            //print_r($this->graph);
            die("getCategoriesOfArticle");
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }
    
    private function addToGraph($parent, $nodes){
        if(!array_key_exists($parent, $this->graph)){
            $this->graph[$parent] = $nodes;
        }
    }
    private function iterative_deepening_depth_first_search($nodes) {
               
        print_r($nodes);
        $children = new SplDoublyLinkedList();
        
        $nodesChildren = array();
        if(count($nodes) > 0){
            //parse one category
            foreach($nodes[0] as $node){
                
                if(in_array($node, $this->main_topic_classification)){
                    echo("\n FOUND GOAL in: ". $node);
                    continue;
                }
                
                $newNodes = $this->getCategoriesOfCategory($node);
                
                if(count($newNodes) > 0){
                    //array_push($children, $newNodes);
                    
                    $children->push($newNodes);
                }
                
  
            }
            
            
            echo("\n nodes \n");
            print_r($nodes);
            echo("\n children \n");
            print_r($children);
   
        }
        
        
        if(count($children) > 0){
            $this->iterative_deepening_depth_first_search($children);
        }else{
            return;
        }
        
        
        
    }
    
    /*ITERATIVE DEEPENING SEARCH*/
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
