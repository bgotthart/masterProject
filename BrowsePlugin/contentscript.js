console.log("contentscript:");

var url = document.URL;
 
chrome.extension.sendMessage({
    url: url
}, function(response) {

});
     