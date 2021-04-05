<?php

function get_asstes_files($module_assets_files, $module_name, $controller, $function)
{
	$load_files = array();
    foreach ($module_assets_files as $key => $file) {
        $load_files['module_name'] = $key;
        if(isset($file['js-files']) && count($file['js-files']) > 0){
            $js_files = $file['js-files'];
            for($i = 0; $i < count($js_files); $i++){
                for($j = 0; $j < count($js_files[$i]['location']); $j++){

                    if(strpos($js_files[$i]['file'], 'http')  !== false){
                        $load_files['js_files']['file'][] = $js_files[$i]['file'];
                    } else {
                        $load_files['js_files']['file'][] = module_base_path() . $key . '/' .$js_files[$i]['file'];
                    }

                    if(strpos($js_files[$i]['location'][$j], '/')){
                        $cn_fn_arr = explode('/', $js_files[$i]['location'][$j]);
                    	$load_files['js_files']['controller'][] = $cn_fn_arr[0];
                    	$load_files['js_files']['function'][] = $cn_fn_arr[1];
                    } else {
                        $load_files['js_files']['controller'][] = '*';
                        $load_files['js_files']['function'][] = '*';
                    }
                }
            }
        }

        if(isset($file['css-files']) && count($file['css-files']) > 0){
            $css_files = $file['css-files'];
            for($k = 0; $k < count($css_files); $k++){
                for($l = 0; $l < count($css_files[$k]['location']); $l++){

                    if(strpos($css_files[$k]['file'], 'http')  !== false){
                        $load_files['css_files']['file'][] = $css_files[$k]['file'];
                    } else {
                        $load_files['css_files']['file'][] = module_base_path() . $key . '/' .$css_files[$k]['file'];
                    }

                    if(strpos($css_files[$k]['location'][$l], '/')){
                        $cn_fn_arr = explode('/', $css_files[$k]['location'][$l]);
                    	$load_files['css_files']['controller'][] = $cn_fn_arr[0];
                    	$load_files['css_files']['function'][] = $cn_fn_arr[1];
                    } else {
                        $load_files['css_files']['controller'][] = '*';
                        $load_files['css_files']['function'][] = '*';
                    }
                }
            }
        }
    }

	$files_array = array();
    foreach ($load_files as $key => $value) {
    	if($key == 'js_files'){
    		for($i = 0; $i < count($value['file']); $i++){
    			if(($controller == $value['controller'][$i] && $function == $value['function'][$i]) || ($value['controller'][$i] == '*' && $value['function'][$i] == '*')){
    				$files_array['js_files'][$i] = $value['file'][$i];
    			}
    		}
    	}
    	if($key == 'css_files'){
    		for($i = 0; $i < count($value['file']); $i++){
    			if(($controller == $value['controller'][$i] && $function == $value['function'][$i]) || ($value['controller'][$i] == '*' && $value['function'][$i] == '*')){
    				$files_array['css_files'][$i] = $value['file'][$i];
    			}
    		}
    	}
    }

    $files_array['js_files'] = isset($files_array['js_files']) && $files_array['js_files'] ? array_values($files_array['js_files']) : array();
    $files_array['css_files'] = isset($files_array['css_files']) && $files_array['css_files'] ? array_values($files_array['css_files']) : array();

    return $files_array;
}

function module_base_path()
{
	$CI =& get_instance();
	// return $CI->config->site_url().$CI->config->item('module_location').$CI->router->fetch_module();
	return $CI->config->site_url().$CI->config->item('module_location');
}

function check_active_extensions($module_name, $company_id) {

    $CI = & get_instance();
    if(!$CI->session->userdata('activated_modules')){
        $extensions = $CI->Extension_model->get_extensions(null, $company_id);
    
        $extensions_name = array();
        if($extensions){
            foreach($extensions as $extension)
            {
                if($extension['is_active'] == 1)
                    $extensions_name[] = $extension['extension_name'];
            }
        }
        
    } else {
        $extensions_name = $CI->session->userdata('activated_modules');
    }

    if(in_array($module_name, $extensions_name)){
        return true;
    } else {
        return false;
    }
}

function show_registration_link()
{
    $CI = & get_instance();

    $extensions = $CI->Extension_model->get_active_extensions(null, 'reseller_package');

    if($extensions && count($extensions) > 0) {
        return true;
    } else {
        return false;
    }
}

function auto_fill_credentials()
{
    $CI = & get_instance();

    $extensions = $CI->Extension_model->get_active_extensions(null, 'auto_populate_credentials');

    if($extensions && count($extensions) > 0) {
        return true;
    } else {
        return false;
    }
}

?>