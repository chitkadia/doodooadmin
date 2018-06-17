<?php
/**
 * Base file of application viewer
 * Every viewer should extend this file
 */
namespace App\Viewer;

abstract class AppViewer {

    // Application container instance
    protected $_container;

    /**
     * Constructor
     */
    public function __construct(\Interop\Container\ContainerInterface $container) {
        $this->_container = $container;
    }

}