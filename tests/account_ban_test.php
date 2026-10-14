<?php
/**
 * Account ban (mode 7 action 4) must not run on GET.
 * Reloading the old result URL toggled the row, so a refresh unbanned
 * the account the staff member had just banned.
 * Run: php tests/account_ban_test.php
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

function run_case($name, $get, $post, $bannedBefore)
{
    global $admin;

    $dir = sys_get_temp_dir() . '/accountban_' . bin2hex(random_bytes(4));
    mkdir($dir . '/includes', 0700, true);
    $log = $dir . '/sql.log';
    $state = $dir . '/banned.txt';
    file_put_contents($log, '');
    file_put_contents($state, $bannedBefore ? '1' : '0');

    $config = <<<'PHP'
<?php
$login = true;
$session_right = 1;
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
function ban_state($value = null) {
    $path = getenv('BAN_STATE');
    if ($value === null) {
        return trim((string)file_get_contents($path)) === '1';
    }
    file_put_contents($path, $value ? '1' : '0');
}
function odbc_exec($conn, $sql) {
    file_put_contents(getenv('SQL_LOG'), $sql . "\n----\n", FILE_APPEND);
    $GLOBALS['last_sql'] = $sql;
    if (stripos($sql, 'INSERT INTO TGLOBAL_GSP.dbo.TUSERPROTECTED') !== false) {
        ban_state(true);
    }
    if (stripos($sql, 'DELETE FROM TGLOBAL_GSP.dbo.TUSERPROTECTED') !== false) {
        ban_state(false);
    }
    return 'q';
}
function odbc_fetch_array($result) {
    if (isset($GLOBALS['last_sql']) && stripos($GLOBALS['last_sql'], 'TACCOUNT') !== false) {
        if (getenv('ACCOUNT_MISSING') === '1') {
            return false;
        }
        return array('szUserID' => 'Player "One"');
    }
    return false;
}
function odbc_num_rows($result) {
    if (isset($GLOBALS['last_sql']) && stripos($GLOBALS['last_sql'], 'TUSERPROTECTED') !== false && stripos($GLOBALS['last_sql'], 'SELECT') !== false) {
        return ban_state() ? 1 : 0;
    }
    return 1;
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
    $env['BAN_STATE'] = $state;
    $env['TEST_OUT'] = $dir . '/out.html';
    $env['TEST_GET'] = json_encode($get);
    $env['TEST_POST'] = json_encode($post);
    if (!empty($get['missing'])) {
        $env['ACCOUNT_MISSING'] = '1';
    }

    $cmd = 'php -d auto_prepend_file= ' . escapeshellarg($dir . '/runner.php');
    $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, $dir, $env);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    $html = file_exists($dir . '/out.html') ? file_get_contents($dir . '/out.html') : '';
    $sqlLog = file_get_contents($log);
    $bannedAfter = trim((string)file_get_contents($state)) === '1';

    assert_true($code === 0, "$name exits 0 (got $code) stderr=" . trim($stderr) . ' stdout=' . trim($stdout));

    return array(
        'html' => $html,
        'sql' => $sqlLog,
        'banned' => $bannedAfter,
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

$open = run_case('open ban confirm', array('mode' => '7', 'action' => '4', 'id' => '15'), array(), false);
assert_true(strpos($open['sql'], 'INSERT INTO TGLOBAL_GSP.dbo.TUSERPROTECTED') === false, 'opening the ban page does not insert a ban');
assert_true(strpos($open['sql'], 'DELETE FROM TGLOBAL_GSP.dbo.TUSERPROTECTED') === false, 'opening the ban page does not delete a ban');
assert_true(strpos($open['html'], 'name="intent" value="ban"') !== false, 'ban confirm posts intent=ban');
assert_true($open['banned'] === false, 'account stays unbanned after opening the confirm page');
cleanup($open['dir']);

$ban = run_case('confirm ban', array('mode' => '7', 'action' => '4', 'id' => '15'), array('intent' => 'ban'), false);
assert_true(strpos($ban['sql'], 'INSERT INTO TGLOBAL_GSP.dbo.TUSERPROTECTED') !== false, 'confirming ban inserts one protection row');
assert_true(strpos($ban['sql'], 'DELETE FROM TGLOBAL_GSP.dbo.TUSERPROTECTED') === false, 'confirming ban does not delete');
assert_true(strpos($ban['html'], 'erfolgreich <b>gebannt</b>') !== false, 'confirming ban says the account was banned');
assert_true($ban['banned'] === true, 'account is banned after confirm');
cleanup($ban['dir']);

$again = run_case('repeat ban', array('mode' => '7', 'action' => '4', 'id' => '15'), array('intent' => 'ban'), true);
assert_true(strpos($again['sql'], 'INSERT INTO TGLOBAL_GSP.dbo.TUSERPROTECTED') === false, 'banning an already banned account does not insert again');
assert_true(strpos($again['sql'], 'DELETE FROM TGLOBAL_GSP.dbo.TUSERPROTECTED') === false, 'submitting ban again does not unban');
assert_true(strpos($again['html'], 'bereits <b>gebannt</b>') !== false, 'repeat ban says the account is already banned');
assert_true($again['banned'] === true, 'account stays banned when ban is submitted twice');
cleanup($again['dir']);

$reload = run_case('reload ban url', array('mode' => '7', 'action' => '4', 'id' => '15'), array(), true);
assert_true(strpos($reload['sql'], 'DELETE FROM TGLOBAL_GSP.dbo.TUSERPROTECTED') === false, 'reloading the ban url does not unban');
assert_true(strpos($reload['html'], 'name="intent" value="unban"') !== false, 'reload shows the unban confirmation');
assert_true($reload['banned'] === true, 'account stays banned after reload');
cleanup($reload['dir']);

$unban = run_case('confirm unban', array('mode' => '7', 'action' => '4', 'id' => '15'), array('intent' => 'unban'), true);
assert_true(strpos($unban['sql'], 'DELETE FROM TGLOBAL_GSP.dbo.TUSERPROTECTED WHERE dwUserID = 15') !== false, 'confirming unban deletes only that account');
assert_true(strpos($unban['html'], 'erfolgreich <b>entbannt</b>') !== false, 'confirming unban says the account was unbanned');
assert_true($unban['banned'] === false, 'account is unbanned after confirm');
cleanup($unban['dir']);

$inject = run_case(
    'injected id',
    array('mode' => '7', 'action' => '4', 'id' => "15' OR '1'='1"),
    array('intent' => 'unban'),
    true
);
assert_true(strpos($inject['sql'], "OR '1'='1") === false, 'a quoted id cannot widen the unban');
assert_true(strpos($inject['sql'], 'dwUserID = 15') !== false, 'a quoted id is used as the integer 15');
assert_true($inject['banned'] === false, 'only the targeted account is unbanned');
cleanup($inject['dir']);

$missing = run_case('missing account', array('mode' => '7', 'action' => '4', 'id' => '404', 'missing' => '1'), array('intent' => 'ban'), false);
assert_true(strpos($missing['sql'], 'INSERT INTO TGLOBAL_GSP.dbo.TUSERPROTECTED') === false, 'missing account is not banned');
assert_true(strpos($missing['html'], 'existiert nicht') !== false, 'missing account shows an error');
cleanup($missing['dir']);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}
fwrite(STDOUT, "all account ban checks passed\n");
exit(0);
