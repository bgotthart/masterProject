<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Database
 *
 * @author biancagotthart
 */
require_once("DBpediaDatabase.php");

class DatabaseClass {

    private $arc_store;
    private $dbpedia_store;

    const EX_URL = "http://example.org";
    const PREFIX_EX = "PREFIX ex: <http://www.example.org/#>";
    const PREFIX_RDF = "PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>";
    const PREFIX_OX = "PREFIX owl: <http://www.w3.org/2002/07/owl#>";

    private $dbpedia_database;
    private $user_id = "http://www.semanticweb.org/ontologies/2013/0/ex.owl#BiancaGotthart";
    private $testKeywords = Array("IOS" => Array(0 => "http://dbpedia.org/resource/Category:Mach", 1 => "http://dbpedia.org/resource/Category:Microkernels", 2 => "http://dbpedia.org/resource/Category:Operating_system_kernels", 3 => "http://dbpedia.org/resource/Category:Computer_architecture", 4 => "http://dbpedia.org/resource/Category:Areas_of_computer_science", 5 => "http://dbpedia.org/resource/Category:Computer_science", 6 => "http://dbpedia.org/resource/Category:Applied_sciences", 7 => "http://dbpedia.org/resource/Category:Scientific_disciplines", 8 => "http://dbpedia.org/resource/Category:Science"),
        "Android" => Array());

    public function __construct() {

        $this->dbpedia_database = new DBpedia_DatabaseClass();

        $this->loadConfigFile();

        $this->initDBStore();
    }

    public function insertUserQuery($keywords) {

      
        $saveConcepts = $this->dbpedia_database->getCategories($keywords);
        print_r($saveConcepts);
       
        //not ready yet

        $errors = false;

        $config_xml = $this->loadConfigFile();


        foreach ($saveConcepts as $key => $value) {

            foreach ($value as $data) {
                if (strstr($data, 'Category:') !== false) {
                    $termArray = explode("Category:", $data);
                    $term = $termArray[1];
                } else {
                    $term = $data;
                }
                //print_r($data);

                /*
                  $insert = self::PREFIX_EX . ' ' .
                  self::PREFIX_OX . ' ' .
                  self::PREFIX_RDF .
                  ' INSERT INTO <' . $config_xml->fileBaseURL . '/config/Profile_v1.owl>{
                  <' . self::EX_URL . '#' . $data . '> rdf:type owl:NamedIndividual;
                  ex:hasName "' . $data . '";
                  rdf:type    ex:Concept;
                  ex:isInterestedBy ' . '<' . $this->user_id . '> ;
                  ex:hasConnectionTo ' . '<> ;
                  }';

                  $result = $this->arc_store->query($insert, 'rows', '', true);
                  if ($errs = $this->arc_store->getErrors()) {
                  print_r($errs);
                  $errors = true;
                  }

                  if (!$errors) {
                  die("YEAH"); return '{"response": [{ "function":"insertUserQuery" ,
                  "message":"Entities are successfully added to User Profile",
                  "data": "' . $data . '}]}';
                  } else {
                  die("NOOO"); return '{"response": [{ "function":"insertUserQuery" ,
                  "message":"ERROR: Entities NOT added to User Profile",
                  "data": "' . $data . '" }]}';
                  }
                 * 
                 */
            }
        }
    }

    public function selectQuery() {

        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                ' SELECT *
                    WHERE
                {     
                    ?concept rdf:type <http://www.semanticweb.org/ontologies/2013/0/ex.owl#Concept> . 
                    ?x rdf:type <http://www.semanticweb.org/ontologies/2013/0/ex.owl#Concept> . 
                    {
                        ?x <http://www.semanticweb.org/ontologies/2013/0/ex.owl#isConnectedWith> ?concept . 
                    }
                    UNION {
                        ?concept <http://www.semanticweb.org/ontologies/2013/0/ex.owl#hasConnectionTo> ?x . 
                    }
                    OPTIONAL{ ?concept <http://www.semanticweb.org/ontologies/2013/0/ex.owl#hasWeight> ?weightC . }
                    OPTIONAL{ ?x <http://www.semanticweb.org/ontologies/2013/0/ex.owl#hasWeight> ?weightX . }
                    OPTIONAL {?concept  <http://www.semanticweb.org/ontologies/2013/0/ex.owl#hasName> ?nameC . }
                    OPTIONAL {?x  <http://www.semanticweb.org/ontologies/2013/0/ex.owl#hasName> ?nameX . }
                    
                }';


        if ($errs = $this->arc_store->getErrors()) {
            echo("ERROR in SELECT TOPIC: ");
            print_r($errs);
            return;
        }

        $topics = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {

            foreach ($rows as $row) {
                if (isset($row['nameC'])) {
                    $topics[$row['concept']]['name'] = $row['nameC'];
                    $topics[$row['concept']]['uri'] = $row['concept'];
                } else {
                    $topics[$row['concept']]['name'] = $row['nameX'];
                    $topics[$row['concept']]['uri'] = $row['x'];
                }
                if (isset($row['concept'])) {
                    $topics[$row['concept']]['connectionName'] = $row['nameX'];
                    $topics[$row['concept']]['connection'] = $row['x'];
                } else {
                    $topics[$row['concept']]['connectionName'] = $row['nameC'];
                    $topics[$row['concept']]['connection'] = $row['concept'];
                }
            }
        } else {
            echo( '<em>No data returned from local Database</em>');
        }

        return $topics;
    }

    private function initDBStore() {

        $config_xml = $this->loadConfigFile();

        $db_config = array(
            'db_host' => (string) $config_xml->database->db_host,
            'db_name' => (string) $config_xml->database->db_name,
            'db_user' => (string) $config_xml->database->db_user,
            'db_pwd' => (string) $config_xml->database->db_pwd,
            /* SPARQL endpoint settings */
            'endpoint_features' => array(
                'select', 'construct', 'ask', 'describe', // allow read
                'load', 'insert', 'delete', // allow update
                'dump' // allow backup
            ),
        );

        $this->arc_store = ARC2::getStore($db_config);
        if (!$this->arc_store->isSetUp()) {
            $this->arc_store->setUp();

            //$this->arc_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/Profile_v2.owl>');
            $this->arc_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/Profile_v1.owl>');
        }

        if ($errs = $this->arc_store->getErrors()) {
            echo("ERROR in INIT DB: ");
            print_r($errs);
        }
    }

    public function initDBpediaDump() {
        $config_xml = $this->loadConfigFile();
        /*
         * TODO: Install local DBpedia Dump
         */

        $dbpedia_config = array(
            'db_host' => (string) $config_xml->database->db_host,
            'db_name' => (string) $config_xml->database->db_name,
            'db_user' => (string) $config_xml->database->db_user,
            'db_pwd' => (string) $config_xml->database->db_pwd,
            'store_name' => 'dbpedia_store',
            'endpoint_features' => array(
                'select',
                'load'
            ),
        );
        $this->dbpedia_store = ARC2::getStore($dbpedia_config);
        if (!$this->dbpedia_store->isSetUp()) {
            $this->dbpedia_store->setUp();

            // $this->dbpedia_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/article_categories_en.nt>');
        }

        $this->dbpedia_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/article_categories_en.nt>');

        if ($errs = $this->dbpedia_store->getErrors()) {
            echo("ERROR in INIT DBpedia Dump: ");
            print_r($errs);
        }

        die("finished with dbpedia dump");
    }

    private function loadConfigFile() {
        return simplexml_load_file("../config/config.xml");
    }

}

?>
