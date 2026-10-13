# search(): the exact code-match group never runs.
s~if \(\$codekey !== ''\) \{(\n\s+self::collect)~if (false) {$1~s;
