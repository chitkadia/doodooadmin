<?php
/**
 * Base file of application commands
 * Every command should extend this file
 */
namespace App\Commands;

abstract class AppCommand {

    // Application container instance
    protected $_container;

    /**
     * Constructor
     */
    public function __construct(\Interop\Container\ContainerInterface $container) {
        $this->_container = $container;
    }

}