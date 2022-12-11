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
        
        if(href[0] == '#') { return href; } // Do nothing on fragments.
        if(href.indexOf('javascript') == 0) { return href; } // Do nothing on JS code.
        if(href.indexOf('mailto') == 0) { return href; } // Do nothing on mailto links.S
        /* if(href.indexOf('//') != -1 // If this URL contains a domain.
            && href.indexOf(window.location.host) == -1 // If this is an external URL.
            ) { 
                return href;
        }*/

        try { href = new URL(href); }
        catch (e) {
            // Make sure the protocol and hostname are part of the url (otherwise the URL object will throw an error).
            href = window.location.protocol + '//' + window.location.hostname + href;

            try { href = new URL(href); }
            catch (e) {
                // URL is defective.
                console.log(href + " cannot be processed.")
                throw e;
            }
        }
        
        if(href.host != window.location.hostname) { return href; } // This is an externat URL, don't touch it.

        href.searchParams.set('pwa-id', id); // Append the PWA's id.

        return href;
    };

    /* From one page request to the next, there must be a way for us to know the user is using which PWA.
     * Use a URL parameter for that effet and make sure it's always added to the next URL. */
    $('a').click(function(e) {
       $(this).attr('href', injectPWAId($(this).attr("href")));

       // injectPWAId is defined inthe PWA.js file.
    });

    // Do the same with form actions.
    $('form').each(function() { 
        $(this).attr('action', injectPWAId($(this).attr("action"))); 
    });


    // Create an observer instance to inject the pwa-id parameter to URLs that are created dynamically.
    const observer = new MutationObserver((mutationList, observer) => {
        for (const mutation of mutationList) {
            if (mutation.type === 'childList') {
                // Each time a new set of notes is added, traverse them to add events that append a URL parameter to their links.

                console.log('A child node has been added or removed.');

                $(mutation.target).find('a').click(function(e) {
                    $(this).attr('href', injectPWAId($(this).attr("href")));
                });

                $(mutation.target).find('form').each(function() { 
                    $(this).attr('action', injectPWAId($(this).attr("action"))); 
                });
            } 
            /*else if (mutation.type === 'attributes') {
                console.log(`The ${mutation.attributeName} attribute was modified.`);
            }*/
        }
    });

    // Start observing the target node for configured mutations.
    observer.observe($('body').get(0), { attributes: true, childList: true, subtree: true });

} else { // Not in PWA mode.
    
}