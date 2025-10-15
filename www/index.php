<?php
    require_once 'conversion.php';

    class PHPueServer {
        public $bDevMode;

        public function __construct()
        {
            $this->bDevMode = $this->detectDevMode();
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
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Access-Control-Allow-Origin: *');

            $lastCheck = time();

            while(true) {
                $files = array_merge(
                    glob('*.pvue'),
                    glob('components/*.pvue'),
                    glob('views/*.pvue'),
                    glob('backend/*.php') // Watch backend files too!
                );

                $bChanged = false;
                foreach($files as $file) {
                    if(filemtime($file) > $lastCheck) {
                        $bChanged = true;
                        break;
                    }
                }

                if($bChanged) {
                    echo "data: ".json_encode(['reload' => true, 'time' => time()])."\n\n";
                    ob_flush();
                    flush();
                    $lastCheck = time();
                }

                sleep(1);
            }
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
            // Check if .dist directory exists and has App.php
            $distApp = '.dist/App.php';
            $appPVue = 'App.pvue';
            
            if(file_exists($distApp) && is_dir('.dist')) {
                // Serve from built .dist directory
                $this->serveFromDist();
            } elseif(file_exists($appPVue)) {
                // Serve from source .pvue files (development mode)
                $this->serveFromSource();
            } else {
                http_response_code(500);
                echo "Error: Neither App.pvue nor .dist/App.php found";
            }
        }
        
        private function serveFromDist() {
            $distApp = '.dist/App.php';
            
            // Auto-load backend classes in production
            $this->autoLoadBackendClasses();
            
            if(file_exists($distApp)) {
                // Include the built App.php
                include $distApp;
            } else {
                http_response_code(500);
                echo "Error: .dist/App.php not found";
            }
        }
        
        private function serveFromSource() {
            $appPVue = 'App.pvue';
            
            // Auto-load backend classes in development
            $this->autoLoadBackendClasses();
            
            $this->preProcessAllViewsForAjax();
            $phpCode = convert_pvue_file($appPVue, true);
            eval('?>' . $phpCode);
        }
        
        private function autoLoadBackendClasses() {
            // Development mode - load from source
            $backendDir = $this->bDevMode ? 'backend' : '.dist/backend';
            
            if (!is_dir($backendDir)) return;
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($backendDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    require_once $file->getPathname();
                }
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
            
            // Copy backend FIRST so they're available during compilation
            $this->copyBackendLoaders();
            
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

            foreach ($iterator as $pvueFile) {
                if (pathinfo($pvueFile, PATHINFO_EXTENSION) === 'pvue') {
                    $relativePath = str_replace('\\', '/', substr($pvueFile, strlen('components/'))); // Normalize slashes
                    $phpTargetPath = '.dist/components/' . substr($relativePath, 0, -5) . '.php'; // Replace .pvue with .php

                    $phpTargetDir = dirname($phpTargetPath);
                    if (!is_dir($phpTargetDir)) {
                        mkdir($phpTargetDir, 0755, true);
                    }

                    $phpCode = convert_pvue_file($pvueFile, false, $pvueFile);
                    file_put_contents($phpTargetPath, $phpCode);

                    echo "✅ Compiled: $pvueFile -> $phpTargetPath\n";
                }
            }

            $files = glob('views/*.pvue');
            foreach ($files as $pvueFile) {
                $phpFile = '.dist/pages/' . basename($pvueFile, '.pvue') . '.php';
                $phpCode = convert_pvue_file($pvueFile, false, $pvueFile);
                file_put_contents($phpFile, $phpCode);
                echo "✅ Compiled: $pvueFile -> $phpFile\n";
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
            if(!$this->bDevMode) return '';

            return <<<HTML
                <script>
                    if(typeof(EventSource) !== "undefined") {
                        const eventSource = new EventSource("?hot-reload=1");

                        eventSource.onmessage = function(event) {
                            const data = JSON.parse(event.data);

                            if(data.reload) {
                                console.log("🔄 PHPue Hot Reload: Changes detected, refreshing...");
                                setTimeout(() => {
                                    window.location.reload();
                                }, 100);
                            }
                        }

                        eventSource.onerror = function(event) {
                            console.log("❌ PHPue Hot Reload: Connection lost");
                        }

                        console.log("🔥 PHPue Hot Reload: Connected and watching for changes...")
                    } else console.log("❌ PHPue Hot Reload: Not supported in this browser");
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
        define('PHPUE_BUILD_MODE', true);

        $server->build();
        exit;
    }

    $server->serve();
?>