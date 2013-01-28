        console.log("contentscript:");

var url = document.URL;
    
    chrome.extension.sendMessage({url: url}, function(response) {
        console.log("contentscript response:");
        console.log(response);

    });
     