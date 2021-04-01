<?php

/**
 * class NuveiVersionResolver
 * 
 * Try to resolve different versions problem in the plugin here
 */
class NuveiVersionResolver
{
    public static function get_token_name()
    {
        if (version_compare(VERSION, '3.0.0.0', '>')) {
            return 'user_token';
        }
		
        return 'token';
    }
	
    public static function get_source_application()
    {
        if (version_compare(VERSION, '3.0.0.0', '>')) {
            return 'OPENCART_3_0_PLUGIN';
        }
		
        return 'OPENCART_2_3_PLUGIN';
    }
    
    public static function get_ctr_file_path()
    {
        if (version_compare(VERSION, '2.2.0.0', '>')) {
            return 'extension/payment/nuvei';
        }
		
        return 'payment/nuvei';
    }
    
    public static function get_adm_ctr_text_extension_key()
    {
        if (version_compare(VERSION, '3.0.0.0', '>')) {
            return 'text_extension';
        }
		
        return 'text_payment';
    }
    
    public static function get_adm_ctr_extensions_url()
    {
        if (version_compare(VERSION, '3.0.0.0', '>')) {
            return 'marketplace/extension';
        }
        if (version_compare(VERSION, '2.2.0.0', '>')) {
            return 'extension/extension';
        }
		
        return 'extension/payment';
    }
    
    public static function get_settings_prefix()
    {
        if (version_compare(VERSION, '3.0.0.0', '>')) {
            return 'payment_nuvei_';
        }
		
        return 'nuvei_';
    }
    
}
