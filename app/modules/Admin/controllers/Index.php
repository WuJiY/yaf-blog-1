<?php
class IndexController extends Yaf_Controller_Abstract {

	public function indexAction() {//默认Action
		echo 'Admin';exit;
		//$this->getView()->assign("content", "Hello Admin");
	}

}
?>
