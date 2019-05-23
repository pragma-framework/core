<?php

namespace Pragma\Console\Commands;

use AtlantisPHP\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Serve extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'serve {port=8000}';

  /**
   * The full command description.
   *
   * @var string
   */
  protected $help = 'This command allows you to serve your Application';

  /**
   * The descriptions of the console commands.
   *
   * @var array
   */
  protected $descriptions = [
    'serve' => 'Serve the application on the PHP development serve',
    'port' => 'The port to serve the application on'
  ];

  /**
   * @param InputInterface  $input
   * @param OutputInterface $output
   *
   * @return void
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $appPath = str_replace('vendor/pragma-framework/core/Pragma/Console/Commands', '', __DIR__);

    /**
     * Get Port
     */
    $port = $input->getOption('port');

    /**
     * Check if Port is numeric
     */
    if (!is_numeric($port)) return $output->writeln('Invalid port number');

    /**
     * Alert the userr
     */
    $output->writeln("<info>Running Pragma application on</info> http://localhost:{$port}");

    /**
     * Serve application
     */
    passthru('php -S localhost:' . $port . ' -t ' . $appPath . '/public');
  }

}