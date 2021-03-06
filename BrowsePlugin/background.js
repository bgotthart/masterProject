var activated = true;

function sendRequest(url){
    
    if(activated){
        
        console.log("sending..." + url);
    
        var xhr = new XMLHttpRequest();
    
        xhr.open("POST", "http://localhost/~biancagotthart/masterProject/Project/processAction.php", true);
        xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
  
        xhr.onreadystatechange = function (aEvt) {
            if (xhr.readyState == 4) {
                if (xhr.status == 200){

                    var jsonResponse = xhr.responseText;
                    console.log("Saving concepts successfully done!");
                //console.log(jsonResponse);

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
            } else{
             
            }      
        }; 
        if(url.substring(0,4) != "http"){
            url += "http://" + url;
        }else{
            console.log("Working on: " + url);
        }
        xhr.send("call=handlingAPIRequest&url="+url);
    }else{
        console.log("plugin is deactivated");
    }
}

chrome.extension.onMessage.addListener(
    function(request, sender, sendResponse) {
        sendRequest(request.url);
       
    });
    
chrome.tabs.onUpdated.addListener(function(tabId, changeInfo, tab) {

    console.log(changeInfo.status);
    if (changeInfo.status == 'complete') {
        // Execute some script when the page is fluly (DOM) ready
        chrome.browserAction.getBadgeText({}, function(result){

            if(result != "OFF"){ 
                activated = true;
                return;
            }else{
                activated = false;

                return;
            }
        });
  
    }
});