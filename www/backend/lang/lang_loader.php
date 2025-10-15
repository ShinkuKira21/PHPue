<?php

    // FUNCTION IS AUTO LOADED IN, AND THEN BE CALLED IN .PVUE FILES ANYWHERE!
    // No requirement to use `require '';`
    function load_lang($language) {
        $lang_file = __DIR__ . '/' . $language . '.php';

        if (file_exists($lang_file)) {
            include($lang_file);
            return $lang;
        } else {
            include(__DIR__ . '/english.php');
            return $lang;
        }
    }
?>