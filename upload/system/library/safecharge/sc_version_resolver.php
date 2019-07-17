<?php

/**
 * class SafeChargeVersionResolver
 * 
 * Try to resolve different versions problem in the plugin here
 * 
 * 2018-09
 * SafeCharge
 */
class SafeChargeVersionResolver
{
    public static function get_token_name()
    {
        if (version_compare(VERSION, '3.0.0.0', '>')) {
            return 'user_token';
        }
		
        return 'token';
    }
    
    public static function get_ctr_file_path()
    {
        if (version_compare(VERSION, '2.2.0.0', '>')) {
            return 'extension/payment/safecharge';
        }
		
        return 'payment/safecharge';
    }
    
    public static function get_public_ctr_file_path()
    {
        return 'payment/safecharge';
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
    
    public static function get_tpl_extension()
    {
        if (version_compare(VERSION, '2.2.0.0', '>')) {
            return '';
        }
		
        return '.tpl';
    }
    
    public static function get_settings_prefix()
    {
        if (version_compare(VERSION, '3.0.0.0', '>')) {
            return 'payment_safecharge_';
        }
		
        return 'safecharge_';
    }
    
    /**
     * Function get_catalog_tpl
     * 
     * As we know v3+ is smarter and it is easy to to get template
     * For v2 you need to add extension of the file and part of the path.
     * 
     * @param string $curr_tpl - part of the path and file
     * @return string
     */
    public static function get_catalog_tpl_path($curr_tpl)
    {
        if (version_compare(VERSION, '3.0.0.0', '>')) {
            return $curr_tpl;
        }
		
        return 'default/template/' . $curr_tpl . '.tpl';
    }
    
}
