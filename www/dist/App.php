<?php
// Auto session management
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$phpue_header = <<<HTML

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <script src="assets/js/jquery-3.7.1.min.js"></script>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

HTML;
    // Auto-injected routing system
    $current_route = $_GET['page'] ?? 'index';
    $available_routes = array_keys(get_phpue_routing()->routes);
    
    if (!in_array($current_route, $available_routes)) {
        http_response_code(404);
        $current_route = 'index'; // Fallback to index
    }
    
    $GLOBALS['phpue_current_route'] = $current_route;
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

// @require processed and removed
// Added view to routing: views/index.pvue
// Added view to routing: views/about.pvue
    

?>
<!DOCTYPE html>
<html>
<head>
    <?= $current_header ?? '' ?>
</head>
<body>

    <?php

    $routes = phpue_navigation();
    $routes = array_reverse($routes, true);

?>

    <nav class="navbar navbar-expand-lg bg-light">
        <div class="container">
            <a class="navbar-brand" href=""><img alt="Free Frontend Logo" class="img-fluid" height="" src="https://freefrontend.dev/wp-content/uploads/free-frontend-logo.png" width="300"></a> <button aria-controls="navbarSupportedContent9" aria-expanded="false" aria-label="Toggle navigation" class="navbar-toggler" data-bs-target="#navbarSupportedContent9" data-bs-toggle="collapse" type="button"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent9">
                <form class="d-flex align-items-center position-relative ms-lg-3">
                    <div class="input-group align-items-center mt-3 mt-lg-0">
                        <input aria-describedby="button-addon2" aria-label="Search" class="form-control" placeholder="Search" type="text"> <button class="btn bg-white border" id="button-addon2" type="button"><svg class="bi bi-search" fill="currentColor" height="20" viewbox="0 0 16 16" width="20" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"></path></svg></button>
                    </div>
                </form>
                <ul class="navbar-nav ms-auto my-2 my-lg-0">
<?php foreach($routes as $route): ?>                    <li  class="nav-item me-4">
                        <a class="nav-link" href="{{ $route['url'] }}">{{ $route['title'] }}</a>
                    </li><?php endforeach; ?>
                </ul>
            </div>
        </div>
    </nav>



    <h1 class="font-weight-bold text-primary">hi</h1>
        <?php
        $routing = get_phpue_routing();
        echo $routing->getCurrentPageContent();
    ?>


</body>
</html>
