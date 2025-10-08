<?php
    class PHPueConverter {
        public function convertPVueToPHP($pvueContent, $isRoot = false) {
            $script = $this->extractBetween($pvueContent, '<script setup>', '</script>');
            $template = $this->extractBetween($pvueContent, '<template>', '</template>');
            $cscript = $this->extractBetween($pvueContent, '<cscript>', '</cscript>');
            
            if ($isRoot) {
                $script = $this->handleRequires($script);
            }
            
            $convertedTemplate = $this->convertVueSyntax($template);
            
            $convertedCscript = $this->handleCscript($cscript);
            
            $output = $this->buildOutput($script, $convertedTemplate, $convertedCscript, $isRoot);
            
            return $output;
        }
        
        private function extractBetween($content, $startTag, $endTag) {
            $pattern = '/' . preg_quote($startTag, '/') . '(.*?)' . preg_quote($endTag, '/') . '/s';
            preg_match($pattern, $content, $matches);
            return $matches[1] ?? '';
        }
        
        private function handleRequires($scriptContent) {
            // Handle @require for components
            preg_match_all('/@require "([^"]+)"/', $scriptContent, $componentRequires);
            foreach ($componentRequires[1] as $component) {
                $phpComponent = str_replace('.pvue', '.php', $component);
                $scriptContent .= "\ninclude '$phpComponent';";
            }
            
            // Handle #require for pages/routing
            preg_match_all('/#require "([^"]+)"/', $scriptContent, $pageRequires);
            $pages = [];
            foreach ($pageRequires[1] as $page) {
                $pages[] = str_replace('.pvue', '', basename($page));
                $this->autoCompilePage($page);
            }
            
            if (!empty($pages)) {
                $routingLogic = $this->generateRoutingLogic($pages);
                $scriptContent = $routingLogic . $scriptContent;
            }
            
            $scriptContent = preg_replace('/(@require|#require) "[^"]+"/', '', $scriptContent);
            
            return $scriptContent;
        }
        
        private function autoCompilePage($pagePath) {
            $pvueFile = $pagePath;
            $phpFile = str_replace('.pvue', '.php', $pagePath);
            
            if (!file_exists($phpFile) || filemtime($pvueFile) > filemtime($phpFile)) {
                $content = file_get_contents($pvueFile);
                $compiled = $this->convertPVueToPHP($content, false);
                file_put_contents($phpFile, $compiled);
            }
        }
        
        private function generateRoutingLogic($pages) {
            $pagesList = "['" . implode("', '", $pages) . "']";
            
            return <<<  PHP
                \n// Auto-generated routing system
                \$available_pages = $pagesList;
                \$requested_page = \$_GET['page'] ?? '{$pages[0]}';
                \$current_page = in_array(\$requested_page, \$available_pages) ? 
                    "views/\$requested_page.php" : 
                    "views/404.php";\n
            PHP;
        }
        
        private function convertVueSyntax($template) {
            $template = preg_replace('/\{\{\s*(\$.*?)\s*\}\}/', '<?= $1 ?>', $template);
            
            $template = preg_replace_callback('/v-for="(\$.*?) in (\$.*?)"/', 
                function($matches) {
                    $item = trim($matches[1]);
                    $array = trim($matches[2]); 
                    return "php-for=\"$item in $array\"";
                }, 
                $template);
            
            $vForStack = [];
            $vIfStack = [];
            
            $lines = explode("\n", $template);
            $output = [];
            
            foreach ($lines as $line) {
                if (preg_match('/<(\w+)([^>]*) php-for="(\$[^"]+) in (\$[^"]+)"([^>]*)>/', $line, $matches)) {
                    $tag = $matches[1];
                    $item = trim($matches[3]); 
                    $array = trim($matches[4]);
                    
                    $vForStack[] = $tag;
                    $line = preg_replace('/php-for="[^"]+"/', '', $line);
                    $line = "<?php foreach($array as $item): ?>" . $line;
                }
                
                if (preg_match('/<(\w+)([^>]*) v-if="([^"]*)"([^>]*)>/', $line, $matches)) {
                    $tag = $matches[1];
                    $condition = trim($matches[3]);
                    
                    $vIfStack[] = $tag;
                    $line = preg_replace('/v-if="[^"]+"/', '', $line);
                    $line = "<?php if($condition): ?>" . $line;
                }
                
                if (preg_match('/<\/(\w+)>/', $line, $matches)) {
                    $closingTag = $matches[1];
                    
                    if (!empty($vIfStack) && $closingTag === end($vIfStack)) {
                        array_pop($vIfStack);
                        $line = $line . "<?php endif; ?>";
                    }
                    
                    if (!empty($vForStack) && $closingTag === end($vForStack)) {
                        array_pop($vForStack);
                        $line = $line . "<?php endforeach; ?>";
                    }
                }
                
                $output[] = $line;
            }
            
            $template = implode("\n", $output);
            
            return $template;
        }


        private function handleCscript($cscript) {
            if (empty($cscript)) return '';
            
            // Inject PHP variables into cscript (basic implementation)
            $cscript = preg_replace('/const\s+(\w+)\s*=\s*{\s*php:\s*(\w+)\s*}/', 
                                'const $1 = <?= json_encode($2) ?>;', $cscript);
            
            return "<script>\n" . $cscript . "\n</script>";
        }
        
        private function buildOutput($script, $template, $cscript, $isRoot) {
            $output = "<?php\n";
            
            // Add session_start for root components with setup tag
            if ($isRoot && !str_contains($script, 'session_start()')) {
                $output .= "// Auto session start for root component\n";
                $output .= "if (session_status() === PHP_SESSION_NONE) {\n";
                $output .= "    session_start();\n";
                $output .= "}\n";
            }
            
            $output .= $script . "\n?>\n";
            $output .= $template . "\n";
            $output .= $cscript;
            
            return $output;
        }
    }

    function convert_pvue_file($pvueFilePath, $isRoot = false) {
        $converter = new PHPueConverter();
        
        if (!file_exists($pvueFilePath)) {
            throw new Exception("PVue file not found: $pvueFilePath");
        }
        
        $content = file_get_contents($pvueFilePath);
        return $converter->convertPVueToPHP($content, $isRoot);
    }
?>