if(navigator.standalone || // Safari
    (window.matchMedia('(display-mode: standalone)').matches) // Chrome
) {
    // App is in standalone mode.
    
    // Show a loader when the URL is changing (because in standalone mode the browser's loader is not visible).
    $(window).on('beforeunload', function() {
        $('body').append('<div id="pwa-loader" onclick="$(this).hide();"></div>');
    });

    var id = mw.config.get("wgCurrentPWAId");

    // No id was provided by the server, maybe the user modified the URL manually?
    if(!id) { 
        return; // Let the user proceed, the pwa-id will be reinjected in the url when the user closes and reopen the app.
    }

    /* If the main page differs from the start_url (as stated in the manifest, replace links to the main page with links to the start_url. */
    var currentMainPagePath = mw.config.get('wgArticlePath').replace('$1', mw.message('mainpage'));
    var start_url = mw.config.get('wgCurrentPWAStartUrl');
    
    if(currentMainPagePath != start_url) { // If the URLs do not match.
        $('a[href="' + currentMainPagePath + '"]').attr('href', start_url); // Redirect all links to the main page to start_url.
    }

    /*
     * Adds the current PWA id to a URL. 
     */
    function injectPWAId(href) {
        
        if(href[0] == '#') { return; } // Do nothing on fragments.
        if(href.indexOf('javascript') == 0) { return; } // Do nothing on JS code.
        if(href.indexOf('mailto') == 0) { return; } // Do nothing on mailto links.

        if(href[href.length - 1] == '/' && href.indexOf('?') == -1) { href = href + '?pwa-id=' + id; }
        else {

            // Make sure the protocol and hostname are part of the url (otherwise the URL object will throw an error).
            if(href.indexOf(window.location.protocol) != 0) { href = window.location.protocol + '//' + window.location.hostname + href; }
            
            try { href = new URL(href); }
            catch (e) {
                console.log(href + " cannot be processed.")
                throw e;
            }

            href.searchParams.set('pwa-id', id);
        }

        return href;
    };

    /* From one page request to the next, there must be a way for us to know the user is using which PWA.
     * Use a URL parameter for that effet and make sure it's always added to the next URL. */
    $('a').click(function(e) {
       $(this).attr('href', injectPWAId($(this).attr("href")));
    });

    // Do the same with form actions.
    $('form').each(function() { 
        $(this).attr('action', injectPWAId($(this).attr("action"))); 
    });
}
else { // Not in PWA mode.
    
}