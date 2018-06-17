<?php
/**
 * Register application wide routes (Document Viewer)
 */
$sh_app->map(["GET"], "/", \App\Viewer\DocumentViewer::class . ":index");

$sh_app->group("/view", function() {
    $this->group("/{code}", function() {
        $this->map(["GET"], "", \App\Viewer\DocumentViewer::class . ":view");
        $this->map(["GET"], "/d/{file_code}", \App\Viewer\DocumentViewer::class . ":view");
    });
});

$sh_app->group("/v1file", function() {
    $this->group("/{link}", function() {
        $this->map(["GET"], "", \App\Viewer\DocumentRedirect::class . ":viewDocument");   
    });
});

$sh_app->group("/v1fileperformance", function() {
    $this->group("/{link}", function() {
        $this->map(["GET"], "", \App\Viewer\DocumentRedirect::class . ":viewFilePerformance");   
    });
});

$sh_app->group("/v1filelinkperformance", function() {
    $this->group("/{link}", function() {
        $this->map(["GET"], "", \App\Viewer\DocumentRedirect::class . ":viewFileLinkPerformance");   
    });
});




$sh_app->group("/preview", function() {
    $this->group("/{code}", function() {
        $this->map(["GET"], "", \App\Viewer\DocumentViewer::class . ":view");
        $this->map(["GET"], "/d/{file_code}", \App\Viewer\DocumentViewer::class . ":view");
    });
    
})->add(function(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, $next) {
    // Set preview mode on
    $request = $request->withAttribute("preview", 1);

    // Execute the request
    $response = $next($request, $response);

    // Send response
    return $response;
});