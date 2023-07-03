<?php
namespace Pragma\Tests;

use Pragma\ORM\Model;

class TesttableRelLink extends Model{
    public function __construct(){
        parent::__construct('testtablerellink');
        $this->belongs_to('Pragma\\Tests\\TesttableRel', 'rel1', ['col_on' => 'rel1_id']);
        $this->belongs_to('Pragma\\Tests\\TesttableRel', 'rel2', ['col_on' => 'rel2_id']);
    }
}
