<?php
    require_once 'conversion.php';

    class PHPueServer {
        private $converter;
        public $bDevMode;

        public function __construct()
        {
            $this->converter = new PHPueConverter();
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
            echo "✅ Build complete! All .pvue files compiled to dist/ directory\n";
        }

        private function ensureDistDirectory() {
            $distDir = 'dist';
            if (!is_dir($distDir)) {
                mkdir($distDir, 0755, true);
            }
            if (!is_dir($distDir . '/components')) {
                mkdir($distDir . '/components', 0755, true);
            }
            if (!is_dir($distDir . '/pages')) {
                mkdir($distDir . '/pages', 0755, true);
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
            // Serve directly from .pvue files without creating dist files
            $appPVue = 'App.pvue';
            
            if(file_exists($appPVue)) {
                $phpCode = convert_pvue_file($appPVue, true);
                eval('?>' . $phpCode);
            } else {
                http_response_code(500);
                echo "Error: App.pvue not found";
            }
        }

        private function compileViewOnDemand($viewPath) {
            if (file_exists($viewPath)) {
                $phpCode = convert_pvue_file($viewPath, false);
                eval('?>' . $phpCode);
                return true;
            }
            return false;
        }

        private function compileAllFiles() {
            // Compile App.pvue
            $appPVue = 'App.pvue';
            $appPHP = 'dist/App.php';
            if(file_exists($appPVue)) {
                $phpCode = convert_pvue_file($appPVue, true);
                file_put_contents($appPHP, $phpCode);
                echo "✅ Compiled: $appPVue -> $appPHP\n";
            }

            // Compile all components
            $files = glob('components/*.pvue');
            foreach ($files as $pvueFile) {
                $phpFile = 'dist/components/' . basename($pvueFile, '.pvue') . '.php';
                $phpCode = convert_pvue_file($pvueFile, false);
                file_put_contents($phpFile, $phpCode);
                echo "✅ Compiled: $pvueFile -> $phpFile\n";
            }

            // Compile all views
            $files = glob('views/*.pvue');
            foreach ($files as $pvueFile) {
                $phpFile = 'dist/pages/' . basename($pvueFile, '.pvue') . '.php';
                $phpCode = convert_pvue_file($pvueFile, false);
                file_put_contents($phpFile, $phpCode);
                echo "✅ Compiled: $pvueFile -> $phpFile\n";
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

    // Enhanced routing detection
    function get_current_route() {
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($request_uri, PHP_URL_PATH);
        
        // Remove leading/trailing slashes
        $path = trim($path, '/');
        
        // Handle root route
        if (empty($path)) {
            return 'index';
        }
        
        return $path;
    }

    // Override the $_GET['page'] for clean URLs
    $_GET['page'] = get_current_route();

    $server = new PHPueServer();

    // Check if build command was called
    if (isset($_GET['build']) || (isset($argv[1]) && $argv[1] === 'build')) {
        $server->build();
        exit;
    }

    $server->serve();
?>