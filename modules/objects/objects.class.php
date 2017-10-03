<?php
/**
* Objects 
*
* Objects
*
* @package MajorDoMo
* @author Serge Dzheigalo <jey@tut.by> http://smartliving.ru/
* @version 0.4 (wizard, 12:05:51 [May 22, 2009])
*/
//
//
class objects extends module {

/**
* objects
*
* Module class constructor
*
* @access private
*/
function objects() {
  $this->name="objects";
  $this->title="<#LANG_MODULE_OBJECT_INSTANCES#>";
  $this->module_category="<#LANG_SECTION_OBJECTS#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=1) {
 $data=array();
 if (IsSet($this->id)) {
  $data["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $data["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $data["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->tab)) {
  $data["tab"]=$this->tab;
 }
 return parent::saveParams($data);
}
/**
* getParams
*
* Getting module parameters from query string
*
* @access public
*/
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['TAB']=$this->tab;
  if ($this->single_rec) {
   $out['SINGLE_REC']=1;
  }
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}
/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }
 if ($this->data_source=='objects' || $this->data_source=='') {
  if ($this->view_mode=='' || $this->view_mode=='search_objects') {
   $this->search_objects($out);
  }

  if ($this->view_mode=='clone' && $this->id) {
   $this->clone_object($this->id);
  }


  if ($this->view_mode=='edit_objects') {
   $this->edit_objects($out, $this->id);
  }
  if ($this->view_mode=='delete_objects') {
   $this->delete_objects($this->id);
   $this->redirect("?");
  }
 }
}

/**
* Title
*
* Description
*
* @access public
*/
 function clone_object($id) {

  $rec=SQLSelectOne("SELECT * FROM objects WHERE ID='".$id."'");
  $rec['TITLE']=$rec['TITLE'].' (copy)';
  unset($rec['ID']);
  $rec['ID']=SQLInsert('objects', $rec);

  $seen_pvalues=array();
  $properties=SQLSelect("SELECT * FROM properties WHERE OBJECT_ID='".$id."'");
  $total=count($properties);
  for($i=0;$i<$total;$i++) {
   $p_id=$properties[$i]['ID'];
   unset($properties[$i]['ID']);
   $properties[$i]['OBJECT_ID']=$rec['ID'];
   $properties[$i]['ID']=SQLInsert('properties', $properties[$i]);
   $p_value=SQLSelectOne("SELECT * FROM pvalues WHERE PROPERTY_ID='".$p_id."'");
   if ($p_value['ID']) {
    $seen_pvalues[$p_value['ID']]=1;
    unset($p_value['ID']);
    $p_value['PROPERTY_ID']=$properties[$i]['ID'];
    $p_value['OBJECT_ID']=$rec['ID'];
    SQLInsert('pvalues', $p_value);
   }
  }

  $pvalues=SQLSelect("SELECT * FROM pvalues WHERE OBJECT_ID='".$id."'");
  $total=count($properties);
  for($i=0;$i<$total;$i++) {
   $p_id=$pvalues[$i]['ID'];
   if ($seen_pvalues[$p_id]) {
    continue;
   }
   unset($pvalues[$i]['ID']);
   $pvalues[$i]['OBJECT_ID']=$rec['ID'];
   $pvalues[$i]['ID']=SQLInsert('pvalues', $pvalues[$i]);
  }

  $methods=SQLSelect("SELECT * FROM methods WHERE OBJECT_ID='".$id."'");
  $total=count($methods);
  for($i=0;$i<$total;$i++) {
   unset($methods[$i]['ID']);
   $methods[$i]['OBJECT_ID']=$rec['ID'];
   $methods[$i]['ID']=SQLInsert('methods', $methods[$i]);
  }

  $this->redirect("?view_mode=edit_objects&id=".$rec['ID']);

 }

/**
* FrontEnd
*
* Module frontend
*
* @access public
*/
function usual(&$out) {

 if ($this->ajax) {

  header("HTTP/1.0: 200 OK\n");
  header('Content-Type: text/html; charset=utf-8');

  global $op;
  global $id;
  $res=array();
  if ($op=='get_object') {
   $res=$this->processObject($id);
  }
  echo json_encode($res);

  global $db;$db->disconnect();
  exit;
 }

 if ($this->class) {
  $objects=getObjectsByClass($this->class);
  if (!$this->code) {
   $template='#title# <i>#description#</i><br/>';
  } else {
   $template=$this->code;
  }
  $result='';
  if ($objects[0]['ID']) {
   $total=count($objects);
   for($i=0;$i<$total;$i++) {
    $objects[$i]=SQLSelectOne("SELECT * FROM objects WHERE ID='".$objects[$i]['ID']."'");
    $line=$template;
    $line=preg_replace('/\#title\#/is', $objects[$i]['TITLE'], $line);
    $line=preg_replace('/\#description\#/is', $objects[$i]['DESCRIPTION'], $line);
    if (preg_match_all('/\#([\w\d_-]+?)\#/is', $line, $m)) {
     $totalm=count($m[0]);
     for($im=0;$im<$totalm;$im++) {
      $property=trim($objects[$i]['TITLE'].'.'.$m[1][$im]);
      $line=str_replace($m[0][$im], getGlobal($property), $line);
     }
    }
    $result.=$line;
   }
  }
  $out['RESULT']=$result;
 }

}
/**
* objects search
*
* @access public
*/
 function search_objects(&$out) {
  require(DIR_MODULES.$this->name.'/objects_search.inc.php');
 }
/**
* objects edit/add
*
* @access public
*/
 function edit_objects(&$out, $id) {
  require(DIR_MODULES.$this->name.'/objects_edit.inc.php');
 }
/**
* objects delete record
*
* @access public
*/
 function delete_objects($id) {
  $rec=SQLSelectOne("SELECT * FROM objects WHERE ID='$id'");
  // some action for related tables
  SQLExec("DELETE FROM history WHERE OBJECT_ID='".$rec['ID']."'");
  SQLExec("DELETE FROM methods WHERE OBJECT_ID='".$rec['ID']."'");
  SQLExec("DELETE FROM pvalues WHERE OBJECT_ID='".$rec['ID']."'");
  SQLExec("DELETE FROM properties WHERE OBJECT_ID='".$rec['ID']."'");
  SQLExec("DELETE FROM objects WHERE ID='".$rec['ID']."'");
 }


/**
* Title
*
* Description
*
* @access public
*/
 function loadObject($id) {
  $rec=SQLSelectOne("SELECT * FROM objects WHERE ID='".DBSafe($id)."'");
  if (IsSet($rec['ID'])) {
   $this->id=$rec['ID'];
   $this->object_title=$rec['TITLE'];
   $this->class_id=$rec['CLASS_ID'];
   if ($this->class_id) {
    $class_rec=SQLSelectOne("SELECT ID,TITLE FROM classes WHERE ID=".$this->class_id);
    $this->class_title=$class_rec['TITLE'];
   }
   $this->description=$rec['DESCRIPTION'];
   $this->location_id=$rec['LOCATION_ID'];
   //$this->keep_history=$rec['KEEP_HISTORY'];
  } else {
   return false;
  }

 }


/**
* Title
*
* Description
*
* @access public
*/
 function getParentProperties($id, $def='', $include_self=0) {
  $class=SQLSelectOne("SELECT * FROM classes WHERE ID='".(int)$id."'");

  $properties=SQLSelect("SELECT properties.*, classes.TITLE as CLASS_TITLE FROM properties LEFT JOIN classes ON properties.CLASS_ID=classes.ID WHERE CLASS_ID='".$id."' AND OBJECT_ID=0");

  if ($include_self) {
   $res=$properties;
  } else {
   $res=array();
  }

  if (!is_array($def)) {
   $def=array();
   foreach($properties as $p) {
    $def[]=$p['TITLE'];
   }
  }

  foreach($properties as $p) {
   if (!in_array($p['TITLE'], $def)) {
    $res[]=$p;
    $def[]=$p['TITLE'];
   }
  }

  if ($class['PARENT_ID']) {
   $p_res=$this->getParentProperties($class['PARENT_ID'], $def);
   if ($p_res[0]['ID']) {
    $res=array_merge($res, $p_res);
   }
  }

  return $res;

 }

 function getParentMethods($id, $def='', $include_self=0) {
  $class=SQLSelectOne("SELECT * FROM classes WHERE ID='".(int)$id."'");

  $methods=SQLSelect("SELECT methods.*, classes.TITLE as CLASS_TITLE FROM methods LEFT JOIN classes ON methods.CLASS_ID=classes.ID WHERE CLASS_ID='".$id."' AND OBJECT_ID=0");

  if ($include_self) {
   $res=$methods;
  } else {
   $res=array();
  }
  


  if (!is_array($def)) {
   $def=array();
   foreach($methods as $p) {
    $def[]=$p['TITLE'];
   }
  }

  foreach($methods as $p) {
   if (!in_array($p['TITLE'], $def)) {
    $res[]=$p;
    $def[]=$p['TITLE'];
   }
  }

  if ($class['PARENT_ID']) {
   $p_res=$this->getParentMethods($class['PARENT_ID'], $def);
   if ($p_res[0]['ID']) {
    $res=array_merge($res, $p_res);
   }
  }

  return $res;

 }


 /**
 * Title
 *
 * Description
 *
 * @access public
 */
  function getMethodByName($name, $class_id, $id) {

   if ($id) {
    $meth=SQLSelectOne("SELECT ID FROM methods WHERE OBJECT_ID='".(int)$id."' AND TITLE LIKE '".DBSafe($name)."'");
    if ($meth['ID']) {
     return $meth['ID'];
    }
   }

   //include_once(DIR_MODULES.'classes/classes.class.php');
   //$cl=new classes();
   //$meths=$cl->getParentMethods($class_id, '', 1);
   $meths=$this->getParentMethods($class_id, '', 1);

   $total=count($meths);
   for($i=0;$i<$total;$i++) {
    if (strtolower($meths[$i]['TITLE'])==strtolower($name)) {
     return $meths[$i]['ID'];
    }
   }
   return false;   

  }

/**
* Title
*
* Description
*
* @access public
*/
 function raiseEvent($name, $params=0, $parent=0) {

  $p='';
  $url=BASE_URL.'/objects/?object='.urlencode($this->object_title).'&op=m&m='.urlencode($name);
  if (is_array($params)) {
   foreach($params as $k=>$v) {
    $p.=utf2win(' '.$k.':"'.$v.'"');
    $url.='&'.urlencode($k).'='.urlencode($v);
   }
  }

  $data=getURL($url, 0);
  
 }

 function callClassMethod($name, $params=0) {
  $this->callMethod($name, $params, 1);
 }

 function callMethodSafe($name,$params = 0) {
  $current_call=$this->object_title.'.'.$name;
  $call_stack=array();
  if (isset($_GET['m_c_s']) && is_array($_GET['m_c_s'])) {
   $call_stack = $_GET['m_c_s'];
  }

  if (in_array($current_call,$call_stack)) {
   $call_stack[]=$current_call;
   DebMes("Warning: cross-linked call of ".$current_call."\nlog:\n".implode(" -> \n",$call_stack));
   return 0;
  }

  $call_stack[]=$current_call;
  $data=array(
   'object'=>$this->object_title,
      'op'=>'m',
      'm'=>$name,
      'm_c_s'=>$call_stack
  );
  $url=BASE_URL.'/objects/?'.http_build_query($data);
  if (is_array($params)) {
   foreach($params as $k=>$v) {
    $url.='&'.$k.'='.urlencode($v);
   }
  }
  $result = getURLBackground($url,0);
  return $result;
 }

/**
* Title
*
* Description
*
* @access public
*/
 function callMethod($name, $params=0, $parentClassId=0) {

  startMeasure('callMethod');

  $original_method_name=$this->object_title.'.'.$name;

  startMeasure('callMethod ('.$original_method_name.')');

 if (!$parentClassId) {
  $id=$this->getMethodByName($name, $this->class_id, $this->id);
  $parentClassId = $this->class_id;
 } else {
  $id=$this->getMethodByName($name, $parentClassId, 0);
 }

  if ($id) {

   $method=SQLSelectOne("SELECT * FROM methods WHERE ID='".$id."'");

   $method['EXECUTED']=date('Y-m-d H:i:s');
   if (!$method['OBJECT_ID']) {
    if (!$params) {
     $params=array();
    }
    $params['ORIGINAL_OBJECT_TITLE']=$this->object_title;
   }
   if ($params) {
    $saved_params=$params;
    unset($saved_params['m_c_s']);
    $method['EXECUTED_PARAMS']=json_encode($saved_params);
    if (strlen($method['EXECUTED_PARAMS'])>250) {
     $method['EXECUTED_PARAMS']=substr($method['EXECUTED_PARAMS'],0,250);
    }
   }
   SQLUpdate('methods', $method);

   if ($method['OBJECT_ID'] && $method['CALL_PARENT']==1) {
    // call class method
    $parent_success = $this->callMethod($name, $params, $this->class_id);
   } elseif ($method['CALL_PARENT']==1) {
    $parentClass=SQLSelectOne("SELECT ID, PARENT_ID FROM classes WHERE ID=".(int)$parentClassId);
    if ($parentClass['PARENT_ID']) {
     $parent_success = $this->callMethod($name, $params, $parentClass['PARENT_ID']);
    }
   }

   if ($method['SCRIPT_ID']) {
   /*
    $script=SQLSelectOne("SELECT * FROM scripts WHERE ID='".$method['SCRIPT_ID']."'");
    $code=$script['CODE'];
   */
    runScriptSafe($method['SCRIPT_ID']);
   } else {
    $code=$method['CODE'];
   }
   

   if ($code!='') {

    /*
    if (defined('SETTINGS_DEBUG_HISTORY') && SETTINGS_DEBUG_HISTORY==1) {
     $class_object=SQLSelectOne("SELECT NOLOG FROM classes WHERE ID='".$this->class_id."'");
     if (!$class_object['NOLOG']) {

      $prevLog=SQLSelectOne("SELECT ID, UNIX_TIMESTAMP(ADDED) as UNX FROM history WHERE OBJECT_ID='".$this->id."' AND METHOD_ID='".$method['ID']."' ORDER BY ID DESC LIMIT 1");
      if ($prevLog['ID']) {
       $prevRun=$prevLog['UNX'];
       $prevRunPassed=time()-$prevLog['UNX'];
      }

      $h=array();
      $h['ADDED']=date('Y-m-d H:i:s');
      $h['OBJECT_ID']=$this->id;
      $h['METHOD_ID']=$method['ID'];
      $h['DETAILS']=serialize($params);
      if ($parent) {
       $h['DETAILS']='(parent method) '.$h['DETAILS'];
      }
      $h['DETAILS'].="\n".'code: '."\n".$code;
      SQLInsert('history', $h);
     }
    }
    */


     try {
       $success = eval($code);
       if ($success === false) {
         //getLogger($this)->error(sprintf('Error in "%s.%s" method.', $this->object_title, $name));
         registerError('method', sprintf('Exception in "%s.%s" method.', $this->object_title, $name));
       }
     } catch (Exception $e) {
       //getLogger($this)->error(sprintf('Exception in "%s.%s" method', $this->object_title, $name), $e);
       registerError('method', sprintf('Exception in "%s.%s" method '.$e->getMessage(), $this->object_title, $name));
     }

   }
   endMeasure('callMethod', 1);
   endMeasure('callMethod ('.$original_method_name.')', 1);
   if ($method['OBJECT_ID'] && $method['CALL_PARENT']==2) {
    $parent_success = $this->callMethod($name, $params, $this->class_id);
   } elseif ($method['CALL_PARENT']==2) {
    $parentClass=SQLSelectOne("SELECT ID, PARENT_ID FROM classes WHERE ID=".(int)$parentClassId);
    if ($parentClass['PARENT_ID']) {
     $parent_success = $this->callMethod($name, $params, $parentClass['PARENT_ID']);
    }
   } else {
    $parent_success=true;
   }

   if (isset($success)) {
    return $success;
   } else {
    return $parent_success;
   }

  } else {
   endMeasure('callMethod ('.$original_method_name.')', 1);
   endMeasure('callMethod', 1);
   return false;
  }
 }

/**
* Title
*
* Description
*
* @access public
*/
 function getPropertyByName($name, $class_id, $object_id) {
  $rec=SQLSelectOne("SELECT ID FROM properties WHERE OBJECT_ID='".(int)$object_id."' AND TITLE LIKE '".DBSafe($name)."'");
  if ($rec['ID']) {
   return $rec['ID'];
  }

  //include_once(DIR_MODULES.'classes/classes.class.php');
  //$cl=new classes();
  //$props=$cl->getParentProperties($class_id, '', 1);
  $props=$this->getParentProperties($class_id, '', 1);

  $total=count($props);
  for($i=0;$i<$total;$i++) {
   if (strtolower($props[$i]['TITLE'])==strtolower($name)) {
    return $props[$i]['ID'];
   }
  }

 return false;

 }



/**
* Title
*
* Description
*
* @access public
*/
 function getProperty($property) {

  $property = trim($property);

  if ($this->object_title) {
   $value=SQLSelectOne("SELECT VALUE FROM pvalues WHERE PROPERTY_NAME = '".DBSafe($this->object_title.'.'.$property)."'");
   if (isset($value['VALUE'])) {
    startMeasure('getPropertyCached2');
    endMeasure('getPropertyCached2', 1);
    endMeasure('getProperty ('.$property.')', 1);
    endMeasure('getProperty', 1);
    return $value['VALUE'];
   }
  }
  startMeasure('getProperty');
  startMeasure('getProperty ('.$property.')');

  if ($this->object_title) {
   if ($property=='object_title') {
    return $this->object_title;
   } elseif ($property=='object_description') {
    return $this->description;
   } elseif ($property=='object_id') {
    return $this->id;
   } elseif ($property=='class_title') {
    return $this->class_title;
   }
  }

  $id=$this->getPropertyByName($property, $this->class_id, $this->id);
  if ($id) {
   $value=SQLSelectOne("SELECT * FROM pvalues WHERE PROPERTY_ID='".(int)$id."' AND OBJECT_ID='".(int)$this->id."'");
   if (!$value['PROPERTY_NAME'] && $this->object_title) {
    $value['PROPERTY_NAME']=$this->object_title.'.'.$property;
    SQLUpdate('pvalues', $value);
   }
  } else {
   $value['VALUE']=false;
  }
  endMeasure('getProperty ('.$property.')', 1);
  endMeasure('getProperty', 1);
  if (!isset($value['VALUE'])) {
   $value['VALUE']=false;
  }
  return $value['VALUE'];
 }

/**
* Title
*
* Description
*
* @access public
*/
 function setProperty($property, $value, $no_linked=0, $source='') {

  startMeasure('setProperty');
  startMeasure('setProperty ('.$property.')');

  $property = trim($property);

  if (is_null($value)) {
   $value='';
  }

  if (!$source && is_string($no_linked)) {
   $source=$no_linked;
   $no_linked=0;
  }

  if (defined('TRACK_DATA_CHANGES') && TRACK_DATA_CHANGES==1) {
   $save=1;

   if (!is_numeric(trim($value))) {
    $save=0;
   }

   if (defined('TRACK_DATA_CHANGES_IGNORE') && TRACK_DATA_CHANGES_IGNORE!='' && $save) {
    $tmp=explode(',', TRACK_DATA_CHANGES_IGNORE);
    $total=count($tmp);
    for($i=0;$i<$total;$i++) {
     $regex=trim($tmp[$i]);
     if (preg_match('/'.$regex.'/is', $this->object_title.'.'.$property)) {
      $save=0;
      break;
     }
    }
   }
   if ($save) {
    if ($this->location_id) {
     $location=current(SQLSelectOne("SELECT TITLE FROM locations WHERE ID=".(int)$this->location_id));
    } else {
     $location='';
    }


   if (defined('LOG_DIRECTORY') && LOG_DIRECTORY!='') {
    $path=LOG_DIRECTORY;
   } else {
    $path = ROOT . 'debmes';
   }

    $today_file=$path . '/'.date('Y-m-d').'.data';
    $f=fopen($today_file, "a+");
    if ($f) {
                fputs($f, date("Y-m-d H:i:s"));
                fputs($f, "\t".$this->object_title.'.'.$property."\t".trim($value)."\t".trim($source)."\t".trim($location)."\n");
                fclose($f);
                @chmod($today_file, 0666);
    }   
   }
  }

  startMeasure('getPropertyByName');
  $id=$this->getPropertyByName($property, $this->class_id, $this->id);
  endMeasure('getPropertyByName');
  $old_value='';

  $cached_name='MJD:'.$this->object_title.'.'.$property;

  startMeasure('setproperty_update');
  if ($id) {
   $prop=SQLSelectOne("SELECT * FROM properties WHERE ID='".$id."'");
   startMeasure('setproperty_update_getvalue');
   $v=SQLSelectOne("SELECT * FROM pvalues WHERE PROPERTY_ID='".(int)$id."' AND OBJECT_ID='".(int)$this->id."'");
   endMeasure('setproperty_update_getvalue');
   $old_value=$v['VALUE'];

   if ($prop['DATA_TYPE']==5 && $value!=$old_value) { // image
    $path_parts=pathinfo($value);
    $extension=strtolower($path_parts['extension']);
    if ($extension!='jpg' && $extension!='jpeg' && $extension!='png'  && $extension!='gif') {
     $extension='jpg';
    }
    $image_file_name=date('Ymd_His').'.'.$extension;
    if (preg_match('/^http.+/',$value)) {
     $image_data=getURL($value);
     @mkdir(ROOT.'cms/images/'.$prop['ID'],0777);
     SaveFile(ROOT.'cms/images/'.$prop['ID'].'/'.$image_file_name,$image_data);
     $value=$prop['ID'].'/'.$image_file_name;
    } elseif (file_exists($value)) {
     @mkdir(ROOT.'cms/images/'.$prop['ID'],0777);
     copyFile($value,ROOT.'cms/images/'.$prop['ID'].'/'.$image_file_name);
     $value=$prop['ID'].'/'.$image_file_name;
    } else {
     $value = '';
    }
    if ($value!='' && file_exists(ROOT.'cms/images/'.$value)) {
     $lst=GetImageSize(ROOT.'cms/images/'.$value);
     //$image_width=$lst[0];
     //$image_height=$lst[1];
     $image_format=$lst[2];
     if (!$image_format) {
      @unlink(ROOT.'cms/images/'.$value);
      $value = '';
     }
    } else {
     $value = '';
    }
    if ($value!='' && $old_value!='' && !$prop['KEEP_HISTORY'] && file_exists(ROOT.'cms/images/'.$old_value)) {
     @unlink(ROOT.'cms/images/'.$old_value);
    }
    if ($value=='') $value=$old_value;
   }

   $v['VALUE']=$value.'';
   $v['SOURCE']=$source.'';
   if ($v['ID']) {
    $v['UPDATED']=date('Y-m-d H:i:s');
    //if ($old_value!=$value) {
     SQLUpdate('pvalues', $v);
    //} else {
    // SQLExec("UPDATE pvalues SET UPDATED='".$v['UPDATED']."' WHERE ID='".$v['ID']."'");
    //}
   } else {
    $v['PROPERTY_ID']=$id;
    $v['OBJECT_ID']=$this->id;
    $v['VALUE']=$value.'';
    $v['SOURCE']=$source.'';
    $v['UPDATED']=date('Y-m-d H:i:s');
    $v['ID']=SQLInsert('pvalues', $v);
   }
   //DebMes(" $id to $value ");
  } else {
    $prop=array();
    $prop['OBJECT_ID']=$this->id;
    $prop['TITLE']=$property;
    //$prop['VALUE']='';
    $prop['ID']=SQLInsert('properties', $prop);

    $v['PROPERTY_ID']=$prop['ID'];
    $v['OBJECT_ID']=$this->id;
    $v['VALUE']=$value.'';
    $v['SOURCE']=$source.'';
    $v['UPDATED']=date('Y-m-d H:i:s');
    $v['ID']=SQLInsert('pvalues', $v);
  }
  endMeasure('setproperty_update');

  saveToCache($cached_name, $value);

  if (function_exists('postToWebSocketQueue')) {
   startMeasure('setproperty_postwebsocketqueue');
   postToWebSocketQueue($this->object_title.'.'.$property, $value);
   endMeasure('setproperty_postwebsocketqueue');
  }

  /*
  if ($this->keep_history>0) {
   $prop['KEEP_HISTORY']=$this->keep_history;
  }
  */

  if (IsSet($prop['KEEP_HISTORY']) && ($prop['KEEP_HISTORY']>0)) {
   $q_rec=array();
   $q_rec['VALUE_ID']=$v['ID'];
   $q_rec['ADDED']=date('Y-m-d H:i:s');
   $q_rec['VALUE']=$value.'';
   $q_rec['SOURCE']=$source.'';
   $q_rec['OLD_VALUE']=$old_value;
   $q_rec['KEEP_HISTORY']=$prop['KEEP_HISTORY'];
   SQLInsert('phistory_queue', $q_rec);
  }


  if (isset($prop['ONCHANGE']) && $prop['ONCHANGE']) {
   global $property_linked_history;
   if (!$property_linked_history[$property][$prop['ONCHANGE']]) {
    $property_linked_history[$property][$prop['ONCHANGE']]=1;
    $params=array();
    $params['PROPERTY']=$property;
    $params['NEW_VALUE']=(string)$value;
    $params['OLD_VALUE']=(string)$old_value;
    $params['SOURCE']=(string)$source;
    //$this->callMethod($prop['ONCHANGE'], $params);
    $this->callMethodSafe($prop['ONCHANGE'], $params);
    unset($property_linked_history[$property][$prop['ONCHANGE']]);
   }
  }

  if (IsSet($v['LINKED_MODULES']) && $v['LINKED_MODULES']) { // TO-DO !
   if (!is_array($no_linked) && $no_linked) {
    return;
   } elseif (!is_array($no_linked)) {
    $no_linked=array();
   }


   $tmp=explode(',', $v['LINKED_MODULES']);
   $total=count($tmp);


   startMeasure('linkedModulesProcessing');
   for($i=0;$i<$total;$i++) {
    $linked_module=trim($tmp[$i]);

    if (isset($no_linked[$linked_module])) {
     continue;
    }
    startMeasure('linkedModule'.$linked_module);
    if (file_exists(DIR_MODULES.$linked_module.'/'.$linked_module.'.class.php')) {
     include_once(DIR_MODULES.$linked_module.'/'.$linked_module.'.class.php');
     $module_object=new $linked_module;
     if (method_exists($module_object, 'propertySetHandle')) {
      $module_object->propertySetHandle($this->object_title, $property, $value);
     }
    }
    endMeasure('linkedModule'.$linked_module);
   }
   endMeasure('linkedModulesProcessing');
  }

  /*
   $h=array();
   $h['ADDED']=date('Y-m-d H:i:s');
   $h['OBJECT_ID']=$this->id;
   $h['VALUE_ID']=$v['ID'];
   $h['OLD_VALUE']=$old_value;
   $h['NEW_VALUE']=$value;
   SQLInsert('history', $h);
  */


  endMeasure('setProperty ('.$property.')', 1);
  endMeasure('setProperty', 1);

 }

 function getWatchedProperties($objects) {
  $properties=array();
  $ids=explode(',',$objects);
  include_once(DIR_MODULES.'classes/classes.class.php');
  $cl=new classes();

  foreach($ids as $object_id) {
   $this->loadObject($object_id);
   $props=$cl->getParentProperties($this->class_id, '', 1);
   $my_props=SQLSelect("SELECT * FROM properties WHERE OBJECT_ID='".(int)$object_id."'");
   if ($my_props[0]['ID']) {
    foreach($my_props as $p) {
     $props[]=$p;
    }
   }
   if (is_array($props)) {
    foreach($props as $k=>$v) {
     if (substr($v['TITLE'],0,1)=='_') continue;
     $properties[]=array('PROPERTY'=>mb_strtolower($this->object_title.'.'.$v['TITLE'], 'UTF-8'), 'OBJECT_ID'=>$object_id);
    }
   }
  }
  return $properties;
 }

 function processObject($object_id) {
  $object_rec=SQLSelectOne("SELECT * FROM objects WHERE ID=".(int)$object_id);
  $result=array('HTML'=>'','OBJECT_ID'=>$object_rec['ID']);
  $template=getObjectClassTemplate($object_rec['TITLE']);
  $result['HTML']=processTitle($template,$this);
  return $result;
 }

/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($parent_name="") {
  parent::install($parent_name);
 }
/**
* Uninstall
*
* Module uninstall routine
*
* @access public
*/
 function uninstall() {
  SQLExec('DROP TABLE IF EXISTS objects');
  parent::uninstall();
 }
/**
* dbInstall
*
* Database installation routine
*
* @access private
*/
 function dbInstall($data) {

  //SQLExec("DROP TABLE IF EXISTS `cached_values`;");
  $sqlQuery = "CREATE TABLE IF NOT EXISTS `cached_values`
               (`KEYWORD`   char(100) NOT NULL,
                `DATAVALUE` char(255) NOT NULL,
                `EXPIRE`    datetime  NOT NULL,
                PRIMARY KEY (`KEYWORD`)
               ) ENGINE = MEMORY DEFAULT CHARSET=utf8;";
  SQLExec($sqlQuery);

  $sqlQuery = "CREATE TABLE IF NOT EXISTS `cached_ws`
               (`PROPERTY`   char(100) NOT NULL,
                `DATAVALUE` varchar(20000) NOT NULL,
                `POST_ACTION`   char(100) NOT NULL,
                `ADDED`    datetime  NOT NULL,
                PRIMARY KEY (`PROPERTY`)
               ) ENGINE = MEMORY DEFAULT CHARSET=utf8;";
  SQLExec($sqlQuery);

  //echo ("Executing $sqlQuery\n");

/*
objects - Objects
*/
  $data = <<<EOD
 objects: ID int(10) unsigned NOT NULL auto_increment
 objects: SYSTEM varchar(255) NOT NULL DEFAULT ''
 objects: TITLE varchar(255) NOT NULL DEFAULT ''
 objects: CLASS_ID int(10) NOT NULL DEFAULT '0'
 objects: DESCRIPTION text
 objects: LOCATION_ID int(10) NOT NULL DEFAULT '0'
 objects: KEEP_HISTORY int(10) NOT NULL DEFAULT '0'

 properties: ID int(10) unsigned NOT NULL auto_increment
 properties: CLASS_ID int(10) NOT NULL DEFAULT '0'
 properties: OBJECT_ID int(10) NOT NULL DEFAULT '0'
 properties: SYSTEM varchar(255) NOT NULL DEFAULT ''
 properties: TITLE varchar(255) NOT NULL DEFAULT ''
 properties: KEEP_HISTORY int(10) NOT NULL DEFAULT '0'
 properties: DATA_KEY int(3) NOT NULL DEFAULT '0' 
 properties: DATA_TYPE int(3) NOT NULL DEFAULT '0' 
 properties: DESCRIPTION text
 properties: ONCHANGE varchar(255) NOT NULL DEFAULT ''
 properties: INDEX (CLASS_ID)
 properties: INDEX (OBJECT_ID)
 
 pvalues: ID int(10) unsigned NOT NULL auto_increment
 pvalues: PROPERTY_NAME varchar(100) NOT NULL DEFAULT ''
 pvalues: PROPERTY_ID int(10) NOT NULL DEFAULT '0'
 pvalues: OBJECT_ID int(10) NOT NULL DEFAULT '0'
 pvalues: VALUE text
 pvalues: UPDATED datetime
 pvalues: SOURCE varchar(20) NOT NULL DEFAULT ''
 pvalues: LINKED_MODULES varchar(255) NOT NULL DEFAULT ''
 pvalues: INDEX (PROPERTY_ID)
 pvalues: INDEX (OBJECT_ID)
 pvalues: INDEX (PROPERTY_NAME) 

 phistory: ID int(10) unsigned NOT NULL auto_increment
 phistory: VALUE_ID int(10) unsigned NOT NULL DEFAULT '0'
 phistory: SOURCE varchar(20) NOT NULL DEFAULT ''
 phistory: ADDED datetime
 phistory: INDEX (VALUE_ID)

 phistory_queue: ID int(10) unsigned NOT NULL auto_increment
 phistory_queue: VALUE_ID int(10) unsigned NOT NULL DEFAULT '0'
 phistory_queue: VALUE text
 phistory_queue: OLD_VALUE text
 phistory_queue: KEEP_HISTORY int(10) unsigned NOT NULL DEFAULT '0'
 phistory_queue: SOURCE varchar(20) NOT NULL DEFAULT ''
 phistory_queue: ADDED datetime


EOD;
  parent::dbInstall($data);
 }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgTWF5IDIyLCAyMDA5IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
