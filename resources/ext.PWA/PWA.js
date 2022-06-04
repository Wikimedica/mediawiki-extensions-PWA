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

    // Enable the install button for the currently active PWA.
    $(".pwa-" + mw.config.get("wgCurrentPWAId") + "-install-button").removeClass("pwa-disabled-install-button");

    window.validateCanInstallPWA = function(id) {

        var ua = navigator.userAgent || navigator.vendor || window.opera;

        // Cannot install the app from the Facebook browser.
        if((ua.indexOf("FBAN") > -1) && (ua.indexOf("FBAV") > -1)) {
            alert(mw.message("PWA-install-from-FB-not-allowed", mw.config.get("wgCurrentPWAName"), window.location.href));

            return false;
        }    
        
        if(id != mw.config.get("wgCurrentPWAId")){
            alert(mw.message("PWA-cannot-install", mw.config.get("wgCurrentPWAName")));

            return false; // Cannot install.
        }

        return true; // Can install.
    }

    window.PWAiOSInstall = function(id) {
        
        if(!validateCanInstallPWA(id)) { return; }

        /* Apple's mobile safari does not support native adding to homescreen. Instead, show the user a gif tell them how to add
        * the app manually to their home screen. */
        overlay = $('<div id="pwa-overlay" onclick="$(this).fadeOut();"><div id="pwa-overlay-text">'+ mw.message("PWA-add-to-home-screen", mw.config.get("wgCurrentPWAName")) + '<br><img src ="' + mw.config.get('wgScriptPath')+'/extensions/PWA/resources/ext.PWA/iPhone.gif"/></div></div>');
        $('body').append(overlay);
    }

    window.PWAAndroidInstall = function(id) {

        if(!validateCanInstallPWA(id)) { return; }

        if(window.deferredBeforeInstallPrompt) { window.deferredBeforeInstallPrompt.prompt(); } // Fire the stash event.
    }

    // Initialize deferredPrompt for use later to show browser install prompt.
    let deferredBeforeInstallPrompt;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault(); // Prevent the mini-infobar from appearing on mobile.
        window.deferredBeforeInstallPrompt = e; // Stash the event so it can be triggered later.
        console.log(`'beforeinstallprompt' event was fired.`);
    });
}