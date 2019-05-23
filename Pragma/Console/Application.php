<?php

namespace Pragma\Console;

use AtlantisPHP\Console\Application as Console;

class Application
{
  /**
   * Return console application
   *
   * @return Void
   */
  public static function boot()
  {
    $application = new Console('Pragma Console');

    $application->load(__DIR__ . '/Commands');

    $application->run();
  }
}