<?php
namespace Pragma\Helpers;

use Composer\Script\Event;
use Composer\Installer\PackageEvent;

class Migrate{
 	// post-package-install
	public static function postPackageInstall(PackageEvent $event){
		if (self::checkConfig($event)) {
			$installedPackage = $event->getOperation()->getPackage()->getName();
			self::phinxMigrateFromPackageName($event, $installedPackage, true);
		} else {
			die();
		}
	}
	// post-package-update
	public static function postPackageUpdate(PackageEvent $event){
		if (self::checkConfig($event)) {
			$installedPackage = $event->getOperation()->getPackage()->getName();
			self::phinxMigrateFromPackageName($event, $installedPackage);
		} else {
			die();
		}
	}
	// pre-package-uninstall
	public static function prePackageUninstall(PackageEvent $event){
		if (self::checkConfig($event)) {
			$installedPackage = $event->getOperation()->getPackage()->getName();
			self::phinxRollbackFromPackageName($event, $installedPackage);
		} else {
			die();
		}
	}

	protected static function loadPhinxConfig($packageName){
		if (strpos($packageName, 'pragma-framework/') === 0) {
			$name = str_replace('pragma-framework/', '', $packageName);
			$phinxPath = realpath(__DIR__.'/../../../'.$name).'/phinx.php';
			if(file_exists($phinxPath) && file_exists(realpath(__DIR__.'/../../../../').'/robmorgan/phinx/app/phinx.php')){
				$phinxApp = require realpath(__DIR__.'/../../../../').'/robmorgan/phinx/app/phinx.php';
				$phinxTextWrapper = new \Phinx\Wrapper\TextWrapper($phinxApp);

				$phinxTextWrapper->setOption('parser', 'PHP');
				$phinxTextWrapper->setOption('environment', 'default');
				$phinxTextWrapper->setOption('configuration', $phinxPath);
				return $phinxTextWrapper;
			}
		}
		return false;
	}
	protected static function phinxMigrateFromPackageName(&$event, $packageName, $install = false) {
		$phinxTextWrapper = self::loadPhinxConfig($packageName);
		if ($phinxTextWrapper !== false) {
			$log = $phinxTextWrapper->getMigrate();
			$event->getIO()->write($log);
			if ($install) {
				$log = $phinxTextWrapper->getSeed();
				$event->getIO()->write($log);
			}
		}
	}
	protected static function phinxRollbackFromPackageName(&$event, $packageName) {
		$phinxTextWrapper = self::loadPhinxConfig($packageName);
		if ($phinxTextWrapper !== false) {
			$log = $phinxTextWrapper->getRollback(null, 0);
			$event->getIO()->write($log);
		}
	}
}
