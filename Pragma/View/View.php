<?php
namespace Pragma\View;

class View {
	const DEFAULT_YIELD = 'default';

	private $tpl;
	private $static_version_file;
	private $template_path;
	private $content_for;

	private $flash_messages;

	private static $view = null;

	public function __construct(){
		$this->tpl = array();
		$this->content_for = array();
		$this->static_version_file = 1;
	}


	public static function getInstance(){
		if(self::$view == null){
			self::setInstance(new View());
		}
		return self::$view;
	}

	public static function setInstance(View $v){
		if($v != null){
			self::$view = $v ;
		}else{
		   self::$view = new View();
		}
	}

	public function initFlashStructure(&$tab){
		$this->flash_messages = &$tab;
	}

	public function flash($message, $flash_state = ''){
		if(!isset($this->flash_messages)) $this->flash_messages = array();
		$this->flash_messages[] = array('message' => $message, 'state' => $flash_state);
	}

	public function flushFlash(){
		$temp = $this->flash_messages;
		$this->clearFlash();
		return $temp;
	}

	public function clearFlash(){
		$this->flash_messages = array();
	}

	/**
	*	update template path
	*/
	public function set_tpl_path($path){
		$this->template_path = $path;
	}

	public function set_static_version($version){
		$this->static_version_file = $version;
	}

	/**
	*	give the path of resources, with the current static version
	*/
	public function getTemplateWebPath($file = '',$version = true) {
		$webPath = '/assets/';

		if(!empty($file) && $version) {
			$webPath .= $file.'?'.$this->get_static_version();
		}else{
			$webPath .= $file;
		}

		return $webPath;
	}

	public function getTemplatePath($file = '') {
		return $this->template_path.$file;
	}

	public function get_static_version() {
		return $this->static_version_file;
	}

	/**
	*	main layout
	*/
	public function setLayout($path){
		$this->tpl['layout']['path'] = $path;
	}

	/**
	*	allow developper to assign a value to the view
	*/
	public function assign($key, $value){
		$this->tpl['vars'][$key] = $value;
	}

	public function assign_multiple($values){
		if(!isset($this->tpl['vars'])){
			$this->tpl['vars'] = [];
		}
		array_merge($this->tpl['vars'], $values);
	}

	public function has($key){
		return isset($this->tpl['vars'][$key]);
	}

	//get the value of a variable assigned to the view
	public function get($key){
		return (isset($this->tpl['vars'][$key])) ? $this->tpl['vars'][$key] : null;
	}

	// bind tpl.php file to a yield of the layout
	public function render($path, $yield = self::DEFAULT_YIELD){
		$this->tpl['layout']['yields'][$yield] = $path;
	}

	//include a tpl file locally. $object is passed to the partial in order to factorize code and embed data from the parent view
	public function partial($path, $object = array()){
		if(!empty($path)) include $path;
	}

	//include a tpl file exactly where the yields method is called
	public function yields($yield = self::DEFAULT_YIELD){
		if(!empty($this->content_for[$yield])) echo $this->content_for[$yield];
		if(!empty($this->tpl['layout']['yields'][$yield]) && file_exists($this->getTemplatePath($this->tpl['layout']['yields'][$yield])))
			include $this->getTemplatePath($this->tpl['layout']['yields'][$yield]);
	}

	// Test existence of a yield section
	public function isYieldable($yield = self::DEFAULT_YIELD) {
		return (!empty($this->tpl['layout']['yields'][$yield]) && file_exists($this->getTemplatePath($this->tpl['layout']['yields'][$yield])));
	}

	public function clear($yield = null){
		if(is_null($yield)) unset($this->tpl['layout']['yields']);
		else unset($this->tpl['layout']['yields'][$yield]);
	}

	//render the whole layout and display it
	public function compute(){
		if(file_exists($this->getTemplatePath($this->tpl['layout']['path']))){
			include $this->getTemplatePath($this->tpl['layout']['path']);
		}else
			echo "Layout does not exists ".$this->tpl['layout']['path'];
	}

	//compile the whole layout and return a string of the result
	public function compile(){
		ob_start();
		$this->compute();
		$compilation = ob_get_contents();
		ob_end_clean();
		return $compilation;
	}

	public function content_for($yield, $content){
		if(! isset($this->content_for[$yield])) $this->content_for[$yield] = '';
		$this->content_for[$yield] .= $content . "\n";

	}

	public function image_tag($path, $with_tpl_path = true, $attributes = array()){
		if($with_tpl_path) $src = $this->getTemplateWebPath('images/'.$path);
		else $src = $path;
		$img = '<img src="'.$src.'" ';
		foreach($attributes as $attr => $val){
			$img .= $attr.'="'.$val.'" ';
		}
		$img .= '/>';
		return $img;
	}

	public function javascript_tag($path){
		return '<script type="text/javascript" src="'.$path.'"></script> ';
	}

	public function stylesheet_tag($path, $media = 'screen'){
		return '<link title="" media="'.$media.'" href="'.$path.'" rel="stylesheet" type="text/css"/>';
	}
}
?>
