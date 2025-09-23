<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 
 *  get plain text from multidimensional array
 * 
 */	
if ( ! function_exists('array_to_plain_text'))
{
	function array_to_plain_text($data)
	{
		$output = array();
        $item = new RecursiveIteratorIterator(new RecursiveArrayIterator($data));
        foreach($item as $value) {
            $output[] = $value;
        }  
        return implode(' ', $output);        
	}
}


/**
 * 
 * 
 * Return array value by key
 * 
 */
if ( ! function_exists('array_get_value'))
{
	function array_get_value($data,$key)
	{
        if(array_key_exists($key,$data)){
            return $data[$key];
        }
        return false;
    }
}




/**
 * @param array $array
 * @param array|string $parents
 * @param string $glue
 * @return mixed
 */
if ( ! function_exists('get_array_nested_value'))
{
    function get_array_nested_value(array &$array, $parents, $glue = '/')
    {
        if (!is_array($parents)) {
            $parents = explode($glue, $parents);
        }

        $ref = &$array;

        foreach ((array) $parents as $parent) {
            if (is_array($ref) && array_key_exists($parent, $ref)) {
                $ref = &$ref[$parent];
            } else {
                return null;
            }
        }
        return $ref;
    }
}


/**
 * @param array $array
 * @param array|string $parents
 * @param mixed $value
 * @param string $glue
 */
if ( ! function_exists('set_array_nested_value'))
{
    function set_array_nested_value(array &$array, $parents, $value, $glue = '/')
    {
        if (!is_array($parents)) {
            $parents = explode($glue, (string) $parents);
        }

        $ref = &$array;

        foreach ($parents as $parent) {
            if (isset($ref) && !is_array($ref)) {
                $ref = array();
            }

            $ref = &$ref[$parent];
        }

        $ref = $value;
    }
}


/**
 * @param array $array
 * @param array|string $parents
 * @param string $glue
 */
if ( ! function_exists('array_unset_value'))
{
    function array_unset_value(&$array, $parents, $glue = '.')
    {
        if (!is_array($parents)) {
            $parents = explode($glue, $parents);
        }

        $key = array_shift($parents);

        if (empty($parents)) {
            unset($array[$key]);
        } else {
            array_unset_value($array[$key], $parents);
        }
    }
}


if ( ! function_exists('array_remove_nulls'))
{
    function array_remove_nulls(array &$array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = array_remove_nulls($value);
            }
            if ($value===null) {
                unset($array[$key]);
            }
        }
        return $array;
    }
}

if ( ! function_exists('array_remove_empty'))
{
    function array_remove_empty(array &$array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = array_remove_empty($value);
                if (count($value)==0){
                    $value=null;
                }
            }else{
                if(trim($value)==''){
                    $value=null;
                }
            }
            if ($value===null) {
                unset($array[$key]);
            }
        }

        //reindex if numeric array
        if (!empty($array) && ctype_digit(implode('', array_keys($array)))) {
            $array = array_values($array);
        }

        return $array;
    }
}


if ( ! function_exists('is_indexed_array'))
{
    //check if array has numeric keys
    function is_indexed_array($arr)
    {
        if (!is_array($arr)){
            return false;
        }
        if (array() === $arr) return false;
        $k = array_keys( $arr ); 
        return $k === array_keys( $k );
    }
}


if ( ! function_exists('is_simple_indexed_array'))
{
    //array has numeric keys and contain no sub arrays
    function is_simple_indexed_array($arr)
    {
        $indexed=is_indexed_array($arr);

        if ($indexed==true){
            foreach($arr as $key=>$value){
                if (is_array($value)){
                    foreach($value as $child){
                        if (is_array($child)){
                            return false;
                        }
                    }
                }
            }        
        }

        return $indexed;
    }
}

if ( ! function_exists('array_indexed_elements'))
{
    //return array nodes with simple arrays
    function array_indexed_elements($arr,$parent=null)
    {
        $output=array();
        foreach($arr as $node_key=>$node){

            $el_path=implode("/",array_filter(array($parent, $node_key)));

            if(is_simple_indexed_array($node)){
                $output[]= $el_path;
            }

            if (is_array($node)){
                $output=array_merge($output,array_indexed_elements($node,$el_path));
            }
        }

        return $output;
    }
}


if (! function_exists('array_data_get')) {
    /**
     * Get an item from an array or object using "dot" notation.
     * 
     * author: https://github.com/rappasoft
     *
     * @param  mixed  $target
     * @param  string|array|int|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    function array_data_get($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if (is_null($segment)) {
                return $target;
            }

            if ($segment === '*') {
                if (! is_array($target)) {
                    return value($default);
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = array_data_get($item, $key);
                }

                return in_array('*', $key) ? array_collapse($result) : $result;
            }

            if (array_accessible($target) && array_exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param  mixed $value
     * @return mixed
     */
    function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }
}

if (! function_exists('array_collapse')) {
    /**
     * Collapse an array of arrays into a single array.
     *
     * @param  iterable  $array
     *
     * @return array
     */
    function array_collapse(iterable $array): array
    {
        $results = [];

        foreach ($array as $values) {
            if (!is_array($values)) {
                continue;
            }

            $results[] = $values;
        }

        return array_merge([], ...$results);
    }
}


if (! function_exists('array_exists')) {
    /**
     * Determine if the given key exists in the provided array.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string|int  $key
     *
     * @return bool
     */
    function array_exists($array, $key): bool
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }

        return array_key_exists($key, $array);
    }
}

if (! function_exists('array_accessible')) {
    /**
     * Determine whether the given value is array accessible.
     *
     * @param  mixed  $value
     *
     * @return bool
     */
    function array_accessible($value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }
}


if (! function_exists('is_array_of_array')) {
    function is_array_of_array($arr)
    {
        if (!is_array($arr)){
            return false;
        }

        foreach($arr as $row){
        if (is_array($row) && is_assoc_array($row)){
            return false;
        }
        }
        return true;
  }
}


if (! function_exists('is_assoc_array')) {
  function is_assoc_array($array){
    $keys = array_keys($array);
    return $keys !== array_keys($keys);
  }
}



if (! function_exists('array_merge_replace_recursive')) {
    /**
	 * 
	 * Merge and replace nested deep nested array + indexed array
	 * 
	 * @metadata - original array
	 * @replace - partial array
	 * 
	 */
	function array_merge_replace_recursive($metadata, $replace)
    {
        $metadata=array_replace_recursive($metadata,$replace);
        $metadata_indexed_fields=array_indexed_elements($metadata);

        foreach($metadata_indexed_fields as $path){
            if ($replace_value=get_array_nested_value($replace,$path,"/")){
                set_array_nested_value($metadata,$path,$replace_value,"/");
            }
        }

        return $metadata;
	}
}