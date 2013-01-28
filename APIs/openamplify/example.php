<?php
// REMOVE THE .txt EXTENSION FROM THIS FILE NAME IF YOU WANT TO USE IT AS A PHP FILE.

// If you don't have an API key, you can register here: http://www.openamplify.com/member/register
$apiKey = 'n3qfmujka7vw9te7jmujkxrqd3n2q8cf';						// Insert your API key here.
$analysis = 'all';						// all (default), actions, topics, demographics, styles.
$outputFormat = 'xml';					// xml (default), json, dart.
// select either this option or the following... not both.
//$sourceURL = 'http://www.example.com';
$inputText = 'Lorem ipsum...';

// This example uses the Curl library for URL access as it provides a lot of utility.
// The following section is all Curl specific.  If you use another method of 
//		sending the request you can safely ignore the following.
// ******************** BEGIN CURL SPECIFIC CODE ********************
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://portaltnx20.openamplify.com/AmplifyWeb_v21/AmplifyThis');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

// Build the POST variables. 
$post = 'apiKey='  . urlencode($apiKey) . 
		'&analysis=' . urlencode($analysis) . 
		'&outputFormat=' . urlencode($outputFormat) .
//		'&sourceURL=' . urlencode($sourceURL);
		'&inputText=' . urlencode($inputText); // 

curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post); 
$result = curl_exec ($ch);
curl_close ($ch);

die($result);
// ******************** END CURL SPECIFIC CODE ********************

// raw dump of XML.
// echo $result . "\n";

// initialize a SimpleXML object.
$xml = new SimpleXMLElement($result);

// Now just do some XPath searches.
echo "Topics:\n";
foreach ($xml->xpath('//Topic') as $n) {
    echo "\t" . $n->Name . "\n";
}

echo "Actions:\n";
foreach ($xml->xpath('//Action') as $n) {
    echo "\t" . $n->Name . "\n";
}

echo "Demographics:\n";
foreach ($xml->xpath('//Demographics') as $n) {
	echo "\tAge: " . $n->Age->Name . "\n";
	echo "\tGender: " . $n->Gender->Name . "\n";
	echo "\tEducation: " . $n->Education->Name . "\n";
}

echo "Style:\n";
foreach ($xml->xpath('//Styles') as $n) {
	echo "\tSlang: " . $n->Slang->Name . "\n";
	echo "\tFlamboyance: " . $n->Flamboyance->Name . "\n";
}
?>