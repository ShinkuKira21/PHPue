<!-- Author: Edward Patch -->

<?php
    class PHPRouting {
        public $routes = [];
        
        public function addView($viewPath) {
            $viewName = basename($viewPath, '.pvue');
            $headerContent = $this->extractHeaderContent($viewPath);
            
            $this->routes[$viewName] = [
                'file' => $viewPath,
                'compiled' => str_replace('.pvue', '.php', $viewPath),
                'route' => $viewName === 'index' ? '/' : "/$viewName",
                'header' => $this->extractMetaData($headerContent)
            ];
        }

        public function loadFromJson($jsonFile) {
            if (!file_exists($jsonFile)) return;

            $data = json_decode(file_get_contents($jsonFile), true);
            if (is_array($data)) {
                $this->routes = $data;
            }
        }
        
        private function extractHeaderContent($pvueFile) {
            if (!file_exists($pvueFile)) return '';
            
            $content = file_get_contents($pvueFile);
            preg_match('/<header>(.*?)<\/header>/s', $content, $matches);
            
            return $matches[1] ?? '';
        }
        
        private function extractMetaData($headerContent) {
            if (empty($headerContent)) return [];
            
            $meta = [];
            
            // Extract <title>
            if (preg_match('/<title>(.*?)<\/title>/s', $headerContent, $matches)) {
                $meta['title'] = trim($matches[1]);
            }
            
            // Extract meta tags
            if (preg_match_all('/<meta\s+name="([^"]+)"\s+content="([^"]*)"/', $headerContent, $matches)) {
                foreach ($matches[1] as $index => $name) {
                    $meta[$name] = $matches[2][$index];
                }
            }
            
            // Extract styles
            if (preg_match_all('/<link\s+rel="stylesheet"\s+href="([^"]*)"/', $headerContent, $matches)) {
                $meta['stylesheets'] = $matches[1];
            }
            
            return $meta;
        }
        
        public function getNavigation() {
            $nav = [];
            foreach ($this->routes as $name => $route) {
                $nav[] = [
                    'name' => $name,
                    'title' => $route['header']['title'] ?? ucfirst($name),
                    'url' => $route['route']
                ];
            }
            return $nav;
        }
        
        public function getRouteMeta($routeName) {
            return $this->routes[$routeName]['header'] ?? [];
        }
    }

    class PHPueConverter {   
        private $routing;
    
        public function __construct() {
            $this->routing = new PHPRouting();
        }

        public function convertPVueToPHP($pvueContent, $bRoot = false) {
            $script = $this->extractBetween($pvueContent, '<script setup>', '</script>');

            if (empty(trim($script))) {
                $script = $this->extractBetween($pvueContent, '<script>', '</script>');
            }

            $header = $this->extractBetween($pvueContent, '<header>', '</header>');
            $template = $this->extractBetween($pvueContent, '<template>', '</template>');
            $cscript = $this->extractBetween($pvueContent, '<cscript>', '</cscript>');
            
            // Initialize component map
            $componentMap = [];
            
            if ($bRoot) {
                // Handle requires and get both script content and component map
                $requireResult = $this->handleRequires($script);
                $script = $requireResult['script'];
                $componentMap = $requireResult['components'];
                
                $script = $this->injectDynamicHeaderLogic($script);
            }
            
            // Convert Vue syntax in template
            $convertedTemplate = $this->convertVueSyntax($template);
            
            // Inject components into the converted template
            $convertedTemplate = $this->injectComponents($convertedTemplate, $componentMap);
            
            $convertedCscript = $this->handleCscript($cscript);
            
            $output = $this->buildOutput($script, $convertedTemplate, $convertedCscript, $bRoot, $header);
            
            return $output;
        }

        private function injectComponents($template, $componentMap) {
            foreach ($componentMap as $componentName => $componentContent) {
                // Replace <ComponentName></ComponentName> with the actual component content
                $placeholder = '<' . $componentName . '></' . $componentName . '>';
                $template = str_replace($placeholder, $componentContent, $template);
                
                // Also handle self-closing tags <ComponentName/>
                $selfClosingPlaceholder = '<' . $componentName . '/>';
                $template = str_replace($selfClosingPlaceholder, $componentContent, $template);
            }
            
            return $template;
        }

        private function injectDynamicHeaderLogic($scriptContent) {
            $dynamicHeaderLogic = <<<'PHP'
                // Auto-injected dynamic header system
                $current_page = $_GET['page'] ?? 'index';
                $current_header = '';

                // Get header from current page if it exists
                $available_pages = array_keys(get_phpue_routing()->routes);
                if (in_array($current_page, $available_pages)) {
                    $page_file = "views/$current_page.pvue";
                    if (file_exists($page_file)) {
                        $page_content = file_get_contents($page_file);
                        preg_match('/<header>(.*?)<\/header>/s', $page_content, $matches);
                        $current_header = $matches[1] ?? '';
                    }
                }

                // Fallback to App.pvue header if no page header
                if (empty($current_header) && isset($phpue_header)) {
                    $current_header = $phpue_header;
                }
            PHP;

            return $dynamicHeaderLogic . "\n" . $scriptContent;
        }

        private function injectHeadersIntoRootTemplate($template) {
            // Auto-wrap template with HTML and dynamic headers
            if (strpos($template, '<!DOCTYPE html>') === false) {
                $template = <<<HTML
                <!DOCTYPE html>
                <html>
                <head>
                    <?= \$current_header ?? '' ?>
                </head>
                <body>
                    $template
                </body>
                </html>
                HTML;
            }
            
            return $template;
        }

        public function getRouting()
        { return $this->routing; }
        
        private function extractBetween($content, $startTag, $endTag) {
            $pattern = '/' . preg_quote($startTag, '/') . '(.*?)' . preg_quote($endTag, '/') . '/s';
            preg_match($pattern, $content, $matches);
            return $matches[1] ?? '';
        }
        
        private function handleRequires($scriptContent) {
            // Handle @require ComponentName 'components/Component.pvue'
            preg_match_all('/@require\s+(\w+)\s+\'([^\']+)\'\s*;?/', $scriptContent, $componentRequires, PREG_SET_ORDER);
            
            $componentMap = [];
            
            foreach ($componentRequires as $match) {
                $componentName = $match[1];
                $componentPath = $match[2];
                
                if (file_exists($componentPath)) {
                    $componentContent = file_get_contents($componentPath);
                    $compiledComponent = $this->convertPVueToPHP($componentContent, false);
                    
                    $componentMap[$componentName] = $compiledComponent;
                }
            }

            $lines = explode("\n", $scriptContent);
            $cleanScriptContent = [];

            foreach ($lines as $line) {
                if (preg_match('/^\s*@require\s+\w+\s+\'[^\']+\'\s*;?\s*$/', $line)) {
                    $cleanScriptContent[] = "// @require processed and removed";
                    continue;
                }
                
                if (preg_match('/^\s*#require\s+(?:(\w+)\s+)?[\'"]([^\'"]+\.pvue)[\'"]\s*;?\s*$/', $line, $matches)) {
                    $viewName = $matches[1] ?? '';
                    $viewPath = $matches[2];

                    if (file_exists($viewPath)) {
                        $this->routing->addView($viewPath);
                        
                        $viewName = $viewName ?: basename($viewPath, '.pvue');
                        $pages[] = $viewName;
                        
                        $cleanScriptContent[] = "// Added view to routing: $viewName from $viewPath";
                    } else {
                        $cleanScriptContent[] = "// ERROR: View file not found: $viewPath";
                    }
                } else {
                    $cleanScriptContent[] = $line;
                }
            }

            $scriptContent = implode("\n", $cleanScriptContent);

            if (!empty($pages)) {
                $routingLogic = $this->generateRoutingLogic($pages);
                $scriptContent = $routingLogic . $scriptContent;
            }

            return [
                'script' => $scriptContent,
                'components' => $componentMap
            ];
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
            $defaultPage = $pages[0] ?? 'index';
            
            return <<<PHP
                \n// Auto-generated routing system
                \$available_pages = $pagesList;
                \$requested_page = \$_GET['page'] ?? '$defaultPage';

                // Runtime view compilation
                if (in_array(\$requested_page, \$available_pages)) {
                    \$view_file = "views/\$requested_page.pvue";
                    if (file_exists(\$view_file)) {
                        \$view_content = file_get_contents(\$view_file);
                        \$compiled_view = convert_pvue_file(\$view_content, false);
                        \$current_view_content = \$compiled_view;
                    } else {
                        \$current_view_content = "View file not found: \$view_file";
                    }
                } else {
                    // 404 handling
                    \$current_view_content = "404 - Page '\$requested_page' not found";
                    http_response_code(404);
                }

                // Make current page available to components
                \$GLOBALS['phpue_current_page'] = \$requested_page;
                \n
            PHP;
        }
        
        private function convertVueSyntax($template) {
            $template = preg_replace('/<header>.*?<\/header>/s', '', $template);

            // Convert <View/> to the current page content - handle both formats
            $template = str_replace('<View></View>', '<?= $current_view_content ?? "No view content" ?>', $template);
            $template = str_replace('<View/>', '<?= $current_view_content ?? "No view content" ?>', $template);

            // Handle custom view components like <ViewName></ViewName>
            // This will be handled by the component system
            
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
            
            $cscript = preg_replace('/const\s+(\w+)\s*=\s*{\s*php:\s*(\w+)\s*}/', 
                                'const $1 = <?= json_encode($2) ?>;', $cscript);
            
            return "<script>\n" . $cscript . "\n</script>";
        }
        
        private function buildOutput($script, $template, $cscript, $bRoot, $header = '') {
            $output = "<?php\n";
            
            if ($bRoot && !str_contains($script, 'session_start()')) {
                $output .= "// Auto session management\n";
                $output .= "if (session_status() === PHP_SESSION_NONE) {\n";
                $output .= "    @session_start();\n";
                $output .= "}\n";
            }

            if (!empty($header)) {
                $output .= "\$phpue_header = '" . addslashes($header) . "';\n";
            }

            if ($bRoot) {
                $template = $this->injectHeadersIntoRootTemplate($template);
            }
            
            $output .= $script . "\n";
            $output .= "?>\n";
            
            $output .= $template . "\n";
            $output .= $cscript;
            
            return $output;
        }
    }

    function convert_pvue_file($pvueFilePath, $bRoot = false) {
        $converter = new PHPueConverter();
        
        if (!file_exists($pvueFilePath)) {
            throw new Exception("PVue file not found: $pvueFilePath");
        }
        
        $content = file_get_contents($pvueFilePath);
        return $converter->convertPVueToPHP($content, $bRoot);
    }

    function get_phpue_routing() {
        static $converter = null;
        if ($converter === null) {
            $converter = new PHPueConverter();
            
            // Auto-scan views directory if no routes found
            $routing = $converter->getRouting();
            if (empty($routing->routes)) {
                $views = glob('views/*.pvue');
                foreach ($views as $view) {
                    $routing->addView($view);
                }
            }
        }
        return $converter->getRouting();
    }

    function phpue_navigation($currentPage = null) {
        $routing = get_phpue_routing();
        $currentPage = $currentPage ?? ($_GET['page'] ?? 'index');
        return $routing->getNavigation();
    }

    function phpue_current_meta() {
        $routing = get_phpue_routing();
        $currentPage = $_GET['page'] ?? 'index';
        return $routing->getRouteMeta($currentPage);
    }
?>