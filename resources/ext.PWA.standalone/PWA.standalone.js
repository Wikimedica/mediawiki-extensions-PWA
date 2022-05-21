if(navigator.standalone || // Safari
    (window.matchMedia('(display-mode: standalone)').matches) // Chrome
)
{
    // App is in standalone mode.
    
    // Show a loader when the URL is changing (because in standalone mode the browser's loader is not visible).
    $(window).on('beforeunload', function() {
        $('body').append('<div id="pwa-loader" onclick="$(this).hide();"></div>');
    });
}