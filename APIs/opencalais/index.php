<?php

/**
 * Example usage for the Open Calais Tags class written by Dan Grossman
 * (http://www.dangrossman.info). Read about this class and how to get
 * an API key at http://www.dangrossman.info/open-calais-tags
 */
//require('opencalais_module/opencalais.php');

require('OpenCalais.class.php');

$configxml = simplexml_load_file("../../config/config.xml");
$apikey = (string) $configxml->apis->opencalais->apikey;

$oc = new OpenCalais($apikey, "bgotthart", 0.49);
/*
  $oc->getGenericRelations = true;
  $oc->getSocialTags = true;
  $oc->contentType = "TEXT/HTML";
  $oc->outputFormat = "XML/RDF";
 */

if (isset($_GET['url'])) {
    $url = $_GET['url'];
} else {
    $url = "http://news.google.com/news/url?sa=t&fd=R&usg=AFQjCNEDrgL2u-EM3leLDxFQMgLbZo4vKA&url=http://abcnews.go.com/Sports/wireStory/ryan-throws-td-passes-falcons-rout-giants-34-17992741";
}
print_r($url);
$content = file_get_contents($url);

$entities = $oc->parse($content);

//print_r($entities);


echo("topic");
if (count($entities['topics']) > 0) {
    foreach ($entities['topics'] as $topic) {
        print_r($topic);
    }
}
echo("entities");
if (count($entities['entities']) > 0) {
    foreach ($entities['entities'] as $entity) {
        print_r($entity);
    }
}

echo("socialtags");
if (count($entities['socialTags']) > 0) {
    foreach ($entities['socialTags'] as $socialTag) {
        print_r($socialTag);
    }
}
return $entities;

/*
foreach ($entities as $type => $values) {

    echo "<b>" . $type . "</b>";
    echo "<ul>";

    foreach ($values as $entity) {
        echo "<li>" . $entity . "</li>";
    }

    echo "</ul>";
}
*/