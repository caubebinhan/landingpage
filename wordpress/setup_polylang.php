<?php
if (function_exists('PLL')) {
    $polylang = PLL();
    
    // Check if languages already exist
    $langs = $polylang->model->get_languages_list();
    if (empty($langs)) {
        // English
        $polylang->model->add_language(array(
            'name'       => 'English',
            'slug'       => 'en',
            'locale'     => 'en_US',
            'rtl'        => 0,
            'term_group' => 0,
        ));
        
        // Vietnamese
        $polylang->model->add_language(array(
            'name'       => 'Tiếng Việt',
            'slug'       => 'vi',
            'locale'     => 'vi',
            'rtl'        => 0,
            'term_group' => 0,
        ));
        
        // Japanese
        $polylang->model->add_language(array(
            'name'       => '日本語',
            'slug'       => 'ja',
            'locale'     => 'ja',
            'rtl'        => 0,
            'term_group' => 0,
        ));
        
        // Reload languages list
        $polylang->model->clean_languages_cache();
        
        // Enable browser preference detection and set default
        $options = get_option('polylang');
        $options['default_lang'] = 'en';
        $options['browser'] = 1;
        $options['force_lang'] = 1; // 1 to add language code in URL
        $options['redirect_lang'] = 1;
        update_option('polylang', $options);
        
        echo "Polylang languages (EN, VI, JA) and settings configured successfully!\n";
    } else {
        echo "Languages are already configured.\n";
    }
} else {
    echo "Polylang is not active.\n";
}
