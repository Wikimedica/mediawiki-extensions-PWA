

// Register the service worker.
if ('serviceWorker' in navigator) {
    /* The folder a service worker sits in determines it's scope. Hence, if we want the service loader to apply to the whole wiki, we need
     * to define it by calling MediaWiki's index.php manually using a service worker associated with the current PWA. */
    navigator.serviceWorker.register(mw.config.get("wgScriptPath") + "/index.php?title=MediaWiki:PWA-" + mw.config.get("wgCurrentPWA") + "-serviceWorker.js&action=raw&ctype=text/javascript");
}

// Loads the add-to-homescreen vendor package (defined in vendor).
 mw.loader.using(["ext.PWA.add-to-homescreen"]).then(function() {
    debugger;
    addToHomescreen({
        debug: true,
        autostart: true
    });
});