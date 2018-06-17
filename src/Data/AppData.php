<?php
/**
 * Base file of Data Domain
 */
namespace App\Data;

abstract class AppData {

    // Application container instance
    protected $_container;

    /**
     * Constructor
     */
    public function __construct(\Interop\Container\ContainerInterface $container) {
        $this->_container = $container;
    }

}