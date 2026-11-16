<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
date_default_timezone_set(APP_TIMEZONE);
ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function h(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(): never { header('Location: index.php', true, 303); exit; }
function notice(string $s): void { $_SESSION['notice'] = $s; }
function sec(int $seconds): string { return sprintf('%d:%02d', intdiv(max(0,$seconds),3600), intdiv(max(0,$seconds)%3600,60)); }
function formatLocal(int $t): string { return date('Y-m-d H:i', $t); }
function parseLocal(string $s): int {
    $tz=new DateTimeZone(APP_TIMEZONE);
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',trim($s),$tz);
    if (!$dt || $dt->format('Y-m-d\TH:i')!==trim($s)) throw new RuntimeException('Invalid date/time');
    return $dt->getTimestamp();
}
function localInput(int $t): string { return date('Y-m-d\TH:i',$t); }
function validateTimes(int $start, ?int $end, int $break): void {
    if ($end!==null && $end<=$start) throw new RuntimeException('End must be after start');
    if ($end!==null && ($end-$start)>366*86400) throw new RuntimeException('Session exceeds one year');
    if ($break<0 || $break>525600) throw new RuntimeException('Invalid break length');
    if ($break*60>(($end??time())-$start)) throw new RuntimeException('Break exceeds session length');
}
function projectNames(PDO $db,int $id): array { $st=$db->prepare('SELECT p.name FROM projects p JOIN entry_projects ep ON ep.project_id=p.id WHERE ep.entry_id=? ORDER BY p.name'); $st->execute([$id]); return $st->fetchAll(PDO::FETCH_COLUMN); }
function csvSafe(string $v): string { return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u',$v)?"'".$v:$v; }
function sessionNet(array $e,int $now): int {return max(0,(($e['end_at']===null?$now:(int)$e['end_at'])-(int)$e['start_at'])-(int)$e['break_minutes']*60);}

function checkedIds(PDO $db,int $id): array { $q=$db->prepare('SELECT project_id FROM entry_projects WHERE entry_id = ?'); $q->execute([$id]); return array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)); }
function requireCsrf(): void { if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid request token'); } }
function requireAuth(): void { if (empty($_SESSION['user_id'])) { http_response_code(403); exit('Not signed in'); } }
function requireAdmin(): void { if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Read-only account: action not allowed'); } }
function loadUserRole(PDO $db): void {
    if (empty($_SESSION['user_id'])) return;
    $st=$db->prepare('SELECT u.role, EXISTS(SELECT 1 FROM viewer_invitations i WHERE i.user_id=u.id) AS pending FROM users u WHERE u.id=?');
    $st->execute([(int)$_SESSION['user_id']]);
    $user=$st->fetch();
    if (!$user || ((int)$user['pending']===1 && $user['role']==='viewer')) {
        unset($_SESSION['user_id'],$_SESSION['role'],$_SESSION['remember_expires_at']);
        return;
    }
    $_SESSION['role']=$user['role'];
}
function validUsername(string $username): bool { return (bool)preg_match('/^[A-Za-z0-9_.-]{3,40}$/D', $username); }

// 30-day persistent sign-in: only a random verifier is sent to the browser.
const REMEMBER_COOKIE = 'work_clock_remember';
const REMEMBER_SECONDS = 30 * 86400;
function rememberCookieOptions(int $expires): array {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    return ['expires'=>$expires, 'path'=>rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/', 'secure'=>$https, 'httponly'=>true, 'samesite'=>'Lax'];
}
function clearRememberCookie(): void {
    setcookie(REMEMBER_COOKIE, '', rememberCookieOptions(time()-3600));
    unset($_COOKIE[REMEMBER_COOKIE]);
}
function revokeRememberToken(PDO $db): void {
    $raw = (string)($_COOKIE[REMEMBER_COOKIE] ?? '');
    if (preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/D', $raw, $m)) {
        $db->prepare('DELETE FROM remembered_logins WHERE selector=?')->execute([$m[1]]);
    }
    clearRememberCookie();
}
function createRememberToken(PDO $db, int $userId): void {
    // Revoke any prior remember token on this browser before generating a new one.
    revokeRememberToken($db);
    $selector=bin2hex(random_bytes(16));
    $validator=bin2hex(random_bytes(32));
    $expires=time()+REMEMBER_SECONDS;
    $db->prepare('INSERT INTO remembered_logins (selector,user_id,token_hash,expires_at) VALUES (?,?,?,?)')
       ->execute([$selector,$userId,hash('sha256',$validator),$expires]);
    setcookie(REMEMBER_COOKIE,$selector.':'.$validator,rememberCookieOptions($expires));
    $_COOKIE[REMEMBER_COOKIE]=$selector.':'.$validator;
    $_SESSION['remember_expires_at']=$expires;
}
function restoreRememberedLogin(PDO $db): void {
    if (!empty($_SESSION['user_id'])) return;
    $raw=(string)($_COOKIE[REMEMBER_COOKIE] ?? '');
    if ($raw==='') return;
    if (!preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/D',$raw,$m)) { clearRememberCookie(); return; }
    $st=$db->prepare('SELECT user_id,token_hash,expires_at FROM remembered_logins WHERE selector=?');
    $st->execute([$m[1]]); $token=$st->fetch();
    if (!$token || (int)$token['expires_at']<=time() || !hash_equals($token['token_hash'],hash('sha256',$m[2]))) {
        // Don't delete a valid token on an incorrect validator: prevents easy logout attacks.
        clearRememberCookie(); return;
    }
    session_regenerate_id(true);
    $_SESSION['user_id']=(int)$token['user_id'];
    $_SESSION['remember_expires_at']=(int)$token['expires_at'];
    $_SESSION['csrf']=bin2hex(random_bytes(32));
}

function selectedProjects(PDO $db, array $input, string $new): array {
    $ids = array_values(array_unique(array_filter(array_map('intval',$input),fn($v)=>$v>0)));
    if ($ids) {
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $st=$db->prepare("SELECT id FROM projects WHERE id IN ($placeholders)"); $st->execute($ids);
        if (count($st->fetchAll(PDO::FETCH_COLUMN)) !== count($ids)) throw new RuntimeException('Invalid project');
    }
    $new=trim($new);
    if ($new !== '') {
        if (mb_strlen($new)>80) throw new RuntimeException('Project name is too long');
        $st=$db->prepare('INSERT OR IGNORE INTO projects(name) VALUES(?)'); $st->execute([$new]);
        $st=$db->prepare('SELECT id FROM projects WHERE name=?'); $st->execute([$new]);
        $ids[]=(int)$st->fetchColumn();
    }
    return array_values(array_unique($ids));
}
function updateProjects(PDO $db,int $entryId,array $ids): void {
    $st=$db->prepare('DELETE FROM entry_projects WHERE entry_id=?'); $st->execute([$entryId]);
    $st=$db->prepare('INSERT INTO entry_projects(entry_id,project_id) VALUES(?,?)');
    foreach($ids as $p) $st->execute([$entryId,$p]);
}
try {
    $db = new PDO('sqlite:' . __DIR__ . '/storage/clock.sqlite', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT=>5]);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL)');
    $userCols=$db->query('PRAGMA table_info(users)')->fetchAll();
    if (!in_array('role',array_column($userCols,'name'),true)) {
        $db->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'admin'");
    }
    $db->exec('CREATE TABLE IF NOT EXISTS viewer_invitations (user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, token_hash TEXT NOT NULL, expires_at INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS projects (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE)');
    $db->exec('CREATE TABLE IF NOT EXISTS entries (id INTEGER PRIMARY KEY, start_at INTEGER NOT NULL, end_at INTEGER, description TEXT NOT NULL DEFAULT \'\', break_minutes INTEGER NOT NULL DEFAULT 0)');
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS one_running ON entries ((1)) WHERE end_at IS NULL');
    $db->exec('CREATE TABLE IF NOT EXISTS entry_projects (entry_id INTEGER NOT NULL REFERENCES entries(id) ON DELETE CASCADE, project_id INTEGER NOT NULL REFERENCES projects(id), PRIMARY KEY(entry_id,project_id))');
    $db->exec('CREATE INDEX IF NOT EXISTS entries_start_idx ON entries(start_at)');
    $db->exec('CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT NOT NULL, attempted_at INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS remembered_logins (selector TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, token_hash TEXT NOT NULL, expires_at INTEGER NOT NULL)');
    $db->exec('DELETE FROM remembered_logins WHERE expires_at < ' . time());
} catch(Throwable $e) { http_response_code(500); exit('Cannot open database. Check that the storage/ directory is writable and PDO SQLite is installed.'); }
// A remembered session also ends after 30 days, even if the PHP session survives.
if (isset($_SESSION['remember_expires_at']) && (int)$_SESSION['remember_expires_at'] <= time()) {
    unset($_SESSION['user_id'], $_SESSION['remember_expires_at']);
    clearRememberCookie();
    session_regenerate_id(true);
    $_SESSION['csrf']=bin2hex(random_bytes(32));
}
restoreRememberedLogin($db);
loadUserRole($db);
$setup = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===0;
$error = '';
$invitationUrl = '';
$inviteToken=(string)($_GET['invite']??'');
$invitedUser=null;
if ($inviteToken !== '' && preg_match('/^[a-f0-9]{64}$/D',$inviteToken)) {
    $st=$db->prepare('SELECT u.id,u.username FROM viewer_invitations i JOIN users u ON u.id=i.user_id WHERE i.token_hash=? AND i.expires_at>? AND u.role=?');
    $st->execute([hash('sha256',$inviteToken),time(),'viewer']);
    $invitedUser=$st->fetch()?:null;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    requireCsrf();
    try {
        $action=(string)($_POST['action']??'');
        if ($action==='setup' && $setup) {
            $code=(string)($_POST['setup_code']??'');
            $username=trim((string)($_POST['username']??''));
            $password=(string)($_POST['password']??'');
            if (SETUP_CODE==='REPLACE_WITH_A_LONG_RANDOM_SETUP_CODE' || strlen(SETUP_CODE)<20) throw new RuntimeException('First change SETUP_CODE in config.php to a unique secret of at least 20 characters.');
            if (!hash_equals(SETUP_CODE,$code)) throw new RuntimeException('Incorrect setup code');
            if (!validUsername($username)) throw new RuntimeException('Username: 3–40 letters, digits, . _ or -');
            if (strlen($password)<12) throw new RuntimeException('Use a password of at least 12 characters');
            $st=$db->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,?,'admin')"); $st->execute([$username,password_hash($password,PASSWORD_DEFAULT)]);
            notice('Account created. Sign in below.'); redirect();
        }
        if ($action==='login' && !$setup) {
            $ip=substr((string)($_SERVER['REMOTE_ADDR']??'unknown'),0,64);
            $db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([time()-900]);
            $st=$db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip=?'); $st->execute([$ip]);
            if ((int)$st->fetchColumn()>=10) throw new RuntimeException('Too many attempts. Try again in 15 minutes.');
            $st=$db->prepare('SELECT * FROM users WHERE username=?'); $st->execute([(string)($_POST['username']??'')]); $user=$st->fetch();
            if (!$user || !password_verify((string)($_POST['password']??''),$user['password_hash']) || ($user['role']==='viewer' && (function() use ($db, $user): bool { $s=$db->prepare('SELECT 1 FROM viewer_invitations WHERE user_id=?'); $s->execute([(int)$user['id']]); return (bool)$s->fetchColumn(); })())) {
                $db->prepare('INSERT INTO login_attempts(ip,attempted_at) VALUES(?,?)')->execute([$ip,time()]);
                throw new RuntimeException('Invalid username or password');
            }
            $db->prepare('DELETE FROM login_attempts WHERE ip=?')->execute([$ip]);
            session_regenerate_id(true); $_SESSION['user_id']=(int)$user['id']; $_SESSION['role']=$user['role']; $_SESSION['csrf']=bin2hex(random_bytes(32));
            if (isset($_POST['remember_me'])) createRememberToken($db,(int)$user['id']);
            else { unset($_SESSION['remember_expires_at']); revokeRememberToken($db); }
            redirect();
        }
        if ($action==='logout') { revokeRememberToken($db); $_SESSION=[]; session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32)); redirect(); }
        if ($action==='accept_invite' && !$setup) {
            $token=(string)($_POST['invite_token']??'');
            $password=(string)($_POST['password']??'');
            $confirm=(string)($_POST['password_confirm']??'');
            if (!preg_match('/^[a-f0-9]{64}$/D',$token)) throw new RuntimeException('Invalid invitation');
            if (strlen($password)<12) throw new RuntimeException('Use a password of at least 12 characters');
            if ($password!==$confirm) throw new RuntimeException('Passwords do not match');
            $db->beginTransaction();
            $st=$db->prepare('SELECT u.id FROM viewer_invitations i JOIN users u ON u.id=i.user_id WHERE i.token_hash=? AND i.expires_at>? AND u.role=?');
            $st->execute([hash('sha256',$token),time(),'viewer']);
            $id=$st->fetchColumn();
            if (!$id) throw new RuntimeException('Invitation invalid or expired');
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),(int)$id]);
            $db->prepare('DELETE FROM viewer_invitations WHERE user_id=?')->execute([(int)$id]);
            $db->commit();
            // Revoke a previously remembered identity on this browser, if any.
            revokeRememberToken($db);
            session_regenerate_id(true);
            $_SESSION['user_id']=(int)$id; $_SESSION['role']='viewer';
            $_SESSION['csrf']=bin2hex(random_bytes(32));
            if (isset($_POST['remember_me'])) createRememberToken($db,(int)$id);
            notice('Password set. Welcome!'); redirect();
        }
        requireAuth();
        if ($action==='create_viewer') {
            requireAdmin();
            $username=trim((string)($_POST['viewer_username']??''));
            if (!validUsername($username)) throw new RuntimeException('Username: 3–40 letters, digits, . _ or -');
            $token=bin2hex(random_bytes(32));
            $db->beginTransaction();
            // A random unusable password prevents login until the invite is accepted.
            $st=$db->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,?,'viewer')");
            $st->execute([$username,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);
            $uid=(int)$db->lastInsertId();
            $db->prepare('INSERT INTO viewer_invitations(user_id,token_hash,expires_at) VALUES(?,?,?)')->execute([$uid,hash('sha256',$token),time()+7*86400]);
            $db->commit();
            $url=($_SERVER['HTTPS']??'')==='on' || ($_SERVER['HTTPS']??'')==='1' ? 'https' : 'http';
            $host=(string)($_SERVER['HTTP_HOST']??'localhost');
            // Only emit safe hostnames; don't let an attacker alter invitation domains via Host header.
            if (!preg_match('/^[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$/D',$host)) $host='localhost';
            $path=(string)($_SERVER['SCRIPT_NAME']??'/index.php');
            $invitationUrl=$url.'://'.$host.$path.'?invite='.$token;
            notice('Viewer created. Copy the invitation link now; it will not be displayed again.');
        } elseif ($action==='reinvite_viewer') {
            requireAdmin();
            $uid=filter_var($_POST['viewer_id']??null,FILTER_VALIDATE_INT);
            $st=$db->prepare('SELECT id FROM users WHERE id=? AND role=?');
            $st->execute([$uid,'viewer']);
            if (!$st->fetchColumn()) throw new RuntimeException('Viewer not found');
            $token=bin2hex(random_bytes(32));
            $db->beginTransaction();
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),(int)$uid]);
            $db->prepare('DELETE FROM remembered_logins WHERE user_id=?')->execute([(int)$uid]);
            $db->prepare('INSERT INTO viewer_invitations(user_id,token_hash,expires_at) VALUES(?,?,?) ON CONFLICT(user_id) DO UPDATE SET token_hash=excluded.token_hash,expires_at=excluded.expires_at')->execute([(int)$uid,hash('sha256',$token),time()+7*86400]);
            $db->commit();
            $url=($_SERVER['HTTPS']??'')==='on' || ($_SERVER['HTTPS']??'')==='1' ? 'https' : 'http';
            $host=(string)($_SERVER['HTTP_HOST']??'localhost');
            if (!preg_match('/^[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$/D',$host)) $host='localhost';
            $invitationUrl=$url.'://'.$host.(string)($_SERVER['SCRIPT_NAME']??'/index.php').'?invite='.$token;
            notice('New invitation created. The previous password and login tokens are invalidated until the new invitation is accepted.');
        } elseif ($action==='delete_viewer') {
            requireAdmin();
            $uid=filter_var($_POST['viewer_id']??null,FILTER_VALIDATE_INT);
            $st=$db->prepare('DELETE FROM users WHERE id=? AND role=?');
            $st->execute([$uid,'viewer']);
            notice('Viewer removed. Their remembered logins were revoked.'); redirect();
        } else {
            requireAdmin();
        }
        if (in_array($action,['create_viewer','reinvite_viewer'],true)) {
            // Render invitation link below without redirecting.
        } elseif ($action==='in') {
            $st=$db->prepare('INSERT INTO entries(start_at) VALUES(?)'); $st->execute([time()]); notice('Clocked in.'); redirect();
        }
        if ($action==='out') {
            $st=$db->query('SELECT * FROM entries WHERE end_at IS NULL LIMIT 1');
            $active=$st->fetch();
            if (!$active) throw new RuntimeException('No running session');
            $finishedAt=time(); // The clock stops when the form is submitted.
            $break=filter_var($_POST['break_minutes']??null,FILTER_VALIDATE_INT);
            if ($break===false) throw new RuntimeException('Invalid break length');
            validateTimes((int)$active['start_at'],$finishedAt,$break);
            $description=trim((string)($_POST['description']??''));
            if (mb_strlen($description)>3000) throw new RuntimeException('Description too long');
            $input=$_POST['projects']??[];
            if (!is_array($input)) throw new RuntimeException('Invalid project selection');
            $db->beginTransaction();
            $ids=selectedProjects($db,$input,(string)($_POST['new_project']??''));
            $st=$db->prepare('UPDATE entries SET end_at=?,break_minutes=?,description=? WHERE id=? AND end_at IS NULL');
            $st->execute([$finishedAt,$break,$description,(int)$active['id']]);
            if ($st->rowCount()!==1) throw new RuntimeException('Session already completed');
            updateProjects($db,(int)$active['id'],$ids);
            $db->commit();
            notice('Clocked out. Entry saved.'); redirect();
        }
        if ($action==='delete') {
            $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
            if (!$id) throw new RuntimeException('Invalid entry');
            $db->prepare('DELETE FROM entries WHERE id=?')->execute([$id]); notice('Entry deleted.'); redirect();
        }
        if ($action==='create' || $action==='save') {
            $id=$action==='save'?filter_var($_POST['id']??null,FILTER_VALIDATE_INT):null;
            if ($action==='save' && !$id) throw new RuntimeException('Invalid entry');
            if ($action==='save') { $st=$db->prepare('SELECT * FROM entries WHERE id=?'); $st->execute([$id]); $old=$st->fetch(); if (!$old) throw new RuntimeException('Entry not found'); }
            $start=parseLocal((string)($_POST['start_at']??''));
            $endString=trim((string)($_POST['end_at']??''));
            $end=$endString===''?null:parseLocal($endString);
            if ($action==='create' && $end===null) throw new RuntimeException('Manual entries must have an end time');
            if ($action==='save' && $old['end_at']!==null && $end===null) throw new RuntimeException('Completed sessions require an end time');
            $break=filter_var($_POST['break_minutes']??null,FILTER_VALIDATE_INT);
            if ($break===false) throw new RuntimeException('Invalid break length');
            validateTimes($start,$end,$break);
            $description=trim((string)($_POST['description']??''));
            if (mb_strlen($description)>3000) throw new RuntimeException('Description too long');
            $input=$_POST['projects']??[]; if (!is_array($input)) throw new RuntimeException('Invalid project selection');
            $db->beginTransaction();
            $ids=selectedProjects($db,$input,(string)($_POST['new_project']??''));
            if ($action==='create') {
                $st=$db->prepare('INSERT INTO entries(start_at,end_at,break_minutes,description) VALUES(?,?,?,?)');
                $st->execute([$start,$end,$break,$description]); $id=(int)$db->lastInsertId();
            } else {
                $st=$db->prepare('UPDATE entries SET start_at=?,end_at=?,break_minutes=?,description=? WHERE id=?');
                $st->execute([$start,$end,$break,$description,$id]);
            }
            updateProjects($db,(int)$id,$ids); $db->commit(); notice('Entry saved.'); redirect();
        }
        if ($action==='import') {
            if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name']) || $_FILES['csv']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Upload failed');
            if ($_FILES['csv']['size']>2_000_000) throw new RuntimeException('CSV must be under 2 MB');
            $f=fopen($_FILES['csv']['tmp_name'],'r'); if (!$f) throw new RuntimeException('Cannot read CSV');
            $headers=fgetcsv($f,0,',','"','');
            if (!$headers) throw new RuntimeException('Empty CSV');
            $headers=array_map(fn($s)=>strtolower(trim(preg_replace('/^\xEF\xBB\xBF/','',$s))),$headers);
            $index=array_flip($headers);
            // Accept this app's exported CSV only. Import is atomic and does not delete existing rows.
            foreach(['start (vienna)','end (vienna)','break minutes','projects','description'] as $key) if (!isset($index[$key])) throw new RuntimeException('Unsupported CSV format. Use a CSV exported by Work Clock.');
            $count=0; $db->beginTransaction();
            while (($line=fgetcsv($f,0,',','"',''))!==false) {
                if (count($line)===1 && trim((string)$line[0])==='') continue;
                if (++$count>5000) throw new RuntimeException('Import limited to 5000 entries');
                $startRaw=trim((string)($line[$index['start (vienna)']]??''));
                $endRaw=trim((string)($line[$index['end (vienna)']]??''));
                $parse=function(string $raw): int { $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$raw,new DateTimeZone(APP_TIMEZONE)); if (!$d || $d->format('Y-m-d H:i')!==$raw) throw new RuntimeException('Invalid CSV timestamp'); return $d->getTimestamp(); };
                $start=$parse($startRaw); $end=$endRaw===''?null:$parse($endRaw);
                if ($end===null) throw new RuntimeException('Import running entries only after clocking out');
                $breakRaw=(string)($line[$index['break minutes']]??'0');
                if (!preg_match('/^\d+$/',$breakRaw)) throw new RuntimeException('Invalid CSV break');
                $break=(int)$breakRaw; validateTimes($start,$end,$break);
                $desc=(string)($line[$index['description']]??'');
                if (mb_strlen($desc)>3000) throw new RuntimeException('CSV description too long');
                $st=$db->prepare('INSERT INTO entries(start_at,end_at,description,break_minutes) VALUES(?,?,?,?)'); $st->execute([$start,$end,$desc,$break]);
                $eid=(int)$db->lastInsertId();
                $names=trim((string)($line[$index['projects']]??''));
                foreach($names===''?[]:explode('; ',$names) as $name) {
                    $name=trim($name); if ($name==='') continue;
                    $ids=selectedProjects($db,[],$name);
                    $db->prepare('INSERT OR IGNORE INTO entry_projects(entry_id,project_id) VALUES(?,?)')->execute([$eid,$ids[0]]);
                }
            }
            fclose($f); $db->commit(); notice("Imported $count entries. Imports add rows; duplicates are not automatically detected."); redirect();
        }
        if ($action==='export') {
            // Handled by GET below so CSV downloading is easy to bookmark.
            redirect();
        }
        if (!in_array($action,['create_viewer','reinvite_viewer'],true)) throw new RuntimeException('Unknown action');
    } catch(Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error=$e instanceof PDOException ? 'Database operation failed' : $e->getMessage();
    }
}
$authenticated=!empty($_SESSION['user_id']);
$isAdmin=$authenticated && ($_SESSION['role']??'')==='admin';
$viewers=$isAdmin?$db->query("SELECT u.id,u.username,CASE WHEN i.user_id IS NOT NULL THEN 1 ELSE 0 END AS pending, i.expires_at AS invite_expires FROM users u LEFT JOIN viewer_invitations i ON u.id=i.user_id WHERE u.role='viewer' ORDER BY u.username")->fetchAll():[];
if ($authenticated && ($_GET['download']??'')==='backup') {
    // VACUUM INTO produces a consistent standalone database snapshot.
    $tmp=tempnam(sys_get_temp_dir(),'clock_backup_');
    if ($tmp===false) {http_response_code(500);exit('Backup failed');}
    unlink($tmp); $db->exec('VACUUM INTO '.$db->quote($tmp));
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="work-clock-backup-'.date('Y-m-d').'.sqlite"');
    header('Content-Length: '.filesize($tmp)); readfile($tmp); unlink($tmp); exit;
}
if ($authenticated && ($_GET['download']??'')==='csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="work-clock-'.date('Y-m-d').'.csv"');
    $out=fopen('php://output','w');
    fputcsv($out,['Start (Vienna)','End (Vienna)','Elapsed hours','Break minutes','Net hours','Projects','Description']);
    $rows=$db->query("SELECT e.*, GROUP_CONCAT(p.name, '; ') AS project_names FROM entries e LEFT JOIN entry_projects ep ON ep.entry_id=e.id LEFT JOIN projects p ON p.id=ep.project_id GROUP BY e.id ORDER BY e.start_at")->fetchAll();
    foreach($rows as $e) {
        $end=$e['end_at']===null?null:(int)$e['end_at'];
        $elapsed=($end??time())-(int)$e['start_at']; $net=max(0,$elapsed-(int)$e['break_minutes']*60);
        $fields=[formatLocal((int)$e['start_at']),$end===null?'':formatLocal($end),round($elapsed/3600,3),(int)$e['break_minutes'],round($net/3600,3),$e['project_names']??'',$e['description']];
        // Prevent spreadsheet software interpreting arbitrary user text as a formula.
        foreach([5,6] as $i) $fields[$i]=csvSafe((string)$fields[$i]);
        fputcsv($out,$fields);
    }
    fclose($out); exit;
}
$projects=$authenticated?$db->query('SELECT * FROM projects ORDER BY name COLLATE NOCASE')->fetchAll():[];
$dateFilter=(string)($_GET['date']??'');
if ($dateFilter!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$dateFilter)) $dateFilter='';
$entries=[];
if ($authenticated) {
    $q="SELECT e.*, GROUP_CONCAT(p.name, ', ') AS project_names FROM entries e LEFT JOIN entry_projects ep ON ep.entry_id=e.id LEFT JOIN projects p ON p.id=ep.project_id";
    if ($dateFilter!=='') { $start=(new DateTimeImmutable($dateFilter,new DateTimeZone(APP_TIMEZONE)))->getTimestamp(); $q.=' WHERE e.start_at >= '.(int)$start.' AND e.start_at < '.(int)((new DateTimeImmutable($dateFilter,new DateTimeZone(APP_TIMEZONE)))->modify('+1 day')->getTimestamp()); }
    $entries=$db->query($q.' GROUP BY e.id ORDER BY e.start_at DESC LIMIT 250')->fetchAll();
}
$allEntries=$authenticated?$db->query('SELECT * FROM entries ORDER BY start_at DESC')->fetchAll():[];
$now=time(); $dayStart=(new DateTimeImmutable('today',new DateTimeZone(APP_TIMEZONE)))->getTimestamp();
$weekStart=(new DateTimeImmutable('monday this week',new DateTimeZone(APP_TIMEZONE)))->setTime(0,0)->getTimestamp();
$daySeconds=0;$weekSeconds=0;
foreach($allEntries as $e){ $end=$e['end_at']===null?$now:(int)$e['end_at']; $gross=max(0,$end-(int)$e['start_at']); $net=sessionNet($e,$now); if ((int)$e['start_at'] >= $dayStart) $daySeconds+=$net; if ((int)$e['start_at'] >= $weekStart) $weekSeconds+=$net; }
$projectTotals=[];
if($authenticated){ foreach($db->query('SELECT ep.project_id,p.name,e.* FROM entry_projects ep JOIN projects p ON p.id=ep.project_id JOIN entries e ON e.id=ep.entry_id') as $pe){ $name=$pe['name'];$projectTotals[$name]=($projectTotals[$name]??0)+sessionNet($pe,$now); } arsort($projectTotals); }

$running=$authenticated?($db->query('SELECT * FROM entries WHERE end_at IS NULL LIMIT 1')->fetch()?:null):null;
$notice=$_SESSION['notice']??null; unset($_SESSION['notice']);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Work Clock</title>
<style>
:root{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color-scheme:light dark;font-size:16px}*{box-sizing:border-box}body{max-width:760px;margin:28px auto;padding:0 16px;color:CanvasText;background:Canvas}h1{font-size:1.5rem}h2{font-size:1.1rem;margin:28px 0 10px}p{line-height:1.45}small,.muted{color:#666}button,.button{font:inherit;padding:11px 15px;background:#222;color:white;border:1px solid #222;border-radius:5px;cursor:pointer;text-decoration:none;display:inline-block}button.secondary,.button.secondary{background:Canvas;color:CanvasText;border-color:#888}input[type=text],input[type=password],input[type=number],input[type=datetime-local],input[type=date],input[type=file],textarea{font:inherit;padding:10px;border:1px solid #888;border-radius:4px;width:100%;max-width:100%}textarea{min-height:64px}label{display:block;margin:12px 0 5px}fieldset{border:1px solid color-mix(in srgb,CanvasText 22%,Canvas);border-radius:5px;margin:14px 0;padding:10px}fieldset label{display:inline-flex;align-items:center;gap:5px;margin:5px 12px 5px 0}input[type=checkbox]{width:18px;height:18px}.panel{border:1px solid color-mix(in srgb,CanvasText 22%,Canvas);border-radius:6px;padding:16px;margin:14px 0}.status{font-size:1.2rem;font-weight:650}.ok{color:#17854a}@media(prefers-color-scheme:dark){.ok{color:#6fdf9b}.muted,small,footer{color:#aaa}button,.button{background:#ddd;color:#111;border-color:#ddd}}.alert{color:#ad2525}.row{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}.entry{border-top:1px solid color-mix(in srgb,CanvasText 22%,Canvas);padding:12px 0}.entry summary{cursor:pointer;line-height:1.5}.entry form{padding:10px 0 4px}details{margin-top:4px}.message{padding:10px;background:color-mix(in srgb,CanvasText 7%,Canvas);border-radius:5px}table{border-collapse:collapse;width:100%}td{padding:5px 0}footer{padding:22px 0;color:#666;font-size:.85rem}@media(max-width:520px){body{margin:12px auto}button,.button{min-height:46px}.row{align-items:flex-start}.panel{padding:12px}}
</style></head><body>
<header class="row"><h1>Work Clock</h1><?php if($authenticated): ?><?php if(!$isAdmin): ?><small>Read-only viewer</small><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><button class="secondary" name="action" value="logout">Log out</button></form><?php endif; ?></header>
<?php if ($notice): ?><p class="message"><?=h($notice)?></p><?php endif; ?>
<?php if ($error): ?><p class="message alert"><?=h($error)?></p><?php endif; ?>
<?php if ($setup): ?>
<h2>First-time setup</h2><p>Set a unique <code>SETUP_CODE</code> in <code>config.php</code>, then create your account.</p>
<form method="post" class="panel"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="setup">
<label>Setup code<input type="password" name="setup_code" required></label><label>Username<input name="username" type="text" autocomplete="username" required></label><label>Password (at least 12 characters)<input type="password" name="password" autocomplete="new-password" minlength="12" required></label><p><button>Create account</button></p></form>
<?php elseif ($invitedUser): ?>
<h2>Set your password</h2>
<p>Welcome, <strong><?=h($invitedUser['username'])?></strong>. Choose a password to activate your read-only account. This link works once and expires 7 days after it was issued.</p>
<form method="post" class="panel"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="accept_invite"><input type="hidden" name="invite_token" value="<?=h($inviteToken)?>">
<label>Password (12+ characters)<input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
<label>Confirm password<input type="password" name="password_confirm" autocomplete="new-password" minlength="12" required></label>
<label><input type="checkbox" name="remember_me" value="1" checked> Keep me signed in for 30 days</label>
<p><button>Activate viewer account</button></p></form>
<?php elseif (!$authenticated): ?>
<?php if ($inviteToken!==''): ?><p class="message alert">Invitation invalid or expired. Ask the administrator for a new invitation.</p><?php endif; ?>
<h2>Sign in</h2><form method="post" class="panel"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="login">
<label>Username<input type="text" name="username" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><label class="row" style="gap:8px;align-items:center"><input type="checkbox" name="remember_me" value="1" checked style="width:auto;margin:0"> Keep me signed in for 30 days</label><p><button>Sign in</button></p></form>
<?php else: ?>
<section class="panel">
<p class="status <?= $running?'ok':'' ?>"><?= $running ? '● Clocked in' : '○ Not clocked in' ?></p>
<?php if ($running): ?><p>Since <?=h(formatLocal((int)$running['start_at']))?> · Elapsed <span id="elapsed" data-start="<?=(int)$running['start_at']?>"><?=h(sec(time()-(int)$running['start_at']))?></span> h</p>
<?php if ($isAdmin && (($_GET['finish']??'')==='1' || (($_POST['action']??'')==='out' && $error))): ?>
<form method="post" class="panel" style="margin-top:14px"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="out">
<h2 style="margin-top:0">Finish session</h2>
<p class="muted">Your clock is still running. It stops when you save this form.</p>
<label>Break length (minutes)<input type="number" name="break_minutes" min="0" max="525600" step="1" value="<?=h((string)($_POST['break_minutes']??$running['break_minutes']??0))?>" required></label>
<label>Description / comment<textarea name="description" maxlength="3000" placeholder="What did you work on?"><?=h((string)($_POST['description']??$running['description']??''))?></textarea></label>
<fieldset><legend>Projects (select any number)</legend>
<?php $outSelected = isset($_POST['projects']) && is_array($_POST['projects']) ? array_map('intval',$_POST['projects']) : checkedIds($db,(int)$running['id']); ?>
<?php foreach($projects as $p): ?><label><input type="checkbox" name="projects[]" value="<?=(int)$p['id']?>" <?=in_array((int)$p['id'],$outSelected,true)?'checked':''?>><?=h($p['name'])?></label><?php endforeach; ?>
</fieldset>
<label>New project (optional)<input type="text" name="new_project" maxlength="80" value="<?=h((string)($_POST['new_project']??''))?>" placeholder="Create and assign a project"></label>
<div class="row"><button type="submit">Save &amp; clock out</button><a class="button secondary" href="index.php">Cancel</a></div>
</form>
<?php elseif ($isAdmin): ?><p><a class="button" href="?finish=1">Clock out</a></p><?php endif; ?>
<?php elseif ($isAdmin): ?><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><button name="action" value="in">Clock in</button></form><?php endif; ?>
</section>
<div class="panel"><div class="row"><div><small>Today</small><p><strong><?=h(sec($daySeconds))?> h</strong></p></div><div><small>This week (30h target)</small><p><strong><?=h(sec($weekSeconds))?> / 30:00 h</strong></p></div><div><small>Weekly balance</small><p><strong><?= $weekSeconds>=108000?'+':'−' ?><?=h(sec(abs($weekSeconds-108000)))?> h</strong></p></div></div><small>Totals group sessions by their start date, including overnight sessions.</small></div>
<?php if ($isAdmin): ?><details class="panel"><summary><strong>Add a manual session</strong></summary>
<form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="create">
<label>Clock in<input type="datetime-local" name="start_at" value="<?=h(localInput(time()-3600))?>" required></label>
<label>Clock out<input type="datetime-local" name="end_at" value="<?=h(localInput(time()))?>" required></label>
<label>Break minutes<input type="number" name="break_minutes" min="0" value="0" required></label>
<label>Description<textarea name="description" maxlength="3000"></textarea></label>
<fieldset><legend>Projects</legend><?php foreach($projects as $p): ?><label><input type="checkbox" name="projects[]" value="<?=(int)$p['id']?>"><?=h($p['name'])?></label><?php endforeach;?></fieldset>
<label>New project<input name="new_project" maxlength="80"></label><button>Add session</button></form></details>
<?php endif; ?>
<div class="row"><h2>History</h2><a class="button secondary" href="?download=csv">Export CSV</a></div>
<form method="get" class="row"><label>Filter by start date<input type="date" name="date" value="<?=h($dateFilter)?>"></label><button class="secondary">Filter</button><a href="index.php">All dates</a></form>
<p class="muted">Edit timestamps, breaks, descriptions and projects by opening an entry. Up to 250 entries displayed; CSV exports all.</p>
<?php if(!$entries): ?><p>No entries yet.</p><?php endif; ?>
<?php foreach($entries as $e): $id=(int)$e['id']; $isRunning=$e['end_at']===null; $end=$isRunning?time():(int)$e['end_at']; $elapsed=max(0,$end-(int)$e['start_at']); $net=max(0,$elapsed-((int)$e['break_minutes']*60)); $checked=checkedIds($db,$id); ?>
<details class="entry" <?= ($running && $id===(int)$running['id'])?'open':'' ?>><summary><strong><?=h(date('D, d M', (int)$e['start_at']))?></strong> · <?=h(date('H:i',(int)$e['start_at']))?>–<?= $isRunning?'now':h(date('H:i',$end)) ?> · <?=h(sec($net))?> h net<?= $isRunning?' (running)':'' ?><br><small><?=h($e['project_names']?:'No project')?><?= $e['description']?' · '.h(mb_strimwidth($e['description'],0,90,'…')):'' ?></small></summary>
<?php if (!$isAdmin): ?>
<p><strong>Started:</strong> <?=h(formatLocal((int)$e['start_at']))?></p>
<p><strong>Ended:</strong> <?=$isRunning?'Currently running':h(formatLocal((int)$e['end_at']))?></p>
<p><strong>Break:</strong> <?=(int)$e['break_minutes']?> minutes</p>
<p><strong>Projects:</strong> <?=h($e['project_names']?:'None')?></p>
<p><strong>Description:</strong> <?=nl2br(h($e['description']?:'—'))?></p>
<?php else: ?>
<form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$id?>">
<label>Clock in<input type="datetime-local" name="start_at" value="<?=h(localInput((int)$e['start_at']))?>" required></label>
<label>Clock out <?php if($isRunning): ?><small>(leave blank while running)</small><?php endif; ?><input type="datetime-local" name="end_at" value="<?= $isRunning?'':h(localInput((int)$e['end_at'])) ?>"></label>
<label>Description / comment<textarea name="description" maxlength="3000" placeholder="What did you work on?"><?=h($e['description'])?></textarea></label>
<fieldset><legend>Projects (select any number)</legend><?php if(!$projects): ?><p class="muted">No projects yet. Add one below.</p><?php endif; ?>
<?php foreach($projects as $p): ?><label><input type="checkbox" name="projects[]" value="<?= (int)$p['id'] ?>" <?=in_array((int)$p['id'],$checked,true)?'checked':''?>><?=h($p['name'])?></label><?php endforeach; ?></fieldset>
<label>New project (optional)<input name="new_project" type="text" maxlength="80" placeholder="Create and assign a project"></label>
<label>Break length (minutes)<input name="break_minutes" type="number" min="0" max="1440" step="1" value="<?=(int)$e['break_minutes']?>" required></label>
<p><button>Save entry</button></p></form>
<form method="post" onsubmit="return confirm('Delete this session permanently?')"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$id?>"><button class="secondary">Delete entry</button></form><?php endif; ?></details>
<?php endforeach; ?>
<details class="panel"><summary><strong>Project totals (all time)</strong></summary>
<?php if(!$projectTotals):?><p>No assigned projects.</p><?php endif;?>
<?php foreach($projectTotals as $name=>$seconds):?><p><?=h($name)?>: <strong><?=h(sec($seconds))?> h</strong></p><?php endforeach;?><small>When a session has multiple projects, its full duration counts toward each project; project totals may exceed working hours.</small></details>
<details class="panel"><summary><strong>Import / backup</strong></summary>
<p><a class="button secondary" href="?download=backup">Download SQLite backup</a></p>
<?php if ($isAdmin): ?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="import"><label>Import a Work Clock CSV<input type="file" name="csv" accept=".csv,text/csv" required></label><p><small>CSV imports add records; they do not replace existing entries or detect duplicates. Back up first. Max 2 MB / 5000 rows.</small></p><button>Import CSV</button></form><?php endif; ?></details>
<?php if ($isAdmin): ?>
<details class="panel" <?= $invitationUrl!==''?'open':'' ?>><summary><strong>Viewer accounts</strong></summary>
<p class="muted">Viewers can access all timesheets, comments, projects, CSV exports and database backups. They cannot change anything.</p>
<?php if ($invitationUrl!==''): ?><div class="message"><strong>Copy this one-time invitation link now:</strong><label>Invitation URL<input type="text" readonly value="<?=h($invitationUrl)?>" onclick="this.select()"></label><small>Valid for 7 days; the viewer sets their own password. This link is not stored in plain text.</small></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="create_viewer">
<label>New viewer username<input name="viewer_username" type="text" required minlength="3" maxlength="40" placeholder="e.g. supervisor"></label><p><button>Create viewer &amp; invitation link</button></p></form>
<?php foreach($viewers as $v): ?><div class="entry"><div class="row"><strong><?=h($v['username'])?></strong><small><?= $v['pending']?((int)$v['invite_expires']<time()?'Invite expired':'Invite pending'):'Active' ?></small></div>
<form method="post" style="display:inline-block;margin:6px 6px 0 0"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="viewer_id" value="<?=(int)$v['id']?>"><button class="secondary" name="action" value="reinvite_viewer">New setup link</button></form>
<form method="post" style="display:inline-block" onsubmit="return confirm('Remove this viewer account?')"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="viewer_id" value="<?=(int)$v['id']?>"><button class="secondary" name="action" value="delete_viewer">Remove viewer</button></form></div><?php endforeach; ?>
</details>
<?php endif; ?>
<footer>Work Clock · SQLite storage · Europe/Vienna · Plain PHP</footer>
<?php endif; ?>
<script>const el=document.getElementById('elapsed');if(el){const render=()=>{const n=Math.max(0,Math.floor(Date.now()/1000-Number(el.dataset.start)));el.textContent=Math.floor(n/3600)+':'+String(Math.floor(n%3600/60)).padStart(2,'0')+':'+String(n%60).padStart(2,'0');};render();setInterval(render,1000);}</script></body></html>
