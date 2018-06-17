<?php
/**
 * Library for displaying tracking pixel when error handler of tracking. 
 */
namespace App\Components;

class DisplayPixelComponent {

    /**
     * function is used to displaying tracking pixel when error found
     *
     */
    public static function displayPixel(\Psr\Http\Message\ResponseInterface &$response) {
        $image = @file_get_contents("../track/pixel.png");
        $response->write($image);

        return $response->withHeader("Cache-Control", "no-cache, no-store, must-revalidate")
			->withHeader("Pragma", "no-cache")
            ->withHeader("Content-Type", "image/png")
			->withHeader("Expires", "0");
    }

}
