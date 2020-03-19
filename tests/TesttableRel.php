<?php
namespace Pragma\Tests;

use Pragma\ORM\Model;

class TesttableRel extends Model{
    public function __construct(){
        parent::__construct('testtablerel');

        $this->belongs_to('Pragma\\Tests\\TesttableRel', 'parent', ['col_on' => 'parent_id']);
        $this->has_many('Pragma\\Tests\\TesttableRel', 'children', ['col_to' => 'parent_id']);
        $this->has_many_through('Pragma\\Tests\\TesttableRel', 'sub1', ['through_class' => 'Pragma\\Tests\\TesttableRelLink', 'col_through_on' => 'rel1_id', 'col_through_to' => 'rel2_id']);
        $this->has_many_through('Pragma\\Tests\\TesttableRel', 'sub2', ['through_class' => 'Pragma\\Tests\\TesttableRelLink', 'col_through_on' => 'rel2_id', 'col_through_to' => 'rel1_id']);
    }
}
