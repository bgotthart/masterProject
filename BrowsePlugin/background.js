console.log("background");


chrome.tabs.getSelected(null, function(tab) {
    myFunction(tab.url);
});

function myFunction(tablink) {
  // do stuff here
  console.log(tablink);
}


function sendRequest(url){
    console.log("sending...");
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "http://localhost/IM/masterthesis/Project/processAction.php", true);
    xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
  
    xhr.onreadystatechange = function (aEvt) {
         if (xhr.readyState == 4) {
             if (xhr.status == 200){

                 var jsonResponse = xhr.responseText;
                 
                 console.log(jsonResponse);
                 //console.log(jsonResponse.response[0].message);

             }else{
                 var jsonResponse = xhr.response;
                 
                 console.log(jsonResponse.response[0].message);
                 
                chrome.browserAction.setBadgeText ( {
                     text: "ERR"
                 } );
                 setTimeout(function () {
                     chrome.browserAction.setBadgeText( {
                         text: ""
                     } );
                 }, 2000);
             }
         }        
     }; 
     if(url.substring(0,4) != "http"){
         console.log("NO HTTP " + url);
         url += "http://" + url;
         console.log("FIXED " + url);

     }else{
         console.log("Working on: " + url);
     }
     url = url + 
     xhr.send("call=handlingAPIRequest&url="+url);

}

chrome.extension.onMessage.addListener(
    function(request, sender, sendResponse) {
        console.log("background onMessage");

        sendRequest(request.url);
       
});

chrome.tabs.onUpdated.addListener(function(tabId, changeInfo, tab) {

    console.log(changeInfo.status);
    if (changeInfo.status == 'complete') {
        // Execute some script when the page is fluly (DOM) ready
        chrome.browserAction.getBadgeText({}, function(result){

            if(result != "OFF"){
                console.log("background is on");

                chrome.tabs.executeScript(null, {file:"contentscript.js"}, function(){
                console.log("Callback executeScript!!");
                
                });
            }else{
                console.log("background is off");

                return;
            }
        });
        
        
        
    }
});