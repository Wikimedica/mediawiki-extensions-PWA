

// Register the service worker if is supported.
if ('serviceWorker' in navigator) {
    /* The folder a service worker sits in determines it's scope. Hence, if we want the service loader to apply to the whole wiki, we need
     * to define it by calling MediaWiki's index.php manually using a service worker associated with the current PWA. */
    navigator.serviceWorker.register(mw.config.get("wgScriptPath") + "/index.php?title=MediaWiki:PWA-" + mw.config.get("wgCurrentPWA") + "-serviceWorker.js&action=raw&ctype=text/javascript");
}

// Loads the add-to-homescreen vendor package (defined in vendor).
 mw.loader.using(["ext.PWA.add-to-homescreen"]).then(function() {
    debugger;

    addToHomescreen({
        appID: mw.config.get("wgCurrentPWA"),
        appName: mw.config.get("wgCurrentPWA"),
        debug: true,
        autostart: true,
        customPrompt: {
            title: "Installer ?",
            src: "meta/favicon-96x96.png",
            cancelMsg: "Pas maintenant",
            installMsg: "Installer"
        },
        onBeforeInstallPrompt: function(platform) {
            debugger;
            return platform.beforeInstallPrompt.prompt() 
            .then( function ( evt ) { 
                // Wait for the user to respond to the prompt 
                return platform.beforeInstallPrompt.userChoice; } )
                .then( function ( choiceResult ) { //do stuff here 
                } ) 
            .catch( function ( err ) { 
                if ( err.message.indexOf( "user gesture" ) > -1 ) { 
                    //recycle, but make sure there is a user gesture involved 
                } else if ( err.message.indexOf( "The app is already installed" ) > -1 ) { 
                    //the app is installed, no need to prompt, but you may need to log or update state values 
                } else { 
                    return err; 
                } 
            }); 
        }
    });
});