<!-- Author: Edward Patch -->

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
            $phpCode = convert_pvue_file('App.pvue', true);
    
            eval('?>' . $phpCode);
        }

        private function ensureAppCompiled() {
            $appPVue = 'App.pvue';
            $appPHP = 'App.php';

            if(!file_exists($appPHP) || filemtime($appPVue) > filemtime($appPHP)) {
                $phpCode = convert_pvue_file($appPVue, true);
                file_put_contents($appPHP, $phpCode);
            }

            if($this->bDevMode)
                $this->compileAllComponents();
        }

        private function compileAllComponents() {
            $files = array_merge(
                glob('components/*.pvue'),
                glob('views/*.pvue')
            );

            foreach ($files as $pvueFile) {
                $phpFile = str_replace('.pvue', '.php', $pvueFile);

                if(!file_exists($phpFile) || filemtime($pvueFile) > filemtime($phpFile)) {
                    $phpCode = convert_pvue_file($pvueFile, false);
                    file_put_contents($phpFile, $phpCode);
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

    $server = new PHPueServer();

  

    $server->serve();
?>