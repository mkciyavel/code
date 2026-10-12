<?php
/**
 * Ticket thread pager (mode 9) must page every unsolved message in the thread.
 * Run: php tests/ticket_page_test.php
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

function thread_rows()
{
    return array(
        array('UserID' => '50', 'username' => 'player', 'text' => 'msg-original', 'date' => '2026-10-01 12:00:00', 'solved' => '0'),
        array('UserID' => '50', 'username' => 'player', 'text' => 'msg-day2', 'date' => '2026-10-02 12:00:00', 'solved' => '0'),
        array('UserID' => '50', 'username' => 'player', 'text' => 'msg-day3', 'date' => '2026-10-03 12:00:00', 'solved' => '0'),
        array('UserID' => '50', 'username' => 'player', 'text' => 'msg-day4', 'date' => '2026-10-04 12:00:00', 'solved' => '0'),
        array('UserID' => '1', 'username' => 'admin', 'text' => 'msg-newest', 'date' => '2026-10-05 12:00:00', 'solved' => '0'),
    );
}

function run_case($name, $get)
{
    global $admin;

    $dir = sys_get_temp_dir() . '/ticketpage_' . bin2hex(random_bytes(4));
    mkdir($dir . '/includes', 0700, true);
    $log = $dir . '/sql.log';
    file_put_contents($log, '');

    $config = <<<'PHP'
<?php
$login = true;
$session_right = 1;
$session_id = 1;
$session_user = 'admin';
$gcon = 'stub';
if (!defined('CMS_SERV_NAME')) {
    define('CMS_SERV_NAME', 'Test');
}
function mysql_real_escape_string($string, $link = null) {
    return (string)$string;
}
function ticket_rows_for($sql) {
    $all = json_decode(getenv('TEST_ROWS'), true);
    if (!is_array($all)) {
        $all = array();
    }
    if (stripos($sql, 'SELECT title') !== false) {
        return array(array('title' => 'Cannot login'));
    }
    $rows = $all;
    if (preg_match("/UserID\\s*=\\s*'([^']*)'/i", $sql, $m)) {
        $want = $m[1];
        $rows = array_values(array_filter($rows, function ($row) use ($want) {
            return (string)$row['UserID'] === $want;
        }));
    }
    if (preg_match("/hash\\s*=\\s*'([^']*)'/i", $sql, $m)) {
        $want = $m[1];
        $rows = array_values(array_filter($rows, function ($row) use ($want) {
            return !isset($row['hash']) || (string)$row['hash'] === $want;
        }));
    }
    if (stripos($sql, 'Solved = 0') !== false) {
        $rows = array_values(array_filter($rows, function ($row) {
            return (string)$row['solved'] === '0';
        }));
    }
    if (stripos($sql, 'ORDER BY date DESC') !== false) {
        usort($rows, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
    }
    if (preg_match('/LIMIT\\s+(\\d+)\\s*,\\s*(\\d+)/i', $sql, $m)) {
        $rows = array_slice($rows, (int)$m[1], (int)$m[2]);
    }
    $out = array();
    foreach ($rows as $row) {
        $out[] = array(
            'username' => $row['username'],
            'text' => $row['text'],
            'date' => $row['date'],
            'solved' => $row['solved'],
            'UserID' => $row['UserID'],
            'title' => 'Cannot login',
        );
    }
    return $out;
}
function mysql_query($sql, $link = null) {
    $log = getenv('SQL_LOG');
    file_put_contents($log, $sql . "\n----\n", FILE_APPEND);
    $key = md5($sql . '|' . uniqid('', true));
    $GLOBALS['tq'][$key] = ticket_rows_for($sql);
    $GLOBALS['ti'][$key] = 0;
    return $key;
}
function mysql_fetch_assoc($result) {
    if (!isset($GLOBALS['tq'][$result])) {
        return false;
    }
    $index = $GLOBALS['ti'][$result];
    if ($index >= count($GLOBALS['tq'][$result])) {
        return false;
    }
    $GLOBALS['ti'][$result] = $index + 1;
    return $GLOBALS['tq'][$result][$index];
}
function mysql_num_rows($result) {
    if (!isset($GLOBALS['tq'][$result])) {
        return 0;
    }
    return count($GLOBALS['tq'][$result]);
}
PHP;
    file_put_contents($dir . '/includes/config.php', $config);
    file_put_contents($dir . '/includes/head.php', "<?php\n");
    file_put_contents($dir . '/includes/foot.php', "<?php\n");
    symlink($admin, $dir . '/admin.php');

    $runner = $dir . '/runner.php';
    file_put_contents($runner, <<<'PHP'
<?php
$_GET = json_decode(getenv('TEST_GET'), true);
$_POST = array();
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
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
    $env['TEST_ROWS'] = json_encode(thread_rows());

    $cmd = 'php -d auto_prepend_file= ' . escapeshellarg($runner);
    $descriptors = array(
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $proc = proc_open($cmd, $descriptors, $pipes, $dir, $env);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    $html = file_exists($dir . '/out.html') ? file_get_contents($dir . '/out.html') : '';
    $sqlLog = file_get_contents($log);

    assert_true($code === 0, "$name exits 0 (got $code) stderr=" . trim($stderr) . ' stdout=' . trim($stdout));

    if (is_dir($dir)) {
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

    return array($html, $sqlLog);
}

list($html, $sqlLog) = run_case('page 1 of a five-message thread', array('mode' => '9', 'h' => 'ticket1'));
assert_true(strpos($html, 'msg-newest') !== false, 'page 1 shows the newest message');
assert_true(strpos($html, 'msg-original') === false, 'page 1 does not show the oldest message');
assert_true(strpos($html, '?p=2&mode=9&h=ticket1') !== false, 'page 1 links to page 2, html pager=' . json_encode(match_pager($html)));
assert_true(strpos($sqlLog, "UserID = '1'") === false, 'thread count is not limited to the current admin, log=' . json_encode($sqlLog));

list($html2, $sqlLog2) = run_case('page 2 of a five-message thread', array('mode' => '9', 'h' => 'ticket1', 'p' => '2'));
assert_true(strpos($html2, 'msg-original') !== false, 'page 2 shows the original player message');
assert_true(strpos($html2, 'msg-newest') === false, 'page 2 does not repeat the newest message');

function match_pager($html)
{
    if (preg_match('/<ul id="pager">.*?<\\/ul>/s', $html, $m)) {
        return $m[0];
    }
    return '';
}

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "all ticket page assertions passed\n");
exit(0);
