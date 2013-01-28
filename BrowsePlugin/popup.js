// Copyright (c) 2012 The Chromium Authors. All rights reserved.
// Use of this source code is governed by a BSD-style license that can be
// found in the LICENSE file.

var baseUrl = "http://localhost/IM/MasterThesis/masterthesis";

jQuery(document).ready(function(){
   jQuery("#turnoff").click(function(){
        console.log("turnoff");

        chrome.browserAction.setBadgeText({text: "OFF"});

   });
   jQuery("#turnon").click(function(){
        console.log("turnon");
        chrome.browserAction.setBadgeText({text: ""});
   });
   
   jQuery("#profile").click(function(){
      chrome.tabs.create({url:baseUrl+"index.php"}); 
   });
});
//chrome.runtime.onBrowserStartup
//
//call eventpage / backgroundpage .js
//chrome.runtime.getBackgroundPage()
/*
chrome.runtime.onInstalled.addListener(function() {
    console.log("onInstalled");	
	chrome.browserAction.setBadgeText({text: "ON"});
});

*/

/*
chrome.tabs.onCreated.addListener(function(tab) {
	console.log("onCreated");	

	chrome.browserAction.setBadgeText({text: "ON"});

 });
 */
