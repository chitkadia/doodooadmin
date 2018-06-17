<?php
/**
 * Used to upload document in aws s3 bucket
 */
namespace App\Commands;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Interop\Container\ContainerInterface;
use \App\Components\AwsUploadSericeComponent;
use \App\Components\DateTimeComponent;
use \App\Components\LoggerComponent;
use \App\Models\DocumentMaster;


class DocumentsUploaderCommand extends AppCommand {

    //define constant for main directory of stored document
    const MAIN_DOCUMENT_DIR = __DIR__ ."/../../upload/";

    //define constant for document in process
    const STATUS_IN_PROCESS = 4;

    //define constant for document process pending
    const STATUS_IS_PENDING = 3;

    //define constant for document not converted to pdf
    const CONVERSION_FAILED_STATUS = 5;

    //define constant for document converted status
    const STATUS_ACTIVE = 1;

    //define constant for document process cron execute limt 
    const DOCUMENT_PROCESS_LIMIT_TIME = 50;

    //define constant for converted pdf prefix
    const CONVERTED_PDF_PREFIX = ".pdf";

    //define constant for converted jpg prefix
    const CONVERTED_JPG_PREFIX = "_%04d.jpg";

    //define constant for created zip file prefix
    const CREATED_ZIP_PREFIX = ".zip";

    //define constant for document conversion and upload log file 
    const LOG_FILE_DOCUMENT_PROCESS =  __DIR__ . "/../../logs/document_process.log";

    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container) {
        parent::__construct($container);
    }

    /**
     * uploaded file convert to pdf and upload in s3 bucket
     *
     * @param $request (object): Request object
     * @param $response (object): Response object
     * @param $args (array): Route parameters
     */
    public function convertUpload(ServerRequestInterface $request, ResponseInterface $response, $args) {
    
        $current_time = microtime(true);
        $end_time = $current_time + self::DOCUMENT_PROCESS_LIMIT_TIME;
        $num_records_processed = 0;

        $reminder = (int) $_SERVER["argv"][2];
        $modulo = (int) $_SERVER["argv"][3];

        //Log document process start
        LoggerComponent::log("Document process start", self::LOG_FILE_DOCUMENT_PROCESS);
        
        $document_model = new DocumentMaster();

        while ($end_time >= $current_time) {
            //Get the module wise query parameter
            if (!is_null($reminder) && !empty($modulo)) {
                $condition = [
                    "fields" => [
                        "id",
                        "file_type",
                        "file_path",
                        "file_name",
                        "bucket_name"
                    ],
                    "where" => [
                        ["where" => ["status", "=", self::STATUS_IS_PENDING ]],
                        ["where" => ["id%" . $modulo, "=", $reminder ]]
                    ],
                    "limit" => 1 
                ];
            }

            $row_data = $document_model->fetch($condition);

            if (!empty($row_data["id"])) {

                $file_path = $row_data["file_path"];
                $file_type = $row_data["file_type"];
                $bucket_name = $row_data["bucket_name"];
                $full_path = self::MAIN_DOCUMENT_DIR . $file_path;
                $content_type = $this->getContentType($file_type);

                $full_path_images = self::MAIN_DOCUMENT_DIR . str_replace($file_type, "", $file_path) . self::CONVERTED_JPG_PREFIX;
                
                $file_name = basename($file_path);
                $replace_file_name_with_zip = str_replace($file_type, self::CREATED_ZIP_PREFIX, $file_name);
                $replace_file_name_with_jpg = str_replace(self::CREATED_ZIP_PREFIX, "_*.jpg", $replace_file_name_with_zip);

                $dir_path = self::MAIN_DOCUMENT_DIR . dirname($file_path) . "/";
                $full_path_zip = $dir_path . $replace_file_name_with_zip;
                
                //Array for upload file in bucket
                $file_detail = [
                    "bucket" => $bucket_name,
                    "source_path" => $full_path,
                    "key_name" => $file_path,
                    "content_type" => $content_type,
                    "original_file_name" => $row_data["file_name"]
                ];

                //Array for status update of document
                $update_data = [
                    "id" => $row_data["id"],
                    "modified" => DateTimeComponent::getDateTime()
                ];
	        //update document status in process 
		$update_data["status"] = self::STATUS_IN_PROCESS;
                $document_model->save($update_data);

                LoggerComponent::log("Document processing #". $row_data["id"], self::LOG_FILE_DOCUMENT_PROCESS);
                LoggerComponent::log("Document processing #". $row_data["id"]. " and Type ". $content_type , self::LOG_FILE_DOCUMENT_PROCESS);

	        if($content_type == "application/pdf") {

		    //get Total number of pages
		    exec("pdfinfo " .$full_path. " | grep ^Pages:", $out, $ret);
		    $total_pages = (int) str_replace("Pages:", "", $out[$num_records_processed]);
 
                    $convert_to_jpg = exec("convert ". $full_path . " " . $full_path_images);

                    if (empty($convert_to_jpg)) {

                        $create_zip = exec("zip -j " . $full_path_zip ." $(find " . $dir_path ." -name " . $replace_file_name_with_jpg .")");

                        if ($create_zip) {

                            $file_upload_instance = AwsUploadSericeComponent::getInstance();

	                    //original document upload in bucket
        		    $result = $file_upload_instance->upload($file_detail);

            		    if (!empty($result['ObjectURL'])) {
                                //Document upload successfully log
                                LoggerComponent::log("pdf file uploaded #". $row_data["id"]. " in bucket is success", self::LOG_FILE_DOCUMENT_PROCESS);

                                $zip_file_detail = [
                                    "bucket" => $bucket_name,
                                    "source_path" => $full_path_zip,
                                    "key_name" => dirname($file_path) . "/" . $replace_file_name_with_zip,
                                    "content_type" => "application/zip"
                                ];

                                //zip file upload in bucket
                                $result_for_zip = $file_upload_instance->upload($zip_file_detail);

                                if (!empty($result_for_zip['ObjectURL'])) {

                                    //Zip file upload log
                                    LoggerComponent::log("zip file uploaded #". $row_data["id"]. " in bucket is success", self::LOG_FILE_DOCUMENT_PROCESS);

                                    //update status of document
                                    $update_data["status"] = self::STATUS_ACTIVE;
                                    $update_data["file_pages"] = $total_pages;
				                    $update_data["bucket_path"] = $file_path;

                                    if ($document_model->save($update_data)) {
                                        LoggerComponent::log("Document #". $row_data["id"]. " status updated", self::LOG_FILE_DOCUMENT_PROCESS);
                                    } else {
                                        LoggerComponent::log("Error when status updating for Document #" . $row_data["id"] . "Error". $document_model->getQueryError(), self::LOG_FILE_DOCUMENT_PROCESS);
                                    }

                                    //Delete zip file.
                                    if (@unlink($full_path_zip)) {
                                        LoggerComponent::log("Uploaded zip file #". $row_data["id"]. " is deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                    } else {
                                        LoggerComponent::log("Error occur when zip file deleting #". $row_data["id"]. " zip file not deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                    }

                                    //Remove all images
                                    $images_delete = exec("rm " . $dir_path . $replace_file_name_with_jpg );

                                    if (empty($images_delete)) {
                                        LoggerComponent::log("All images #". $row_data["id"]. " is deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                    } else {
                                        LoggerComponent::log("Error occur when images is deleting #". $row_data["id"]. " images not deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                    }
                                    //delete original document
                                    if (@unlink($full_path)) {
                                        LoggerComponent::log("Uploaded  #". $row_data["id"]. " file is deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                    } else {
                                        LoggerComponent::log("Error occur when uploaded file deleting #". $row_data["id"]. " uploaded file not deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                    }
                                } else {
                                    LoggerComponent::log("Error occur when uploading zip file in bucket #". $row_data["id"]. " Error" . $file_upload_instance->getErrorMessage(), self::LOG_FILE_DOCUMENT_PROCESS);
                                }
                             } else {
                                LoggerComponent::log("Error occur when uploading pdf file in bucket #". $row_data["id"]. " Error" . $file_upload_instance->getErrorMessage(), self::LOG_FILE_DOCUMENT_PROCESS);               
                             }
                        } else {
                            LoggerComponent::log("Error occur when created zip file #". $row_data["id"]. " Error: zip file not created", self::LOG_FILE_DOCUMENT_PROCESS);

                            //update document table
                            $update_data["status"] = self::CONVERSION_FAILED_STATUS;
                            $update_data["file_pages"] = $total_pages;

                            if ($document_model->save($update_data)) {
                                LoggerComponent::log("Document #". $row_data["id"]. " status updated", self::LOG_FILE_DOCUMENT_PROCESS);
                            } else {
                                LoggerComponent::log("Error occur when status updating for #" . $row_data["id"] . "Error". $document_model->getQueryError(), self::LOG_FILE_DOCUMENT_PROCESS);
                            }
                        }
                    } else {
                        LoggerComponent::log("Error occur when document covert to images #". $row_data["id"]. " Error: Document not convert to images", self::LOG_FILE_DOCUMENT_PROCESS);

                        //update document table
                        $update_data["status"] = self::CONVERSION_FAILED_STATUS;
                        $update_data["file_pages"] = $total_pages;

                        if ($document_model->save($update_data)) {
                            LoggerComponent::log("Document #". $row_data["id"]. " status updated", self::LOG_FILE_DOCUMENT_PROCESS);
                        } else {
                            LoggerComponent::log("Error occur when status updating for #" . $row_data["id"] . "Error". $document_model->getQueryError(), self::LOG_FILE_DOCUMENT_PROCESS);
                        }
                    }
                } else {

                    $is_converted = exec("doc2pdf " . $full_path);

                    if (empty($is_converted)) {

                        $full_path_pdf = str_replace($file_type, "", $full_path) . self::CONVERTED_PDF_PREFIX;

		        //get Total number of pages
	                exec("pdfinfo " .$full_path_pdf. " | grep ^Pages:", $out, $ret);
        	        $total_pages = (int) str_replace("Pages:", "", $out[$num_records_processed]);

			$convert_to_jpg = exec("convert ". $full_path_pdf . " " . $full_path_images);

                        if (empty($convert_to_jpg)) {

                            $create_zip = exec("zip -j " . $full_path_zip ." $(find " . $dir_path ." -name " . $replace_file_name_with_jpg .")");

                            if ($create_zip) {

                                $file_upload_instance = AwsUploadSericeComponent::getInstance();

                                //upload original file
                                $result = $file_upload_instance->upload($file_detail);

                                if (!empty($result['ObjectURL'])) {

                                    LoggerComponent::log("document uploaded in bucket #". $row_data["id"], self::LOG_FILE_DOCUMENT_PROCESS);

                                    //get file_name from full_path
                                    $file_name = substr($file_path, strrpos($file_path, "/") + 1);

                                    //replace the extension of file to .pdf
                                    $replace_file_name = str_replace($row_data["file_type"], self::CONVERTED_PDF_PREFIX, $file_name);	

                                    //get path of without file name
                                    $get_path = substr($file_path,0, strrpos($file_path,"/"));

                                    //create full path with replace file name
                                    $full_path = self::MAIN_DOCUMENT_DIR . $get_path . "/" . $replace_file_name;

                                    //destination path of bucket
                                    $key_name = $get_path . "/" . $replace_file_name;

                                    //set array of file_detail
                                    $file_detail["source_path"] = $full_path;
                                    $file_detail["key_name"] = $key_name;
                                    $file_detail["content_type"] = "application/pdf";

                                    //move converted pdf to s3 bucket
                                    $result_for_pdf = $file_upload_instance->upload($file_detail); 

                                    if (!empty($result_for_pdf['ObjectURL'])) {

                                        LoggerComponent::log("pdf file uploaded in bucket #". $row_data["id"], self::LOG_FILE_DOCUMENT_PROCESS);

                                        $zip_file_detail = [
                                            "bucket" => $bucket_name,
                                            "source_path" => $full_path_zip,
                                            "key_name" => dirname($file_path) . "/" . $replace_file_name_with_zip,
                                            "content_type" => "application/zip"
                                        ];

                                        //zip file upload in bucket
                                        $result_for_zip = $file_upload_instance->upload($zip_file_detail);

                                        if (!empty($result_for_zip['ObjectURL'])) {

                                            LoggerComponent::log("zip file uploaded #". $row_data["id"]. " in bucket is success", self::LOG_FILE_DOCUMENT_PROCESS);
                                            //update document table
                                            $update_data["status"] = self::STATUS_ACTIVE;
                                            $update_data["file_pages"] = $total_pages;
                                            $update_data["bucket_path"] = $key_name;

                                            if ($document_model->save($update_data)) {
                                                LoggerComponent::log("Document #". $row_data["id"]. " status updated", self::LOG_FILE_DOCUMENT_PROCESS);
                                            } else {
                                                LoggerComponent::log("Error occur when status updating for Document #" . $row_data["id"] . "Error". $document_model->getQueryError(), self::LOG_FILE_DOCUMENT_PROCESS);
                                            }

                                            //Delete zip file.
                                            if (@unlink($full_path_zip)) {
                                                LoggerComponent::log("Uploaded zip file #". $row_data["id"]. " is deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                            } else {
                                                LoggerComponent::log("Error occur when zip file deleting #". $row_data["id"]. " zip file not deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                            }

                                            //Remove all images
                                            $images_delete = exec("rm " . $dir_path . $replace_file_name_with_jpg );

                                            if (empty($images_delete)) {
                                                LoggerComponent::log("All images #". $row_data["id"]. " is deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                            } else {
                                                LoggerComponent::log("Error occur when images is deleting #". $row_data["id"]. " images not deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                            }

                                            //delete pdf file.
                                            if (@unlink(self::MAIN_DOCUMENT_DIR . $key_name)) {
                                                LoggerComponent::log("Uploaded pdf #". $row_data["id"]. " file deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                            } else {
                                                LoggerComponent::log("Uploaded pdf #". $row_data["id"]. " file not deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                            } 

                                            //delete uploaded document.
                                            if (@unlink(self::MAIN_DOCUMENT_DIR . $file_path)) {
                                                LoggerComponent::log("Uploaded file #". $row_data["id"]. " is deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                            } else {
                                                LoggerComponent::log("Uploaded file #". $row_data["id"]. " is not deleted", self::LOG_FILE_DOCUMENT_PROCESS);
                                            }
                                        } else {
                                            LoggerComponent::log("Error occur when uploading zip file in s3 bucket #". $row_data["id"]. " Error" . $file_upload_instance->getErrorMessage(), self::LOG_FILE_DOCUMENT_PROCESS);
                                        }
                                    } else {
                                        LoggerComponent::log("Error occur when uploading pdf file in s3 bucket #". $row_data["id"]. " Error" . $file_upload_instance->getErrorMessage(), self::LOG_FILE_DOCUMENT_PROCESS);
                                    }
                                } else {
                                    LoggerComponent::log("Error occur when uploading file in s3 bucket #". $row_data["id"]. " Error" . $file_upload_instance->getErrorMessage(), self::LOG_FILE_DOCUMENT_PROCESS);
                                }
                            } else {
                                LoggerComponent::log("Error occur when created zip file #". $row_data["id"]. " Error: zip file not created", self::LOG_FILE_DOCUMENT_PROCESS);

                                //update document table
                                $update_data["status"] = self::CONVERSION_FAILED_STATUS;
                                $update_data["file_pages"] = $total_pages;

                                if ($document_model->save($update_data)) {
                                    LoggerComponent::log("Document #". $row_data["id"]. " status updated", self::LOG_FILE_DOCUMENT_PROCESS);
                                } else {
                                    LoggerComponent::log("Error occur when status updating for #" . $row_data["id"] . "Error". $document_model->getQueryError(), self::LOG_FILE_DOCUMENT_PROCESS);
                                }
                            }
                        } else {
                            LoggerComponent::log("Error occur when document covert to images #". $row_data["id"]. " Error: Document not convert to images", self::LOG_FILE_DOCUMENT_PROCESS);

                            //update document table
                            $update_data["status"] = self::CONVERSION_FAILED_STATUS;
                            $update_data["file_pages"] = $total_pages;

                            if ($document_model->save($update_data)) {
                                LoggerComponent::log("Document #". $row_data["id"]. " status updated", self::LOG_FILE_DOCUMENT_PROCESS);
                            } else {
                                LoggerComponent::log("Error occur when status updating for #" . $row_data["id"] . "Error". $document_model->getQueryError(), self::LOG_FILE_DOCUMENT_PROCESS);
                            }
                        }
                    } else {
                        LoggerComponent::log("Error occur when documen convert to pdf #". $row_data["id"]. " Error: document not converted to pdf", self::LOG_FILE_DOCUMENT_PROCESS);

                        //update document table
                        $update_data["status"] = self::CONVERSION_FAILED_STATUS;

                        if ($document_model->save($update_data)) {
                            LoggerComponent::log("Document #". $row_data["id"]. " status updated", self::LOG_FILE_DOCUMENT_PROCESS);
                        } else {
                            LoggerComponent::log("Error occur when status updating #" . $row_data["id"] . "Error". $document_model->getQueryError(), self::LOG_FILE_DOCUMENT_PROCESS);
                        }
                    }
                }
                LoggerComponent::log("Process Finished for Document #". $row_data["id"], self::LOG_FILE_DOCUMENT_PROCESS);           
            } else {
                LoggerComponent::log("Document not found to be processing", self::LOG_FILE_DOCUMENT_PROCESS);
                break;
            }

            $current_time = microtime(true);
            $num_records_processed++;
        }
        LoggerComponent::log("Total ".$num_records_processed." records to be proceed", self::LOG_FILE_DOCUMENT_PROCESS);
        LoggerComponent::log("===================================================", self::LOG_FILE_DOCUMENT_PROCESS);
    }
    /**
     * Function used to get content type of file
     * @param $file_type, String, type of file Ex: .jpg
     *
     * @return $content_type, String, content type of file Ex: image/jpeg
     */
    private function getContentType($file_type) {
        $content_type = "";

        $file_type = strtolower($file_type);
        switch ($file_type) {
            case ".jpeg" :
            case ".jpg"  :  
                $content_type = "image/jpeg";
                break;
            case ".doc"  :
            case ".docx" :  
                $content_type = "application/msword";
                break;
            case ".ppt"  :
            case ".pptx" :  
                $content_type = "application/vnd.ms-powerpoint";
                break;
            case ".xlsx" :
            case ".xlsm" :
            case ".csv"  :  
                $content_type = "application/vnd.ms-excel";
                break;
            case ".pdf"  :  
                $content_type = "application/pdf";
                break;
            case ".png"  :  
                $content_type = "image/png";
                break;
            default :
                $content_type = "";
                break;
        }   

        return $content_type;
    }

}
