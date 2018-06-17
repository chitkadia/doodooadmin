<?php
/**
 * Base file of application controllers
 * Every controller should extend this file
 */
namespace App\Controllers;

abstract class AppController {

    // Application container instance
    protected $_container;

    /**
     * Constructor
     */
    public function __construct(\Interop\Container\ContainerInterface $container) {
        $this->_container = $container;
    }

}