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
    
    public function __construct($uri, $name, $weight) {
        $this->name = $name;
        $this->uri = $uri;
        $this->weight = $weight;
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
