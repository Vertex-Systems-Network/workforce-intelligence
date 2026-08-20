<?php
/** Audits first-party PHP declarations for required PHPDoc documentation. */
$roots = ['app','database','routes','tests','bootstrap','config','tools','docs'];
$files = [];
foreach ($roots as $root) {
    if (! is_dir($root)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) if ($file->getExtension() === 'php') $files[] = $file->getPathname();
}
if (is_file('workintel-doctor.php')) $files[] = 'workintel-doctor.php';
$missing = [];
$total = 0;
$modifierTokens = array_filter([
    T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT,
    defined('T_READONLY') ? T_READONLY : null,
]);
foreach ($files as $path) {
    $tokens = token_get_all(file_get_contents($path));
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];
        if (! is_array($token)) continue;
        $ids = [T_CLASS,T_INTERFACE,T_TRAIT,T_FUNCTION];
        if (defined('T_ENUM')) $ids[] = T_ENUM;
        if (! in_array($token[0], $ids, true)) continue;
        if ($token[0] === T_CLASS) {
            $previous = $i - 1;
            while ($previous >= 0 && is_array($tokens[$previous]) && $tokens[$previous][0] === T_WHITESPACE) $previous--;
            while ($previous >= 0 && is_array($tokens[$previous]) && in_array($tokens[$previous][0], $modifierTokens, true)) {
                $previous--;
                while ($previous >= 0 && is_array($tokens[$previous]) && $tokens[$previous][0] === T_WHITESPACE) $previous--;
            }
            if ($previous >= 0 && is_array($tokens[$previous]) && $tokens[$previous][0] === T_NEW) continue;
        }
        $next = $i + 1;
        while ($next < $count) {
            $candidate = $tokens[$next];
            if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE,T_COMMENT,T_DOC_COMMENT], true)) { $next++; continue; }
            if ($token[0] === T_FUNCTION && $candidate === '&') { $next++; continue; }
            break;
        }
        if ($next >= $count || ! is_array($tokens[$next]) || $tokens[$next][0] !== T_STRING) continue;
        $total++;
        $previous = $i - 1;
        while ($previous >= 0) {
            $candidate = $tokens[$previous];
            if (is_array($candidate) && $candidate[0] === T_WHITESPACE) { $previous--; continue; }
            if (is_array($candidate) && in_array($candidate[0], $modifierTokens, true)) { $previous--; continue; }
            break;
        }
        if ($previous < 0 || ! is_array($tokens[$previous]) || $tokens[$previous][0] !== T_DOC_COMMENT) {
            $missing[] = $path.':'.$token[2].' '.$tokens[$next][1];
        }
    }
}
echo "PHP documented declarations: {$total}\n";
if ($missing) {
    fwrite(STDERR, "Missing PHPDoc declarations: ".count($missing)."\n".implode("\n", $missing)."\n");
    exit(1);
}
echo "Missing PHPDoc declarations: 0\n";
