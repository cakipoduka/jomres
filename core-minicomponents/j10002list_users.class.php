<?php
/**
 * Core file.
 *
 * @author Vince Wooll <sales@jomres.net>
 *
 * @version Jomres 9.8.29
 *
 * @copyright	2005-2017 Vince Wooll
 * Jomres (tm) PHP, CSS & Javascript files are released under both MIT and GPL2 licenses. This means that you can choose the license that best suits your project, and use it accordingly
 **/

// ################################################################
defined( '_JOMRES_INITCHECK' ) or die( '' );
// ################################################################

class j10002list_users
	{
	function __construct()
		{
		// Must be in all minicomponents. Minicomponents with templates that can contain editable text should run $this->template_touch() else just return
		$MiniComponents = jomres_singleton_abstract::getInstance( 'mcHandler' );
		if ( $MiniComponents->template_touch )
			{
			$this->template_touchable = true;

			return;
			}
		
		$htmlFuncs          = jomres_singleton_abstract::getInstance( 'html_functions' );
		$this->cpanelButton = $htmlFuncs->cpanelButton( JOMRES_SITEPAGE_URL_ADMIN . '&task=list_users', 'Vendors.png', jr_gettext( "_JOMRES_COM_MR_SHOWPROFILES", '_JOMRES_COM_MR_SHOWPROFILES', false, false ), "/".JOMRES_ROOT_DIRECTORY."/images/jomresimages/small/", jr_gettext( "_JOMRES_CUSTOMCODE_MENUCATEGORIES_USERMANAGEMENT", '_JOMRES_CUSTOMCODE_MENUCATEGORIES_USERMANAGEMENT', false, false ) );

		}

	function touch_template_language()
		{
		$output = array ();

		$output[ ] = jr_gettext( "_JOMRES_CUSTOMCODE_MENUCATEGORIES_USERMANAGEMENT", '_JOMRES_CUSTOMCODE_MENUCATEGORIES_USERMANAGEMENT' );

		foreach ( $output as $o )
			{
			echo $o;
			echo "<br/>";
			}
		}

	// This must be included in every Event/Mini-component
	function getRetVals()
		{
		return $this->cpanelButton;
		}
	}
