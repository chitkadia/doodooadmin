<?php
/**
 * Base file of application tracker
 * Every tracker should extend this file
 */
namespace App\Track;

abstract class AppTracker {

    // Application container instance
    protected $_container;

    /**
     * Constructor
     */
    public function __construct(\Interop\Container\ContainerInterface $container) {
        $this->_container = $container;
    }

}