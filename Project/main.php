<?php
include_once("MainController.php");
session_start();
?>

<?php
//unset($_SESSION['controller']);
require_once 'processAction.php';
?>


<!DOCTYPE html>
<html>
    <head>
        <title></title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <LINK href="css/styles.css" rel="stylesheet" type="text/css">

        <script type="text/javascript" src="js/jquery-1.8.2.js"></script>
        <script type="text/javascript" src="js/jquery-ui-1.9.0.custom.min.js"></script>
        <!--main script -->
        <script type="text/javascript">
            
            $(document).ready(function(){
                $("#concepts_container").hide();
                $("#keywords_container").hide();
                
                /*submit for keyword extraction*/
                $("#addInterests").submit(function(e){
                    $("#concepts_container").hide();
                    $("#keywords_container").hide();
                    e.preventDefault();
                    
                    $("body").prepend('<div id="loading"></div>');
                   
                    var data = $(this).serialize();
                    
                    var action = "http://localhost/~biancagotthart/masterProject/Project/processAction.php?call=callZemantaAPI&"+data;

                    $.ajax({
                        url: action
                    }).done(function ( response ) {
                        
                    }).success(function (response){
                        $("#loading").remove();
                        
                        console.log("keywords extraction successfully");
                        
                        console.log(response);
                        var jsonObj = JSON.parse(response);
                        
                        var keywordsArray = new Array();
                        var keywordsOutput = "";
                        var lastElement = jsonObj.keywords[(jsonObj.keywords.length - 1)];
                        
                        for (var i = 0; i < jsonObj.keywords.length; i++) { 
                            keywordsArray.push(jsonObj.keywords[i].name); 
                            
                            if(lastElement == jsonObj.keywords[i]){
                                keywordsOutput += jsonObj.keywords[i].name;
                            }else{
                                keywordsOutput += jsonObj.keywords[i].name + ", ";

                            }
                        }
                        

                        $("#keywords").html(keywordsOutput);
                        $("#keywords_container").show();
                        $("#term").val(keywordsOutput);
                        var keywordsHiddenField = "<input id='input_keywords' type='hidden' name='keywords' value='"+keywordsOutput+"'/>";

                        $("#conceptsOfDBpedia").append(keywordsOutput);
                        
                    }).error(function ( jqXHR, textStatus, errorThrown){
                        console.log("error in keyword extraction");
                        
                        $("#loading").remove();
                    });
                      
                });
                
                /*submit for concept extraction of dbpedia*/
                $("#conceptsOfDBpedia").submit(function(e){
                    $("#concepts_container").hide();
                    $("#keywords_container").hide();
                    e.preventDefault();
                   
                    $("body").prepend('<div id="loading"></div>');
                    var data = $(this).serialize();
                   
                    console.log(this);
                    console.log(data);
                    var action = "http://localhost/~biancagotthart/masterProject/Project/processAction.php?saveKeywords="+data;

                    console.log(action);
                    $.ajax({
                        url: action
                    }).done(function ( response ) {
                        
                    }).success(function (response){
                        $("#loading").remove();
                        console.log("concept extraction successfully");
                        console.log(response);
                        
                    }).error(function ( jqXHR, textStatus, errorThrown){
                        console.log("error in concept extraction");
                    });
                      
                });
                
                $("#addTerm").submit(function(e){
                    $("#concepts_container").hide();
                    $("#keywords_container").hide();
                    e.preventDefault();
                   
                    $("body").prepend('<div id="loading"></div>');
                   
                    var data = $(this).serialize();
                    
                    var term = (data.split("term="))[1];
                    var action = "http://localhost/~biancagotthart/masterProject/Project/processAction.php?saveKeywords=" + term;

                    console.log(action);
                    
                    $.ajax({
                        url: action
                    }).done(function ( response ) {
                        
                    }).success(function (response){
                        $("#loading").remove();
                        
                        console.log("concept extraction successfully");
                        var jsonObj = JSON.parse(response);
                        var keywordsArray = new Array();
                        var keywordsOutput = "<p>";
                        
                        
                        
                        for (var key in jsonObj.results[0]) {
                            keywordsArray[key] = new Array();
                            keywordsOutput += key + ": ";
                                
                            var lastElement = jsonObj.results[0][key][(jsonObj.results[0][key].length - 1)];


                            for (var i = 0; i < jsonObj.results[0][key].length; i++) {
                                keywordsArray[key].push(jsonObj.results[0][key][i].name); 
                                    
                                if(lastElement == jsonObj.results[0][key][i]){
                                    keywordsOutput += jsonObj.results[0][key][i].name;
                                }else{
                                    keywordsOutput += jsonObj.results[0][key][i].name + ", ";

                                }
                            }
                            keywordsOutput += "<br>";
                        }
                        keywordsOutput += "</p>";
                       
                        $("#concepts").html(keywordsOutput);
                        $("#concepts_container").show();
                        
                    })
                });
                    

            });


        </script>
    </head>
    <body>
        <div id="left_content">
            <h1>Demo </h1>

            <h1>Interests of Bianca Gotthart</h1>
            <?php
            echo processAction_printFeedsForUser();
            ?>
        </div>

        <div id="right_content">
            <div id="interests">

                <h1>Interests of Bianca Gotthart</h1>
                <?php
                echo processAction_printInterests();
                ?>
            </div>
        </div>
    </body>
</html>
