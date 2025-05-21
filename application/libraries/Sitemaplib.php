<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * A very simple sitemap link generator
 *
 * This class uses the PHP5 Reflection class to find all the public methods
 * in a CodeIgniter controller class and generate a list of links.
 *
 * @author         Jonathon Hill <jonathon@compwright.com>
 * @license        CodeIgniter license
 * @requires    MY_Parser extended Parser class [added sparse() for parsing templates stored in a string var]
 * @requires    CodeIgniter 1.6 and PHP5
 * @version        1.1
 *
 */
class SitemapLib {


    /**
     * CodeIgniter base object reference
     *
     * @var object
     */
    private $CI;


    /**
     * Sitemap links template
     *
     * @var string
     */
    private $template = '<h2><a href="{section_index}">{section_text}</a></h2><ul>{links}<li><a href="{link_url}">{link_text}</a></li>{/links}</ul>';
    private $template_file; // optional sitemap template file


    /**
     * Hide the index page by default
     *
     * @var boolean
     */
    private $show_index = false;


    /**
     * Method names to ignore
     *
     * @var array
     */
    private $ignore = array(
        '*' => array(
            'get_instance',
            'controller',
            'ci_base'
        )
    );


    /**
     * Sitemap object initialization
     *
     */
    function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->library('parser');
    }


    /**
     * Set configuration option(s).
     *
     * Usage:
     *     $this->sitemap->set_option($option, $value);
     *     $this->sitemap->set_option(array(
     *         'option' => 'value',
     *         ...
     *     ));
     *
     * Options:
     *     template           (string) template stored in string
     *     template_file   (string) template stored in file
     *     show_index      (bool) show or hide the index page
     *
     * @param mixed  $option
     * @param mixed  $value
     * @return boolean
     */
    function set_option($option, $value)
    {
        if(is_array($option))
        {
            foreach($option as $opt => $val)
            {
                if($opt == 'ignore') continue;
                if(isset($this->$opt)) $this->$opt = $val;
            }
            return true;
        }
        elseif(isset($this->$option) && $option != 'ignore')
        {
            $this->$option = $value;
            return true;
        }
        else
        {
            return false;
        }
    }


    /**
     * Return a configuration option
     *
     * @param string $option
     * @return mixed
     */
    function get_option($option)
    {
        return ($this->$option)? $this->option : false;
    }


    /**
     * Ignore a controller or specific pages in a controller
     * Usage:
     *     $this->sitemap->ignore('controller', '*');    // completely ignore a controller
     *     $this->sitemap->ignore('controller', array('page1', 'page2'));    // ignore certain pages
     *
     *
     * @param string $controller
     * @param mixed $pages
     */
    function ignore($controller, $pages)
    {
        $controller = strtolower($controller);
        if(is_array($pages)) {
            array_walk_recursive($pages, 'SitemapLib::stl_callback');
        }
        else {
            $pages = strtolower($pages);
        }

        if(isset($this->ignore[$controller]) AND is_array($this->ignore[$controller])) $pages = array_merge($this->ignore[$controller], (array) $pages);
        $this->ignore[$controller] = $pages;
    }


    /**
     * Build a list of pages in a controller
     *
     * @param string $page        (optional) Build all the links for a specific controller
     * @return string
     */
    function get_links($class = null)
    {
        // Use the PHP5 Reflection class to introspect the controller
        $controller = new ReflectionClass($class);

        $data['links'] = array();
        $data['section_index'] = strtolower(site_url($class));
        $data['section_text'] = ucwords(strtr($class, array('_'=>' ')));

        foreach($controller->getMethods() as $method)
        {
            // skip methods that begin with '_'
            if(substr($method->name, 0, 1) == '_') continue;

            // skip globally ignored names
            if(in_array(strtolower($method->name), $this->ignore['*'])) continue;

            // skip ignored controller methods
            if(isset($this->ignore[strtolower($class)]) AND in_array(strtolower($method->name), (array) $this->ignore[strtolower($class)])) continue;

            // skip index page
            if($method->name == 'index' && !$this->show_index) continue;

            // skip old-style constructor
            if(strtolower($method->name) == strtolower($class)) continue;

            // skip methods that aren't public
            if(!$method->isPublic()) continue;

            // build link data for parser class
            $data['links'][] = array(
                'link_url' => strtolower(site_url("$class/$method->name")),
                'link_text'=> ucwords(strtr($method->name, array('_'=>' '))),
            );
        }

        return ($this->template_file)?
            $this->CI->parser->parse($this->template_file, $data, true) :
            $this->CI->parser->sparse($this->template, $data, true);
    }


    /**
     * Build a complete sitemap from your CI application controllers
     *
     * @return string
     */
    function generate()
    {
        $this->CI->load->helper('file');

        $sitemap = '';
        $controllers_path = APPPATH.'controllers/';
        foreach(get_filenames($controllers_path, true) as $controller) {
            list($class, $ext) = explode('.', ucfirst(basename($controller)));
            if($ext != 'php') continue;     // skip anything other than PHP files
            if(isset($this->ignore[strtolower($class)]) AND $this->ignore[strtolower($class)] == '*') continue;    // skip controllers marked as 'ignore'
            if(!class_exists($class)) include($controller);  // include the class for access
            $sitemap .= $this->get_links($class) . "\n";
        }

        return $sitemap;
    }


    /*
     * Callback wrapper function for strtolower
     * Has 2 args to prevent warnings from the strtolower() function
     */
    static function stl_callback($a, $b) { return strtolower($a); }

} 