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
defined('_JOMRES_INITCHECK') or die('');
// ################################################################

class task_public_media_centre_resources_ajax_existing_images
{
    public function __construct($componentArgs)
    {
        // Must be in all minicomponents. Minicomponents with templates that can contain editable text should run $this->template_touch() else just return
        $MiniComponents = jomres_singleton_abstract::getInstance('mcHandler');
        if ($MiniComponents->template_touch) {
            $this->template_touchable = false;

            return;
        }

        $thisJRUser = jomres_singleton_abstract::getInstance('jr_user');
        if (!$thisJRUser->userIsManager) {
            return;
        }

        $resource_type = jomresGetParam($_REQUEST, 'resource_type', '');
        $resource_id = jomresGetParam($_REQUEST, 'resource_id', '0');
		
		//if resource type is empty, return
		if ($resource_type == '')
			return;
		
		//get_existing_images_trigger
		if (jomres_cmsspecific_areweinadminarea()) {
			$images = $MiniComponents->specificEvent('11040', $resource_type);
		} else {
			$images = $MiniComponents->specificEvent('03383', $resource_type);
		}

        if (!jomres_cmsspecific_areweinadminarea()) {
            $delete_url = JOMRES_SITEPAGE_URL_AJAX.'&task=media_centre_handler&delete=1&resource_type='.$resource_type.'&resource_id='.$resource_id.'&filename=';
        } else {
            $delete_url = JOMRES_SITEPAGE_URL_ADMIN_AJAX.'&task=media_centre_handler&delete=1&resource_type='.$resource_type.'&resource_id='.$resource_id.'&filename=';
        }

        $image_result = '';
        if (!empty($images)) {
            foreach ($images as $image) {
                $image_name_array = explode('/', $image['large']);
                $image_name = $image_name_array[count($image_name_array) - 1];

                $base_path = JOMRES_IMAGELOCATION_ABSPATH;
                $image_small_path = str_replace(get_showtime('live_site').'/'.JOMRES_ROOT_DIRECTORY.'/uploadedimages/', '', $image['small']);

                $output = array();
                $pageoutput = array();

                $output['RANDOM_ID'] = generateJomresRandomString(10);
                $output['FILENAME'] = $image_name;

                if (!file_exists($base_path.$image_small_path)) {  // In the case of features images, it's possible that small images don't exist, so we'll swap to using the "large" (heh, relatively speaking) image instead.
                    $output['IMAGE_REL_SMALL'] = $image['large'];
                } else {
                    $output['IMAGE_REL_SMALL'] = $image['small'];
                }

                $sizes = getimagesize($output['IMAGE_REL_SMALL']);
                if ($sizes[0] > 60) {
                    $output['WIDTH'] = '60';
                    $output['HEIGHT'] = '40';
                } else {
                    $output['WIDTH'] = $sizes[0];
                    $output['HEIGHT'] = $sizes[1];
                }

                $output['IMAGE_REL_LARGE'] = $image['large'];
                $output['_JOMRES_MEDIA_CENTRE_BUTTON_DELETE'] = jr_gettext('_JOMRES_MEDIA_CENTRE_BUTTON_DELETE', '_JOMRES_MEDIA_CENTRE_BUTTON_DELETE', false);
                $output['_JOMRES_MEDIA_CENTRE_BUTTON_VIEW'] = jr_gettext('_JOMRES_MEDIA_CENTRE_BUTTON_VIEW', '_JOMRES_MEDIA_CENTRE_BUTTON_VIEW', false);
                $output['DELETE_URL'] = $delete_url.$image_name;

                $pageoutput[] = $output;
                $tmpl = new patTemplate();

                if (jomres_cmsspecific_areweinadminarea()) {
                    $tmpl->setRoot(JOMRES_TEMPLATEPATH_ADMINISTRATOR);
                } else {
                    $tmpl->setRoot(JOMRES_TEMPLATEPATH_BACKEND);
                }
                $tmpl->readTemplatesFromInput('media_centre_image_list.html');
                $tmpl->addRows('pageoutput', $pageoutput);
                $image_result .= $tmpl->getParsedTemplate();
            }

            $pageoutput[] = array('IMAGES' => $image_result);
            $tmpl = new patTemplate();
            if (jomres_cmsspecific_areweinadminarea()) {
                $tmpl->setRoot(JOMRES_TEMPLATEPATH_ADMINISTRATOR);
            } else {
                $tmpl->setRoot(JOMRES_TEMPLATEPATH_BACKEND);
            }
            $tmpl->readTemplatesFromInput('media_centre_image_list_wrapper.html');
            $tmpl->addRows('pageoutput', $pageoutput);
            $tmpl->displayParsedTemplate();
        } else {
            echo ' <img src="'.get_showtime('live_site').'/'.JOMRES_ROOT_DIRECTORY.'/images/noimage.gif'.'" />';
        }
    }

    // This must be included in every Event/Mini-component
    public function getRetVals()
    {
        return null;
    }
}
