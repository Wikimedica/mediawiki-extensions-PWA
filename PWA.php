<?php
/**
 * PWA extension main class
 *
 * @file
 * @ingroup Extensions
 * @author Antoine Mercier-Linteau
 * @license GPL-2
 */

namespace MediaWiki\Extension\PWA;

use OOUI;

/**
 * PWA extension class.
 */
class PWA {

	/** @param string|null caching var for _getPWAId() */
	private static $_pwaId = null;
	
	/**
	 * @return string|null the currently active PWA id or null if no PWA is in use.
	 */
	private static function _getPWAId() {
		global $wgRequest;

		if(!self::$_pwaId) { 
			if(defined('MW_ENTRY_POINT') && MW_ENTRY_POINT == 'api') {
				// If this is an API call, the URL will not contain the pwa-id, get it from session.
				self::$_pwaId = $wgRequest->getSession()->get('pwa-id');
			} else {
				// Get the pwa-id from the request URL.
				self::$_pwaId = $wgRequest->getQueryValuesOnly()['pwa-id'] ?? false;
				
				// Set the pwa-id in session so it can be used by API calls. Should the pwa-id dissapear (ie: the user uses both the browser and the app at the same time), it will be removed.
				self::$_pwaId ? $wgRequest->getSession()->set('pwa-id', self::$_pwaId): $wgRequest->getSession()->remove('pwa-id');
				$wgRequest->getSession()->save();
			}
		 }

		return self::$_pwaId;
	}
	
	/**
	 * Called before a page displayed.
	 * @param OutputPage $out
	 * @param Skin $skin
	*/
	public static function onBeforePageDisplay(&$out, &$skin) {
		$globalConfig = $skin->getConfig();

		// Add a specific CSS stylesheet in standalone (PWA) mode depending on wether the desktop or mobile skin is used.
		$out->addStyle(
			$globalConfig->get( 'ScriptPath' ) .
				'/index.php?title=MediaWiki:'.($skin->getSkinName() == $globalConfig->get( 'PWAMobileSkin' ) ?
				'PWA-mobile.css': 'PWA-common.css').'&action=raw&ctype=text/css', 'standalone');

		// Register some JS and CSS for standalone mode. This code should be in the service worker but until I get a better grasp of how they work is will be included in every page.
		$out->addModuleStyles('ext.PWA.standalone.css'); // Add the CSS before the JS is loaded.
		$out->addModules('ext.PWA.standalone.js'); // This will add the JS.
		
		// If this request was made within the context of a PWA.
		if($PWAId = self::_getPWAId()) {

			/* Figuring out if a PWA is in use is easy, we just check in JS if the navigator in is standalone mode.
			 * Figuring out which PWA is in use is hard for the following reasons:
			 * - there is no JS call that will let us know which manifest is in use
			 * - cookies are shared between all PWAs and broswer tabs
			 * - indexDB and the Web Storage API are persisted over a domain
			 * - ServiceWorkers work within a domain over scope (if two PWAs share the same scope, it does not work).
			 * 
			 * What remains is trying to do it with URL. To that effet, a pwa-id parameter is provided in the start_url of the manifest and it is then added to every subsequent URL. */

			$configs = $globalConfig->get( 'PWAConfigs' ); 
			if(!isset($configs[$PWAId])) { 

				/* The id could not be found, maybe the user modified the URL manually ? 
				 * Show an error page and prompt the user to close the PWA and re-open it (thus inserting the pwa-id in the start_url again). */
				wfDebugLog ('PWA', 'Invalid PWA id supplied in the URL parameter.'); 
				$out->showErrorPage(wfMessage('PWA-invalid-pwa-id-title'), wfMessage('PWA-invalid-pwa-id-message'));

				return;
			}

			// Retrieve the manifest for this PWA.
			$manifest = json_decode(wfMessage($configs[$PWAId]['manifest'])->text());

			// Expose the start_url parameter.
			$out->addJsConfigVars('wgCurrentPWAStartUrl', $manifest->start_url);
			// Expose the PWA id trough js (probably safer than to retrieve it from the URL client side).
			$out->addJsConfigVars('wgCurrentPWAId', $PWAId);


		} else { // This request was not made within the context of a PWA, present the user with all the code required to install the PWA.

			// Loop over all configured PWAs to check which one we should use for the requested page.
			foreach ($globalConfig->get( 'PWAConfigs' ) as $id => $config){
				if(!$config) { continue; } // If that PWA has been turned off (useful for disabling the default PWA [since the provide_default merge strategy only works in MW > 1.35.3]).

				// Format the $pattern parameter.
				if(!isset($config['patterns'])) {
					wfDebugLog( 'PWA', "$id PWA missing the pattern configuration entry." );
					continue; // Misconfigured property, skip this PWA.
				} else {
					// Make sure patterns is an array.
					$patterns = $config['patterns'];
					$patterns = is_string($patterns) ? [$patterns]: $patterns;
				}

				$title = $out->getTitle()->getFullText();

				// Check if the current page's title matches the pattern.
				foreach($patterns as $pattern)
				{
					$pattern = str_replace('_', ' ', $pattern); // Space and _ are considered equivalent in page titles, Title does no include _ in page names.
					
					if(!preg_match($pattern, $title)) { continue; }
					// The PWA matches the title.
			
					if(!isset($config['manifest'])) {
						wfDebugLog( 'PWA', "$id PWA missing the manifest configuration entry." );
						continue 2; // Misconfigured property, skip this PWA.
					}

					$manifestUrl = $config['manifest'];

					if(!\Title::newFromText($manifestUrl, NS_MEDIAWIKI)->exists()) { // If the manifest pointed to by the config was not defined or does not exist.
						wfDebugLog( 'PWA', "$id PWA manifest does not exist." );
						continue 2; // Skip that PWA.
					}

					$manifest = json_decode(wfMessage($manifestUrl)->text());

					$manifestUrl = $globalConfig->get( 'ScriptPath' ) .'/index.php?title=MediaWiki:'.urlencode($manifestUrl).'&action=raw&ctype=text/json';

					$out->addHeadItem('pwa', '<link rel="manifest" href="'.$manifestUrl.'" data-PWA-id="'.htmlspecialchars($id).'" />');

					$icons = $manifest->icons ?? [];

					if ( $icons[0] ?? false ) {
						$icon = $icons[0]->src;

						// Set the apple-touch-icon (because iOS ignores the icon field in the manifest). Add it as a head item to avoid modification by other extensions.
						$out->addHeadItem('apple-touch-icon', '<link rel="apple-touch-icon" href="'.$icon.'" />');	
						// All other apple-touch-icon link tags will be removed later on in onOutputPageAfterGetHeadLinksArray.
					}
					
					// Register the add-to-homescreen JS/CSS module.
					$out->addModules('ext.PWA.add-to-homescreen');

					// Register the PWA extension's JS which will then register the service worker.
					$out->addModules('ext.PWA');

					// Pass config parameters to mw.config so it can be fetched in JS.
					$out->addJsConfigVars('wgCurrentInstallablePWAId', $id);
					$pwaname = $manifest->name ?? null;
					if ( $pwaname ) {
						$out->addJsConfigVars('wgCurrentInstallablePWAName', $pwaname);
					}

					// Add some more metas.
					$out->addHeadItem('mobile-web-app-capable', '<meta name="mobile-web-app-capable" content="yes" />');
					$out->addHeadItem('apple-mobile-web-app-capable', '<meta name="apple-mobile-web-app-capable" content="yes" />');
					$out->addHeadItem('application-name', '<meta name="application-name" content="'.$pwaname ?? $id.'">');
					$out->addHeadItem('apple-mobile-web-app-title', '<meta name="apple-mobile-web-app-title" content="'.$pwaname ?? $id.'">');

					return; // Skip all other PWA configurations.
				}
			}
		}
	}

	/**
	 * Register parser calls to display icons to install a specific PWA app.
	 * @param OutputParser $parser  
	*/
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setFunctionHook('PWAAndroidInstall', __CLASS__.'::PWAAndroidInstall' );
		$parser->setFunctionHook('PWAiOSInstall', __CLASS__.'::PWAiOSInstall' );

		return true;
	}

	/**
	 * When a RecentChange entry is saved.
	*/
	public static function onRecentChangeSave( \RecentChange &$recentChange ) {

		if($PWAId = self::_getPWAId()) { // If this change was made with a PWA.
			$recentChange->addTags(['PWA-edit', 'PWA-edit-'.$PWAId]);
		}

		return true;
	}

	/**
	 * Register tags in use with this extension. 
	*/
	public static function onRegisterTags( array &$tags ) {
		global $wgPWAConfigs;

		if($wgPWAConfigs) { // If the admin did set a configuration.
			foreach($wgPWAConfigs as $id => $config) {
				$tags[] = "PWA-edit-$id"; // Add an edit tag for each PWA.
			}
		}

		$tags[] = 'PWA-edit';

		return true;
	}

	/** 
	 * Sanitise head links to give priority to those defined by this extension. 
	 * */
	public static function onOutputPageAfterGetHeadLinksArray( &$tags, \OutputPage $output ) {
		// The apple-touch-icon defined by this extension is not in that array because it was added in the head items directly.
		
		foreach($tags as $k => $t) {
			// If this tag defines an apple-touch-icon.
			if(strpos($t, 'apple-touch-icon')) { 
				unset($tags[$k]); // Delete it.
			}
		}
	}

	/** 
	 * If the pwa-id URL parameter was part of the request, propagate it.
	 * */
	public static function onBeforePageRedirect( $out, &$redirect, &$code ) { 
		if($PWAId = self::_getPWAId()) {
			$url = parse_url($redirect);

			if($url['query']) { // If the url had a query.
				$redirect .= '&pwa-id='.$PWAId; // Append to it.
			} else {
				$redirect .= '?pwa-id='.$PWAId; // Add a new query part.
			}
		}
	}

	/**
	 * Magic word handling method.
	 * */
	public static function PWAAndroidInstall( &$parser, $PWAId = '', $height = 50 ) {
		return self::PWAInstall($parser, $PWAId, 'Android', $height);
	}

	/**
	 * Magic word handling method.
	 * */
	public static function PWAiOSInstall( &$parser, $PWAId = '', $height = 50 ) {
		return self::PWAInstall($parser, $PWAId, 'iOS', $height);
	}

	/**
	 * Generic magic word handling method.
	 * */
	public static function PWAInstall( &$parser, $PWAId, $platform, $height = 50 ) {
		global $wgScriptPath, $wgLanguageCode;
		
		/* Buttons are disabled by default and enabled in PWA.js for the PWA associated with this page.
		 * This is to cover the case where install buttons for different PWAs are on the page.
		 * Only the PWA defined in the currently linked manifest can be installed. */
		
		if(!$PWAId) { return '<div class="error">Please provide a valid PWA ID.</div>'; }

		return [
			'<img class="pwa-install-button pwa-'.$PWAId.'-install-button pwa-disabled-install-button pwa-install-'.strtolower($platform).'" onclick="PWA'.$platform.'Install(\''.htmlspecialchars($PWAId).'\');" height="'.htmlspecialchars($height).'" src="'.$wgScriptPath.'/extensions/PWA/resources/ext.PWA/'.lcfirst($platform).'-install-'.$wgLanguageCode.'.svg" />',
			'noparse' => true,
			'isHTML' => true
		];
	}
}
