<?php

// Used by Doctrine

include dirname(__FILE__) . '/../system/bootstrap.php';

return \Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet($em);