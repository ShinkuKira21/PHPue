<?php
    define('PHPUE_VERSION', '0.0.1');
    
    require_once 'conversion.php';

    class PHPueServer {
        public $bDevMode;
        public $bLiveMode;

        public function __construct()
        {
            $this->bDevMode = $this->detectDevMode();
            $this->bLiveMode = isset($_GET['live']);
        }

        public function serve() {
            if(isset($_GET['hot-reload'])) {
                $this->handleHotReload();
                return;
            }

            if(isset($_GET['compile'])) {
                $this->handleCompilation();
                return;
            }

            $this->serveApp();
        }

        public function build() {
            global $argv; // Need to access argv inside the method
            
            // Check if .dist/ exists AND this is NOT a CLI build command
            $isCliBuild = (isset($argv[1]) && $argv[1] === 'build');
            if (is_dir('.dist') && !$isCliBuild) {
                echo "❌ Build disabled - .dist/ directory already exists\n";
                echo "   This appears to be a production environment\n";
                echo "   To force rebuild, use: php index.php build\n";
                return;
            }
            
            $this->ensureDistDirectory();
            $this->compileAllFiles();
            echo "✅ Build complete! All .pvue files compiled to .dist/ directory\n";
        }

        private function ensureDistDirectory() {
            $distDir = '.dist';
            if (!is_dir($distDir)) {
                mkdir($distDir, 0755, true);
            }
            if (!is_dir($distDir . '/assets')) {
                mkdir($distDir . '/assets', 0755, true);
            }
            if (!is_dir($distDir . '/components')) {
                mkdir($distDir . '/components', 0755, true);
            }
            if (!is_dir($distDir . '/pages')) {
                mkdir($distDir . '/pages', 0755, true);
            }
            if (!is_dir($distDir . '/ajax')) {
                mkdir($distDir . '/ajax', 0755, true);
            }
            if (!is_dir($distDir . '/backend')) {
                mkdir($distDir . '/backend', 0755, true);
            }
        }

        private function detectDevMode() {
            return $_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1' || isset($_GET['dev']);
        }

       private function handleHotReload() {
            $bLiveMode = isset($_GET['live']) || $this->bLiveMode;
            
            if (!$bLiveMode) {
                http_response_code(403);
                echo "Hot reload requires ?live parameter";
                return;
            }

            if (ob_get_level()) {
                ob_end_clean();
            }
            
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Access-Control-Allow-Origin: *');
            
            ob_implicit_flush(true);
            
            echo "data: connected\n\n";
            flush();

            $lastCheck = time();
            
            for ($i = 0; $i < 30; $i++) {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                $files = array_merge(
                    glob('*.pvue'),
                    glob('components/*.pvue'), 
                    glob('views/*.pvue')
                );

                $changed = false;
                foreach($files as $file) {
                    if(filemtime($file) > $lastCheck) {
                        $changed = true;
                        break;
                    }
                }

                if($changed) {
                    echo "data: reload\n\n";
                    flush();
                    exit;
                }
                
                echo ": heartbeat\n\n";
                flush();
                
                if (connection_aborted()) {
                    break;
                }
                
                sleep(1);
            }
            
            echo "data: timeout\n\n";
            flush();
        }

        private function handleCompilation()
        {
            $file = $_GET['compile'];
            $bRoot = ($_GET['root'] ?? 'false') === 'true';

            try {
                $phpCode = convert_pvue_file($file, $bRoot);
                header('Content-Type: text/plain');
                echo $phpCode;
            } catch(Exception $e) {
                http_response_code(500);
                echo "Compilation Error: " . $e->getMessage();
            }
        }

        private function serveApp()
        {
            $distApp = '.dist/App.php';
            $appPVue = 'App.pvue';
            
            $currentRoute = $_GET['page'] ?? 'index';
            $routing = get_phpue_routing();
            $routeExists = isset($routing->routes[$currentRoute]);
            
            if(!$routeExists && $currentRoute !== '404') {
                $_GET['page'] = '404';
                http_response_code(404);
            }
            
            if(file_exists($distApp) && is_dir('.dist')) {
                $this->serveFromDist();
            } elseif(file_exists($appPVue)) {
                $this->serveFromSource();
            } else {
                http_response_code(500);
                echo "Error: Neither App.pvue nor .dist/App.php found";
            }
        }
        
        private function serveFromDist() {
            $distApp = '.dist/App.php';
            
            if(file_exists($distApp)) {
                include $distApp;
            } else {
                http_response_code(500);
                echo "Error: .dist/App.php not found";
            }
        }
        
        private function serveFromSource() {
            $appPVue = 'App.pvue';
            
            $this->preProcessAllViewsForAjax();
            $phpCode = convert_pvue_file($appPVue, true);
            eval('?>' . $phpCode);

            if ($this->bLiveMode) {
                echo $this->injectHotReloadScript();
            }
        }
        
        private function preProcessAllViewsForAjax() {
            $converter = get_phpue_converter();
            
            if (file_exists('App.pvue')) {
                $content = file_get_contents('App.pvue');
                $converter->preProcessForAjax($content, 'App.pvue');
            }
            
            $views = glob('views/*.pvue');
            foreach ($views as $view) {
                $content = file_get_contents($view);
                $converter->preProcessForAjax($content, $view);
            }
            
            $components = glob('components/*.pvue');
            foreach ($components as $component) {
                $content = file_get_contents($component);
                $converter->preProcessForAjax($content, $component);
            }
        }

        private function compileAllFiles() {
            $this->ensureDistDirectory();

            $this->copyBackendLoaders();

            $httpReqsDir = 'httpReqs';
            $distHttpReqsDir = '.dist/httpReqs';
            if (is_dir($httpReqsDir)) {
                $this->copyDirectory($httpReqsDir, $distHttpReqsDir);
                echo "✅ Copied httpReqs to .dist/httpReqs/\n";
            }

            $appPVue = 'App.pvue';
            $appPHP = '.dist/App.php';
            if(file_exists($appPVue)) {
                $this->preProcessAllViewsForAjax();
                $phpCode = convert_pvue_file($appPVue, true, $appPVue);
                file_put_contents($appPHP, $phpCode);
                echo "✅ Compiled: $appPVue -> $appPHP\n";
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator('components', RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $relativePath = str_replace('\\', '/', substr($file, strlen('components/')));
                $targetPath = '.dist/components/' . $relativePath;

                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                if ($ext === 'pvue') {
                    $phpTargetPath = substr($targetPath, 0, -5) . '.php';
                    $phpCode = convert_pvue_file($file, false, $file);
                    file_put_contents($phpTargetPath, $phpCode);
                    echo "✅ Compiled: $file -> $phpTargetPath\n";
                } elseif ($ext === 'php') {
                    copy($file, $targetPath);
                    echo "📄 Copied PHP: $file -> $targetPath\n";
                }
            }

            $files = glob('views/*.pvue');
            foreach ($files as $pvueFile) {
                $phpFile = '.dist/pages/' . basename($pvueFile, '.pvue') . '.php';
                $phpCode = convert_pvue_file($pvueFile, false, $pvueFile);
                file_put_contents($phpFile, $phpCode);
                echo "✅ Compiled: $pvueFile -> $phpFile\n";
            }

            $phpViews = glob('views/*.php');
            foreach ($phpViews as $phpView) {
                $targetPath = '.dist/pages/' . basename($phpView);
                copy($phpView, $targetPath);
                echo "📄 Copied PHP: $phpView -> $targetPath\n";
            }

            $converter = get_phpue_converter();
            $converter->generateAjaxFiles();
            echo "✅ Generated AJAX handler files\n";

            $this->copyAssetsToDist();
        }

        private function copyBackendLoaders() {
            $backendDir = 'backend';
            $distBackendDir = '.dist/backend';
            
            if (is_dir($backendDir)) {
                $this->copyDirectory($backendDir, $distBackendDir);
                echo "✅ Copied backend to .dist/backend/\n";
            } else {
                echo "ℹ️ No backend directory found\n";
            }
        }

        private function copyAssetsToDist() {
            $assetsDir = 'assets';
            $distAssetsDir = '.dist/assets';
            
            if (!is_dir($distAssetsDir)) {
                mkdir($distAssetsDir, 0755, true);
            }
            
            if (is_dir($assetsDir)) {
                $this->copyDirectory($assetsDir, $distAssetsDir);
                echo "✅ Copied assets to .dist/assets/\n";
            } else {
                echo "ℹ️ No assets directory found\n";
            }
        }

        private function copyDirectory($source, $destination) {
            if (!is_dir($destination)) {
                mkdir($destination, 0755, true);
            }
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $item) {
                $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                
                if ($item->isDir()) {
                    if (!is_dir($target)) {
                        mkdir($target, 0755, true);
                    }
                } else {
                    copy($item->getPathname(), $target);
                }
            }
        }

        public function injectHotReloadScript() {
            if(!$this->bLiveMode) return '';

            return <<<HTML
                <script>
                    (function setupHotReload() {
                        if(typeof(EventSource) === "undefined") {
                            console.log("❌ PHPue Hot Reload: Not supported in this browser");
                            return;
                        }

                        console.log("🔥 PHPue Live Mode: Hot reload active - AJAX may not work properly");
                        
                        const eventSource = new EventSource("?hot-reload=1&live=1");

                        eventSource.onopen = function() {
                            console.log("✅ PHPue Live Mode: Connected and watching for changes...");
                        };

                        eventSource.onmessage = function(event) {
                            if (event.data === 'reload') {
                                console.log("🔄 PHPue Live Mode: Changes detected, refreshing...");
                                window.location.reload();
                            }
                        };

                        eventSource.onerror = function(event) {
                            console.log("❌ PHPue Live Mode: Connection error");
                            eventSource.close();
                        };

                    })();
                </script>
            HTML;
        }
    }

    function get_current_route() {
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($request_uri, PHP_URL_PATH);
        
        $path = trim($path, '/');
        
        if (empty($path)) {
            return 'index';
        }
        
        return $path;
    }

    $_GET['page'] = get_current_route();

    $server = new PHPueServer();

    if (isset($_GET['build']) || (isset($argv[1]) && $argv[1] === 'build')) {
        $server->build();
        exit;
    }

    $server->serve();
?>