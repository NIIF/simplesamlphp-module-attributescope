<?php

if (!file_exists('vendor/simplesamlphp/simplesamlphp/config/config.php')) {
    copy('vendor/simplesamlphp/simplesamlphp/config-templates/config.php', 'vendor/simplesamlphp/simplesamlphp/config/config.php');
}