<?php
/**
 * Search Query Parser
 *
 * Parses search queries and transforms into the SOLR syntax. 
 * 
 * Supported operators:
 * AND operator: Apples AND Oranges
 * OR operator: Apples OR Oranges
 * NOT operator: NOT Oranges
 * Quotes operator: " Apples "
 * Parentheses operator: Apples AND ( Oranges OR Pears )
 * Star operator: Appl*
 * Complex query: ((Pear* AND NOT Apple*) OR "Plumps OR Limons") AND Oranges
 */

class SearchQueryParser {

    private $stack;
    private $rpn_result;

    public function parse_to_solr_query($query = '') {
        $rpn = $this->parse_to_rpn($query);

        $this->stack = new SplStack();
        foreach($rpn as $tok) {
            if($tok['type'] === 'WORD') {
                $this->stack->push('text:'.$tok['value']);
            } elseif($tok['type'] === 'OPERATOR') {
                if($tok['value'] === 'NOT') {
                    $val = $this->stack->pop();
                    if($val[0] === '-') {
                        $val = substr($val, 1);
                    } else {
                        $val = '-'.$val;
                    }
                    $this->stack->push($val);
                } elseif($tok['value'] === 'AND' || $tok['value'] === 'OR') {
                    $val1 = $this->stack->pop();
                    $val2 = $this->stack->pop();
                    $val = '('.$val1.' '.$tok['value'].' '.$val2.')';
                    $this->stack->push($val);
                }
            }
        }
        
        if($this->stack->isEmpty()) {
            return 'text:*';
        }
        return $this->stack->pop();
    }

    public function parse_to_rpn($query = '') {
        $this->rpn_tree = array();
        $query = trim($query);

        if (strlen($query) === 0) {
            return $this->rpn_tree;
        }

        $query = str_replace('(', ' ( ', $query);
        $query = str_replace(')', ' ) ', $query);
        $query = str_replace('"', ' " ', $query);
        $query = preg_replace('/(\s+)/', ' ', $query);

        $this->stack = new SplStack();
        $quote = NULL;
        $token = strtok($query, " \n\t");
        while ($token !== false) {
            if($token === '(') {
                if(is_null($quote)) {
                    $this->stack->push(array('type'=>'OPERATOR', 'value'=>$token));
                } else {
                    $quote .= '(';
                }
            } elseif($token === ')') {
                if(is_null($quote)) {
                    $this->get_operators_in_brackets_from_stack();
                } else {
                    $quote .= trim($quote) . ') ';
                }
            } elseif($token === '"') {
                if(is_null($quote)) {
                    $quote = '';
                } else {
                    $quote = trim($quote);
                    if($quote !== '') {
                        array_push($this->rpn_tree, array('type'=>'WORD', 'value'=>'"'.$quote.'"'));
                    }
                    $quote = NULL;
                }
            } elseif($token === 'OR' || $token === 'AND' || $token === 'NOT') {
                if(is_null($quote)) {
                    $this->push_operator_on_stack($token);
                } else {
                    $quote .= $token . ' ';
                }
            } else {
                if(is_null($quote)) {
                    if (strpos($token,'*') !== false) {
                        array_push($this->rpn_tree, array('type'=>'WORD', 'value'=>$token));
                    } else {
                        array_push($this->rpn_tree, array('type'=>'WORD', 'value'=>'"'.$token.'"'));
                    }
                } else {
                    $quote .= $token . ' ';
                }
            }

            $token = strtok(" \n\t");
        }

        if(!is_null($quote)) {
            $quote = trim($quote);
            if($quote !== '') {
                array_push($this->rpn_tree, array('type'=>'WORD', 'value'=>'"'.$quote.'"'));
            }
            $quote = NULL;
        }
        $this->get_all_operators_from_stack();

        return $this->rpn_tree;
    }

    private function get_operators_in_brackets_from_stack() {
        if(!$this->stack->isEmpty()) {
            $val = $this->stack->pop();
            while($val['value'] !== '(') {
                array_push($this->rpn_tree, $val);
                if($this->stack->isEmpty()) {
                    break;
                } else {
                    $val = $this->stack->pop();
                }
            }
        }
    }

    private function push_operator_on_stack($token) {
        if($token !== 'NOT' && !$this->stack->isEmpty()) {
            $val = $this->stack->pop();
            while( $this->getOperatorPriority($token) >= $this->getOperatorPriority($val['value'])) {
                array_push($this->rpn_tree, $val);
                if($this->stack->isEmpty()) {
                    break;
                } else {
                    $val = $this->stack->pop();
                }
            }
            $this->stack->push($val);
        }
        $this->stack->push(array('type'=>'OPERATOR', 'value'=>$token));
    }

    private function get_all_operators_from_stack() {
        while(!$this->stack->isEmpty()) {
            $val = $this->stack->pop();
            array_push($this->rpn_tree, $val);
        }
    }

    private function getOperatorPriority($operator) {
        if($operator === 'NOT') {
            return 1;
        } else if($operator === 'AND') {
            return 2;
        } else if($operator === 'OR') {
            return 3;
        }
        return 4;
    }

}

?>
