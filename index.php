<?php


/* Filter URL from redundancies and extract requested active file */
$uri = rtrim( dirname($_SERVER["SCRIPT_NAME"]), '/' );
$uri = '/' . trim( str_replace( $uri, '', $_SERVER['REQUEST_URI'] ), '/' );
$uri = urldecode( $uri );

/* Define some constants to use throughout the system */
define("DOC_FOLDER", './docs');
define("ANCHOR_URL", 'http://localhost/bluejay/');

class tree_node 
{
    public string $name;
    public string $file_name;
    public string $directory;
    public bool $is_directory = false;
    public array $sub_directory = array();

    public function __construct (string $file_name, string $directory, array $sub = array()) {
        $this->file_name = $file_name;
        $this->directory = $directory;

        if ($sub != array()) {
            $this->is_directory = true;
            $this->sub_directory = $sub;
            $this->name = $file_name;
        } else {
            $fp = fopen($directory . '/' . $file_name, 'r');
            $line = fgets($fp) . fgets($fp)[0];
            $this->name = substr($line, 2);
            fclose($fp);
        }
    }

    public function get_href() : string {
        return substr($this->directory . '/' . $this->file_name, strlen(DOC_FOLDER) + 1);
    }

    public function have_readme() : bool {
        if ($this->file_name == "README.md") return true;

        foreach ($this->sub_directory as $node) {
            if (!$node->is_directory && $node->have_readme()) return true;
        }
        return false;
    }

}

/**
 * Create a virtual file-system from a source ($target) directory and recursively 
 * attach subdirectories and files. 
 */
function get_docs_tree(string $target) : array {
    $result = array();

    $current_dir = array_diff(scandir($target), array('..', '.'));
    foreach ($current_dir as $value)
    {
        if (is_dir($target . '/' . $value))
            $result[] = new tree_node($value, $target, get_docs_tree($target . '/' . $value));
        else if (str_contains($value, '.md'))
            $result[] = new tree_node($value, $target);
    }
  
   return $result; 
}

/**
 * Using a virtual file-system create a traversible unordered list that can be
 * used as a navigation/table of contents
 */
function build_table_contents(array $tree, string $active, bool $child = false) : string {
    $result = "<ul>";
    if ($child && !search_current_file($tree, substr($active, 1))) $result = '<ul class="collapsiable">';

    foreach ($tree as $node) {
        if ($node->is_directory) {
            $result .= '<li><i class="fa fa-folder" style="color: #e67e22;"></i>';

            if ($node->have_readme()) {
                $result .= '<a href="' . ANCHOR_URL . $node->get_href() . '">';
            } else {
                $result .= '<a onclick="collapse_items(this)">';
            }
            $result .= $node->name . '</a>' . build_table_contents($node->sub_directory, $active, true) . '</li>';

        } else {
            $file = substr($node->name, 0, -3);
            $result .= '<li><i class="fa fa-file" style="color: #1abc9c;"></i><a href="' . ANCHOR_URL . $node->get_href() . "\">$file</a></li>";
        }
        
    }
    return $result . "</ul>";
}

/**
 * Search a given parent node in a virtual file system to see if it contains
 * a particular file given by $location
 */
function search_current_file(array $parent_node, string $location) : bool {
    $result = false;
    foreach ($parent_node as $node) {
        if ($node->is_directory) {
            if ($node->get_href() === $location) return true;
            $result = $result || search_current_file($node->sub_directory, $location);
        } else {
            if ($node->get_href() === $location) return true;
        }
    }
    return $result;
} 

/**
 * Using the requested url traverse the file-system for the filename and location
 * in the real file system. Returns 'error.md' if not found.
 */
function get_current_file(array $tree, string $location) : string {
    $result = 'error.md';

    if ($location === '/') return DOC_FOLDER . '/README.md';
    foreach ($tree as $node) {
        if ($node->is_directory) {
            if ($node->have_readme()) {
                if ($node->get_href() === $location) {
                    return DOC_FOLDER . '/' . $node->get_href() . '/README.md';
                } 
            } else {
                $result = get_current_file($node->sub_directory, $location);
            }
        } else {
            if ($node->get_href() === $location) return DOC_FOLDER . '/' . $node->get_href(); 
        }
        if ($result != 'error.md') return $result;
    }

    return $result;
}

/* Build the file-system tree */
$tree = get_docs_tree(DOC_FOLDER);

/* Fetch the active file to render */
$target_file = get_current_file($tree, substr($uri, 1));

?>

<html>
<head>
    <title>Bluejay</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/showdown/1.9.1/showdown.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        <?php include("theme.css"); ?>
    </style>
</head>

<body>
    <div class="top-bar">
        <h1>Bluejay Docs</h1>
    </div>
    <div>
        <div class="side-bar">
            <?php echo build_table_contents($tree, $uri); ?>
        </div>
        <div class="main-content">
            <div id="md_content"></div>
        </div>
    </div>
</body>

</html>

<script>
function collapse_items(parent) {
    let value = parent.parentElement.getElementsByClassName("collapsiable")[0].style.display;
    if (value == "none") {
        parent.parentElement.getElementsByClassName("collapsiable")[0].style.display = "block";
    } else {
        parent.parentElement.getElementsByClassName("collapsiable")[0].style.display = "none";
    }
}

var md_text = `<?php
/* Write the active file to the render buffer */
$file = fopen($target_file,"r");
while(!feof($file)) {
    echo str_replace('`', '\`', fgets($file));
}
fclose($file);
?>`;

var converter = new showdown.Converter();
var converted_html = converter.makeHtml(md_text);

// Set content
document.getElementById('md_content').innerHTML = converted_html;

</script>