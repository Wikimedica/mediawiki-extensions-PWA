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

	/**
	 * Called before a page displayed.
	 * @param OutputPage $out
	 * @param Skin $skin
	*/
	public function onBeforePageDisplay(&$out, &$skin) {

		global $wgPWAConfigs, $wgPWAMobileSkin, $wgScriptPath;

		// Add a specific CSS stylesheet in standalone (PWA) mode depending on wether the desktop or mobile skin is used.
		$out->addStyle($wgScriptPath.'/index.php?title=MediaWiki:'.($skin->getSkinName() == $wgMobileSkin ? 'PWA-mobile.css': 'PWA-common.css').'&action=raw&ctype=text/css', 'standalone');

		// Register some JS and CSS for standalone mode. This code should be in the service worker but until I get a better grasp of how they work is will be included in every page.
		$out->addModules('ext.PWA.standalone');

		// Loop over all configured PWAs to check which one we should use for the requested page.
		foreach ($wgPWAConfigs as $name => $config){
			if(!$config) { continue; } // If that PWA has been turned off (useful for disabling the default PWA [since the provide_default merge strategy only works in MW > 1.35.3]).

			// Format the $pattern parameter.
			if(!isset($config['patterns'])) {
				wfDebugLog( 'PWA', "$name PWA missing the pattern configuration entry." );
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
				$pattern = str_replace(' ', '_', $pattern); // Space and _ are considered equivalent in page titles.
				
				if(!preg_match($pattern, $title)) { continue; }
				// The PWA matches the title.
		
				if(!isset($config['manifest'])) {
					wfDebugLog( 'PWA', "$name PWA missing the manifest configuration entry." );
					continue 2; // Misconfigured property, skip this PWA.
				}

				$manifestUrl = $config['manifest'];
				$manifest = json_decode(wfMessage($manifestUrl)->text());

				$manifestUrl = $wgScriptPath.'/index.php?title=MediaWiki:'.urlencode($manifestUrl).'&action=raw&ctype=text/json';

				$out->addHeadItem('pwa', '<link rel="manifest" href="'.$manifestUrl.'" data-PWA-id="'.htmlspecialchars($name).'" />');
				
				/* Home links the interface can be overriden by the PWA. For example, if a PWA wans its home to be /wiki/MyPWAHome instead
				 * of /wiki/Main_Page it can set this parameter to true so when a user clicks the wiki's logo, they are taken to the PWA home
				 * instead of the default home page. */

				 // Peut-être que ça devrait se retrouver directement dans le manifest.json ? Serait plus facile à configuer.
				$overrideHomeLinks = isset($config['overrideHomeLinks']) && $config['overrideHomeLinks'] ? 'true': 'false';
				$out->addHeadItem('pwa-home-links-override', '<script type="text/javascript">var wgPWAOverrideHomeLinks = '.$overrideHomeLinks.';</script>');

				$icon = $manifest->icons[0]->src;

				// Set the apple-touch-icon (because iOS ignore the icon field in the manifest).
				$out->addHeadItem('apple-touch-icon', '<link rel="apple-touch-icon" href="'.$icon.'" />');
				
				// Register the add-to-homescreen JS/CSS module.
				$out->addModules('ext.PWA.add-to-homescreen');

				// Register the PWA extension's JS which will then register the service worker.
				$out->addModules('ext.PWA');

				// Pass config parameters to mw.config so it can be fetch in JS.
				$out->addJsConfigVars('wgCurrentPWAId', $name);
				$out->addJsConfigVars('wgCurrentPWAName', $manifest->name);

				// Add some more metas.
				$out->addHeadItem('mobile-web-app-capable', '<meta name="mobile-web-app-capable" content="yes" />');
				$out->addHeadItem('apple-mobile-web-app-capable', '<meta name="apple-mobile-web-app-capable" content="yes" />');
				$out->addHeadItem('application-name', '<meta name="application-name" content="'.$name.'">');
				$out->addHeadItem('apple-mobile-web-app-title', '<meta name="apple-mobile-web-app-title" content="'.$name.'">');

				return; // Skip all other PWA configurations.
			}
		}
	}

	/**
	 * Register parser calls to display icons to install a specific PWA app.
	 * @param OutputParser $parser  
	*/
	public static function onParserFirstCallInit( &$parser ) 
	{
		$parser->setFunctionHook('PWAAndroidInstall', __CLASS__.'::PWAAndroidInstall' );
		$parser->setFunctionHook('PWAiOSInstall', __CLASS__.'::PWAiOSInstall' );

		return true;
	}

	/**
	 * Magic word handling method.
	 * */
	public static function PWAAndroidInstall( &$parser, $PWAId = '', $height = 50 ) 
	{
		global $wgScriptPath, $wgLanguageCode;
		
		return [
			'<img class="pwa-install-button pwa-install-android" onclick="PWAAndroidInstall(\''.htmlspecialchars($PWAId).'\');" height="'.htmlspecialchars($height).'" src="'.$wgScriptPath.'/extensions/PWA/resources/ext.PWA/android-install-'.$wgLanguageCode.'.svg" />',
			'noparse' => true,
			'isHTML' => true
		];
	}

	/**
	 * Magic word handling method.
	 * */
	public static function PWAiOSInstall( &$parser, $PWAId = '', $height = 50 ) 
	{
		global $wgScriptPath, $wgLanguageCode;
		
		return [
			'<img class="pwa-install-button pwa-install-ios" onclick="PWAiOSInstall(\''.htmlspecialchars($PWAId).'\');" height="'.htmlspecialchars($height).'" src="'.$wgScriptPath.'/extensions/PWA/resources/ext.PWA/iOS-install-'.$wgLanguageCode.'.svg" />',
			'noparse' => true,
			'isHTML' => true
		];
	}
}
