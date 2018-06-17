<?php

/**
 * Component is used to file upload in s3 bucket
 */

namespace App\Components;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class FileUploadComponent {
    
    //store instance of s3 client
    private $_s3_client_instance;
    //store instance of current class
    private static $_instance;
    //store error
    private $_error;

    //define array as constant for setting uploaded file in bucket
    const FILE_UPLOAD_CONFIG = [
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
                "region"  => AWS_ACCESS_REGION
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
     * @return uploaded_url, String, return aws bucket url of file
     */
    public function upload($file_detail) {
        $result = [];
        try{
            $result = $this->_s3_client_instance->putObject([
                'Bucket'       => AWS_BUCKET_NAME,
                'Key'          => $file_detail["key_name"],
                'SourceFile'   => $file_detail["source_path"],
                'ContentType'  => $file_detail["content_type"],
                'ACL'          => self::FILE_UPLOAD_CONFIG["acl"],
                'CacheControl' => self::FILE_UPLOAD_CONFIG["cacheControl"],
                'StorageClass' => self::FILE_UPLOAD_CONFIG["storageClass"],
                'Metadata'     => self::FILE_UPLOAD_CONFIG["metadata"]
            ]);
        } catch(S3Exception $exception) {
            $this->_error = $exception->getMessage();
        }
        return $result;
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