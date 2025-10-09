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
            
            if (preg_match('/<title>(.*?)<\/title>/s', $headerContent, $matches)) {
                $meta['title'] = trim($matches[1]);
            }
            
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
        
        public function preProcessCurrentPage($sourceFile = null) {
            if ($sourceFile === null) {
                $currentRoute = $_GET['page'] ?? 'index';
                $sourceFile = $this->routes[$currentRoute]['file'] ?? 'views/index.pvue';
            }
            
            if (file_exists($sourceFile)) {
                $content = file_get_contents($sourceFile);
                $converter = get_phpue_converter();
                
                // Pre-process to extract AJAX data (but don't execute)
                $converter->preProcessForAjax($content, $sourceFile);
                
                // Store the processed PHP code for later execution
                // FIX: Pass the filename parameter here!
                $GLOBALS['phpue_current_page_code'] = $converter->convertPVueToPHP($content, false, $sourceFile);
                return true;
            }
            return false;
        }
        
        public function getCurrentPageContent() {
            if (isset($GLOBALS['phpue_current_page_code'])) {
                ob_start();
                eval('?>' . $GLOBALS['phpue_current_page_code']);
                return ob_get_clean();
            }
            
            $currentRoute = $_GET['page'] ?? 'index';
            return "<div>Page not found: $currentRoute</div>";
        }
        
        public function buildHeaderFromMeta($meta) {
            $header = '';
            
            if (isset($meta['raw_header']) && !empty($meta['raw_header'])) {
                $header = $this->processAssetPaths($meta['raw_header']);
                $header = $this->ensureCorrectScriptOrder($header);
            }
            
            return $header;
        }

        public function ensureCorrectScriptOrder($header) {
            preg_match_all('/<script[^>]*src="[^"]*jquery[^"]*"[^>]*><\/script>/i', $header, $jqueryMatches);
            preg_match_all('/<script[^>]*src="[^"]*bootstrap[^"]*"[^>]*><\/script>/i', $header, $bootstrapMatches);
            
            $header = preg_replace('/<script[^>]*src="[^"]*jquery[^"]*"[^>]*><\/script>/i', '', $header);
            $header = preg_replace('/<script[^>]*src="[^"]*bootstrap[^"]*"[^>]*><\/script>/i', '', $header);
            
            $orderedScripts = implode("\n", $jqueryMatches[0]) . "\n" . implode("\n", $bootstrapMatches[0]);
            
            if (strpos($header, '</head>') !== false) {
                $header = str_replace('</head>', $orderedScripts . "\n</head>", $header);
            } else {
                $header .= $orderedScripts;
            }
            
            return $header;
        }


        private function processAssetPaths($headerContent) {
            $headerContent = preg_replace_callback(
                '/<script\s+[^>]*src="([^"]*)"[^>]*>/',
                function($matches) {
                    $src = $matches[1];
                    if (strpos($src, 'assets/') === 0 && $src[0] !== '/') {
                        $src = '/' . $src;
                    }
                    return str_replace($matches[1], $src, $matches[0]);
                },
                $headerContent
            );
            
            $headerContent = preg_replace_callback(
                '/<link\s+[^>]*href="([^"]*)"[^>]*>/',
                function($matches) {
                    $href = $matches[1];
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
        private $ajaxHandlingCode = [];
        private $ajaxFunctions = [];
        private $currentPageName = '';
        
        public function __construct() {
            $this->routing = new PHPRouting();
            $this->ajaxHandlingCode = [];
            $this->ajaxFunctions = []; 
        }

        public function convertPVueToPHP($pvueContent, $bRoot = false, $fileName = '') {
            $this->currentPageName = basename($fileName, '.pvue');
            // Debug: make sure we have the right page name
            if (empty($this->currentPageName)) {
                error_log("Warning: Empty page name for file: $fileName");
            }

            $script = $this->extractBetween($pvueContent, '<script setup>', '</script>');
            if (empty(trim($script))) {
                $script = $this->extractBetween($pvueContent, '<script>', '</script>');
            }

            // Process AJAX annotations in EVERY component
            $script = $this->processAjaxAnnotations($script, $this->currentPageName);

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

        public function preProcessForAjax($pvueContent, $fileName = '') {
            $this->currentPageName = basename($fileName, '.pvue');
            $script = $this->extractBetween($pvueContent, '<script setup>', '</script>');
            if (empty(trim($script))) {
                $script = $this->extractBetween($pvueContent, '<script>', '</script>');
            }
            
            // Process AJAX annotations but don't output anything
            $this->processAjaxAnnotations($script, $this->currentPageName);
            
            return $this;
        }

        public function getAjaxFunctions() {
            return $this->ajaxFunctions;
        }

        public function getAjaxHandling() {
            return $this->ajaxHandlingCode;
        }

        public function setAjaxData($functions, $handling) {
            $this->ajaxFunctions = $functions;
            $this->ajaxHandlingCode = $handling;
        }

        /**
         * Process @AJAX annotations - Collect from all components
         */
        private function processAjaxAnnotations($scriptContent, $pageName) {
            // Initialize arrays for this page if they don't exist
            if (!isset($this->ajaxFunctions[$pageName])) {
                $this->ajaxFunctions[$pageName] = [];
            }
            
            // Extract @AJAX functions
            if (preg_match_all('/@AJAX\s*(function\s+\w+\([^)]*\)\s*\{[^}]+\})/s', $scriptContent, $matches)) {
                foreach ($matches[1] as $ajaxFunction) {
                    if (preg_match('/function\s+(\w+)/', $ajaxFunction, $funcMatch)) {
                        $functionName = $funcMatch[1];
                        $this->ajaxFunctions[$pageName][$functionName] = trim($ajaxFunction);
                    }
                }
                // Remove @AJAX functions from this component's script
                $scriptContent = preg_replace('/@AJAX\s*(function\s+\w+\([^)]*\)\s*\{[^}]+\})/s', '// @AJAX function moved to ajax file', $scriptContent);
            }
            
            // Extract AJAX calling logic
            if (preg_match('/(\$input\s*=\s*json_decode\([^;]+;[^if]+if\s*\(\s*isset\(\$input\[\'action\'\]\)[^)]+\)\s*\{[^}]+\})/s', $scriptContent, $matches)) {
                $this->ajaxHandlingCode[$pageName] = trim($matches[1]);
                // Remove AJAX calling logic from this component's script
                $scriptContent = str_replace($matches[1], '// AJAX calling logic moved to ajax file', $scriptContent);
            }
            
            return $scriptContent;
        }

        public function generateAjaxFiles() {
            $ajaxDir = 'dist/ajax';
            if (!is_dir($ajaxDir)) {
                mkdir($ajaxDir, 0755, true);
            }
            
            foreach ($this->ajaxFunctions as $pageName => $functions) {
                // Skip if pageName is empty
                if (empty($pageName)) continue;
                
                // Allow: views (index, about, etc.) AND App
                $isView = false;
                foreach ($this->routing->routes as $routeName => $route) {
                    if ($routeName === $pageName) {
                        $isView = true;
                        break;
                    }
                }
                
                $isApp = ($pageName === 'App');
                
                if (!$isView && !$isApp) {
                    continue; // Skip components (Navbar, etc.) and other files
                }
                
                $ajaxContent = "<?php\n";
                $ajaxContent .= "// AJAX handlers for $pageName\n";
                
                // Add functions
                foreach ($functions as $functionName => $functionCode) {
                    $ajaxContent .= $functionCode . "\n\n";
                }
                
                // Add AJAX calling logic for this page
                if (isset($this->ajaxHandlingCode[$pageName])) {
                    $ajaxContent .= "// AJAX/POST Request Handling for $pageName\n";
                    $ajaxContent .= "if (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n";
                    $indentedAjaxCode = preg_replace('/^/m', '    ', $this->ajaxHandlingCode[$pageName]);
                    $ajaxContent .= $indentedAjaxCode;
                    $ajaxContent .= "}\n";
                }
                
                $ajaxFile = $ajaxDir . "/ajax-$pageName.php";
                file_put_contents($ajaxFile, $ajaxContent);
                echo "✅ Generated AJAX: $ajaxFile\n";
            }
        }

        private function injectRoutingLogic($scriptContent) {
            $routingLogic = <<<'PHP'
                $current_route = $_GET['page'] ?? 'index';
                $available_routes = array_keys(get_phpue_routing()->routes);
                
                if (!in_array($current_route, $available_routes)) {
                    http_response_code(404);
                    $current_route = 'index';
                }
                
                $GLOBALS['phpue_current_route'] = $current_route;
            PHP;

            return $routingLogic . "\n" . $scriptContent;
        }

        private function injectPageContent($template) {
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
                $current_route = $_GET['page'] ?? 'index';
                
                $routing = get_phpue_routing();
                $route_meta = $routing->getRouteMeta($current_route);
                
                $current_header = $phpue_header ?? '';
                
                if (!empty($route_meta)) {
                    $route_header = $routing->buildHeaderFromMeta($route_meta);
                    if (!empty($route_header)) {
                        $current_header = $route_header . "\n" . $current_header;
                    }
                }
            PHP;

            return $dynamicHeaderLogic . "\n" . $scriptContent;
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
            
            // Step 1: Convert p-for to php-for
            $template = preg_replace_callback('/p-for="(\$.*?) in (\$.*?)"/', 
                function($matches) {
                    $item = trim($matches[1]);
                    $array = trim($matches[2]); 
                    return "php-for=\"$item in $array\"";
                }, 
                $template);
            
            // Step 2: Process loops on the ENTIRE template (not line by line)
            $template = preg_replace_callback(
                '/<(\w+)([^>]*)php-for="(\$[^"]+) in (\$[^"]+)"([^>]*)>([\s\S]*?)<\/\1>/',
                function($matches) {
                    $tag = $matches[1];
                    $item = trim($matches[3]); 
                    $array = trim($matches[4]);
                    $content = $matches[6]; // The content between opening and closing tags
                    
                    return "<?php if(isset($array) && is_array($array)): foreach($array as $item): ?>" .
                        "<$tag{$matches[2]}{$matches[5]}>$content</$tag>" .
                        "<?php endforeach; endif; ?>";
                },
                $template
            );
            
            // Step 3: Process template variables
            $template = preg_replace('/\{\{\s*(\$.*?)\s*\}\}/', '<?= htmlspecialchars($1 ?? "") ?>', $template);
            
            $template = preg_replace_callback('/p-model="(\$[^"]*)"/', 
                function($matches) {
                    $variable = trim($matches[1]);
                    return "name=\"".substr($variable,1)."\" value=\"<?= htmlspecialchars($variable ?? '') ?>\"";
                }, 
                $template);
            
            $template = preg_replace_callback('/p-if="([^"]*)"/', 
                function($matches) {
                    $condition = trim($matches[1]);
                    return "php-if=\"$condition\"";
                }, 
                $template);
            
            $pIfStack = [];
            $lines = explode("\n", $template);
            $output = [];
            
            foreach ($lines as $line) {
                if (preg_match('/<(\w+)([^>]*) php-if="([^"]*)"([^>]*)>/', $line, $matches)) {
                    $tag = $matches[1];
                    $condition = trim($matches[3]);
                    
                    $pIfStack[] = $tag;
                    $line = preg_replace('/php-if="[^"]+"/', '', $line);
                    $line = "<?php if($condition): ?>" . $line;
                }
                
                if (preg_match('/<\/(\w+)>/', $line, $matches)) {
                    $closingTag = $matches[1];
                    
                    if (!empty($pIfStack) && $closingTag === end($pIfStack)) {
                        array_pop($pIfStack);
                        $line = $line . "<?php endif; ?>";
                    }
                }
                
                $output[] = $line;
            }
            
            $template = implode("\n", $output);
            
            return $template;
        }


        private function handleCscript($cscript) {
            if (empty($cscript)) return '';
            
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
                
            if ($bRoot) {
                // Auto session management
                if (!str_contains($script, 'session_start()')) {
                    $output .= "// Auto session management\n";
                    $output .= "if (session_status() === PHP_SESSION_NONE) {\n";
                    $output .= "    @session_start();\n";
                    $output .= "}\n";
                }

                $output .= "// Load AJAX handlers\n";
                $output .= "\$ajaxFiles = glob('dist/ajax/ajax-*.php');\n";
                $output .= "if (!empty(\$ajaxFiles)) {\n";
                $output .= "    // Production mode: Load from pre-compiled files\n";
                $output .= "    foreach (\$ajaxFiles as \$ajaxFile) {\n";
                $output .= "        require_once \$ajaxFile;\n";
                $output .= "    }\n";
                $output .= "} else {\n";
                $output .= "    // Development/Runtime mode: Inject AJAX code directly\n";
                
                // Inject all AJAX functions
                foreach ($this->ajaxFunctions as $pageName => $functions) {
                    foreach ($functions as $functionName => $functionCode) {
                        $output .= "    " . str_replace("\n", "\n    ", $functionCode) . "\n\n";
                    }
                }
                $output .= "}\n\n"; // Close the else block here

                // 🚨 MOVE THE AJAX HANDLING OUTSIDE THE CONDITIONAL
                $output .= "// AJAX/POST Request Handling (runs in both modes)\n";
                $output .= "if (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n";
                foreach ($this->ajaxHandlingCode as $pageName => $ajaxCode) {
                    $indentedAjaxCode = preg_replace('/^/m', '    ', $ajaxCode);
                    $output .= $indentedAjaxCode;
                }
                $output .= "    // Exit after AJAX processing to prevent page render\n";
                $output .= "    exit;\n";
                $output .= "}\n\n";
            }
                                            
            if (!empty($header)) {
                $output .= "\$phpue_header = <<<HTML\n{$header}\nHTML;\n";
            }
            
            $output .= $script . "\n";
            $output .= "?>\n";

            if ($bRoot) {
                $output .= "<!DOCTYPE html>\n";
                $output .= "<html>\n";
                $output .= "<head>\n";
                
                // Load ALL headers upfront - App.pvue headers + current view headers
                $output .= "<?php\n";
                $output .= "// Start with App.pvue header\n";
                $output .= "echo \$phpue_header ?? '';\n";
                $output .= "\n";
                $output .= "// Add current view header\n";
                $output .= "\$routing = get_phpue_routing();\n";
                $output .= "\$current_route = \$_GET['page'] ?? 'index';\n";
                $output .= "\$route_meta = \$routing->getRouteMeta(\$current_route);\n";
                $output .= "if (!empty(\$route_meta)) {\n";
                $output .= "    \$view_header = \$routing->buildHeaderFromMeta(\$route_meta);\n";
                $output .= "    if (!empty(\$view_header)) {\n";
                $output .= "        echo \"\\n\" . \$view_header;\n";
                $output .= "    }\n";
                $output .= "}\n";
                $output .= "?>\n";
                
                $output .= "</head>\n";
                $output .= "<body>\n";
                $output .= $template . "\n";
                $output .= $cscript . "\n";
                $output .= "</body>\n";
                $output .= "</html>\n";
            } else {
                $output .= $template . "\n";
                $output .= $cscript . "\n";
            }
            
            return $output;
        }
    }

    function get_phpue_converter() {
        static $converter = null;
        if ($converter === null) {
            $converter = new PHPueConverter();
        }
        return $converter;
    }

    function convert_pvue_file($pvueFilePath, $bRoot = false) {
        $converter = get_phpue_converter();
        
        if (!file_exists($pvueFilePath)) {
            throw new Exception("PVue file not found: $pvueFilePath");
        }
        
        $content = file_get_contents($pvueFilePath);
        return $converter->convertPVueToPHP($content, $bRoot, $pvueFilePath); // ADD filename parameter
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
            
            // PRE-PROCESS THE CURRENT PAGE to collect AJAX data
            // MODIFY this line:
            $currentRoute = $_GET['page'] ?? 'index';
            $sourceFile = $routing->routes[$currentRoute]['file'] ?? 'views/index.pvue';
            $routing->preProcessCurrentPage($sourceFile); // ADD filename parameter
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