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

		global $wgPWAConfigs;

		// Loop over all configured PWAs to check which one we should use for the requested page.
		foreach ($wgPWAConfigs as $name => $config)
		{
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
				if(!preg_match($pattern, $title)) { continue; }
				// The PWA matches the title.
		
				if(!isset($config['manifest'])) {
					wfDebugLog( 'PWA', "$name PWA missing the manifest configuration entry." );
					continue 2; // Misconfigured property, skip this PWA.
				}

				$manifest = $config['manifest'];
				
				// If the manifest is a wiki artile.
				if(isset($config['manifest_type']) && $config['manifest_type'] == 'article'){
					global $wgScriptPath;
					$manifest = $wgScriptPath.'/index.php?title='.urlencode($manifest).'&action=raw&ctype=text/json';
				}
				// Else it's an URL
				
				$out->addHeadItem('pwa', '<link rel="manifest" href="'.$manifest.'" data-PWA-id="'.htmlspecialchars($name).'" />');
				
				/* Home links the interface can be overriden by the PWA. For example, if a PWA wans its home to be /wiki/MyPWAHome instead
				 * of /wiki/Main_Page it can set this parameter to true so when a user clicks the wiki's logo, they are taken to the PWA home
				 * instead of the default home page. */

				 // Peut-être que ça devrait se retrouver directement dans le manifest.json ? Serait plus facile à configuer.
				$overrideHomeLinks = isset($config['overrideHomeLinks']) && $config['overrideHomeLinks'] ? 'true': 'false';
				$out->addHeadItem('pwa-home-links-override', '<script type="text/javascript">var wgPWAOverrideHomeLinks = '.$overrideHomeLinks.';</script>');

				return; // Skip all other PWA configurations.
			}
		}
	}

}
