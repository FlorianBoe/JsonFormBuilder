<?php
require_once dirname(__FILE__) . '/JsonFormBuilderCore.class.php';

class JsonFormBuilderFromJson extends JsonFormBuilderCore {
    
    private $modx;
    private $o_form;
    private $a_json;
    
    function __construct(&$modx) {
        if (is_a($modx, 'modX') === false) {
            JsonFormBuilder::throwError('Failed to create JsonFormBuilder form. Reference to $modx not valid.');
        }
        $this->modx = &$modx;
    }
    
    function setJsonData($json){
        $a_json = json_decode($json,true);
        if(!empty($a_json)){
            $this->a_json = $a_json;
        }else{
            JsonFormBuilder::throwError('Failed to decode JSON "'.$json.'".');
        }
    }
    
    function setJsonDataFromChunk($chunkName){
        $chunk = $this->modx->getObject('modChunk',array('name' => $chunkName));
        if(empty($chunk)){
            JsonFormBuilder::throwError('Failed to find chunk "'.$chunkName.'".');
        }
        $this->setJsonData($chunk->getContent());
    }
    
    function setJsonDataFromFile($filePath){
        $s_jsonContent = file_get_contents(MODX_BASE_PATH.$filePath);
        $this->setJsonData($s_jsonContent);
    }
    
    function getJsonVal($arr,$key,$required=false){
        if(isset($arr[$key]) && $arr[$key]!=''){
            return $arr[$key];
        }else{
            if($required){
                JsonFormBuilder::throwError('Failed to find required JSON property "'.$key.'".');
            }
        }
        return false;
    }
    function output(){
        $a_formRules=array();
        $a_formElements=array();
        
        $this->o_form = new JsonFormBuilder($this->modx,$this->getJsonVal($this->a_json,'id',true));
        
        $a_ignore = array('id','elements');
        foreach($this->a_json as $key=>$val){
            if(in_array($key,$a_ignore)){
                continue;
            }
            $methodName = 'set'.ucfirst($key);
            $this->o_form->$methodName($val);
        }
        
        $a_elements = $this->getJsonVal($this->a_json,'elements',true);
        foreach($a_elements as $element){
            if(is_array($element)===false && empty($element)===false){
                $a_formElements[]=$element;
            }else{
                $elementMethod = 'JsonFormBuilder_element'.ucfirst($this->getJsonVal($element,'element',true));
                //required to set id and label in constructors.. all elements have id and label
                $o_el =  new $elementMethod($element['id'],$element['label']);
                $a_ignore = array('element','rules','id','label');
                foreach($element as $key=>$val){
                    if(in_array($key,$a_ignore)){
                        continue;
                    }
                    $methodName = 'set'.ucfirst($key);
                    $o_el->$methodName($val);
                }
                //add rules if needed
                $a_rules = $this->getJsonVal($element,'rules');
                if($a_rules){
                    foreach($a_rules as $rule){
                        if(is_array($rule)){
                            //rule in assoc array
                            $a_formRules[] = new FormRule($rule['type'],$o_el,$rule['value'],$rule['validationMessage']);
                        }else{
                            //simple rule
                            $a_formRules[] = new FormRule($rule,$o_el);
                        }
                    }
                }
                $a_formElements[]=$o_el;
            }
        }
        if(empty($a_formRules)===false){
            $this->o_form->addRules($a_formRules);
        }
        $this->o_form->addElements($a_formElements);
        return $this->o_form->output();
    }

}
