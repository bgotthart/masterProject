<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of TermItem
 *
 * @author biancagotthart
 */
class TermItem {
    
    private $name;
    private $uri;
    private $weight = 0.0;
    private $isMainTopic = false;
    
    public function __construct($uri, $name, $weight, $isMainTopic = false) {
        $this->name = $name;
        $this->uri = $uri;
        $this->weight = $weight;
        $this->isMainTopic = $isMainTopic;
    }
    
    public function getIsMainTopic(){
        return $this->isMainTopic;
    }
    public function setIsMainTopic($bool){
        $this->isMainTopic = $bool;
    }
    public function getName(){
        return $this->name;
    }
    public function setName($name){
        $this->name = $name;
    }
    public function getUri(){
        return $this->uri;
    }
    public function setUri($uri){
        $this->uri = $uri;
    }
    public function getWeight(){
        return $this->weight;
    }
    public function setWeith($weight){
        $this->weight = $weight;
    }
    
}

?>
