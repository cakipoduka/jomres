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
/**
 * New for v3.2 of Jomres. Allows the system to create a registry file so that the minicomponent handler doesn't need to constantly search folders and record minicomponents on each run.
 */
class minicomponent_registry
{
    private static $configInstance;

    public function __construct()
    {
        $this->registeredClasses = array();
        $this->miniComponentDirectories = array();
        $this->eventPoints = array();
        $this->new_filesize = 0;
        $this->error_detected = false;
        $this->unWantedFolderContents = array('.', '..', 'cvs', '.svn', 'registry.php');
        $this->temp_directory = JOMRES_TEMP_ABSPATH;
        $this->registry_file = JOMRES_TEMP_ABSPATH.'registry.php';

        if (file_exists($this->registry_file)) {
            $this->original_filesize = @filesize($this->registry_file);
        } // @to prevent notices when the file doesn't exist at all
        else {
            $this->original_filesize = 0;
        }

        if (!defined('AUTO_UPGRADE')) {
            if (!is_dir($this->temp_directory)) {
                mkdir($this->temp_directory);
            }

            if (!file_exists($this->registry_file)) {
                $this->regenerate_registry();
                $this->new_filesize = filesize($this->registry_file);
            }

            //this loads the registry items in $this->registeredClasses and $this->miniComponentDirectories
            include_once $this->registry_file;
        }
    }

    public static function getInstance()
    {
        if (!self::$configInstance) {
            self::$configInstance = new self();
        }

        return self::$configInstance;
    }

    public function get_registered_classes()
    {
        return $this->registeredClasses;
    }

    public function get_minicomponent_directories()
    {
        return $this->miniComponentDirectories;
    }

    public function regenerate_registry($force_reload_allowed = false)
    {
        $siteConfig = jomres_singleton_abstract::getInstance('jomres_config_site_singleton');
        $jrConfig = $siteConfig->get();

        if (!isset($jrConfig[ 'safe_mode' ])) {
            $jrConfig[ 'safe_mode' ] = '0';
        }

        $this->registeredClasses = array();
        $this->miniComponentDirectories = array();

        $this->getMiniComponentCoreClasses();
        $this->getMiniComponentCMSSpecificClasses();

        if ($jrConfig[ 'safe_mode' ] == '0') {
            $this->getMiniCorePluginsClasses();
            $this->getMiniCoreTaskClasses();
            $this->getMiniComponentRemoteClasses();

            if (!defined('AUTO_UPGRADE')) {
                $this->getMiniComponentCmsTemplateClasses();
            }
        }

        asort($this->registeredClasses);

        $this->save_registry_file();

        $this->new_filesize = filesize($this->registry_file);

        //delete js files in /jomres/temp dir
        if (isset($_REQUEST['task']) && ($_REQUEST['task'] == 'rebuildregistry' || $_REQUEST['task'] == 'save_site_settings')) {
            $javascript_files_in_temp_dir = scandir_getfiles(JOMRES_TEMP_ABSPATH, $extension = 'js');
            foreach ($javascript_files_in_temp_dir as $file) {
                unlink(JOMRES_TEMP_ABSPATH.$file);
            }
        }
		
		//delete installed and remote plugins cached lists
		if (file_exists(JOMRES_TEMP_ABSPATH.'installed_plugins_data.php')) {
			unlink(JOMRES_TEMP_ABSPATH.'installed_plugins_data.php');
		}
		if (file_exists(JOMRES_TEMP_ABSPATH.'remote_plugins_data.php')) {
			unlink(JOMRES_TEMP_ABSPATH.'remote_plugins_data.php');
		}
		
		//delete the classes registry
		if (file_exists(JOMRES_TEMP_ABSPATH.'registry_classes.php')) {
			unlink(JOMRES_TEMP_ABSPATH.'registry_classes.php');
		}

        //rebuild the shortcodes list
        if (!defined('AUTO_UPGRADE')) {
            $shortcode_parser = jomres_singleton_abstract::getInstance('shortcode_parser');
            $shortcode_parser->build_shortcodes($force = true);
        }

        //reload page if registry changed
        if ($this->original_filesize != $this->new_filesize && $force_reload_allowed) {
            echo "<script>alert('Reloading current page as minicomponents registry has changed');</script>";
            echo '<script>window.location.reload();</script>';
        }
    }

    public function save_registry_file()
    {
        $existed = false;
        if (file_exists($this->registry_file)) {
            if (!unlink($this->registry_file)) {
                error_logging('Could not delete existing registry file  :: '.$this->registry_file);

                return false;
            }
            $existed = true;
        }

        $this->miniComponentDirectories = array_unique($this->miniComponentDirectories);
		sort($this->miniComponentDirectories);
		ksort($this->registeredClasses);

        if (!file_put_contents($this->registry_file,
'<?php
##################################################################
defined( \'_JOMRES_INITCHECK\' ) or die( \'\' );
##################################################################

$this->registeredClasses = ' .var_export($this->registeredClasses, true).';
$this->miniComponentDirectories = ' .var_export($this->miniComponentDirectories, true).';
')) {
            trigger_error('ERROR: '.$this->registry_file.' can`t be saved. Please solve the permission problem and try again.', E_USER_ERROR);
            exit;
        }
    }

    // If this is Joomla we'll also scan the Joomla template's html/com_jomres dir for minicomponents.
    public function getMiniComponentCmsTemplateClasses()
    {
        if (!this_cms_is_joomla() && !this_cms_is_wordpress()) {
            return;
        }

        if (this_cms_is_joomla()) {
            $db = JFactory::getDBO();
            $query = 'SELECT `template` FROM #__template_styles WHERE `client_id` = 0 AND `home` = 1';
            $db->setQuery($query);
            $templateName = $db->loadResult();

            $jrePath = JOMRESCONFIG_ABSOLUTE_PATH.'templates'.JRDS.$templateName.JRDS.'html'.JRDS.'com_jomres'.JRDS;
        } elseif (this_cms_is_wordpress()) {
            $jrePath = get_stylesheet_directory().JRDS.'html'.JRDS.'com_jomres'.JRDS;
        } else {
            return;
        }

        $d = @dir($jrePath);
        $docs = array();
        if ($d) {
            while (false !== ($entry = $d->read())) {
                $filename = $entry;
                if (substr($entry, 0, 1) != '.') {
                    $docs[ ] = $entry;
                }
            }

            $d->close();
            if (!empty($docs)) {
                sort($docs);
                foreach ($docs as $doc) {
                    $listdir = $jrePath.$doc.JRDS;
                    $dr = @dir($listdir);
                    if ($dr) {
                        while (false !== ($entry = $dr->read())) {
                            $filename = $entry;
                            $this->registerComponentFile($listdir, $filename, 'cmstemplate');
                        }
                        $dr->close();
                    }
                }
            }
        }
    }

    // Reads in class files from the components table and inserts them into the registeredClasses array
    public function getMiniComponentRemoteClasses()
    {
        $jrePath = JOMRESCONFIG_ABSOLUTE_PATH.JOMRES_ROOT_DIRECTORY.JRDS.'remote_plugins'.JRDS;
        $d = @dir($jrePath);
        $docs = array();
        if ($d) {
            while (false !== ($entry = $d->read())) {
                $filename = $entry;
                if (substr($entry, 0, 1) != '.') {
                    $docs[ ] = $entry;
                }
            }
            $d->close();
            if (!empty($docs)) {
                sort($docs);
                foreach ($docs as $doc) {
                    $listdir = $jrePath.$doc.JRDS;
                    $dr = @dir($listdir);
                    if ($dr) {
                        while (false !== ($entry = $dr->read())) {
                            $filename = $entry;
                            $this->registerComponentFile($listdir, $filename, 'remotecomponent');
                        }
                        $dr->close();
                    }
                }
            }
        }
    }

    public function getMiniComponentCMSSpecificClasses()
    {
        $jrePath = JOMRESCONFIG_ABSOLUTE_PATH.JOMRES_ROOT_DIRECTORY.JRDS.'libraries'.JRDS.'jomres'.JRDS.'cms_specific'.JRDS._JOMRES_DETECTED_CMS.JRDS;
        $d = @dir($jrePath);
        if ($d) {
            while (false !== ($entry = $d->read())) {
                $filename = $entry;
                $this->registerComponentFile($jrePath, $filename, 'cms_specific_component');
            }
            $d->close();
        }
    }

    // Reads in class files from the events folder and inserts them into the registeredClasses array
    public function getMiniComponentCoreClasses()
    {
        $listdir = JOMRESCONFIG_ABSOLUTE_PATH.JOMRES_ROOT_DIRECTORY.JRDS.'core-minicomponents'.JRDS;
        $d = @dir($listdir);
        if ($d) {
            while (false !== ($entry = $d->read())) {
                $filename = $entry;
                $this->registerComponentFile($listdir, $filename, 'core');
            }
            $d->close();
        }
    }

    public function getMiniCorePluginsClasses()
    {
        $jrePath = JOMRESCONFIG_ABSOLUTE_PATH.JOMRES_ROOT_DIRECTORY.JRDS.'core-plugins'.JRDS;
        $d = @dir($jrePath);
        $docs = array();
        if ($d) {
            while (false !== ($entry = $d->read())) {
                $filename = $entry;
                if (substr($entry, 0, 1) != '.') {
                    $docs[ ] = $entry;
                }
            }
            $d->close();
            if (!empty($docs)) {
                sort($docs);
                foreach ($docs as $doc) {
                    $listdir = $jrePath.$doc.JRDS;
                    $dr = @dir($listdir);
                    if ($dr) {
                        while (false !== ($entry = $dr->read())) {
                            $filename = $entry;
                            $this->registerComponentFile($listdir, $filename, 'core-plugin');
                        }
                        $dr->close();
                    }
                }
            }
        }
    }

    // Jomres 9.9 April 2017
    public function getMiniCoreTaskClasses()
    {
        $core_task_dirs = array (
            "admin",
            "manager",
            "public",
            "reception",
            "registered"
            );
        foreach ($core_task_dirs as $subdir ) {
            $jrePath = JOMRESCONFIG_ABSOLUTE_PATH.JOMRES_ROOT_DIRECTORY.JRDS.'core-tasks'.JRDS.$subdir.JRDS;
            $d = @dir($jrePath);
            $docs = array();

            if ($d) {
                while (false !== ($entry = $d->read())) {
                    $filename = $entry;
                    if (substr($entry, 0, 1) != '.') {
                        $docs[ ] = $entry;
                    }
                }

                $d->close();
                if (!empty($docs)) {
                    foreach ($docs as $doc) {
                            $this->registerComponentFile($jrePath, $doc, 'core-tasks');
                    }
                }
            }            
        }

    }
    
    public function registerComponentFile($filePath, $filename, $eventType = 'component')
    {
        $strippedName = str_replace('.', '', $filename);
        $strippedName = substr($strippedName, 0, -8);

        $classfileEventPoint = substr($strippedName, 1, 5);
        $classfileEventName = substr($strippedName, 6);
        
        // Jomres 9.9
        // Currently task scripts are numbered 06000 for public tasks, 06001 for reception tasks, 06002 for manager tasks and 06005 for logged in user tasks.
        // We would like to replace these numbers with more developer readable names like task_reception_dashboard however for the forseeable future Jomres will need to be able
        // to handle both numbered scripts and new style class name scripts. Here we will look for new style classname tasks and if they´re found we will substitute the names with the numbers
        // this behaviour will be all but invisible to developers, allowing a transition time of months if not years.
        $bang = explode("_" , $strippedName );
        if (isset($bang[0]) && isset($bang[1]) && isset($bang[2]) ) {
            // Because class names can include underscores, we now need to reattach $bang[2] and onwards back together again.
            $cnt = count($bang);
            $full_class_name = '';
            for ($i=0;$i<$cnt;$i++){
                if ($i>1) {
                    $full_class_name.=$bang[$i]."_";
                }
            }

            if ($bang[0] == "task") {
                 if ($bang[1] == "public") {
                    $classfileEventPoint = "06000";
                }
                 if ($bang[1] == "reception") {
                    $classfileEventPoint = "06001";
                }
                 if ($bang[1] == "manager") {
                    $classfileEventPoint = "06002";
                }
                 if ($bang[1] == "registered") {
                    $classfileEventPoint = "06005";
                }
                 if ($bang[1] == "admin") {
                    $classfileEventPoint = "16000";
                }
            $classfileEventName = substr($full_class_name, 0, -1); // Trim the trailing underscore
            }
        }
        
		$path_parts = pathinfo($filePath.$filename);
        if (isset($path_parts[ 'extension' ])) {
            $extension = $path_parts[ 'extension' ];
        }

		if (
			is_file($filePath.$filename) && 
			!in_array(strtolower($filename), $this->unWantedFolderContents) && 
			(int)$classfileEventPoint > 0 && 
			(int)$classfileEventPoint <= 99999 && 
			strtolower($extension) == 'php'
			) {
			if (
				isset($this->registeredClasses[ $classfileEventPoint][$classfileEventName]) && 
				($this->registeredClasses[ $classfileEventPoint ][$classfileEventName][ 'eventtype' ] == 'component' ||
				$this->registeredClasses[ $classfileEventPoint ][$classfileEventName][ 'eventtype' ] == 'remotecomponent' ||
				$this->registeredClasses[ $classfileEventPoint ][$classfileEventName][ 'eventtype' ] == 'cms_specific_component' )
				) {
				$text = '';
				$text .= '<font color="red" face="arial" size="1">Warning: Event override collision. You have two or more mini-components attempting to perform the same override function. System behaviour may be unpredictable'.'</font><br>';
				$text .= '<b>'.$classfileEventPoint.$classfileEventName.'</b><br>';
				$text .= '<b>'.$this->registeredClasses[ $classfileEventPoint ][$classfileEventName][ 'filepath' ].'</b><br>';
				$text .= 'Collides with this and possibly more mini-components: '.'<br>';
				$text .= '<b>'.$classfileEventPoint.$classfileEventName.'</b><br>';
				$text .= '<b>'.$filePath.'</b><br>';
				echo $text;
				$this->error_detected = true;
				error_logging('Minicomponent collision :: '.$text);
			}

			$this->miniComponentDirectories[ ] = $filePath;
			$this->registeredClasses[ $classfileEventPoint ][$classfileEventName] = array('filepath' => $filePath, 'eventtype' => $eventType , "full_file_name" => $strippedName);
		}
    }
}
