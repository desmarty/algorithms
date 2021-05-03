<?php
class GTree{
    function db_data($config){
        /***
        $config = array('db_conn', 'table_name', 'parent_col', 'data_col')
        db_conn = PDO database connection variable
        table_name = table to be used
        parent_col = Parent key coloumn
        data_col = Primary key coloumn
        start_node = (optional) Primary id from where traversal will start
        max_depth = (optional) Total depth to retrieve

        return type :
            if success: 2D array sorted in depth
            if error: string - error details
        ***/
        $depth = 0;
        $no_of_child = true;
        $parent_list = NULL;
        $depth_array = array();
        $error = false;
        if(!isset($config['db_conn'])) $error = true;
        if(!isset($config['table_name'])) $error = true;
        if(!isset($config['parent_col'])) $error = true;
        if(!isset($config['data_col'])) $error = true;
        if($error == true)
            return 'configuration array should have following compulsary keys: 1. db_conn,  2. table_name, 3. parent_col, 4. data_col';
        else{
            //table variables
            $db_conn = $config['db_conn'];                  //database connection variable
            $table_name = $config['table_name'];            //table to be used
            $parent_col = $config['parent_col'];            //parent key coloumn
            $data_col = $config['data_col'];                //primary key coloumn
            if(isset($config['start_node'])) $start_node = $config['start_node'];
            if(isset($config['max_depth'])) $max_depth = $config['max_depth'];
            while($no_of_child){
                if(isset($max_depth))
                    if($depth > ($max_depth-1))
                        break;
                if($depth == 0) {
                    if(isset($start_node)) $task_stmt = "SELECT * FROM $table_name WHERE $data_col = :start_node";
                    else $task_stmt = "SELECT * FROM $table_name WHERE $parent_col IS NULL";
                }
                else $task_stmt = "SELECT * FROM $table_name WHERE $parent_col IN $parent_list ORDER BY $parent_col ASC, $data_col ASC";
                $task_stmt = $db_conn->prepare($task_stmt);
                if(isset($start_node)) $task_stmt->bindParam(':start_node', $start_node);
                $task_stmt -> execute();
                $task_arr = $task_stmt->fetchAll(PDO::FETCH_ASSOC);
                $no_of_child = $task_stmt->rowcount();

                if($no_of_child > 0){
                    $next_parent = array();
                    foreach($task_arr as $task_data){
                        array_push($next_parent, $task_data[$data_col]);
                    }
                    $parent_list = implode(",", $next_parent);
                    $parent_list = '('.$parent_list.')';
                    array_push($depth_array, $task_arr);
                    $depth++;
                }
            }
            return $depth_array;
        }

    }
    function array_data($config){

    }
    function construct_tree($depth_array, $config){
        /***
        retrun type : string (either constructed tree / error statement)
        $depth_array = array(array(nodes of root), array(nodes of 1st depth), array(nodes of 2nd depth), .... array(nodes of nth depth))
        $config = array(
                    'data_key' = key of current data
                    'parent_key' = key of parent data
                    'data_preview' = array(conditional_column, array(array(column_value,  preview),
                                                                    array(column_value,  preview),
                                                                    ...
                                                                    array(column_value, preview)
                                                                )
                    'data_preview' => array(
                                        array(
                                            array(
                                                array(MERGER, 'column_name', 'coloumn_value', OPERATOR),
                                                array(MERGER, 'column_name', 'coloumn_value', OPERATOR),
                                                ...
                                            ), $preview_string),
                                        array(
                                            array(
                                                array(MERGER, 'column_name', 'coloumn_value', OPERATOR),
                                                array(MERGER, 'column_name', 'coloumn_value', OPERATOR),
                                                ...
                                            ), $preview_string)
                                        ...
                                        ),
                    'enclosers' = array(child_encloser, parent_encloser)

                    MERGER: string - NULL, AND, OR | NULL defines the first condition
                    column_name : key to compare value with the current itterated data
                    column_value : conditional value for current itterated value
                    OPERATOR: equals, not_equals

                    preview : A preview string with data exchangers as format: {{column_name_1}}, {{column_name_2}}, {{column_name_3}}...{{column_name_N}}
                                e.g: <li>Its a list with these data:   {{column_name_1}}, {{column_name_2}}, {{column_name_3}}...{{column_name_N}}</li>
                    child_encloser : encloses all the child - string including {{child_encloser}}, e.g: <li>{{child_encloser}}</li>
                    parent_encloser : encloses child_enclosers to super node - string including {{parent_encloser}}, e.g: <ul>{{parent_encloser}}</ul>
        ***/
        //listing
        $error = false;
        if(!isset($config['data_key'])) $error = true;
        if(!isset($config['parent_key'])) $error = true;
        if(!isset($config['data_preview'])) $error = true;
        if(!isset($config['enclosers'])) $error = true;
        if($error)
            return 'configuration array should have following compulsary keys: 1. data_key,  2. parent_key, 3. data_preview, 4. enlosers';
        else{
            $data_key = $config['data_key'];
            $parent_key = $config['parent_key'];
            $data_preview = $config['data_preview'];
            $enclosers = $config['enclosers'];
            $child_encloser = $enclosers[0];
            $parent_encloser = $enclosers[1];
            $empty_encloser = $enclosers[0];
            $depth_array = array_reverse($depth_array);
            $ul_child = array();
            $ul_parent = array();
            $tree = array();
            for($depth=0; $depth < sizeof($depth_array); $depth++){
                $li = NULL;
                foreach($depth_array[$depth] as $depth_key => $depth_data){
                    $curr_parent = $depth_data[$parent_key];
                    $curr_id = $depth_data[$data_key];
                    foreach($data_preview as $dp_value){
                        $conditions = $dp_value[0];
                        $satisfied = false;
                        $prev_condition = NULL;
                        foreach($conditions as $condition_data){
                            $merger = $condition_data[0];
                            $col_name = $condition_data[1];
                            $col_value = $condition_data[2];
                            $operator = $condition_data[3];
                            switch ($merger){
                                case NULL:
                                    if($operator == 'equals'){
                                        if($depth_data[$col_name] == $col_value) $satisfied = true;
                                        else $satisfied = false;
                                    }
                                    else if($operator == 'not_equals'){
                                        if($depth_data[$col_name] != $col_value) $satisfied = true;
                                        else $satisfied = false;
                                    }
                                    break;
                                case 'and':
                                    if($operator == 'equals'){
                                        if($depth_data[$col_name] == $col_value) $satisfied = $satisfied && true;
                                        else $satisfied = $satisfied && false;
                                    }
                                    else if($operator == 'not_equals'){
                                        if($depth_data[$col_name] != $col_value) $satisfied = $satisfied && true;
                                        else $satisfied = $satisfied && false;
                                    }
                                    break;
                                case 'or':
                                    if($operator == 'equals'){
                                        if($depth_data[$col_name] == $col_value) $satisfied = $satisfied || true;
                                        else $satisfied = $satisfied || false;
                                    }
                                    else if($operator == 'not_equals'){
                                        if($depth_data[$col_name] != $col_value) $satisfied = $satisfied || true;
                                        else $satisfied = $satisfied || false;
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                        if($satisfied){
                            #$preview_str = $condition_arr[1];
                            $preview_str = $dp_value[1];
                            $pattern = "/\{\{(.*?)\}\}/";
                            preg_match_all($pattern, $preview_str, $matches);
                            $matches = $matches[1];
                            foreach($matches as $match){
                                $preview_str = preg_replace("/\{\{($match)\}\}/", $depth_data[$match], $preview_str);
                            }
                        }
                    }
                    //$li = '<li>'.$depth_data[$data_key].' '.$depth_data['milestone'].'</li>';
                    $li = $preview_str;
                    if(!isset($ul_parent[$curr_parent])) $ul_parent[$curr_parent] = NULL;
                    $ul_parent[$curr_parent] .= $li;
                    if(isset($ul_child[$curr_id])){
                        //$li = '<li>'.$ul_child[$curr_id].'</li>';
                        $li = preg_replace("/\{\{(child_encloser)\}\}/", $ul_child[$curr_id], $child_encloser);
                        $ul_parent[$curr_parent] .= $li;
                        unset($ul_child[$curr_id]);
                    }
                }
                foreach($ul_parent as $key => $data){
                    //$sys_vars = array('_child_count_' => $child_count[$key]);
                    //$ul_parent[$key] = '<ul>'.$data.'</ul>';
                    $ul_parent[$key] = preg_replace("/\{\{(parent_encloser)\}\}/", $data, $parent_encloser);
                }
                //echo '<pre>'.var_export($ul_parent, true).'</pre>';
                $ul_child = $ul_parent;
                $ul_parent = array();
            }
            //echo $ul_child[''];
            return $ul_child[''];
        }
    }
}
?>
