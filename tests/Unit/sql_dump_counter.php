<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/scripts/development/count-sql-dump-rows.php';
$fixture = <<<'SQL'
-- comment with ; and fake INSERT INTO `ignored` (`x`) VALUES (1);
CREATE TABLE `empty_table` (`id` INT);
CREATE TABLE `records` (`id` INT, `payload` TEXT);
INSERT INTO `records` (`id`, `payload`) VALUES
(1, 'embedded ; and (parentheses)'),
(2, 'escaped \' quote; still inside'),
(3, 'doubled '' quote; still inside'),
(4, 'multiline
value; )');
/* comment with ; and fake values (1), (2) */
INSERT INTO `records` (`id`, `payload`) VALUES (5, 'next statement');
SQL;
$file=tempnam(sys_get_temp_dir(),'jl-dump-counter-');
try {
    file_put_contents($file,$fixture);
    $counts=dumpCounts($file);
    if($counts!==['empty_table'=>0,'records'=>5]) throw new RuntimeException('Dump row scanner lost SQL quoting/comment boundaries');
    echo "PASS: SQL row counter handles quoted semicolons, parentheses, escapes, comments and multiline values.\n";
} finally { if(is_file($file)) unlink($file); }
