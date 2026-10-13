<?php
/**
 * Edit forms must keep quotation marks inside field values.
 * A raw quote used to end the HTML attribute, so the next save stored
 * only the text before the quote.
 * Run: php tests/edit_form_attribute_test.php
 */

$root = dirname(__DIR__);
$admin = $root . '/admin.php';
$failures = 0;

function assert_true($cond, $message)
{
    global $failures;
    if (!$cond) {
        $failures++;
        fwrite(STDERR, "FAIL: $message\n");
    } else {
        fwrite(STDOUT, "OK: $message\n");
    }
}

function input_value($html, $name)
{
    if (!preg_match('/<input\b[^>]*\bname="' . preg_quote($name, '/') . '"[^>]*>/i', $html, $tag)) {
        return null;
    }
    if (!preg_match('/\bvalue="([^"]*)"/', $tag[0], $value)) {
        return null;
    }
    return html_entity_decode($value[1], ENT_QUOTES, 'UTF-8');
}

function textarea_value($html, $name)
{
    if (!preg_match('/<textarea\b[^>]*\bname="' . preg_quote($name, '/') . '"[^>]*>(.*?)<\/textarea>/s', $html, $tag)) {
        return null;
    }
    return html_entity_decode($tag[1], ENT_QUOTES, 'UTF-8');
}

function run_case($name, $get, $post, $expect)
{
    global $admin;

    $dir = sys_get_temp_dir() . '/editattr_' . bin2hex(random_bytes(4));
    mkdir($dir . '/includes', 0700, true);
    $log = $dir . '/sql.log';
    file_put_contents($log, '');

    file_put_contents($dir . '/includes/config.php', <<<'PHP'
<?php
$login = true;
$session_right = 4;
$session_id = 1;
$session_user = 'admin';
$gcon = 'stub';
if (!defined('CMS_SERV_NAME')) {
    define('CMS_SERV_NAME', 'Test');
}
function mysql_real_escape_string($string, $link = null) {
    return (string)$string;
}
function mysql_query($sql, $link = null) {
    file_put_contents(getenv('SQL_LOG'), $sql . "\n----\n", FILE_APPEND);
    if (stripos(ltrim($sql), 'SELECT') === 0) {
        return 'row';
    }
    return 'updated';
}
function mysql_fetch_assoc($result) {
    if ($result !== 'row') {
        return false;
    }
    return array(
        'id' => '7',
        'ID' => '7',
        'title' => getenv('TEST_TITLE'),
        'text' => getenv('TEST_TEXT'),
        'image' => getenv('TEST_IMAGE'),
        'hot' => getenv('TEST_HOT'),
        'itemID' => getenv('TEST_ITEM_ID'),
        'itemName' => getenv('TEST_ITEM_NAME'),
        'amount' => getenv('TEST_AMOUNT'),
        'price' => getenv('TEST_PRICE'),
    );
}
function mysql_num_rows($result) {
    return 1;
}
PHP
    );
    file_put_contents($dir . '/includes/head.php', "<?php\n");
    file_put_contents($dir . '/includes/foot.php', "<?php\n");
    if (!file_exists($dir . '/admin.php')) {
        symlink($admin, $dir . '/admin.php');
    }
    file_put_contents($dir . '/runner.php', <<<'PHP'
<?php
$_GET = json_decode(getenv('TEST_GET'), true);
$_POST = json_decode(getenv('TEST_POST'), true);
if (!is_array($_GET)) {
    $_GET = array();
}
if (!is_array($_POST)) {
    $_POST = array();
}
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = empty($_POST) ? 'GET' : 'POST';
ob_start();
include 'admin.php';
file_put_contents(getenv('TEST_OUT'), ob_get_clean());
PHP
    );

    $env = array();
    foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $env[$key] = $value;
        }
    }
    $env['SQL_LOG'] = $log;
    $env['TEST_OUT'] = $dir . '/out.html';
    $env['TEST_GET'] = json_encode($get);
    $env['TEST_POST'] = json_encode($post);
    $env['TEST_TITLE'] = isset($expect['title']) ? $expect['title'] : 'Patch notes';
    $env['TEST_TEXT'] = isset($expect['text']) ? $expect['text'] : 'Body';
    $env['TEST_IMAGE'] = isset($expect['image']) ? $expect['image'] : 'banner.png';
    $env['TEST_HOT'] = isset($expect['hot']) ? $expect['hot'] : '1';
    $env['TEST_ITEM_ID'] = isset($expect['item_id']) ? $expect['item_id'] : '1001';
    $env['TEST_ITEM_NAME'] = isset($expect['item_name']) ? $expect['item_name'] : 'Starter';
    $env['TEST_AMOUNT'] = isset($expect['amount']) ? $expect['amount'] : '5';
    $env['TEST_PRICE'] = isset($expect['price']) ? $expect['price'] : '250';

    $cmd = 'php -d auto_prepend_file= ' . escapeshellarg($dir . '/runner.php');
    $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, $dir, $env);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    $html = file_exists($dir . '/out.html') ? file_get_contents($dir . '/out.html') : '';
    $sqlLog = file_get_contents($log);

    assert_true($code === 0, "$name exits 0 (got $code) stderr=" . trim($stderr) . ' stdout=' . trim($stdout));

    if (isset($expect['title_value'])) {
        $got = input_value($html, 'title');
        assert_true($got === $expect['title_value'], "$name title field is " . json_encode($expect['title_value']) . ' got ' . json_encode($got));
    }
    if (isset($expect['img_value'])) {
        $got = input_value($html, 'img');
        assert_true($got === $expect['img_value'], "$name image field is " . json_encode($expect['img_value']) . ' got ' . json_encode($got));
    }
    if (isset($expect['item_name_value'])) {
        $got = input_value($html, 'itemName');
        assert_true($got === $expect['item_name_value'], "$name item name is " . json_encode($expect['item_name_value']) . ' got ' . json_encode($got));
    }
    if (isset($expect['item_text_value'])) {
        $got = textarea_value($html, 'itemText');
        assert_true($got === $expect['item_text_value'], "$name item text is " . json_encode($expect['item_text_value']) . ' got ' . json_encode($got));
    }
    if (isset($expect['update_contains'])) {
        assert_true(strpos($sqlLog, $expect['update_contains']) !== false, "$name SQL contains " . json_encode($expect['update_contains']) . ' log=' . json_encode($sqlLog));
    }
    if (isset($expect['update_excludes'])) {
        assert_true(strpos($sqlLog, $expect['update_excludes']) === false, "$name SQL excludes " . json_encode($expect['update_excludes']) . ' log=' . json_encode($sqlLog));
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        if ($file->isLink() || $file->isFile()) {
            unlink($file->getPathname());
        } else {
            rmdir($file->getPathname());
        }
    }
    rmdir($dir);
}

$quoted = 'Patch "Nightfall" ist live';
$amp = 'Tom & Jerry\'s return';
$image = 'my file.png';

run_case(
    'news edit keeps quoted title',
    array('mode' => '13', 'id' => '7'),
    array(),
    array(
        'title' => $quoted,
        'image' => $image,
        'title_value' => $quoted,
        'img_value' => 'my file',
    )
);

run_case(
    'slider edit keeps quoted title',
    array('mode' => '14', 'id' => '7'),
    array(),
    array(
        'title' => $quoted,
        'image' => 'slide.png',
        'title_value' => $quoted,
        'img_value' => 'slide',
    )
);

run_case(
    'news edit keeps ampersand and apostrophe',
    array('mode' => '13', 'id' => '7'),
    array(),
    array(
        'title' => $amp,
        'title_value' => $amp,
        'img_value' => 'banner',
    )
);

run_case(
    'news save stores the full quoted title',
    array('mode' => '13', 'id' => '7'),
    array(
        'title' => $quoted,
        'type' => '1',
        'img' => 'my file',
        'text' => 'Zeile eins',
    ),
    array(
        'title' => $quoted,
        'update_contains' => 'title = \'Patch "Nightfall" ist live\'',
        'update_excludes' => '&quot;',
    )
);

run_case(
    'cashshop edit keeps quoted item name and description',
    array('mode' => '10', 'action' => '1', 'id' => '7'),
    array(),
    array(
        'item_name' => 'Paket "Starter"',
        'text' => 'Use </textarea> carefully & often',
        'item_name_value' => 'Paket "Starter"',
        'item_text_value' => 'Use </textarea> carefully & often',
    )
);

run_case(
    'cashshop save stores the full item name',
    array('mode' => '10', 'action' => '1', 'id' => '7'),
    array(
        'itemID' => '1001',
        'itemName' => 'Paket "Starter"',
        'itemAmount' => '5',
        'itemText' => 'Heiltrank',
        'itemPrice' => '250',
    ),
    array(
        'update_contains' => 'ItemName = \'Paket "Starter"\'',
        'update_excludes' => '&quot;',
    )
);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}
fwrite(STDOUT, "all edit form attribute checks passed\n");
exit(0);
