<?php

/**
 * Component is used to file upload in s3 bucket
 */

namespace App\Components;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class AwsUploadSericeComponent {
    
    //store instance of s3 client
    private $_s3_client_instance;
    //store instance of current class
    private static $_instance;
    //store error
    private $_error;

    //define array as constant for setting uploaded file in bucket
    private $file_upload_config = [
        "acl"          => "public-read",
        "cacheControl" => "max-age=5256000",
        "storageClass" => "REDUCED_REDUNDANCY",
        "metadata"     => ["param1" => "Saleshandy"]
    ];

    /**
     * Instantiate an Amazon S3 client.
     * By declaring it private, it doesn't allow to create object of this class (to treat as Singleton class)
     */
    private function __construct() {
        try {
            $this->_s3_client_instance = new S3Client([
                "version" => AWS_ACCESS_VERSION,
                "region"  => AWS_ACCESS_REGION,
                "credentials" => [
       		   "key"    => AWS_ACCESS_KEY, 
       		   "secret" => AWS_ACCESS_SECRET_KEY
   		],
            ]);

        } catch (S3Exception $exception) {
            $this->_error = $exception->getMessage();
        }
    }
    /**
     * Get an instance of this class
     * This will return only one instance(to treat as Singleton class)
     *
     * @return _instance
     */
    public static function getInstance() {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    /**
     * Function is used to upload file in s3 bucket
     *
     * @param $file_detail, Array, Array of file detail 
     * -------$file_detail["source_path"], source path of file
     * -------$file_detail["key_name"], file_name stored in bukcet (bucket_path)
     * -------$file_detail["content_type"], content type of file
     *
     * @return $result, Array of object, object of uploaded file
     */
    public function upload($file_detail) {
        if (!empty($file_detail["original_file_name"])) {
    	   $this->file_upload_config["metadata"]["file_name"] = $file_detail["original_file_name"];
    	}
    	$result = [];
        try{
            $result = $this->_s3_client_instance->putObject([
                'Bucket'       => $file_detail["bucket"],
                'Key'          => $file_detail["key_name"],
                'SourceFile'   => $file_detail["source_path"],
                'ContentType'  => $file_detail["content_type"],
                'ACL'          => $this->file_upload_config["acl"],
                'CacheControl' => $this->file_upload_config["cacheControl"],
                'StorageClass' => $this->file_upload_config["storageClass"],
                'Metadata'     => $this->file_upload_config["metadata"]
            ]);
        } catch(S3Exception $exception) {
            $this->_error = $exception->getMessage();
        }
        return $result;
    }
    /**
     * Function is used to upload base64 image upload in bucket
     *
     * @param $image_data, String, base64 encoded string of image
     * @param $bucket_path, String, bucket path 
     */
    public function base64ImageUpload($image_data, $bucket_path, $bucket_name) {

        //get content_type of file
        list($type, $image_string) = explode(",", $image_data);
        $file_type = explode(";", $type);
        $content_type = end(explode(":", $file_type[0]));
       
        $uploaded_url = "";
        try{
            $result = $this->_s3_client_instance->putObject([
                'Bucket'       => $bucket_name,
                'Key'          => $bucket_path,
                'Body'         => base64_decode($image_string),
                'ContentType'  => $content_type,
                'ACL'          => self::FILE_UPLOAD_CONFIG["acl"],
                'CacheControl' => self::FILE_UPLOAD_CONFIG["cacheControl"],
                'StorageClass' => self::FILE_UPLOAD_CONFIG["storageClass"],
                'Metadata'     => self::FILE_UPLOAD_CONFIG["metadata"]
            ]);
        } catch(S3Exception $exception) {
            $this->_error = $exception->getMessage();
        }
        if(!empty($result["ObjectURL"])) {
            $uploaded_url = $result["ObjectURL"];
        }
        return $uploaded_url;
    }
    /**
     * Get error message
     *
     * @return _error
     */
    public function getErrorMessage() {
        return $this->_error;
    }
}
