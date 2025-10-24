<?php
global $phpue_header;
$phpue_header = <<<HTML
    <title>404 - Page Not Found</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="description" content="The page you're looking for doesn't exist">
    <meta name="keywords" content="404, page not found">
    <meta name="author" content="Edward Patch">
HTML;

$GLOBALS['phpue_http404_header'] = $phpue_header;
?>