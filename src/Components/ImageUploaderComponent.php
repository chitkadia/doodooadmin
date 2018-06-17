<?php
/**
 * Library for uploading image
 */
namespace App\Components;

class ImageUploaderComponent {

    /**
     * Upload image (base64 to image file)
     *
     * @param $base6e_image (string): Base64 image string
     * @param $path (string): Path where image should be saved
     *
     * @return (array) Uploaded image details
     */
    public static function uploadImage($base6e_image, $path) {
        $return_data = [
            "error" => null,
            "name" => ""
        ];

        $image_payload = explode(";", $base6e_image);
        $image_ext = ($image_payload[0] == "data:image/png") ? ".png" : ".jpg";

        $image_name = strtotime("now") . rand(111, 999) . $image_ext;
        $final_path = $path . "/" . $image_name;

        try {
            $file = fopen($final_path, "wb");

            $data = explode(",", $base6e_image);
            fwrite($file, base64_decode($data[1]));
            fclose($file);

            $return_data["name"] = $image_name;

        } catch(\Exception $e) {
            $return_data["error"] = $e->getMessage();
        }

        return $return_data;
    }

}