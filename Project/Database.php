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
require_once("DBpediaDatabase_Local.php");
require_once("DBpediaDatabase_Online.php");
require_once("TermItem.php");

class DatabaseClass {

    private $arc_store;
    private $dbpedia_store;

    const EX_URL = "http://www.example.org/";
    const PREFIX_EX = "PREFIX ex: <http://www.example.org#>";
    const PREFIX_RDF = "PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>";
    const PREFIX_OX = "PREFIX owl: <http://www.w3.org/2002/07/owl#>";

    private $dbpedia_database;
    private $dbpedia_database2;
    private $user_id = "http://www.example.org#BiancaGotthart";

    public function __construct() {

        $this->initDBStore();
        $this->initDBpediaDump();

        $this->dbpedia_database = new DBpedia_DatabaseClass();
        $this->dbpedia_database2 = new DBpedia_DatabaseOnlineClass($this->dbpedia_store);

        $this->loadConfigFile();
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

        if ($saveConcepts == null) {
            return '{"response": [{ "status": 400, "function":"insertUserQuery" ,"message":"Not saved"}]}';
        }

        $errors = false;

        $config_xml = $this->loadConfigFile();


        $resultJson = '"results": [{';
        $lastConcept = end($saveConcepts);
        foreach ($saveConcepts as $key => $value) {


            $last = end($value)->getUri();
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
            foreach ($value as $node) {

                $data = $node->getUri();

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
                  <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>    <http://www.example.org#Concept> .';

                foreach ($value as $node2) {
                    $data2 = $node2->getUri();
                    if ($data2 != $data) {
                        $insert .= '<' . $data . '> <http://www.example.org#hasConnectionTo> <' . $data2 . '> . ';
                        $insert .= '<' . $data2 . '>  <http://www.example.org#hasConnectionTo> <' . $data . '> . ';
                    }
                }

                $insert .= '}';


                $result = $this->arc_store->query($insert, '', '', true);
                if ($errs = $this->arc_store->getErrors()) {
                    echo("error");
                    print_r($errs);
                    $errors = true;
                }
            }


            if ($lastConcept[0]->getUri() == $value[0]->getUri()) {
                $resultJson .= "]";
            } else {
                $resultJson .= "],";
            }
        }

        $resultJson .= "}]";

        if (!$errors) {
            $response = '{"response": [{ "status": 200, "function":"insertUserQuery" ,"message":"Entities are successfully added to User Profile", ' . $resultJson . '}]}';
            echo($response);
            return $response;
        } else {
            $response = '{"response": [{ "status": 400, "function":"insertUserQuery" , "message":"ERROR: Entities NOT added to User Profile" }]}';
            echo($response);
            return $response;
        }
    }

    private function getConnectionWeightOfTerms() {
        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                'SELECT DISTINCT *
                    WHERE
                    {
                        ?connection rdf:type ex:Connection .
                        ?concept rdf:type ex:Concept .
                        ?connection ex:connectionHasConcept ?concept .
                        ?connection ex:hasConnectionWeight ?weight .
                        ?concept ex:hasName ?name .
    
                    }';

        if ($errs = $this->arc_store->getErrors()) {
            echo '{"response": [{ "function":"selectQuery" ,"message":"Error in SELECT", "error:"' . $errs . '}]}';
            print_r($errs);
        }

        $topics = array();
        $connections = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {

            foreach ($rows as $row) {
                if (!isset($connections[$row['connection']])) {
                    $connections[$row['connection']] = array();
                }
                if (!isset($connections[$row['connection']]['concept'])) {
                    $connections[$row['connection']]['concept'] = array();
                }
                $concept = array();
                $concept['name'] = $row['name'];
                $concept['uri'] = $row['concept'];
                array_push($connections[$row['connection']]['concept'], $concept);
            }
        } else {
            echo '{"response": [{ status: 200, "function":"selectQuery" ,"message":"No data returned"}]}';
            print_r($errs);
        }
        return $connections;
    }

    public function selectQuery() {

        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                'SELECT DISTINCT ?concept ?name (COUNT(?connection) as ?weight)
                    WHERE
                    {
                        ?concept rdf:type ex:Concept .
                        ?concept  <http://www.example.org#hasName> ?name .
                        ?concept <http://www.example.org#hasConnectionTo> ?connection .
                        
                        
                         
                    } GROUP BY ?concept ORDER BY DESC(xsd:integer(?weight))';


        if ($errs = $this->arc_store->getErrors()) {
            echo '{"response": [{ "function":"selectQuery" ,"message":"Error in SELECT", "error:"' . $errs . '}]}';
            print_r($errs);
        }

        $topics = array();
        $connections = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {

            foreach ($rows as $row) {

                if (isset($row['name'])) {
                    $topics[$row['concept']]['name'] = $row['name'];
                }

                $topics[$row['concept']]['uri'] = $row['concept'];


                if (isset($row['weight']) && $row['weight'] > 0) {
                    if (!isset($topics[$row['concept']]['weight'])) {
                        $topics[$row['concept']]['weight'] = $row['weight'];
                    } else {
                        $weight = $topics[$row['concept']]['weight'];
                        $weight += $row['weight'];
                        $topics[$row['concept']]['weight'] = $weight;
                    }
                }
            }
        } else {
            echo '{"response": [{ status: 200, "function":"selectQuery" ,"message":"No data returned"}]}';
            print_r($errs);
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

        $dbpedia_config = array(
            'db_host' => (string) $config_xml->database->db_host,
            'db_name' => (string) $config_xml->database->db_name,
            'db_user' => (string) $config_xml->database->db_user,
            'db_pwd' => (string) $config_xml->database->db_pwd,
            'store_name' => 'dbpedia_store',
            'endpoint_features' => array(
                'select', 'construct', 'ask', 'describe', // allow read
                'load', 'insert', 'delete', // allow update
                'dump' // allow backup
            ),
        );
        $this->dbpedia_store = ARC2::getStore($dbpedia_config);
        if (!$this->dbpedia_store->isSetUp()) {
            $this->dbpedia_store->setUp();

            $this->dbpedia_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/skos_categories_en.nt>');
        }

        // $this->dbpedia_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/article_categories_en.nt>');
        //$this->dbpedia_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/skos_categories_en.nt>');

        if ($errs = $this->dbpedia_store->getErrors()) {
            echo("ERROR in INIT DBpedia Dump: ");
            print_r($errs);
        }
    }

    private function loadConfigFile() {
        return simplexml_load_file("../config/config.xml");
    }

    /*     * **** debugging methods **** */

    public function getMainTopics() {
        print_r($this->dbpedia_database->main_topic_classification);
    }

    public function selectAllDBpedia() {

        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                'SELECT DISTINCT * WHERE {
                ?x
                <http://www.w3.org/2004/02/skos/core#broader>
                <http://dbpedia.org/resource/Category:Main_topic_classifications>
                }';


        if ($errs = $this->dbpedia_store->getErrors()) {
            echo '{"response": [{ "function":"selectQuery" ,"message":"No data returned", "error:"' . $errs . '}]}';
        }
        $table = "<table>";
        if ($rows = $this->dbpedia_store->query($q, 'rows')) {

            foreach ($rows as $row) {

                $table .= "<tr>";
                $table .= "<td>" . $row['x'] . "</td>";
                $table .= "</tr>";
            }
        }
        $table .= "</table>";
        print_r($table);
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
                    if (isset($row['weight'])) {
                        $topics[$row['concept']]['weight'] = $row['weight'];
                    }
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

}

?>
