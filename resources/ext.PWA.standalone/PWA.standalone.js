if(navigator.standalone || // Safari
    (window.matchMedia('(display-mode: standalone)').matches) // Chrome
)
{
    // App is in standalone mode.
    
    // Show a loader when the URL is changing (because in standalone mode the browser's loader is not visible).
    $(window).on('beforeunload', function() {
        $('body').append('<div id="pwa-loader" onclick="$(this).hide();"></div>');
    });

    /* Tell the server we are in PWA mode and which PWA is in use (so changes can be tagged accordingly in RecentChanges or usage tracked in site stats).
     * Use a cookie for that. The WebRequest API (https://developer.mozilla.org/en-US/docs/Mozilla/Add-ons/WebExtensions/Intercept_HTTP_requests)
     * would be ideal, but not properly supported by Apple. */
    document.cookie = 'PWAId=' + mw.config.get("wgCurrentPWAId") + ';path=/ ;max-age=3600';
}