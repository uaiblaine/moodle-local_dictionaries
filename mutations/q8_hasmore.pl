# search(): hasmore is always false.
s~'hasmore' => count\(\$found\) > \$limit,~'hasmore' => false,~s;
