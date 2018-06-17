<?php
/**
 * Library for validating data intigrity of models
 */
namespace App\Components;

class ModelValidationsComponent {

    const FIELD_REQUIRED = "required";
    const FIELD_NOTEMTPY = "notempty";
    const FIELD_REQ_NOTEMPTY = "req_notempty";
    const FIELD_NUMBER = "number";
    const FIELD_EMAIL = "email";
    const FIELD_URL = "url";
    const FIELD_ALPHA = "alphabets";
    const FIELD_ALNUM = "alphanumeric";
    const FIELD_NUM_MIN = "min_number";
    const FIELD_NUM_MAX = "max_number";
    const FIELD_NUM_RANGE = "number_range";
    const FIELD_TEXT_MIN = "min_length";
    const FIELD_TEXT_MAX = "max_length";
    const FIELD_TEXT_RANGE = "text_range";
    const FIELD_MATCH = "matchto";
    const FIELD_REGEX = "regex";
    const FIELD_DATE = "date";
    const FIELD_DATETIME = "datetime";
    const FIELD_TIMESTAMP = "timestamp";
    const FIELD_JSON_ARRAY = "json_array";

    /**
     * Validate data with validation settings
     *
     * @param $validations (array): Array of validation settings
     * @param $data (array): Array of data to be validated
     *
     * @return (array) Array of failed validation messages
     */
    public static function validate($validations, $data) {
        $errors = [];

        if (!empty($validations)) {
            foreach ($validations as $field => $validation) {
                foreach ($validation as $rule) {
                    $invalid = false;

                    switch ($rule["type"]) {
                        case self::FIELD_REQUIRED:
                            if (!isset($data[$field])) {
                                $errors[] = (empty($rule["message"])) ? "Parameter Missing: " . $field . " is required." : $rule["message"];
                                $invalid = true;
                            }
                            break;

                        case self::FIELD_NOTEMTPY:
                            if (trim($data[$field]) == "") {
                                $errors[] = (empty($rule["message"])) ? "Parameter Required: " . $field . " should not be empty." : $rule["message"];
                                $invalid = true;
                            }
                            break;

                        case self::FIELD_REQ_NOTEMPTY:
                            if (isset($data[$field])) {
                                if (trim($data[$field]) == "") {
                                    $errors[] = (empty($rule["message"])) ? "Parameter Required: " . $field . " is required." : $rule["message"];
                                    $invalid = true;
                                }
                            } else {
                                $errors[] = (empty($rule["message"])) ? "Parameter Missing: " . $field . " is required." : $rule["message"];
                                $invalid = true;
                            }
                            break;

                        case self::FIELD_NUMBER:
                            if (isset($data[$field])) {
                                if (!is_numeric($data[$field])) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " is not a valid number." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_EMAIL:
                            if (isset($data[$field])) {
                                if (!filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " is not a valid email." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_URL:
                            if (isset($data[$field])) {
                                if (!filter_var($data[$field], FILTER_VALIDATE_URL)) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " is not a valid url." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_ALPHA:
                            if (isset($data[$field])) {
                                if (!ctype_alpha($data[$field])) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " should contain only alphabets." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_ALNUM:
                            if (isset($data[$field])) {
                                if (!ctype_alnum($data[$field])) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " should contain only alphanumeric." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_NUM_MIN:
                            if (isset($data[$field])) {
                                if ($data[$field] < $rule["arg"][0]) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " should be greater than " . $rule["arg"][0] . "." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_NUM_MAX:
                            if (isset($data[$field])) {
                                if ($data[$field] > $rule["arg"][0]) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " should be less than " . $rule["arg"][0] . "." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_NUM_RANGE:
                            if (isset($data[$field])) {
                                if ($data[$field] < $rule["arg"][0] || $data[$field] > $rule["arg"][1]) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " should be between " . $rule["arg"][0] . " and " . $rule["arg"][1] . "." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_TEXT_MIN:
                            if (isset($data[$field])) {
                                if (strlen($data[$field]) < $rule["arg"][0]) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " should be at least " . $rule["arg"][0] . " characters long." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_TEXT_MAX:
                            if (isset($data[$field])) {
                                if (strlen($data[$field]) > $rule["arg"][0]) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " should contain maximum " . $rule["arg"][0] . " characters." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_TEXT_RANGE:
                            if (isset($data[$field])) {
                                if (strlen($data[$field]) < $rule["arg"][0] || strlen($data[$field]) > $rule["arg"][1]) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " should be between " . $rule["arg"][0] . " and " . $rule["arg"][1] . " characters." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_MATCH:
                            if (isset($data[$field])) {
                                if ($data[$field] != $data[$rule["arg"][0]]) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " does not match with " . $rule["arg"][0] . "." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_REGEX:
                            if (isset($data[$field])) {
                                if (preg_match($rule["arg"][0], $data[$field])) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " validation failed." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_DATE:
                            if (isset($data[$field])) {
                                list($year, $month, $day) = explode("/", $mydate);
                                if (!checkdate($month, $day, $year)) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " is not a valid date." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_DATETIME:
                            if (isset($data[$field])) {
                                $d = \DateTime::createFromFormat("Y/m/d H:i:s", $data[$field]);
                                if (!($d && $d->format("Y/m/d H:i:s") == $data[$field])) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " is not a valid datetime." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_TIMESTAMP:
                            if (isset($data[$field])) {
                                if (!is_numeric($data[$field])) {
                                    $errors[] = (empty($rule["message"])) ? "Invalid Parameter: " . $field . " is not a valid timestamp." : $rule["message"];
                                    $invalid = true;
                                }
                            }
                            break;

                        case self::FIELD_JSON_ARRAY:
                            if (empty($data[$field])) {
                                $errors[] = (empty($rule["message"])) ? $field . " must be an non-empty array." : $rule["message"];
                                $invalid = true;
                            }
                            break;

                        default:
                            $errors[] = "Unknown validation type.";
                            break;
                    }

                    // In case of multiple error checks, skip on failure of any first
                    if ($invalid) {
                        break;
                    }
                }
            }
        }

        return $errors;
    }

}