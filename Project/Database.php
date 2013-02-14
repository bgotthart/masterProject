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

    const EX_URL = "http://www.example.org/";
    const PREFIX_EX = "PREFIX ex: <http://www.example.org#>";
    const PREFIX_RDF = "PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>";
    const PREFIX_OX = "PREFIX owl: <http://www.w3.org/2002/07/owl#>";

    private $dbpedia_database;
    private $user_id = "http://www.example.org#BiancaGotthart";
    private $testKeywords = Array("IOS" => Array(0 => "http://dbpedia.org/resource/Category:Mach", 1 => "http://dbpedia.org/resource/Category:Microkernels", 2 => "http://dbpedia.org/resource/Category:Operating_system_kernels", 3 => "http://dbpedia.org/resource/Category:Computer_architecture", 4 => "http://dbpedia.org/resource/Category:Areas_of_computer_science", 5 => "http://dbpedia.org/resource/Category:Computer_science", 6 => "http://dbpedia.org/resource/Category:Applied_sciences", 7 => "http://dbpedia.org/resource/Category:Scientific_disciplines", 8 => "http://dbpedia.org/resource/Category:Science", 9 => "http://dbpedia.org/resource/Category:IOS"),
        "Android" => Array());

    public function __construct() {

        $this->dbpedia_database = new DBpedia_DatabaseClass();

        $this->loadConfigFile();

        $this->initDBStore();
    }

    public function deleteUserQuery() {

        $config_xml = $this->loadConfigFile();


        $insert = ' DELETE  {
                   <http://dbpedia.org/resource/Category:History_of_technology> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2002/07/owl##NamedIndividual> ;
                    <http://www.example.org#hasName> ?name .
                   }';


        $result = $this->arc_store->query($insert, '', '', true);
        if ($errs = $this->arc_store->getErrors()) {
            echo("error");
            print_r($errs);
            $errors = true;
        }

        //print_r($this->selectQuery());
    }

    public function updateUserQuery() {

        $config_xml = $this->loadConfigFile();

        $insert = ' INSERT DATA {
                   <http://dbpedia.org/resource/Sport> <http://www.example.org#hasWeight> 100 .
                   }';


        $result = $this->arc_store->query($insert, '', '', true);
        if ($errs = $this->arc_store->getErrors()) {
            echo("error");
            print_r($errs);
            $errors = true;
        }
        echo("test");
        print_r($result);
        // print_r($this->selectAllQuery());
    }

    public function insertUserQuery($keywords) {


        $saveConcepts = $this->dbpedia_database->getCategories($keywords);

        $errors = false;

        $config_xml = $this->loadConfigFile();


        $resultJson = '"results": [{';
        $lastConcept = end($saveConcepts);
        foreach ($saveConcepts as $key => $value) {
            
            $last = end($value);
            
            if (strstr($key, 'Category:') !== false) {
                $termArray = explode("Category:", $key);
                $identifier = $termArray[1];
            } else if (strstr($key, 'resource/') !== false) {
                $termArray = explode("resource/", $key);
                $identifier = $termArray[1];
            } else if (strstr($key, 'page/') !== false) {
                $termArray = explode("page/", $key);
                $identifier = $termArray[1];
            } else {
                $identifier = $key;
            }

            $resultJson .= '"' . $identifier . '": [';
            foreach ($value as $data) {


                $name = '';
                if (strstr($data, 'Category:') !== false) {
                    $termArray = explode("Category:", $data);
                    $name = $termArray[1];
                } else if (strstr($data, 'resource/') !== false) {
                    $termArray = explode("resource/", $data);
                    $name = $termArray[1];
                } else if (strstr($data, 'page/') !== false) {
                    $termArray = explode("page/", $data);
                    $name = $termArray[1];
                } else {
                    $name = $data;
                }



                $stringName = $this->dbpedia_database->prepareName($name);

                if ($last == $data) {
                    $resultJson .= '{"name": "' . $stringName . '"}';
                } else {
                    $resultJson .= '{"name": "' . $stringName . '"},';
                }
                $insert = ' INSERT INTO <' . $config_xml->fileBaseURL . '/config/Profile_v1.owl> {
                  <' . $data . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2002/07/owl##NamedIndividual>;
                  <http://www.example.org#hasName> "' . $stringName . '" ;
                  <http://www.example.org#hasWeight> 0 ;    
                  <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>    <http://www.example.org#Concept> ;';


                $lastElement = end($value);

                foreach ($value as $data2) {

                    if ($data2 != $data) {
                        if ($data != $data2) {
                            if ($lastElement == $data2) {
                                $insert .= '  <http://www.example.org#hasConnectionTo> <' . $data2 . '> .';
                            } else {
                                $insert .= '  <http://www.example.org#hasConnectionTo> <' . $data2 . '> ;';
                            }
                        }
                    }
                }

                $insert .= '}';

                $result = $this->arc_store->query($insert, '', '', true);
                if ($errs = $this->arc_store->getErrors()) {
                    echo("error");
                    print_r($errs);
                    $errors = true;
                }
            
               // $resultJson .= "}";
            }
            
            if($lastConcept == $value){
                $resultJson .= "]";
            }else{
                $resultJson .= "],";
            }
            
        }

        $resultJson .= "}]";
        
        if (!$errors) {
            return '{"response": [{ "status": 200, "function":"insertUserQuery" ,"message":"Entities are successfully added to User Profile", ' . $resultJson . '}]}';
        } else {
            return '{"response": [{ "status": 400, "function":"insertUserQuery" , "message":"ERROR: Entities NOT added to User Profile" }]}';
        }
    }

    public function selectQuery() {


        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                ' SELECT DISTINCT ?concept ?name ?weight
                    WHERE
                {     
                    ?concept rdf:type ex:Concept .
                    ?concept <http://www.example.org#hasWeight> ?weight . 
                    ?concept  <http://www.example.org#hasName> ?name . 
                    FILTER ( ?weight > 8 )
                } GROUP BY ?concept';



        if ($errs = $this->arc_store->getErrors()) {
            echo '{"response": [{ "function":"selectQuery" ,"message":"No data returned", "error:"' . $errs . '}]}';
        }

        $topics = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {

            foreach ($rows as $row) {

                if (isset($row['name'])) {
                    $topics[$row['concept']]['name'] = $row['name'];
                    $topics[$row['concept']]['uri'] = $row['concept'];

                    $topics[$row['concept']]['weight'] = $row['weight'];
                }

                $concept = $row['concept'];
                $name = $row['name'];


                $q = self::PREFIX_EX . ' ' .
                        self::PREFIX_OX . ' ' .
                        self::PREFIX_RDF . ' ' .
                        ' SELECT DISTINCT *
                    WHERE
                {     
                    ?concept rdf:type ex:Concept .
                    ?connection rdf:type ex:Concept .
                    <' . $concept . '> rdf:type ex:Concept .
                    ?concept  <http://www.example.org#hasName> "' . $name . '" .
                    ?connection <http://www.example.org#hasName> ?connectionName .
                    
                    {
                        ?connection <http://www.example.org#isConnectedWith> ?concept . 
                        
                        
                    }UNION{
                        ?concept <http://www.example.org#hasConnectionTo> ?connection .
                    }
                    
                     
                        ?connection <http://www.example.org#hasWeight> ?connectionWeight .
                        FILTER ( ?connectionWeight > 20 )
                    
                     
                        ?concept <http://www.example.org#hasWeight> ?conceptWeight .
                        FILTER ( ?conceptWeight > 20 )
                    
                }';

                if ($connectionErrs = $this->arc_store->getErrors()) {
                    echo("ERROR in SELECT Connection of Concepts: ");
                    print_r($connectionErrs);
                    return;
                }

                $connections = array();
                if ($connectionRows = $this->arc_store->query($q, 'rows')) {

                    foreach ($connectionRows as $connection) {
                        // print_r($connection);

                        if ($connection != $concept) {
                            $connection['connection'] = $connection['connection'];
                            $connection['connectionName'] = $connection['connectionName'];
                            $connection['connectionWeight'] = $connection['connectionWeight'];
                            array_push($connections, $connection);
                        }
                    }
                    $topics[$row['concept']]['connections'] = $connections;
                }
            }
        } else {
            echo '{"response": [{ status: 200, "function":"selectQuery" ,"message":"No data returned", "error:"' . $errs . '}]}';
        }


        return $topics;
    }

    public function selectAllQuery() {


        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                ' SELECT DISTINCT ?concept ?name ?weight
                    WHERE
                {     
                    ?concept rdf:type ex:Concept .
                    OPTIONAL{ ?concept <http://www.example.org#hasWeight> ?weight . }
                    ?concept  <http://www.example.org#hasName> ?name . 
                    
                } GROUP BY ?concept';



        if ($errs = $this->arc_store->getErrors()) {
            echo '{"response": [{ "function":"selectQuery" ,"message":"No data returned", "error:"' . $errs . '}]}';
        }

        $topics = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {

            foreach ($rows as $row) {

                if (isset($row['name'])) {
                    $topics[$row['concept']]['name'] = $row['name'];
                    $topics[$row['concept']]['uri'] = $row['concept'];
                }

                $concept = $row['concept'];
                $name = $row['name'];
                $q = self::PREFIX_EX . ' ' .
                        self::PREFIX_OX . ' ' .
                        self::PREFIX_RDF . ' ' .
                        ' SELECT DISTINCT ?connection ?connectionName
                    WHERE
                {     
                    ?connection rdf:type ex:Concept .
                    ?connection  <http://www.example.org#hasName> ?connectionName . 
                    <' . $concept . '> rdf:type ex:Concept .
                    ?connection  <http://www.example.org#hasName> "' . $name . '" . 

    
                }';

                $q = self::PREFIX_EX . ' ' .
                        self::PREFIX_OX . ' ' .
                        self::PREFIX_RDF . ' ' .
                        ' SELECT DISTINCT *
                    WHERE
                {     
                    ?concept rdf:type ex:Concept .
                    ?connection rdf:type ex:Concept .
                    <' . $concept . '> rdf:type ex:Concept .
                    ?concept  <http://www.example.org#hasName> "' . $name . '" .
                    ?connection <http://www.example.org#hasName> ?connectionName .
                    {
                        ?connection <http://www.example.org#isConnectedWith> ?concept . 
                        
                        
                    }UNION{
                        ?concept <http://www.example.org#hasConnectionTo> ?connection .
                    }
                    
                    
    
                }';

                if ($connectionErrs = $this->arc_store->getErrors()) {
                    echo("ERROR in SELECT Connection of Concepts: ");
                    print_r($connectionErrs);
                    return;
                }

                $connections = array();
                if ($connectionRows = $this->arc_store->query($q, 'rows')) {

                    foreach ($connectionRows as $connection) {
                        // print_r($connection);

                        if ($connection != $concept) {
                            $connection['connection'] = $connection['connection'];
                            $connection['connectionName'] = $connection['connectionName'];

                            array_push($connections, $connection);
                        }


                        // $topics[$row['concept']]['connection'] = $connection['connection'];
                        // $topics[$row['concept']]['connectionName'] = $connection['connectionName'];
                    }
                    $topics[$row['concept']]['connections'] = $connections;
                }
            }
        } else {
            return '{"response": [{ status: 200, "function":"selectQuery" ,"message":"No data returned", "error:"' . $errs . '}]}';
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

            $this->arc_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/Profile_v1.owl>');
        }
        $this->arc_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/Profile_v1.owl>');

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

    public function getMainTopics() {
        return $this->dbpedia_database->main_topic_classification;
    }

}

?>
