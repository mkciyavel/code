<?php
/**
 * Character edits (mode 8) must not write while the character is online.
 * The overview hides the edit links, but the save handlers used to apply
 * the change anyway, so a login between opening the form and submitting
 * overwrote the live character.
 * Run: php tests/character_online_edit_test.php
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

function run_case($name, $get, $post, $online)
{
    global $admin;

    $dir = sys_get_temp_dir() . '/charonline_' . bin2hex(random_bytes(4));
    mkdir($dir . '/includes', 0700, true);
    $log = $dir . '/sql.log';
    file_put_contents($log, '');

    $config = <<<'PHP'
<?php
$login = true;
$session_right = 3;
$session_id = 7;
$session_user = 'support';
$gcon = 'stub';
if (!defined('CMS_SERV_NAME')) {
    define('CMS_SERV_NAME', 'Test');
}
function mysql_real_escape_string($string, $link = null) {
    return (string)$string;
}
function mysql_query($sql, $link = null) {
    return true;
}
function mysql_fetch_assoc($result) {
    return false;
}
function mysql_num_rows($result) {
    return 0;
}
function odbc_exec($conn, $sql) {
    file_put_contents(getenv('SQL_LOG'), $sql . "\n----\n", FILE_APPEND);
    $GLOBALS['last_sql'] = $sql;
    return 'q';
}
function odbc_fetch_array($result) {
    return false;
}
function odbc_num_rows($result) {
    $sql = isset($GLOBALS['last_sql']) ? $GLOBALS['last_sql'] : '';
    if (stripos($sql, 'TCURRENTUSER') !== false && stripos($sql, 'SELECT') !== false) {
        return getenv('CHAR_ONLINE') === '1' ? 1 : 0;
    }
    return 0;
}
PHP;
    file_put_contents($dir . '/includes/config.php', $config);
    file_put_contents($dir . '/includes/head.php', "<?php\n");
    file_put_contents($dir . '/includes/foot.php', "<?php\n");
    symlink($admin, $dir . '/admin.php');

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
    $env['CHAR_ONLINE'] = $online ? '1' : '0';

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

    return array(
        'html' => $html,
        'sql' => $sqlLog,
        'dir' => $dir,
    );
}

function cleanup($dir)
{
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

function assert_blocked($name, $get, $post, $updateNeedle)
{
    $result = run_case($name, $get, $post, true);
    assert_true(strpos($result['sql'], $updateNeedle) === false, "$name does not write $updateNeedle");
    assert_true(strpos($result['sql'], 'UPDATE ') === false, "$name does not run any UPDATE");
    assert_true(strpos($result['html'], 'ausgeloggt sein') !== false, "$name tells the admin the character must be offline");
    assert_true(strpos($result['html'], 'name="new') === false, "$name does not show the edit form");
    cleanup($result['dir']);
}

assert_blocked(
    'online level save',
    array('mode' => '8', 'action' => '5', 'cid' => '42'),
    array('newlvl' => '50'),
    "SET bLevel = '50'"
);

assert_blocked(
    'online rename',
    array('mode' => '8', 'action' => '11', 'cid' => '42'),
    array('newname' => 'Renamed'),
    "SET szName = 'Renamed'"
);

assert_blocked(
    'online money save',
    array('mode' => '8', 'action' => '10', 'cid' => '42'),
    array('newgold' => '10', 'newsilver' => '0', 'newcooper' => '0'),
    'SET dwGold'
);

$offline = run_case(
    'offline level save',
    array('mode' => '8', 'action' => '5', 'cid' => '42'),
    array('newlvl' => '50'),
    false
);
assert_true(strpos($offline['sql'], "SET bLevel = '50'") !== false, 'offline level save still updates bLevel');
assert_true(strpos($offline['sql'], "dwCharID = '42'") !== false, 'offline level save targets that character');
assert_true(strpos($offline['html'], 'ausgeloggt sein') === false, 'offline level save does not show the online error');
assert_true(strpos($offline['html'], 'erfolgreich') !== false, 'offline level save reports success');
cleanup($offline['dir']);

$form = run_case(
    'offline level form',
    array('mode' => '8', 'action' => '5', 'cid' => '42'),
    array(),
    false
);
assert_true(strpos($form['sql'], 'UPDATE ') === false, 'opening the level form does not write');
assert_true(strpos($form['html'], 'name="newlvl"') !== false, 'offline level form is shown');
cleanup($form['dir']);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}
fwrite(STDOUT, "all character online-edit checks passed\n");
exit(0);
