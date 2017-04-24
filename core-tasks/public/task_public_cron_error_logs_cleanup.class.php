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

class task_public_cron_error_logs_cleanup
{
    public function __construct()
    {
        $MiniComponents = jomres_singleton_abstract::getInstance('mcHandler');
        if ($MiniComponents->template_touch) {
            $this->template_touchable = false;

            return;
        }
        $jomresConfig_secret = get_showtime('secret');
        $secret = base64_decode(jomresGetParam($_REQUEST, 'secret', ''));
		
		$maxFileSize = 1024 * 1024;

        if ($secret == $jomresConfig_secret) {
            $log_path = JOMRES_SYSTEMLOG_PATH;
            $files = scandir_getfiles($log_path);

            if (!empty($files)) {
                foreach ($files as $f) {
					
					//zip logs bigger than 1MB
					$bang = explode('.', $f);
					if (isset($bang[2]) && $bang[2] == 'log') {
						$size = filesize(($log_path.$f));
						if ($size > $maxFileSize) {
							$newFileName = date('U').'_'.$f.'.zip';
							$zip = new ZipArchive();
							$zip->open($log_path.$newFileName, ZipArchive::CREATE);
							$zip->addFile($log_path.$f, $f);
							$zip->close();

							unlink($log_path.$f);
						}
					}
					
					//delete files older than a month
                    if ($f != '.htaccess' && $f != 'web.config' && time() - filemtime($log_path.$f) >= 30 * 24 * 60 * 60) { // 30 days
                        unlink($log_path.$f);
                    }
                }
            }
        }
    }

    // This must be included in every Event/Mini-component
    public function getRetVals()
    {
        return null;
    }
}
