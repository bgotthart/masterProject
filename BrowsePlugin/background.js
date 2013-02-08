
function sendRequest(url){
    console.log("sending..." + url);
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "http://localhost/~biancagotthart/masterProject/Project/processAction.php", true);
    xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
  
    xhr.onreadystatechange = function (aEvt) {
         if (xhr.readyState == 4) {
             if (xhr.status == 200){

                 var jsonResponse = xhr.responseText;
                 console.log("resposne 1");
                console.log(jsonResponse);

             }else{
                 var jsonResponse = xhr.response;
                 console.log("resposne 2");
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
         url += "http://" + url;
     }else{
         console.log("Working on: " + url);
     }
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
                return;
            }else{
                console.log("background is off");

                return;
            }
        });
  
    }
});