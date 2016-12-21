<?php

class pluginWikipal extends Plugin
{

    public $config = null;

    public function __construct()
    {
        // get the plugin config
        include(DOC_ROOT.'/plugins/wikipal/_plugin_config.inc.php');

        // load config into the object
        $this->config = $pluginConfig;
    }

    public function getPluginDetails()
    {
        return $this->config;
    }
    
    public function install()
    {
        // setup database
        $db = Database::getDatabase();

        // copy over WikiPal details from core if they exist
        $pre_wikipal_webgate_id = $db->getValue('SELECT config_value FROM site_config WHERE config_key="wikipal_payments_merchant" LIMIT 1');
        $pre_wikipal_method = $db->getValue('SELECT config_value FROM site_config WHERE config_key="wikipal_payments_server" LIMIT 1');
        if( $pre_wikipal_webgate_id && $pre_wikipal_method )
        {
            // get plugin details
            $pluginDetails = $this->getPluginDetails();

            // update settings
            $db = Database::getDatabase();
            $db->query('UPDATE plugin SET plugin_settings = :plugin_settings WHERE folder_name = :folder_name', 
				array('
					plugin_settings'=>'{
						"wikipal_webgate_id":"'.$pre_wikipal_webgate_id.'",
						"wikipal_method":"'.$pre_wikipal_method.'"
					}', 'folder_name' => $pluginDetails['folder_name']
				)
			);
            
            // delete old config value
            $db->query('DELETE FROM site_config WHERE config_key="wikipal_payments_merchant" LIMIT 1');
            $db->query('DELETE FROM site_config WHERE config_key="wikipal_payments_server" LIMIT 1');
        }

        return parent::install();
    }
	    
	public function uninstall()
    {
        // setup database
        $db = Database::getDatabase();    
        $db->query('DELETE FROM site_config WHERE config_key="wikipal_payments_merchant" LIMIT 1');
        $db->query('DELETE FROM site_config WHERE config_key="wikipal_payments_server" LIMIT 1');
		
		//this section deletes all wikipal setting from database after uninstall ... you can remove it if you dont want this action....
		$pluginDetails = $this->getPluginDetails();
        $db->query('DELETE FROM plugin WHERE plugin_name="'.$pluginDetails["plugin_name"].'" LIMIT 1');
		//
		
		
        return parent::uninstall();
    }

}