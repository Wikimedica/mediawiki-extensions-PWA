// Register the service worker if it's supported.
if ('serviceWorker' in navigator) {
    /* The folder a service worker sits in determines it's scope. Hence, if we want the service loader to apply to the whole wiki, we need
     * to define it by calling MediaWiki's index.php manually using a service worker associated with the current PWA. */
    navigator.serviceWorker.register(mw.config.get("wgScriptPath") + "/index.php?title=MediaWiki:PWA-" + mw.config.get("wgCurrentPWAId") + "-serviceWorker.js&action=raw&ctype=text/javascript");
}

if(!navigator.standalone || // Safari
    !(window.matchMedia('(display-mode: standalone)').matches) // Chrome
)
{
    // App is not in standalone mode.

    // Loads the add-to-homescreen vendor package (defined in vendor).
    mw.loader.using(["ext.PWA.add-to-homescreen"]).then(function() {

            var platform;
            
            addToHomescreen({
                appID: mw.config.get("wgCurrentPWAId"),
                appName: mw.config.get("wgCurrentPWAName"),
                debug: true,
                autostart: true,
                customPrompt: {
                    title: "Installer ?",
                    src: "meta/favicon-96x96.png",
                    cancelMsg: "Pas maintenant",
                    installMsg: "Installer"
                },
                onCanInstall: function(p, _i) {
                    
                    if(p.isStandalone) {
                        return; // Already within the PWA app.
                    }
                    
                    platform = p;

                    if(mw.config.get("wgMFAmc") != undefined) { // Only show the install prompt in mobile mode.
                        $('#pwa-prompt').fadeIn(); // Show the install prompt.
                    }
                },
                onBeforeInstallPrompt: function(platform) {
                    // Nothing done here, the beforeInstallPrompt event was halted by the addtohomescreen.js library so we can trigger it later.
                }
            });

        if(mw.config.get("wgMFAmc") != undefined) // Only defined in mobile mode.
        {

            $('#pwa-install').click(function() {
                // User clicked the install button.
                
                if(platform.isIDevice){ // If we are on a IDevice
                    /* Apple's mobile safari does not support native adding to homescreen. Instead, show the user a gif tell them how to add
                    * the app manually to their home screen. */
                    overlay = $('<div id="pwa-overlay" onclick="$(this).fadeOut();"><div id="pwa-overlay-text">'+ mw.message("PWA-add-to-home-screen", mw.config.get("wgCurrentPWAName")) + '<br><img src ="' + mw.config.get('wgScriptPath')+'/extensions/PWA/resources/ext.PWA/iPhone.gif"/></div></div>');
                    $('body').append(overlay);
                    $('#pwa-prompt').fadeOut(); // Hide the install prompt.

                    return;
                }

                if(platform.beforeInstallPrompt) {
                    return platform.beforeInstallPrompt.prompt() 
                    .then( function ( evt ) { 
                        debugger;
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
        }
    });
}