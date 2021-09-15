<?php


/* Filter URL from redundancies */
$uri = rtrim( dirname($_SERVER["SCRIPT_NAME"]), '/' );
$uri = '/' . trim( str_replace( $uri, '', $_SERVER['REQUEST_URI'] ), '/' );
$uri = urldecode( $uri );

// echo $uri;


$target_directory = './docs';

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
        return $this->directory . '/' . $this->file_name;
    }

    public function have_readme() : bool {
        if ($this->file_name == "README.md") return true;

        foreach ($this->sub_directory as $node) {
            if (!$node->is_directory && $node->have_readme()) return true;
        }
        return false;
    }

}

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

function build_table_contents(array $tree, bool $child = false) : string {
    $result = "<ul>";
    if ($child) $result = '<ul class="collapsiable">';

    foreach ($tree as $node) {
        if ($node->is_directory) {
            if ($node->have_readme()) {
                $result .= "<li><a href=\"" . $node->get_href() . "\">$node->name</a>" . build_table_contents($node->sub_directory, true) . "</li>";
            } else {
                $result .= "<li><a onclick=\"collapse_items(this)\">$node->name</a>" . build_table_contents($node->sub_directory, true) . "</li>";
            }

        } else {
            $file = substr($node->name, 0, -3);
            $result .= "<li><a href=\"" . $node->get_href() . "\">$file</a></li>";
        }
        
    }
    return $result . "</ul>";
}


$tree = get_docs_tree($target_directory);


// echo in_array('README.md', $tree);

?>

<html>

<head>
    <title>Bluejay</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/showdown/1.9.1/showdown.min.js"></script>
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
            <?php echo build_table_contents($tree); ?>
        </div>
        <div class="main-content">
            <div id="md_content">
                <h1>Hello World</h1>
                <p>A test paragraph body</p>
                <p>The second paragraph body</p>
            </div>
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

$file = fopen("docs/README.md","r");

while(! feof($file))
  {
  echo str_replace('`', '\`', fgets($file));
  }

fclose($file);

?>`;

var converter = new showdown.Converter();
var converted_html = converter.makeHtml(md_text);

// Set content
document.getElementById('md_content').innerHTML = converted_html;

</script>