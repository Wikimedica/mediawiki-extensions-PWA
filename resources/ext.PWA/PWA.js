
if(!navigator.standalone || // Safari
    !(window.matchMedia('(display-mode: standalone)').matches) // Chrome
)
{
    // App is not in standalone mode.
    
    // Register the service worker if it's supported.
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function(registrations) {
            // Unregister all service workers before registering.
            registrations.forEach(function(r) { r.unregister(); });
        });
    }

    console.log('Registering service worker.') ;
        
    /* The folder a service worker sits in determines it's scope. Hence, if we want the service loader to apply to the whole wiki, we need
    * to define it by calling MediaWiki's index.php manually using a service worker associated with the current PWA. */
    navigator.serviceWorker.register(mw.config.get("wgScriptPath") + "/index.php?title=MediaWiki:PWA-" + mw.config.get("wgCurrentInstallablePWAId") + "-serviceWorker.js&action=raw&ctype=text/javascript");
    /* Do not register a service worked in standalone mode. The effect will be that each PWA will have its own service worker code. 
    
    Note sure if this works though ...
    */

    var ua = navigator.userAgent || navigator.vendor || window.opera;

    // If this is not the Facebook browser.
    if(!((ua.indexOf("FBAN") > -1) || (ua.indexOf("FBAV") > -1))) {

        // Enable the install button for the currently active PWA.
        $(".pwa-" + mw.config.get("wgCurrentInstallablePWAId") + "-install-button").removeClass("pwa-disabled-install-button");
    } 

    window.validateCanInstallPWA = function(id) {

        var ua = navigator.userAgent || navigator.vendor || window.opera;

        // Cannot install the app from the Facebook browser.
        if((ua.indexOf("FBAN") > -1) || (ua.indexOf("FBAV") > -1)) {
            alert(mw.message("PWA-install-from-FB-not-allowed", mw.config.get("wgCurrentInstallablePWAName"), window.location.href));

            return false;
        }    
        
        /* We could check if the PWA exists in the config, but this would mean adding more JS code client side. Skip that for now and just show a cannot install message.
        if(mw.config.get("wgPWAConfigs")[id] === null) {
            alert("pwa does not exist");

            return false;
        }*/
        if(id != mw.config.get("wgCurrentInstallablePWAId")){
            alert(mw.message("PWA-cannot-install", mw.config.get("wgCurrentInstallablePWAName")));

            return false; // Cannot install.
        }

        return true; // Can install.
    }

    window.PWAiOSInstall = function(id) {
        
        if(!validateCanInstallPWA(id)) { return; }

        /* Apple's mobile safari does not support native adding to homescreen. Instead, show the user a gif tell them how to add
        * the app manually to their home screen. */
        overlay = $('<div id="pwa-overlay"><div id="pwa-overlay-text">'+ mw.message("PWA-add-to-home-screen", mw.config.get("wgCurrentInstallablePWAName")) + '<br><img src ="' + mw.config.get('wgScriptPath')+'/extensions/PWA/resources/ext.PWA/iPhone.gif"/></div></div>');
        $('body').append(overlay);

        /* Safari does not care about the start_url parameter in the manifest. Instead, it used the current URL as the start URL.
         * to have the pwa-id in the url, we thus need to inject right before the user adds the page to its homescreen.
         * see: https://stackoverflow.com/questions/42379228/web-app-manifest-start-url-doesnt-work-for-safari
         */

        const newUrl = new URL(window.location);
        newUrl.searchParams.set('pwa-id', id);
        const oldUrl= new URL(window.location);

        history.replaceState(history.state, "", newUrl);

        $(overlay).click(function() {
            $(this).fadeOut();
            history.replaceState(history.state, "", oldUrl);
        });
    }

    window.PWAAndroidInstall = function(id) {

        if(!validateCanInstallPWA(id)) { return; }

        if(window.deferredBeforeInstallPrompt) { window.deferredBeforeInstallPrompt.prompt(); } // Fire the stashed event.
    }

    // Initialize deferredPrompt for use later to show browser install prompt.
    let deferredBeforeInstallPrompt;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault(); // Prevent the mini-infobar from appearing on mobile.
        window.deferredBeforeInstallPrompt = e; // Stash the event so it can be triggered later.
        console.log(`'beforeinstallprompt' event was fired.`);
    });
}