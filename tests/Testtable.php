<?php
namespace Pragma\Tests;

use Pragma\ORM\Model;

class Testtable extends Model{
    public function __construct(){
        return parent::__construct('testtable');
    }
}
