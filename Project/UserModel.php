<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of UserModel
 *
 * @author biancagotthart
 */
class UserModel {
    
    private $name;
    
    private $aInterests;
    
    public function getName(){
        return $name;
    }
    
    public function getInterests(){
        return $getInterests;
    }
    
    public function addInterest($interest){
        array_push($aInterests);
    }
}

?>
