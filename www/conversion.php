<?php
    class PHPRouting {
        public $routes = [];
        
        public function addView($viewPath) {
            $viewName = basename($viewPath, '.pvue');
            $headerContent = $this->extractHeaderContent($viewPath);
            
            $this->routes[$viewName] = [
                'file' => $viewPath,
                'compiled' => 'dist/pages/' . $viewName . '.php',
                'route' => $viewName === 'index' ? '/' : "/$viewName",
                'header' => $this->extractMetaData($headerContent)
            ];
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
            
            // Extract complete header content for fallback
            $meta['raw_header'] = $headerContent;
            
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
        
        public function getCurrentPageContent() {
            $currentRoute = $_GET['page'] ?? 'index';
            $sourceFile = $this->routes[$currentRoute]['file'] ?? 'views/index.pvue';
            
            if (file_exists($sourceFile)) {
                ob_start();
                $content = file_get_contents($sourceFile);
                $converter = new PHPueConverter();
                $phpCode = $converter->convertPVueToPHP($content, false);
                eval('?>' . $phpCode);
                return ob_get_clean();
            }
            
            return "<div>Page not found: $currentRoute</div>";
        }
        
        public function buildHeaderFromMeta($meta) {
            $header = '';
            
            // If we have a raw header, use that directly
            if (isset($meta['raw_header']) && !empty($meta['raw_header'])) {
                $header = $this->processAssetPaths($meta['raw_header']);
                $header = $this->ensureCorrectScriptOrder($header);
            }
            
            return $header;
        }

        public function ensureCorrectScriptOrder($header) {
            // Extract jQuery and Bootstrap scripts
            preg_match_all('/<script[^>]*src="[^"]*jquery[^"]*"[^>]*><\/script>/i', $header, $jqueryMatches);
            preg_match_all('/<script[^>]*src="[^"]*bootstrap[^"]*"[^>]*><\/script>/i', $header, $bootstrapMatches);
            
            // Remove them from the header
            $header = preg_replace('/<script[^>]*src="[^"]*jquery[^"]*"[^>]*><\/script>/i', '', $header);
            $header = preg_replace('/<script[^>]*src="[^"]*bootstrap[^"]*"[^>]*><\/script>/i', '', $header);
            
            // Re-add in correct order: jQuery first, then Bootstrap
            $orderedScripts = implode("\n", $jqueryMatches[0]) . "\n" . implode("\n", $bootstrapMatches[0]);
            
            // Insert scripts before the closing </head> tag or at the end
            if (strpos($header, '</head>') !== false) {
                $header = str_replace('</head>', $orderedScripts . "\n</head>", $header);
            } else {
                $header .= $orderedScripts;
            }
            
            return $header;
        }


        private function processAssetPaths($headerContent) {
            // Process script src paths
            $headerContent = preg_replace_callback(
                '/<script\s+[^>]*src="([^"]*)"[^>]*>/',
                function($matches) {
                    $src = $matches[1];
                    // Convert relative asset paths to absolute
                    if (strpos($src, 'assets/') === 0 && $src[0] !== '/') {
                        $src = '/' . $src;
                    }
                    return str_replace($matches[1], $src, $matches[0]);
                },
                $headerContent
            );
            
            // Process link href paths (for CSS, etc.)
            $headerContent = preg_replace_callback(
                '/<link\s+[^>]*href="([^"]*)"[^>]*>/',
                function($matches) {
                    $href = $matches[1];
                    // Convert relative asset paths to absolute
                    if (strpos($href, 'assets/') === 0 && $href[0] !== '/') {
                        $href = '/' . $href;
                    }
                    return str_replace($matches[1], $href, $matches[0]);
                },
                $headerContent
            );
            
            return $headerContent;
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
            
            $componentMap = [];
            $requiredComponents = [];
            
            if ($bRoot) {
                $requireResult = $this->handleRequires($script);
                $script = $requireResult['script'];
                $componentMap = $requireResult['components'];
                $requiredComponents = $requireResult['required'];
                
                $script = $this->injectDynamicHeaderLogic($script);
                $script = $this->injectRoutingLogic($script);
            }
            
            $convertedTemplate = $this->convertVueSyntax($template);
            
            $usedComponents = $this->findComponentsInTemplate($template);
            $missingComponents = array_diff($usedComponents, $requiredComponents);
            
            if (!empty($missingComponents)) {
                $warning = "// WARNING: The following components were used but not required: " . implode(', ', $missingComponents);
                $script = $warning . "\n" . $script;
            }
            
            $convertedTemplate = $this->injectComponents($convertedTemplate, $componentMap);
            
            if ($bRoot) {
                $convertedTemplate = $this->injectPageContent($convertedTemplate);
            }
            
            $convertedCscript = $this->handleCscript($cscript);
            
            $output = $this->buildOutput($script, $convertedTemplate, $convertedCscript, $bRoot, $header);
            
            return $output;
        }

        private function injectRoutingLogic($scriptContent) {
            $routingLogic = <<<'PHP'
                // Auto-injected routing system
                $current_route = $_GET['page'] ?? 'index';
                $available_routes = array_keys(get_phpue_routing()->routes);
                
                if (!in_array($current_route, $available_routes)) {
                    http_response_code(404);
                    $current_route = 'index'; // Fallback to index
                }
                
                $GLOBALS['phpue_current_route'] = $current_route;
            PHP;

            return $routingLogic . "\n" . $scriptContent;
        }

        private function injectPageContent($template) {
            // Replace <View> component with dynamic page content
            $pageInjectionLogic = <<<'PHP'
                <?php
                    $routing = get_phpue_routing();
                    echo $routing->getCurrentPageContent();
                ?>
            PHP;
            
            $template = str_replace('<View></View>', $pageInjectionLogic, $template);
            $template = str_replace('<View/>', $pageInjectionLogic, $template);
            
            return $template;
        }

        private function findComponentsInTemplate($template) {
            preg_match_all('/<(\w+)><\/\1>|<(\w+)\/>/', $template, $matches);
            
            $components = [];
            if (!empty($matches[1])) {
                $components = array_merge($components, array_filter($matches[1]));
            }
            if (!empty($matches[2])) {
                $components = array_merge($components, array_filter($matches[2]));
            }
            
            $htmlTags = ['div', 'span', 'p', 'a', 'button', 'input', 'form', 'img', 'ul', 'li', 'nav', 'header', 'footer', 'main', 'section', 'article', 'View'];
            $components = array_diff($components, $htmlTags);
            
            return array_unique($components);
        }

        private function injectComponents($template, $componentMap) {
            foreach ($componentMap as $componentName => $componentContent) {
                if ($componentName === '__view_components') continue;
                
                $placeholder = '<' . $componentName . '></' . $componentName . '>';
                $template = str_replace($placeholder, $componentContent, $template);
                
                $selfClosingPlaceholder = '<' . $componentName . '/>';
                $template = str_replace($selfClosingPlaceholder, $componentContent, $template);
            }
            
            return $template;
        }

        private function injectDynamicHeaderLogic($scriptContent) {
            $dynamicHeaderLogic = <<<'PHP'
                // Auto-injected dynamic header system
                $current_route = $_GET['page'] ?? 'index';
                
                $routing = get_phpue_routing();
                $route_meta = $routing->getRouteMeta($current_route);
                
                // Start with App.pvue header as base
                $current_header = $phpue_header ?? '';
                
                // If current route has its own header, MERGE them (don't replace)
                if (!empty($route_meta)) {
                    $route_header = $routing->buildHeaderFromMeta($route_meta);
                    if (!empty($route_header)) {
                        // Merge: Use route header + App header
                        $current_header = $route_header . "\n" . $current_header;
                    }
                }
            PHP;

            return $dynamicHeaderLogic . "\n" . $scriptContent;
        }

        private function injectHeadersIntoRootTemplate($template) {
            // Remove any existing DOCTYPE or html structure
            $template = preg_replace('/<!DOCTYPE[^>]*>/i', '', $template);
            $template = preg_replace('/<html[^>]*>/i', '', $template);
            $template = preg_replace('/<\/html>/i', '', $template);
            $template = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $template);
            $template = preg_replace('/<body[^>]*>/i', '', $template);
            $template = preg_replace('/<\/body>/i', '', $template);
            
            // Wrap with proper HTML structure
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
            
            return $template;
        }

        public function getRouting()
        { return $this->routing; }
        
        private function extractBetween($content, $startTag, $endTag) {
            $pattern = '/' . preg_quote($startTag, '/') . '(.*?)' . preg_quote($endTag, '/') . '/s';
            preg_match($pattern, $content, $matches);
            return $matches[1] ?? '';
        }
        
        public function handleRequires($scriptContent) {
            preg_match_all('/@require\s+(\w+)\s+\'([^\']+)\'\s*;?/', $scriptContent, $componentRequires, PREG_SET_ORDER);
            
            $componentMap = [];
            $requiredComponents = [];
            
            foreach ($componentRequires as $match) {
                $componentName = $match[1];
                $componentPath = $match[2];
                $requiredComponents[] = $componentName;
                
                if (file_exists($componentPath)) {
                    $componentContent = file_get_contents($componentPath);
                    $compiledComponent = $this->convertPVueToPHP($componentContent, false);
                    
                    $componentMap[$componentName] = $compiledComponent;
                } else {
                    $scriptContent .= "\n// ERROR: Component file not found: $componentPath\n";
                }
            }

            $lines = explode("\n", $scriptContent);
            $cleanScriptContent = [];

            foreach ($lines as $line) {
                if (preg_match('/^\s*@require\s+\w+\s+\'[^\']+\'\s*;?\s*$/', $line)) {
                    $cleanScriptContent[] = "// @require processed and removed";
                    continue;
                }
                
                if (preg_match('/^\s*#require\s+[\'"]([^\'"]+\.pvue)[\'"]\s*;?\s*$/', $line, $matches)) {
                    $viewPath = $matches[1];

                    if (file_exists($viewPath)) {
                        $this->routing->addView($viewPath);
                        $cleanScriptContent[] = "// Added view to routing: $viewPath";
                    } else {
                        $cleanScriptContent[] = "// ERROR: View file not found: $viewPath";
                    }
                } else {
                    $cleanScriptContent[] = $line;
                }
            }

            $scriptContent = implode("\n", $cleanScriptContent);

            return [
                'script' => $scriptContent,
                'components' => $componentMap,
                'required' => $requiredComponents
            ];
        }
        
        private function convertVueSyntax($template) {
            $template = preg_replace('/<header>.*?<\/header>/s', '', $template);
            
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
            
            // Convert PHP variables to JavaScript - handle all types properly
            $cscript = preg_replace_callback(
                '/\{\{\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\}\}/',
                function($matches) {
                    $phpVar = $matches[1];
                    return "<?= json_encode($phpVar, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>";
                },
                $cscript
            );
            
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
                $output .= "\$phpue_header = <<<HTML\n{$header}\nHTML;\n";
            }
            
            $output .= $script . "\n";
            $output .= "?>\n";
            
            // Properly structure the HTML with scripts at the bottom
            if ($bRoot) {
                $output .= "<!DOCTYPE html>\n";
                $output .= "<html>\n";
                $output .= "<head>\n";
                $output .= "    <?= \$current_header ?? '' ?>\n";
                $output .= "</head>\n";
                $output .= "<body>\n";
                $output .= $template . "\n";
                $output .= $cscript . "\n"; // Move cscript to bottom of body
                $output .= "</body>\n";
                $output .= "</html>\n";
            } else {
                $output .= $template . "\n";
                $output .= $cscript . "\n";
            }
            
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
        return $routing->getNavigation();
    }

    function phpue_current_meta() {
        $routing = get_phpue_routing();
        $currentPage = $_GET['page'] ?? 'index';
        return $routing->getRouteMeta($currentPage);
    }
?>